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
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/home.css">
    <script defer src="/js/app.js"></script>
    <script defer src="/js/password-strength.js"></script>
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?=View::e($logoUrl)?>" aria-label="<?= $auth ? 'Ir al inicio de la cuenta' : 'Ir a la presentación de Dulce Naranja' ?>">
        <span class="brand-mark" aria-hidden="true"></span>
        <span>Dulce Naranja</span>
    </a>

    <nav>
        <?php if ($auth): ?>
            <a class="<?=$active('/catalogo')?>" href="/catalogo">Catálogo</a>
            <a class="pill shop-pill<?=$active('/tienda')?>" href="/tienda">Tienda</a>
            <a class="<?=$active('/cart')?>" href="/cart">Carrito <span class="badge"><?=array_sum($_SESSION['cart'] ?? [])?></span></a>
            <a class="<?=$active('/profile')?>" href="/profile">Mi cuenta</a>
            <?php if ($management): ?>
                <a class="<?=$active('manage')?>" href="/admin/products">Gestionar</a>
            <?php endif; ?>
            <form class="inline" method="post" action="/logout">
                <?=Csrf::field()?>
                <button class="linkbtn">Salir</button>
            </form>
        <?php else: ?>
            <a class="<?=$active('/login')?>" href="/login">Ingresar</a>
            <a class="pill<?=$active('/register')?>" href="/register">Registrarse</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
    <?php foreach ($flashes as $f): ?>
        <div class="toast <?=View::e($f['type'])?>"><?=View::e($f['message'])?></div>
    <?php endforeach; ?>
