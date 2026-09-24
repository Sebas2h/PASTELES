param(
    [switch]$Elevated,
    [switch]$NoBrowser,
    [switch]$NoPause
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$ProjectRoot = $PSScriptRoot
$StatePath = Join-Path $ProjectRoot 'storage\runtime\server.json'
$SchemaCandidates = @(
    (Join-Path $ProjectRoot 'database\schema.sql'),
    (Join-Path $ProjectRoot 'database\database.sql'),
    (Join-Path $ProjectRoot 'database\init.sql'),
    (Join-Path $ProjectRoot 'schema.sql')
)

function Write-Ok([string]$Message) { Write-Host "[OK] $Message" -ForegroundColor Green }
function Write-Info([string]$Message) { Write-Host "[..] $Message" -ForegroundColor Cyan }
function Write-Warn([string]$Message) { Write-Host "[!]  $Message" -ForegroundColor Yellow }
function Write-Fail([string]$Message) { Write-Host "[X]  $Message" -ForegroundColor Red }

function Stop-WithError([string]$Message) {
    Write-Fail $Message
    Write-Host ''
    Write-Host 'No se realizaron operaciones DROP DATABASE, DROP TABLE ni borrado de bases existentes.' -ForegroundColor DarkGray
    throw $Message
}

function Test-IsAdministrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Restart-Elevated([string]$Reason) {
    if ($Elevated -or (Test-IsAdministrator)) {
        Stop-WithError "$Reason Se requieren privilegios de Administrador y no fue posible continuar."
    }

    Write-Warn "$Reason Se solicitara permiso de Administrador."
    $argumentLine = '-NoProfile -ExecutionPolicy Bypass -File "{0}" -Elevated' -f $PSCommandPath
    if ($NoBrowser) { $argumentLine += ' -NoBrowser' }
    Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList $argumentLine -WorkingDirectory $ProjectRoot | Out-Null
    exit 0
}

function Refresh-ProcessPath {
    $machine = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    $user = [Environment]::GetEnvironmentVariable('Path', 'User')
    $env:Path = (($machine, $user) -join ';')
}

function Get-CommandPath([string[]]$Names) {
    foreach ($name in $Names) {
        $command = Get-Command $name -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($command) { return $command.Source }
    }
    return $null
}

function Require-Winget {
    $winget = Get-CommandPath @('winget.exe', 'winget')
    if (-not $winget) {
        Stop-WithError 'No se encontro winget. Instala "App Installer" desde Microsoft Store o instala manualmente la dependencia faltante.'
    }
    return $winget
}

function Install-WingetPackage([string]$Id, [string]$DisplayName) {
    if (-not (Test-IsAdministrator)) {
        Restart-Elevated "$DisplayName no esta instalado."
    }

    $winget = Require-Winget
    Write-Info "Instalando $DisplayName con winget..."
    & $winget install --id $Id --exact --accept-source-agreements --accept-package-agreements --silent
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) {
        Write-Warn "La instalacion silenciosa de $DisplayName devolvio codigo $exitCode. Se intentara modo normal."
        & $winget install --id $Id --exact --accept-source-agreements --accept-package-agreements
        $exitCode = $LASTEXITCODE
    }
    if ($exitCode -ne 0) {
        Stop-WithError "No fue posible instalar $DisplayName (codigo $exitCode)."
    }
    Refresh-ProcessPath
}

function Ensure-Node {
    $node = Get-CommandPath @('node.exe', 'node')
    if (-not $node) {
        Install-WingetPackage 'OpenJS.NodeJS.LTS' 'Node.js LTS'
        $node = Get-CommandPath @('node.exe', 'node')
    }
    if (-not $node) { Stop-WithError 'Node.js se instalo pero no aparece en PATH. Cierra y vuelve a abrir PowerShell.' }

    $npm = Get-CommandPath @('npm.cmd', 'npm.exe', 'npm')
    if (-not $npm) { Stop-WithError 'Node.js esta disponible, pero npm no fue encontrado.' }

    $nodeVersion = (& $node --version 2>$null)
    $npmVersion = (& $npm --version 2>$null)
    $nodeMajor = [int](($nodeVersion -replace '^v', '').Split('.')[0])
    if ($nodeMajor -lt 18) { Stop-WithError "Node.js $nodeVersion es demasiado antiguo. Instala Node.js 18 o superior." }
    Write-Ok "Node.js detectado: $nodeVersion"
    Write-Ok "npm detectado: $npmVersion"
    return @{ Node = $node; Npm = $npm }
}

