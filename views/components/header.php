<?php
use App\Helpers\{View,Csrf,Auth};

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$management = $auth && Auth::canAccessManagement();

// Una sola entrada autenticada: gestión para roles autorizados y catálogo para compradores.
$logoUrl = !$auth ? '/' : ($management ? '/admin' : '/catalogo');

$active = function (string $section) use ($path): string {
    if ($section === 'manage') {
        return str_starts_with($path, '/admin') ? ' active' : '';
    }
    if ($section === '/') {
        return $path === '/' ? ' active' : '';
    }
    return str_starts_with($path, $section) ? ' active' : '';
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=View::e($title)?></title>
    <link rel="stylesheet" href="/css/app.css?v=reward-code-1">
    <link rel="stylesheet" href="/css/home.css">
    <script defer src="/js/app.js?v=reward-code-1"></script>
    <script defer src="/js/password-strength.js"></script>
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?=View::e($logoUrl)?>" aria-label="<?= $auth ? 'Ir al inicio de la cuenta' : 'Ir a la presentación de Dulce Naranja' ?>">
        <img class="brand-logo" src="/images/logo-dulce-naranja.svg" alt="" aria-hidden="true">
    </a>

    <nav>
        <?php if ($auth): ?>
            <a class="<?=$active('/catalogo')?>" href="/catalogo">Catálogo</a>
            <a class="pill shop-pill<?=$active('/tienda')?>" href="/tienda">Recompensas</a>
            <a class="topbar-cart<?=$active('/cart')?>" href="/cart" aria-label="Ir al carrito, <?=$cartCount?> productos" title="Carrito"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 1.9-1.4L22 8H6M10 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg><span class="badge"><?=$cartCount?></span></a>
            <a class="<?=$active('/profile')?>" href="/profile">Mi cuenta</a>
            <?php if ($management): ?>
                <a class="<?=$active('manage')?>" href="/admin/products">Gestionar</a>
            <?php endif; ?>
            <form class="inline" method="post" action="/logout">
                <?=Csrf::field()?>
                <button class="linkbtn">Salir</button>
            </form>
        <?php else: ?>
            <a class="pill<?=$active('/login')?>" href="/login">Iniciar sesión</a>
        <?php endif; ?>
    </nav>
</header>
<dialog class="app-modal" id="app-modal" aria-labelledby="app-modal-title" aria-describedby="app-modal-message" data-app-modal<?php if ($flashes): ?> data-open-on-load data-modal-type="notice"<?php endif; ?>>
    <div class="app-modal__icon" data-modal-icon aria-hidden="true"><?=($flashes[0]['type']??'')==='error'?'!':'✓'?></div>
    <h2 id="app-modal-title" data-modal-title><?=$flashes ? (($flashes[0]['type']??'')==='error'?'Revisa esto':'Listo') : 'Confirmar acción'?></h2>
    <div id="app-modal-message" class="app-modal__message" data-modal-message>
        <?php foreach ($flashes as $f): ?><p class="app-modal__notice <?=View::e($f['type'])?>"><?=View::e($f['message'])?></p><?php endforeach; ?>
    </div>
    <div class="app-modal__actions">
        <button type="button" class="secondary" data-modal-cancel hidden>Cancelar</button>
        <button type="button" class="primary" data-modal-ok>Entendido</button>
    </div>
</dialog>
<main class="container">
