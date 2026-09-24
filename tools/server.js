'use strict';

const http = require('http');
const fs = require('fs');
const path = require('path');
const { spawn, spawnSync } = require('child_process');

function parseArgs(argv) {
  const args = {};
  for (let i = 2; i < argv.length; i += 1) {
    const key = argv[i];
    if (!key.startsWith('--')) continue;
    const name = key.slice(2);
    const value = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : true;
    args[name] = value;
  }
  return args;
}

const args = parseArgs(process.argv);
const root = path.resolve(String(args.root || path.join(__dirname, '..')));
const host = String(args.host || '127.0.0.1');
const publicPort = Number(args.port || 3000);
const phpPort = Number(args['php-port'] || 8080);
const phpExecutable = String(args.php || 'php');
const mariaPid = Number(args['maria-pid'] || 0);
const publicDir = path.join(root, 'public');
const router = path.join(publicDir, 'router.php');
const runtimeDir = path.join(root, 'storage', 'runtime');
const statePath = path.join(runtimeDir, 'server.json');
const publicUrl = `http://${host}:${publicPort}`;
const phpUrl = `http://${host}:${phpPort}`;

process.title = 'Pasteleria - Node Local Server';

function log(prefix, message) {
  const now = new Date().toLocaleTimeString();
  console.log(`[${now}] [${prefix}] ${message}`);
}

function fail(message) {
  log('ERROR', message);
  process.exitCode = 1;
}

if (!Number.isInteger(publicPort) || publicPort < 1 || publicPort > 65535) {
  fail('Puerto publico invalido.');
  process.exit();
}
if (!Number.isInteger(phpPort) || phpPort < 1 || phpPort > 65535 || phpPort === publicPort) {
  fail('Puerto PHP invalido o igual al puerto publico.');
  process.exit();
}
if (!fs.existsSync(path.join(publicDir, 'index.php')) || !fs.existsSync(router)) {
  fail(`No se encontro la aplicacion PHP esperada en ${publicDir}.`);
  process.exit();
}

fs.mkdirSync(runtimeDir, { recursive: true });

let shuttingDown = false;
let proxyServer = null;
let phpProcess = null;

function removeOwnState() {
  try {
    if (!fs.existsSync(statePath)) return;
    const state = JSON.parse(fs.readFileSync(statePath, 'utf8'));
    if (Number(state.nodePid) === process.pid) fs.unlinkSync(statePath);
  } catch (_) {
    // El archivo de estado es auxiliar; un fallo al limpiarlo no debe ocultar el cierre real.
  }
}

function stopPhp() {
  if (!phpProcess || phpProcess.killed) return;
  try {
    if (process.platform === 'win32') {
      spawnSync('taskkill', ['/PID', String(phpProcess.pid), '/T', '/F'], {
        stdio: 'ignore',
        windowsHide: true,
      });
    } else {
      phpProcess.kill('SIGTERM');
    }
  } catch (_) {
    try { phpProcess.kill(); } catch (_) { /* no-op */ }
  }
}

function stopMariaDb() {
  if (!Number.isInteger(mariaPid) || mariaPid <= 0) return;
  try {
    if (process.platform === 'win32') {
      spawnSync('taskkill', ['/PID', String(mariaPid), '/T', '/F'], {
        stdio: 'ignore', windowsHide: true,
      });
    } else {
      process.kill(mariaPid, 'SIGTERM');
    }
  } catch (_) { /* no-op */ }
}

function shutdown(code = 0) {
  if (shuttingDown) return;
  shuttingDown = true;
  log('NODE', 'Deteniendo servidor local...');
  removeOwnState();
  if (proxyServer) {
    try { proxyServer.close(); } catch (_) { /* no-op */ }
  }
  stopPhp();
  stopMariaDb();
  setTimeout(() => process.exit(code), 150).unref();
}

process.on('SIGINT', () => shutdown(0));
process.on('SIGTERM', () => shutdown(0));
process.on('exit', removeOwnState);
process.on('uncaughtException', (error) => {
  log('ERROR', error && error.stack ? error.stack : String(error));
  shutdown(1);
});

log('NODE', 'Iniciando PHP integrado...');
phpProcess = spawn(
  phpExecutable,
  ['-S', `${host}:${phpPort}`, '-t', publicDir, router],
  {
    cwd: root,
    env: process.env,
    windowsHide: true,
    stdio: ['ignore', 'pipe', 'pipe'],
  },
);