function Ensure-Php {
    $php = Get-CommandPath @('php.exe', 'php')
    if (-not $php) {
        $installed = $false
        foreach ($id in @('PHP.PHP.8.4', 'PHP.PHP.8.3', 'PHP.PHP.8.2')) {
            try {
                if (-not (Test-IsAdministrator)) { Restart-Elevated 'PHP no esta instalado.' }
                $winget = Require-Winget
                Write-Info "Intentando instalar PHP con el paquete $id..."
                & $winget install --id $id --exact --accept-source-agreements --accept-package-agreements --silent
                if ($LASTEXITCODE -eq 0) { $installed = $true; break }
            } catch { }
        }
        if (-not $installed) {
            Stop-WithError 'No fue posible instalar PHP automaticamente. Instala PHP 8.1+ y asegurate de agregar php.exe al PATH.'
        }
        Refresh-ProcessPath
        $php = Get-CommandPath @('php.exe', 'php')
    }
    if (-not $php) { Stop-WithError 'PHP no aparece en PATH despues de la instalacion.' }

    $version = (& $php -r "echo PHP_VERSION;" 2>$null)
    & $php -r "exit(version_compare(PHP_VERSION, '8.1.0', '>=') ? 0 : 1);"
    if ($LASTEXITCODE -ne 0) { Stop-WithError "PHP $version es demasiado antiguo. Se requiere PHP 8.1 o superior." }
    Write-Ok "PHP detectado: $version"

    & $php -r "exit(extension_loaded('pdo_mysql') && extension_loaded('fileinfo') ? 0 : 1);"
    if ($LASTEXITCODE -ne 0) {
        Stop-WithError 'PHP esta instalado, pero faltan las extensiones pdo_mysql y/o fileinfo. Habilitalas en php.ini antes de iniciar la aplicacion.'
    }
    Write-Ok 'Extensiones PHP pdo_mysql y fileinfo disponibles'
    return $php
}

function Find-MariaDbClient {
    $command = Get-CommandPath @('mariadb.exe', 'mysql.exe', 'mariadb', 'mysql')
    if ($command) { return $command }

    $roots = @()
    if ($env:ProgramFiles) { $roots += $env:ProgramFiles }
    if (${env:ProgramFiles(x86)}) { $roots += ${env:ProgramFiles(x86)} }

    $found = @()
    foreach ($root in $roots) {
        $dirs = Get-ChildItem -Path $root -Directory -Filter 'MariaDB*' -ErrorAction SilentlyContinue | Sort-Object Name -Descending
        foreach ($dir in $dirs) {
            foreach ($name in @('mariadb.exe', 'mysql.exe')) {
                $candidate = Join-Path $dir.FullName "bin\$name"
                if (Test-Path $candidate) { $found += $candidate }
            }
        }
    }

    if ($found.Count -gt 0) { return $found[0] }
    return $null
}

function Find-MariaDbInstallation {
    foreach ($version in @('12.3', '11.8')) {
        $bin = Join-Path $env:ProgramFiles "MariaDB $version\bin"
        $client = Join-Path $bin 'mariadb.exe'
        $server = Join-Path $bin 'mariadbd.exe'
        $installer = Join-Path $bin 'mariadb-install-db.exe'
        if ((Test-Path $client) -and (Test-Path $server) -and (Test-Path $installer)) {
            Write-Ok "MariaDB $version detectado"
            return @{ Version=$version; Client=$client; Server=$server; Installer=$installer }
        }
    }
    return $null
}

function Set-DotEnvValue {
    param([string]$Path, [string]$Key, [string]$Value)
    $lines = @([IO.File]::ReadAllLines($Path, [Text.Encoding]::UTF8))
    $found = $false
    for ($index = 0; $index -lt $lines.Count; $index++) {
        if ($lines[$index] -match ('^\s*' + [regex]::Escape($Key) + '\s*=')) {
            $lines[$index] = "$Key=$Value"
            $found = $true
            break
        }
    }
    if (-not $found) { $lines += "$Key=$Value" }
    [IO.File]::WriteAllLines($Path, $lines, (New-Object Text.UTF8Encoding($false)))
}

function Read-SecretText([string]$Prompt) {
    $secure = Read-Host $Prompt -AsSecureString
    $credential = New-Object Management.Automation.PSCredential('local', $secure)
    return $credential.GetNetworkCredential().Password
}

function Read-NewDatabasePassword {
    while ($true) {
        $first = Read-SecretText 'Crea una contrasena para MariaDB (minimo 8 caracteres)'
        if ($first.Length -lt 8) {
            Write-Warn 'La contrasena debe tener al menos 8 caracteres.'
            continue
        }
        $second = Read-SecretText 'Repite la nueva contrasena'
        if ($first -ne $second) {
            Write-Warn 'Las contrasenas no coinciden. Intentalo otra vez.'
            continue
        }
        return $first
    }
}

