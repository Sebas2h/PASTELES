<?php use App\Helpers\View; ?>

<section class="admin-welcome">
    <h1>Hola, <?=View::e($auth['username'])?></h1>
    <p>Este es tu resumen de hoy. Que cada pedido termine en un momento dulce.</p>
</section>

<div class="metrics">
    <div>
        <small>Productos</small>
        <strong><?=$metrics['products']?></strong>
    </div>
    <div>
        <small>Compradores</small>
        <strong><?=$metrics['clients']?></strong>
    </div>
    <div>
        <small>Pedidos</small>
        <strong><?=$metrics['orders']?></strong>
    </div>
    <div>
        <small>Pendientes</small>
        <strong><?=$metrics['pending']?></strong>
    </div>
    <div>
        <small>Ventas</small>
        <strong>$<?=number_format($metrics['sales'],2)?></strong>
    </div>
    <div>
        <small>Puntos entregados</small>
        <strong><?=$metrics['points']?></strong>
    </div>
</div>