phpProcess.stdout.on('data', (chunk) => process.stdout.write(`[PHP] ${chunk}`));
phpProcess.stderr.on('data', (chunk) => process.stderr.write(`[PHP] ${chunk}`));
phpProcess.on('error', (error) => {
  log('ERROR', `No se pudo iniciar PHP: ${error.message}`);
  shutdown(1);
});
phpProcess.on('exit', (code, signal) => {
  if (!shuttingDown) {
    log('ERROR', `PHP se detuvo inesperadamente (codigo=${code}, signal=${signal || 'N/A'}).`);
    shutdown(code || 1);
  }
});

function requestPhp(pathname = '/login', timeoutMs = 1000) {
  return new Promise((resolve) => {
    const request = http.get(`${phpUrl}${pathname}`, (response) => {
      response.resume();
      resolve(response.statusCode >= 100 && response.statusCode < 600);
    });
    request.setTimeout(timeoutMs, () => {
      request.destroy();
      resolve(false);
    });
    request.on('error', () => resolve(false));
  });
}

async function waitForPhp() {
  for (let attempt = 1; attempt <= 30; attempt += 1) {
    if (await requestPhp('/login', 700)) return true;
    await new Promise((resolve) => setTimeout(resolve, 300));
  }
  return false;
}

function writeState() {
  const state = {
    projectRoot: root,
    nodePid: process.pid,
    phpPid: phpProcess.pid,
    host,
    publicPort,
    phpPort,
    url: publicUrl,
    startedAt: new Date().toISOString(),
  };
  const tempPath = `${statePath}.${process.pid}.tmp`;
  fs.writeFileSync(tempPath, JSON.stringify(state, null, 2), 'utf8');
  fs.renameSync(tempPath, statePath);
}

function startProxy() {
  proxyServer = http.createServer((req, res) => {
    if (req.url === '/__local_health') {
      res.writeHead(200, {
        'Content-Type': 'application/json; charset=utf-8',
        'Cache-Control': 'no-store',
      });
      res.end(JSON.stringify({
        ok: true,
        projectRoot: root,
        nodePid: process.pid,
        phpPid: phpProcess.pid,
        publicPort,
        phpPort,
      }));
      return;
    }

    const headers = { ...req.headers };
    headers.host = `${host}:${phpPort}`;
    headers['x-forwarded-host'] = req.headers.host || `${host}:${publicPort}`;
    headers['x-forwarded-proto'] = 'http';
    headers['x-forwarded-for'] = req.socket.remoteAddress || host;

    const upstream = http.request({
      hostname: host,
      port: phpPort,
      method: req.method,
      path: req.url,
      headers,
    }, (upstreamResponse) => {
      res.writeHead(upstreamResponse.statusCode || 502, upstreamResponse.headers);
      upstreamResponse.pipe(res);
    });

    upstream.on('error', (error) => {
      log('PROXY', `Error hacia PHP: ${error.message}`);
      if (!res.headersSent) {
        res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' });
      }
      res.end('El backend PHP no esta disponible temporalmente.');
    });

    req.on('aborted', () => upstream.destroy());
    req.pipe(upstream);
  });

  proxyServer.on('error', (error) => {
    log('ERROR', `No se pudo abrir ${publicUrl}: ${error.message}`);
    shutdown(1);
  });

  proxyServer.listen(publicPort, host, () => {
    writeState();
    console.log('');
    console.log('========================================');
    console.log('      PASTELERIA - SERVIDOR LOCAL');
    console.log('========================================');
    console.log('Node.js:       ACTIVO');
    console.log(`PHP backend:    ${phpUrl}`);
    console.log(`Servidor web:   ${publicUrl}`);
    console.log('');
    console.log('Presiona CTRL + C para detener Node y PHP.');
    console.log('========================================');
    console.log('');
  });
}

(async () => {
  const ready = await waitForPhp();
  if (!ready) {
    log('ERROR', 'PHP no respondio durante el arranque. Revisa PHP, .env y MariaDB.');
    shutdown(1);
    return;
  }
  log('PHP', `Backend listo en ${phpUrl}`);
  startProxy();
})();