function Start-ProjectMariaDb {
    param([hashtable]$Installation, [string]$EnvPath, [hashtable]$EnvMap)

    $baseDir = Join-Path $ProjectRoot 'storage\mariadb'
    $dataDir = Join-Path $baseDir 'data'
    $logPath = Join-Path $baseDir 'mariadb.log'
    $errorLogPath = Join-Path $baseDir 'mariadb-error.log'
    $pidPath = Join-Path $baseDir 'mariadb.pid'
    New-Item -ItemType Directory -Path $baseDir -Force | Out-Null

    $port = 3310
    $databaseIsNew = -not (Test-Path (Join-Path $dataDir 'mysql'))
    $savedPassword = if ($EnvMap.ContainsKey('LOCAL_DB_PASSWORD')) { [string]$EnvMap['LOCAL_DB_PASSWORD'] } else { '' }
    $needsPasswordChange = $EnvMap.ContainsKey('LOCAL_DB_PASSWORD_NEEDS_CHANGE') -and $EnvMap['LOCAL_DB_PASSWORD_NEEDS_CHANGE'] -eq 'true'

    if ($databaseIsNew) {
        Write-Host ''
        Write-Info 'MariaDB se configurara por primera vez para esta aplicacion.'
        $password = Read-NewDatabasePassword
    } elseif ($needsPasswordChange -and $savedPassword) {
        # Compatibilidad con la primera version del lanzador, que creo una clave
        # aleatoria. Se usa solo para entrar y reemplazarla por una elegida.
        $password = $savedPassword
    } elseif ($savedPassword) {
        $password = $savedPassword
    } else {
        Write-Host ''
        $password = Read-SecretText 'Escribe la contrasena de MariaDB'
        if (-not $password) { Stop-WithError 'No se ingreso la contrasena de MariaDB.' }
    }

    if ($databaseIsNew) {
        Write-Info "Preparando una base MariaDB $($Installation.Version) privada para esta aplicacion..."
        New-Item -ItemType Directory -Path $dataDir -Force | Out-Null
        & $Installation.Installer "--datadir=$dataDir" "--password=$password" "--port=$port" --allow-remote-root-access
        if ($LASTEXITCODE -ne 0) { Stop-WithError "No se pudo inicializar MariaDB (codigo $LASTEXITCODE)." }
        Write-Ok 'Base MariaDB local inicializada'
    }

    Set-DotEnvValue $EnvPath 'DB_HOST' '127.0.0.1'
    Set-DotEnvValue $EnvPath 'DB_PORT' ([string]$port)
    Set-DotEnvValue $EnvPath 'DB_USER' 'root'
    Set-DotEnvValue $EnvPath 'DB_PASSWORD' $password
    Set-DotEnvValue $EnvPath 'LOCAL_DB_PASSWORD' $password
    $EnvMap['DB_HOST']='127.0.0.1'; $EnvMap['DB_PORT']=[string]$port
    $EnvMap['DB_USER']='root'; $EnvMap['DB_PASSWORD']=$password; $EnvMap['LOCAL_DB_PASSWORD']=$password

    try {
        Invoke-MariaQuery -Client $Installation.Client -Db @{Host='127.0.0.1';Port=$port;User='root';Password=$password;Name='mysql'} -Sql 'SELECT 1;' -WithoutDatabase | Out-Null
        $existingPid = Get-Content $pidPath -ErrorAction SilentlyContinue | Select-Object -First 1
        Write-Ok "MariaDB privado ya estaba activo en el puerto $port"
        if ($needsPasswordChange) {
            Write-Info 'Debes reemplazar la clave automatica anterior por una contrasena personal.'
            $newPassword = Read-NewDatabasePassword
            $escapedPassword = $newPassword.Replace("'", "''")
            Invoke-MariaQuery -Client $Installation.Client -Db @{Host='127.0.0.1';Port=$port;User='root';Password=$password;Name='mysql'} -Sql "ALTER USER 'root'@'localhost' IDENTIFIED BY '$escapedPassword';" -WithoutDatabase | Out-Null
            $password = $newPassword
            Set-DotEnvValue $EnvPath 'DB_PASSWORD' $password
            Set-DotEnvValue $EnvPath 'LOCAL_DB_PASSWORD' $password
            Set-DotEnvValue $EnvPath 'LOCAL_DB_PASSWORD_NEEDS_CHANGE' 'false'
            $EnvMap['DB_PASSWORD']=$password; $EnvMap['LOCAL_DB_PASSWORD']=$password
            Write-Ok 'Nueva contrasena de MariaDB configurada'
        }
        return [int]$existingPid
    } catch { }

    if (-not (Test-PortAvailable $port)) { Stop-WithError 'La contrasena de MariaDB no es correcta.' }
    Remove-Item $logPath -Force -ErrorAction SilentlyContinue
    Remove-Item $errorLogPath -Force -ErrorAction SilentlyContinue
    $arguments = @(
        "--datadir=$dataDir", "--port=$port", '--bind-address=127.0.0.1',
        '--console', "--pid-file=$pidPath", '--character-set-server=utf8mb4',
        '--collation-server=utf8mb4_unicode_ci'
    )
    Write-Info "Iniciando MariaDB $($Installation.Version) privado en 127.0.0.1:$port..."
    $process = Start-Process -FilePath $Installation.Server -ArgumentList $arguments -WorkingDirectory $baseDir -WindowStyle Hidden -PassThru -RedirectStandardOutput $logPath -RedirectStandardError $errorLogPath
    for ($attempt=1; $attempt -le 40; $attempt++) {
        Start-Sleep -Milliseconds 300
        if ($process.HasExited) {
            $detail = ((Get-Content $logPath -Raw -ErrorAction SilentlyContinue), (Get-Content $errorLogPath -Raw -ErrorAction SilentlyContinue)) -join "`n"
            Stop-WithError "MariaDB se cerro durante el arranque.`n$detail"
        }
        try {
            Invoke-MariaQuery -Client $Installation.Client -Db @{Host='127.0.0.1';Port=$port;User='root';Password=$password;Name='mysql'} -Sql 'SELECT 1;' -WithoutDatabase | Out-Null
            Write-Ok "MariaDB privado activo (PID $($process.Id))"
            if ($needsPasswordChange) {
                Write-Host ''
                Write-Info 'Debes reemplazar la clave automatica anterior por una contrasena personal.'
                $newPassword = Read-NewDatabasePassword
                $escapedPassword = $newPassword.Replace("'", "''")
                Invoke-MariaQuery -Client $Installation.Client -Db @{Host='127.0.0.1';Port=$port;User='root';Password=$password;Name='mysql'} -Sql "ALTER USER 'root'@'localhost' IDENTIFIED BY '$escapedPassword';" -WithoutDatabase | Out-Null
                $password = $newPassword
                Set-DotEnvValue $EnvPath 'DB_PASSWORD' $password
                Set-DotEnvValue $EnvPath 'LOCAL_DB_PASSWORD' $password
                Set-DotEnvValue $EnvPath 'LOCAL_DB_PASSWORD_NEEDS_CHANGE' 'false'
                $EnvMap['DB_PASSWORD']=$password; $EnvMap['LOCAL_DB_PASSWORD']=$password
                Write-Ok 'Nueva contrasena de MariaDB configurada'
            }
            return $process.Id
        } catch { }
    }
    Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
    Stop-WithError "MariaDB no respondio. Revisa $logPath"
}

