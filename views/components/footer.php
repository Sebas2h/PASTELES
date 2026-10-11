<?php use App\Helpers\View; ?>
</main>
<?php if($auth): ?>
    <button class="floating-cart" type="button" data-cart-open aria-label="Abrir carrito con <?=$cartCount?> productos" title="Ver mi carrito"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 1.9-1.4L22 8H6M10 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg><span class="floating-cart-count"><?=$cartCount?></span></button>
    <button class="cart-drawer-backdrop" type="button" data-cart-close aria-label="Cerrar carrito" hidden></button>
    <aside class="cart-drawer" data-cart-drawer aria-hidden="true" aria-labelledby="cart-drawer-title" inert>
        <div class="cart-drawer-head"><div><span class="eyebrow">PEDIDO ACTUAL</span><h2 id="cart-drawer-title">Tu carrito</h2></div><button class="cart-drawer-close" type="button" data-cart-close aria-label="Cerrar">×</button></div>
        <?php if(!$cartItems): ?><div class="cart-drawer-empty"><span>🛒</span><p>Aún no agregas productos.</p><a href="/catalogo">Explorar productos</a></div>
        <?php else: ?><div class="cart-drawer-items"><?php foreach($cartItems as $cartItem): ?><div class="cart-drawer-item"><div><strong><?=View::e($cartItem['name'])?></strong><small><?=$cartItem['quantity']?> × $<?=number_format((float)$cartItem['price'],2)?></small></div><b>$<?=number_format($cartItem['line_total'],2)?></b></div><?php endforeach; ?></div><div class="cart-drawer-total"><span>Total</span><strong>$<?=number_format($cartTotal,2)?></strong></div><a class="primary btn wide" href="/cart">Ver carrito y compras</a><?php endif; ?>
    </aside>
<?php endif; ?>
<footer><p>Hecho con dulzura · Pastelería Dulce Naranja</p></footer></body></html>
