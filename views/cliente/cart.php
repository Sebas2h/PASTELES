<?php use App\Helpers\{View,Csrf,Auth}; ?>
<section class="page-head"><span class="eyebrow">TU SELECCIÓN</span><h1>Mi carrito</h1><p>Revisa lo que estás pidiendo y el seguimiento de tus compras.</p></section>

<section class="cart-section">
    <div class="section-head cart-section-head"><div><span class="eyebrow">PEDIDO ACTUAL</span><h2>Lo que llevas</h2></div><a href="/catalogo">Seguir comprando →</a></div>
    <?php if(!$items): ?><div class="empty cart-empty"><span>🛒</span><h2>Tu carrito está vacío</h2><a class="primary btn" href="/catalogo">Explorar productos</a></div>
    <?php else: ?>
        <div class="cart-layout">
            <form method="post" action="/cart/update" class="panel cart-items-form">
                <?=Csrf::field()?>
                <?php foreach($items as $i): ?>
                    <div class="cart-item"><div class="thumb"><?php if($i['image']): ?><img src="<?=View::e($i['image'])?>" alt=""><?php else: ?>🍰<?php endif; ?></div><div><h3><?=View::e($i['name'])?></h3><small>$<?=number_format($i['price'],2)?> c/u</small></div><label class="cart-quantity">Cantidad<input type="number" min="0" max="<?=$i['stock']?>" name="qty[<?=$i['id']?>]" value="<?=$i['quantity']?>"></label><strong>$<?=number_format($i['price']*$i['quantity'],2)?></strong></div>
                <?php endforeach; ?>
                <button class="secondary" type="submit">Actualizar cantidades</button>
            </form>
            <aside class="summary"><h2>Resumen</h2><div><span>Subtotal</span><strong>$<?=number_format($totals['subtotal'],2)?></strong></div>
                <?php if(Auth::check()): ?>
                    <form method="post" action="/checkout" class="checkout-form"><?=Csrf::field()?>
                        <fieldset class="delivery-choice"><legend>¿Cómo quieres recibirlo?</legend><label><input type="radio" name="delivery_method" value="RETIRO" checked><span>🏠 Retirar en la pastelería</span></label><label><input type="radio" name="delivery_method" value="DOMICILIO"><span>🛵 Envío a domicilio</span></label></fieldset>
                        <fieldset class="reward-choice" data-reward-choice>
                            <legend>Canjear recompensa</legend>
                            <label class="reward-choice-option"><input type="radio" name="reward_mode" value="none" checked><span>No usar recompensas</span></label>
                            <label class="reward-choice-option"><input type="radio" name="reward_mode" value="points" <?=empty($totals['rewards'])?'disabled':''?>><span>Usar recompensa con puntos</span></label>
                            <label class="reward-choice-option"><input type="radio" name="reward_mode" value="promo"><span>Usar código promocional</span></label>
                            <label class="points-reward-field" data-points-reward>Elige tu recompensa<select name="reward_id" disabled required><option value="" disabled selected>Selecciona una recompensa</option><?php foreach($totals['rewards'] as $r): ?><option value="<?=$r['id']?>"><?=View::e($r['name'])?> · <?=$r['points_required']?> pts · <?=$r['discount_percent']?>%</option><?php endforeach; ?></select></label>
                            <div class="promo-code-field" data-promo-field hidden><span>Ingresa el código de tu cupón</span><div class="promo-code-slots" role="group" aria-label="Código promocional: DN seguido de 10 caracteres"><span class="promo-code-slot promo-code-fixed">D</span><span class="promo-code-slot promo-code-fixed">N</span><span class="promo-code-slot promo-code-fixed">-</span><?php for($index=1;$index<=10;$index++): ?><input class="promo-code-slot" type="text" maxlength="1" inputmode="text" autocomplete="off" pattern="[A-Fa-f0-9]" aria-label="Carácter <?=$index?> de 10" data-promo-char data-index="<?=$index-1?>" disabled><?php endfor; ?></div><input type="hidden" name="promo_code" value="" data-promo-value><small>El código aparece en Mis canjes y solo se puede usar una vez.</small></div>
                        </fieldset>
                        <button class="primary wide">Confirmar pedido</button>
                    </form>
                <?php else: ?><a class="primary btn wide" href="/login">Inicia sesión para comprar</a><?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</section>

<?php if(Auth::check()): ?>
<section class="purchase-history" data-purchase-history>
    <div class="section-head cart-section-head"><div><span class="eyebrow">TU ACTIVIDAD</span><h2>Mis compras</h2></div></div>
    <div class="purchase-tabs" role="tablist" aria-label="Categorías de compras">
        <button type="button" class="chip active" data-purchase-tab="pending">Pendientes <span><?=$orderBuckets['pending']?count($orderBuckets['pending']):0?></span></button>
        <button type="button" class="chip" data-purchase-tab="completed">Compras hechas <span><?=$orderBuckets['completed']?count($orderBuckets['completed']):0?></span></button>
        <?php if($orderBuckets['cancelled']): ?><button type="button" class="chip" data-purchase-tab="cancelled">Canceladas <span><?=count($orderBuckets['cancelled'])?></span></button><?php endif; ?>
    </div>
    <?php foreach(['pending'=>'Pendientes','completed'=>'Compras hechas','cancelled'=>'Canceladas'] as $key=>$label): ?>
        <?php if($key==='cancelled'&&!$orderBuckets[$key])continue; ?>
        <div class="purchase-list" data-purchase-panel="<?=$key?>" <?=$key!=='pending'?'hidden':''?>>
            <?php if(!$orderBuckets[$key]): ?><div class="panel purchase-empty"><span><?=$key==='completed'?'🍊':'🧁'?></span><p><?=$key==='pending'?'No tienes compras pendientes.':($key==='completed'?'Tus compras recibidas aparecerán aquí.':'No tienes compras canceladas.')?></p></div><?php endif; ?>
            <?php foreach($orderBuckets[$key] as $order): ?>
                <article class="purchase-card panel">
                    <div class="purchase-summary"><div><span class="eyebrow">PEDIDO #<?=$order['id']?></span><p><?=date('d/m/Y · H:i',strtotime($order['created_at']))?> · <?=$order['items_count']?> producto<?=$order['items_count']===1?'':'s'?></p></div><strong>$<?=number_format($order['total'],2)?></strong></div>
                    <div class="purchase-status-line"><span class="status"><?=View::e($order['customer_status'])?></span><small><?=$order['delivery_method']==='DOMICILIO'?'Envío a domicilio':'Retiro en pastelería'?></small></div>
                    <details class="purchase-details"><summary>Ver detalles</summary><div class="purchase-detail-list"><?php foreach($order['items'] as $item): ?><div><span><?=View::e($item['product_name'])?> × <?=$item['quantity']?></span><strong>$<?=number_format($item['subtotal'],2)?></strong></div><?php endforeach; ?><div class="purchase-totals"><span>Subtotal</span><strong>$<?=number_format($order['subtotal'],2)?></strong></div><?php if((float)$order['discount']>0): ?><div><span>Descuento</span><strong>−$<?=number_format($order['discount'],2)?></strong></div><?php endif; ?></div></details>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>