function Ensure-MariaDbClient {
    $client = Find-MariaDbClient
    if (-not $client) {
        Install-WingetPackage 'MariaDB.Server' 'MariaDB Server'
        $client = Find-MariaDbClient
    }
    if (-not $client) {
        Stop-WithError 'MariaDB se instalo, pero no se encontro mariadb.exe/mysql.exe.'
    }
    Write-Ok "Cliente MariaDB detectado: $client"
    return $client
}

function Get-MariaDbService([int]$Port) {
    $services = Get-Service -ErrorAction SilentlyContinue | Where-Object {
        $_.Name -match 'MariaDB|MySQL' -or $_.DisplayName -match 'MariaDB|MySQL'
    }
    if (-not $services) { return $null }

    # Cuando hay varios MariaDB instalados, identifica el que realmente escucha
    # en el puerto configurado. Elegir simplemente el primero puede arrancar la
    # base de datos de otro proyecto.
    try {
        $listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction Stop | Select-Object -First 1
        if ($listener) {
            $serviceInfo = Get-CimInstance Win32_Service -ErrorAction Stop | Where-Object {
                $_.ProcessId -eq $listener.OwningProcess
            } | Select-Object -First 1
            if ($serviceInfo) {
                return ($services | Where-Object Name -eq $serviceInfo.Name | Select-Object -First 1)
            }
        }
    } catch { }

    $preferred = $services | Where-Object { $_.Name -eq 'MariaDB' } | Select-Object -First 1
    if ($preferred) { return $preferred }
    return ($services | Select-Object -First 1)
}

function Ensure-MariaDbService([int]$Port) {
    $service = Get-MariaDbService -Port $Port
    if (-not $service) {
        Write-Warn 'No se detecto un servicio MariaDB/MySQL registrado. Se intentara conectar directamente; esto es valido si MariaDB fue iniciado de otra forma.'
        return
    }

    if ($service.Status -eq 'Running') {
        Write-Ok "MariaDB activo (servicio: $($service.Name))"
        return
    }

    Write-Info "MariaDB esta detenido. Iniciando servicio $($service.Name)..."
    try {
        Start-Service -Name $service.Name
        $service.WaitForStatus('Running', (New-TimeSpan -Seconds 15))
        Write-Ok "MariaDB iniciado correctamente (servicio: $($service.Name))"
    } catch {
        if (-not (Test-IsAdministrator)) {
            Restart-Elevated 'No fue posible iniciar el servicio de MariaDB sin elevacion.'
        }
        Stop-WithError "No fue posible iniciar el servicio $($service.Name): $($_.Exception.Message)"
    }
}

function Save-DbPassword {
    param([string]$EnvPath, [string]$Password)

    if ($Password -match "[`r`n]") { Stop-WithError 'La contrasena de MariaDB no puede contener saltos de linea.' }
    $lines = @(Get-Content -LiteralPath $EnvPath -ErrorAction Stop)
    $found = $false
    for ($index = 0; $index -lt $lines.Count; $index++) {
        if ($lines[$index] -match '^\s*DB_PASSWORD\s*=') {
            $lines[$index] = 'DB_PASSWORD=' + $Password
            $found = $true
            break
        }
    }
    if (-not $found) { $lines += 'DB_PASSWORD=' + $Password }
    [IO.File]::WriteAllLines($EnvPath, $lines, (New-Object Text.UTF8Encoding($false)))
    Write-Ok 'Contrasena de MariaDB guardada en .env'
}

function Read-DotEnv([string]$Path) {
    $map = @{}
    foreach ($line in Get-Content -Path $Path -ErrorAction Stop) {
        $trimmed = $line.Trim()
        if (-not $trimmed -or $trimmed.StartsWith('#') -or -not $trimmed.Contains('=')) { continue }
        $parts = $trimmed.Split('=', 2)
        $key = $parts[0].Trim()
        $value = $parts[1].Trim().Trim('"').Trim("'")
        $map[$key] = $value
    }
    return $map
}

