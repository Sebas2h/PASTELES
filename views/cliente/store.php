<?php
use App\Helpers\{View,Csrf};

$balance = (int)($auth['points_balance'] ?? 0);
$statusLabels = [
    'AVAILABLE' => 'Disponible',
    'USED' => 'Usado',
    'CANCELLED' => 'Cancelado',
];
?>
<section class="store-hero">
    <div>
        <span class="eyebrow">TIENDA · BETA</span>
        <h1>Convierte tus puntos en premios</h1>
        <p>Esta primera versión deja listo el sistema de canjes. Los cupones obtenidos quedan guardados en tu cuenta para conectarlos al carrito en la siguiente mejora.</p>
    </div>
    <div class="store-balance">
        <small>Tu saldo</small>
        <strong>⭐ <?=number_format($balance)?> pts</strong>
        <span>Los puntos se descuentan al confirmar cada canje.</span>
    </div>
</section>

<div class="store-beta-note">
    <b>🧪 Modo beta:</b> el canje y el código ya son reales dentro del sistema; la aplicación automática del cupón al checkout se añadirá después.
</div>

<section class="section-head store-section-head">
    <div>
        <span class="eyebrow">RECOMPENSAS</span>
        <h2>Elige tu premio</h2>
    </div>
</section>

<div class="store-grid">
    <?php foreach ($items as $item): ?>
        <?php
        $cost = (int)$item['points_cost'];
        $enough = $balance >= $cost;
        $inStock = $item['stock'] === null || (int)$item['stock'] > 0;
        $available = $enough && $inStock;
        ?>
        <article class="store-card">
            <div class="store-card-icon"><?=$item['kind']==='COUPON'?'🎟️':'🎁'?></div>
            <div class="store-card-copy">
                <span class="store-kind"><?=$item['kind']==='COUPON'?'Cupón':'Beneficio'?></span>
                <h3><?=View::e($item['name'])?></h3>
                <p><?=View::e($item['description'])?></p>
                <?php if ($item['discount_percent'] !== null): ?>
                    <div class="store-discount"><?=View::e(rtrim(rtrim(number_format((float)$item['discount_percent'],2,'.',''),'0'),'.'))?>% OFF</div>
                <?php endif; ?>
                <?php if ($item['stock'] !== null): ?>
                    <small>Stock beta: <?=max(0,(int)$item['stock'])?></small>
                <?php else: ?>
                    <small>Disponibilidad ilimitada</small>
                <?php endif; ?>
            </div>
            <div class="store-card-footer">
                <strong>⭐ <?=number_format($cost)?> pts</strong>
                <form method="post" action="/tienda/canjear/<?=$item['id']?>" onsubmit="return confirm('¿Canjear <?=number_format($cost)?> puntos por <?=View::e($item['name'])?>?');">
                    <?=Csrf::field()?>
                    <button class="primary" <?=$available?'':'disabled'?>>
                        <?=$available?'Canjear':(!$inStock?'Agotado':'Faltan puntos')?>
                    </button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<section class="section-head store-section-head">
    <div>
        <span class="eyebrow">MI BILLETERA</span>
        <h2>Mis canjes</h2>
    </div>
</section>

<section class="panel store-wallet">
    <?php if (!$redemptions): ?>
        <div class="empty store-empty">
            <span>🎫</span>
            <h3>Aún no tienes cupones</h3>
            <p class="muted">Cuando canjees una recompensa aparecerá aquí con su código.</p>
        </div>
    <?php else: ?>
        <div class="store-redemptions">
            <?php foreach ($redemptions as $redemption): ?>
                <article class="store-ticket">
                    <div>
                        <span class="store-kind"><?=View::e($redemption['kind']==='COUPON'?'Cupón':'Beneficio')?></span>
                        <h3><?=View::e($redemption['name'])?></h3>
                        <code><?=View::e($redemption['code'])?></code>
                    </div>
                    <div class="store-ticket-meta">
                        <span class="status"><?=View::e($statusLabels[$redemption['status']] ?? $redemption['status'])?></span>
                        <strong>-<?=number_format((int)$redemption['points_spent'])?> pts</strong>
                        <small><?=View::e(date('d/m/Y H:i', strtotime($redemption['redeemed_at'])))?></small>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
