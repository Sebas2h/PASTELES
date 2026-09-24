<?php use App\Helpers\View; ?>

<section class="page-head">
    <span class="eyebrow">HOLA, <?=View::e(strtoupper((string)$auth['username']))?></span>
    <h1>Tu espacio dulce</h1>
    <p>
        Bienvenido, <b><?=View::e($auth['username'])?></b> 👋
        Tienes <b>⭐ <?=View::e((string)$auth['points_balance'])?> puntos</b> disponibles.
    </p>
</section>

<div class="quick-grid">
    <a class="quick" href="/catalogo">
        <span>🍰</span>
        <h3>Ver catálogo</h3>
        <p>Encuentra tu próximo pastel favorito.</p>
    </a>

    <a class="quick" href="/profile">
        <span>⭐</span>
        <h3>Mis puntos y cuenta</h3>
        <p>Consulta tus datos, movimientos y pedidos.</p>
    </a>

    <a class="quick" href="/cart">
        <span>🛒</span>
        <h3>Mi carrito</h3>
        <p>Revisa tus productos y continúa con tu compra.</p>
    </a>
</div>