function Ensure-EnvironmentFile {
    $envPath = Join-Path $ProjectRoot '.env'
    $examplePath = Join-Path $ProjectRoot '.env.example'
    if (-not (Test-Path $envPath)) {
        if (-not (Test-Path $examplePath)) { Stop-WithError 'No existe .env ni .env.example.' }
        Copy-Item -Path $examplePath -Destination $envPath
        Write-Ok 'Se creo .env a partir de .env.example'
    } else {
        Write-Ok 'Archivo .env existente: se conservara sin sobrescribirlo'
    }
    return @{ Path = $envPath; Values = (Read-DotEnv $envPath) }
}

function Get-RequiredEnv([hashtable]$EnvMap, [string]$Key, [string]$DefaultValue) {
    if ($EnvMap.ContainsKey($Key) -and $EnvMap[$Key] -ne '') { return [string]$EnvMap[$Key] }
    return $DefaultValue
}

function Invoke-MariaQuery {
    param(
        [string]$Client,
        [hashtable]$Db,
        [string]$Sql,
        [switch]$WithoutDatabase
    )

    $arguments = @(
        '--protocol=TCP',
        "--host=$($Db.Host)",
        "--port=$($Db.Port)",
        "--user=$($Db.User)",
        '--batch',
        '--skip-column-names'
    )
    if (-not $WithoutDatabase) { $arguments += "--database=$($Db.Name)" }

    $previousPassword = $env:MYSQL_PWD
    $previousErrorPreference = $ErrorActionPreference
    try {
        if ($Db.Password) { $env:MYSQL_PWD = $Db.Password }
        elseif (Test-Path Env:MYSQL_PWD) { Remove-Item Env:MYSQL_PWD }

        # MariaDB 12.3 escribe ciertas advertencias no fatales en stderr.
        # El codigo de salida es la fuente fiable para decidir si fallo.
        $ErrorActionPreference = 'Continue'
        $output = & $Client @arguments -e $Sql 2>&1
        $clientExitCode = $LASTEXITCODE
        $ErrorActionPreference = $previousErrorPreference
        if ($clientExitCode -ne 0) {
            throw (($output | Out-String).Trim())
        }
        return @($output | Where-Object { $_.ToString() -notmatch '^WARNING:' })
    } finally {
        $ErrorActionPreference = $previousErrorPreference
        if ($null -ne $previousPassword) { $env:MYSQL_PWD = $previousPassword }
        elseif (Test-Path Env:MYSQL_PWD) { Remove-Item Env:MYSQL_PWD }
    }
}

function Import-Schema {
    param([string]$Client, [hashtable]$Db, [string]$SchemaPath)

    $schemaText = Get-Content -Path $SchemaPath -Raw -Encoding UTF8
    if ($schemaText -match '(?im)\bDROP\s+(DATABASE|TABLE)\b|\bTRUNCATE\s+TABLE\b') {
        Stop-WithError 'El esquema contiene instrucciones destructivas DROP/TRUNCATE. Por seguridad no se importara automaticamente.'
    }

    $dbMatch = [regex]::Match($schemaText, '(?im)CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?')
    if ($dbMatch.Success -and $dbMatch.Groups[1].Value -ne $Db.Name) {
        Stop-WithError "El schema.sql apunta a '$($dbMatch.Groups[1].Value)' pero .env usa '$($Db.Name)'. Corrige la configuracion para evitar crear una base inesperada."
    }

    Write-Info "Importando esquema seguro desde $SchemaPath..."
    $arguments = @('--protocol=TCP', "--host=$($Db.Host)", "--port=$($Db.Port)", "--user=$($Db.User)")
    $previousPassword = $env:MYSQL_PWD
    try {
        if ($Db.Password) { $env:MYSQL_PWD = $Db.Password }
        elseif (Test-Path Env:MYSQL_PWD) { Remove-Item Env:MYSQL_PWD }

        $argumentLine = ($arguments | ForEach-Object { if ($_ -match '\s') { '"' + ($_ -replace '"', '\"') + '"' } else { $_ } }) -join ' '
        $importProcess = Start-Process -FilePath $Client -ArgumentList $argumentLine -Wait -PassThru -NoNewWindow -RedirectStandardInput $SchemaPath
        if ($importProcess.ExitCode -ne 0) { throw "mariadb/mysql devolvio codigo $($importProcess.ExitCode)" }
    } finally {
        if ($null -ne $previousPassword) { $env:MYSQL_PWD = $previousPassword }
        elseif (Test-Path Env:MYSQL_PWD) { Remove-Item Env:MYSQL_PWD }
    }
    Write-Ok 'Esquema importado sin eliminar datos existentes'
}

function Ensure-Database {
    param([string]$Client, [hashtable]$EnvMap, [string]$EnvPath)

    $dbPassword = ''
    if ($EnvMap.ContainsKey('DB_PASSWORD')) { $dbPassword = [string]$EnvMap['DB_PASSWORD'] }
    $db = @{
        Host = (Get-RequiredEnv $EnvMap 'DB_HOST' '127.0.0.1')
        Port = (Get-RequiredEnv $EnvMap 'DB_PORT' '3306')
        Name = (Get-RequiredEnv $EnvMap 'DB_NAME' 'pasteleria_db')
        User = (Get-RequiredEnv $EnvMap 'DB_USER' 'root')
        Password = $dbPassword
    }

    if ($db.Name -notmatch '^[A-Za-z0-9_]+$') {
        Stop-WithError "DB_NAME '$($db.Name)' no es un identificador seguro. Usa solo letras, numeros y guion bajo."
    }
    if ($db.Port -notmatch '^\d+$') { Stop-WithError 'DB_PORT debe ser numerico.' }

    try {
        Invoke-MariaQuery -Client $Client -Db $db -Sql 'SELECT 1;' -WithoutDatabase | Out-Null
        $serverVersion = (Invoke-MariaQuery -Client $Client -Db $db -Sql 'SELECT VERSION();' -WithoutDatabase | Select-Object -First 1).ToString().Trim()
        Write-Ok "Conexion MariaDB correcta como '$($db.User)' en $($db.Host):$($db.Port)"
        Write-Ok "Servidor SQL detectado: $serverVersion"
    } catch {
        $connectionError = $_.Exception.Message
        if ($connectionError -match 'Access denied' -and $db.User -eq 'root' -and -not $db.Password) {
            Write-Warn "MariaDB rechazo al usuario root porque DB_PASSWORD esta vacio."
            $securePassword = Read-Host 'Escribe la contrasena root de MariaDB (no se mostrara)' -AsSecureString
            $credential = New-Object Management.Automation.PSCredential('root', $securePassword)
            $db.Password = $credential.GetNetworkCredential().Password
            if (-not $db.Password) { Stop-WithError 'No se ingreso una contrasena de MariaDB.' }
            try {
                Invoke-MariaQuery -Client $Client -Db $db -Sql 'SELECT 1;' -WithoutDatabase | Out-Null
                Save-DbPassword -EnvPath $EnvPath -Password $db.Password
                $EnvMap['DB_PASSWORD'] = $db.Password
                $serverVersion = (Invoke-MariaQuery -Client $Client -Db $db -Sql 'SELECT VERSION();' -WithoutDatabase | Select-Object -First 1).ToString().Trim()
                Write-Ok "Conexion MariaDB correcta como '$($db.User)' en $($db.Host):$($db.Port)"
                Write-Ok "Servidor SQL detectado: $serverVersion"
            } catch {
                Stop-WithError "La contrasena proporcionada no permitio conectar con MariaDB.`nDetalle: $($_.Exception.Message)"
            }
        } else {
            Stop-WithError "No fue posible conectar con MariaDB usando .env.`nVerifica DB_HOST, DB_PORT, DB_USER y DB_PASSWORD.`nDetalle: $connectionError"
        }
    }

    $exists = Invoke-MariaQuery -Client $Client -Db $db -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$($db.Name)';" -WithoutDatabase
    if ([int](($exists | Select-Object -First 1).ToString().Trim()) -eq 0) {
        Write-Info "Creando base de datos $($db.Name)..."
        Invoke-MariaQuery -Client $Client -Db $db -Sql "CREATE DATABASE IF NOT EXISTS ``$($db.Name)`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" -WithoutDatabase | Out-Null
        Write-Ok "Base de datos $($db.Name) creada"
    } else {
        Write-Ok "Base de datos encontrada: $($db.Name)"
    }

    $schemaPath = $SchemaCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
    if (-not $schemaPath) { Stop-WithError 'No se encontro database/schema.sql, database/database.sql, database/init.sql ni schema.sql.' }

    $schemaText = Get-Content -Path $schemaPath -Raw -Encoding UTF8
    $tableMatches = [regex]::Matches($schemaText, '(?im)CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?')
    $expectedTables = @()
    foreach ($match in $tableMatches) { $expectedTables += $match.Groups[1].Value }
    $expectedTables = @($expectedTables | Select-Object -Unique)

    if ($expectedTables.Count -eq 0) { Stop-WithError 'No se pudieron detectar tablas CREATE TABLE IF NOT EXISTS en el esquema.' }

    $existingRaw = Invoke-MariaQuery -Client $Client -Db $db -Sql 'SHOW TABLES;'
    $existingTables = @($existingRaw | ForEach-Object { $_.ToString().Trim() } | Where-Object { $_ })
    $missingTables = @($expectedTables | Where-Object { $existingTables -notcontains $_ })

    if ($missingTables.Count -gt 0) {
        Write-Warn "Faltan tablas: $($missingTables -join ', '). Se ejecutara el schema idempotente (CREATE IF NOT EXISTS)."
        Import-Schema -Client $Client -Db $db -SchemaPath $schemaPath
    } else {
        Write-Ok "Tablas verificadas: $($expectedTables.Count)/$($expectedTables.Count)"
    }

    $existingRaw = Invoke-MariaQuery -Client $Client -Db $db -Sql 'SHOW TABLES;'
    $existingTables = @($existingRaw | ForEach-Object { $_.ToString().Trim() } | Where-Object { $_ })
    $missingAfter = @($expectedTables | Where-Object { $existingTables -notcontains $_ })
    if ($missingAfter.Count -gt 0) {
        Stop-WithError "El esquema termino pero aun faltan tablas: $($missingAfter -join ', ')"
    }

    return $db
}

function Ensure-InstallKey {
    param([string]$Client, [hashtable]$Db, [string]$Php)

    $setup = (Invoke-MariaQuery -Client $Client -Db $Db -Sql "SELECT setting_value FROM system_settings WHERE setting_key='setup_completed' LIMIT 1;" | Select-Object -First 1).ToString().Trim()
    $admins = (Invoke-MariaQuery -Client $Client -Db $Db -Sql "SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='ADMINISTRADOR';" | Select-Object -First 1).ToString().Trim()

    $keyPath = Join-Path $ProjectRoot 'storage\install.key'
    if ($setup -eq '0' -and [int]$admins -eq 0) {
        if (-not (Test-Path $keyPath)) {
            Write-Info 'La instalacion inicial esta pendiente. Generando codigo local para crear el primer ADMIN...'
            & $Php (Join-Path $ProjectRoot 'bin\generate-install-key.php')
            if ($LASTEXITCODE -ne 0) { Stop-WithError 'No se pudo generar storage/install.key.' }
        }
        if (Test-Path $keyPath) {
            $key = (Get-Content $keyPath -Raw).Trim()
            Write-Host ''
            Write-Host "CODIGO DE INSTALACION ADMIN: $key" -ForegroundColor Magenta
            Write-Host 'Usalo una sola vez en /setup-admin. Se eliminara al crear el primer administrador.' -ForegroundColor DarkGray
            Write-Host ''
        }
    } elseif ($setup -eq '1' -and [int]$admins -eq 0) {
        Write-Warn 'setup_completed=1 pero no existe ningun ADMIN. Por seguridad NO se genero una nueva clave ni se reabrio el setup.'
        Write-Warn 'Usa un procedimiento administrativo de recuperacion; el PS1 no alterara setup_completed.'
    } else {
        Write-Ok 'Configuracion inicial del administrador ya protegida/completada'
    }
}

function Ensure-NodeDependencies([string]$Npm) {
    $packagePath = Join-Path $ProjectRoot 'package.json'
    if (-not (Test-Path $packagePath)) {
        Write-Warn 'No existe package.json; el launcher usa solo modulos nativos de Node.js.'
        return
    }

    try { $package = Get-Content $packagePath -Raw | ConvertFrom-Json }
    catch { Stop-WithError "package.json no es JSON valido: $($_.Exception.Message)" }

    $dependencyCount = 0
    $depsProp = $package.PSObject.Properties['dependencies']
    $devDepsProp = $package.PSObject.Properties['devDependencies']
    if ($depsProp -and $depsProp.Value) { $dependencyCount += $depsProp.Value.PSObject.Properties.Count }
    if ($devDepsProp -and $devDepsProp.Value) { $dependencyCount += $devDepsProp.Value.PSObject.Properties.Count }

    if ($dependencyCount -eq 0) {
        Write-Ok 'El launcher Node.js no requiere paquetes externos'
        return
    }

    $nodeModules = Join-Path $ProjectRoot 'node_modules'
    if (-not (Test-Path $nodeModules)) {
        Write-Info 'Instalando dependencias de Node.js...'
        Push-Location $ProjectRoot
        try {
            & $Npm install
            if ($LASTEXITCODE -ne 0) { Stop-WithError "npm install fallo con codigo $LASTEXITCODE" }
        } finally { Pop-Location }
        Write-Ok 'Dependencias de Node.js instaladas'
    } else {
        Write-Ok 'Dependencias de Node.js encontradas'
    }
}

function Test-PortAvailable([int]$Port) {
    $listener = $null
    try {
        $listener = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, $Port)
        $listener.Start()
        return $true
    } catch {
        return $false
    } finally {
        if ($listener) { try { $listener.Stop() } catch { } }
    }
}

function Get-FreePort([int[]]$Candidates) {
    foreach ($port in $Candidates) {
        if (Test-PortAvailable $port) { return $port }
    }
    return $null
}

function Test-ExistingProjectServer {
    if (-not (Test-Path $StatePath)) { return $null }
    try {
        $state = Get-Content $StatePath -Raw | ConvertFrom-Json
        if (-not $state.projectRoot -or ([IO.Path]::GetFullPath([string]$state.projectRoot) -ne [IO.Path]::GetFullPath($ProjectRoot))) {
            return $null
        }
        $process = Get-Process -Id ([int]$state.nodePid) -ErrorAction SilentlyContinue
        if (-not $process) { Remove-Item $StatePath -Force -ErrorAction SilentlyContinue; return $null }

        $health = Invoke-RestMethod -Uri (([string]$state.url) + '/__local_health') -TimeoutSec 2 -ErrorAction Stop
        if ($health.ok -and ([IO.Path]::GetFullPath([string]$health.projectRoot) -eq [IO.Path]::GetFullPath($ProjectRoot))) {
            return $state
        }
    } catch {
        Remove-Item $StatePath -Force -ErrorAction SilentlyContinue
    }
    return $null
}

function Quote-NativeArg([string]$Value) {
    return '"' + $Value.Replace('"', '\"') + '"'
}

function Start-LocalServer {
    param([string]$Node, [string]$Php, [int]$MariaPid)

    $existing = Test-ExistingProjectServer
    if ($existing) {
        Write-Ok "La aplicacion ya esta ejecutandose: $($existing.url)"
        if (-not $NoBrowser) { Start-Process ([string]$existing.url) | Out-Null }
        return
    }

    $publicPort = Get-FreePort @(3000..3010)
    if (-not $publicPort) { Stop-WithError 'No hay puertos libres entre 3000 y 3010 para Node.js.' }
    $phpPort = Get-FreePort @(8080..8090)
    if (-not $phpPort) { Stop-WithError 'No hay puertos libres entre 8080 y 8090 para PHP.' }

    $serverJs = Join-Path $ProjectRoot 'tools\server.js'
    if (-not (Test-Path $serverJs)) { Stop-WithError 'No existe tools/server.js.' }

    $argumentLine = @(
        (Quote-NativeArg $serverJs),
        '--root', (Quote-NativeArg $ProjectRoot),
        '--host', '127.0.0.1',
        '--port', [string]$publicPort,
        '--php-port', [string]$phpPort,
        '--php', (Quote-NativeArg $Php),
        '--maria-pid', [string]$MariaPid
    ) -join ' '

    Write-Info "Iniciando Node.js en http://127.0.0.1:$publicPort y PHP interno en 127.0.0.1:$phpPort..."
    Start-Process -FilePath $Node -ArgumentList $argumentLine -WorkingDirectory $ProjectRoot | Out-Null

    $url = "http://127.0.0.1:$publicPort"
    $healthy = $false
    for ($attempt = 1; $attempt -le 30; $attempt++) {
        Start-Sleep -Milliseconds 400
        try {
            $health = Invoke-RestMethod -Uri "$url/__local_health" -TimeoutSec 2 -ErrorAction Stop
            if ($health.ok -and ([IO.Path]::GetFullPath([string]$health.projectRoot) -eq [IO.Path]::GetFullPath($ProjectRoot))) {
                $healthy = $true
                break
            }
        } catch { }
    }

    if (-not $healthy) {
        Stop-WithError 'El servidor Node.js no respondio al health check. Revisa la consola del servidor que se abrio para ver los logs.'
    }
    Write-Ok 'Servidor Node.js funcionando correctamente'

    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri "$url/login" -TimeoutSec 4 -ErrorAction Stop
        if ([int]$response.StatusCode -ge 200 -and [int]$response.StatusCode -lt 500) {
            Write-Ok "Aplicacion PHP responde por HTTP (status $($response.StatusCode))"
        }
    } catch {
        Stop-WithError "Node responde, pero la aplicacion PHP no cargo /login: $($_.Exception.Message)"
    }

    Write-Host ''
    Write-Host '========================================' -ForegroundColor DarkCyan
    Write-Host '      PASTELERIA - SERVIDOR LOCAL' -ForegroundColor Cyan
    Write-Host '========================================' -ForegroundColor DarkCyan
    Write-Host 'MariaDB:       ACTIVO' -ForegroundColor Green
    Write-Host 'Node.js:       ACTIVO' -ForegroundColor Green
    Write-Host 'PHP:           ACTIVO' -ForegroundColor Green
    Write-Host "URL:           $url" -ForegroundColor White
    Write-Host 'Servidor/logs: ventana Node.js abierta' -ForegroundColor White
    Write-Host 'CTRL+C:        detener Node.js + PHP en esa ventana' -ForegroundColor White
    Write-Host '========================================' -ForegroundColor DarkCyan
    Write-Host ''

    if (-not $NoBrowser) {
        Start-Process $url | Out-Null
        Write-Ok 'Navegador abierto'
    }
}

try {
    Clear-Host
    Write-Host '========================================' -ForegroundColor DarkCyan
    Write-Host '   PASTELERIA DULCE NARANJA - LOCAL' -ForegroundColor Cyan
    Write-Host '========================================' -ForegroundColor DarkCyan
    Write-Host "Proyecto: $ProjectRoot" -ForegroundColor DarkGray
    Write-Host ''

    if (-not (Test-Path (Join-Path $ProjectRoot 'public\index.php'))) {
        Stop-WithError 'Iniciar-Pasteleria.ps1 debe estar en la raiz del proyecto; no se encontro public/index.php.'
    }

    New-Item -ItemType Directory -Path (Join-Path $ProjectRoot 'storage\uploads') -Force | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $ProjectRoot 'storage\runtime') -Force | Out-Null
    Write-Ok 'Carpetas de almacenamiento verificadas'

    $nodeTools = Ensure-Node
    $php = Ensure-Php
    $envInfo = Ensure-EnvironmentFile
    $mariaInstall = Find-MariaDbInstallation
    if (-not $mariaInstall) {
        Ensure-MariaDbClient | Out-Null
        $mariaInstall = Find-MariaDbInstallation
    }
    if (-not $mariaInstall) { Stop-WithError 'No se encontro una instalacion completa de MariaDB 11.8 o 12.3.' }
    $mariaPid = Start-ProjectMariaDb -Installation $mariaInstall -EnvPath $envInfo.Path -EnvMap $envInfo.Values
    $db = Ensure-Database -Client $mariaInstall.Client -EnvMap $envInfo.Values -EnvPath $envInfo.Path
    Ensure-InstallKey -Client $mariaInstall.Client -Db $db -Php $php
    Ensure-NodeDependencies -Npm $nodeTools.Npm
    Start-LocalServer -Node $nodeTools.Node -Php $php -MariaPid $mariaPid
} catch {
    Write-Fail $_.Exception.Message
    Write-Host ''
    if (-not $NoPause) { Read-Host 'Presiona ENTER para cerrar' | Out-Null }
    exit 1
}
