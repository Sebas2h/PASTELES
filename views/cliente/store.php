<?php
use App\Helpers\{View,Csrf};

$balance = (int)($auth['points_balance'] ?? 0);
$statusLabels = [
    'AVAILABLE' => 'Disponible',
    'USED' => 'Usado',
    'CANCELLED' => 'Cancelado',
];
$wheelStock = null;
foreach ($items as $storeItem) {
    if ($storeItem['kind'] === 'WHEEL') { $wheelStock = $storeItem['stock']; break; }
}
?>
<section class="store-hero">
    <div>
        <span class="eyebrow">TIENDA · BETA</span>
        <h1>Convierte tus puntos en premios</h1>
        <p>Canjea tus puntos por cupones o prueba tu suerte en la ruleta.</p>
    </div>
    <div class="store-balance">
        <small>Tu saldo</small>
        <strong>⭐ <?=number_format($balance)?> pts</strong>
        <span>Los puntos se descuentan al confirmar cada canje.</span>
    </div>
</section>

<div class="store-beta-note">
    <b>🧪 Modo beta:</b> los premios y sus códigos quedan guardados en tu billetera. Los cupones todavía no se aplican automáticamente en el checkout.
</div>

<section class="section-head store-section-head">
    <div>
        <span class="eyebrow">RECOMPENSAS</span>
        <h2>Elige tu premio</h2>
    </div>
</section>

<div class="store-grid store-grid--wheel">
    <?php foreach ($items as $item): ?>
        <?php
        $cost = $item['kind'] === 'WHEEL' ? $wheelCost : (int)$item['points_cost'];
        $enough = $balance >= $cost;
        $inStock = $item['stock'] === null || (int)$item['stock'] > 0;
        $available = $enough && $inStock;
        ?>
        <?php if ($item['kind'] === 'WHEEL'): ?>
            <?php if ($balance >= $wheelCost): ?>
            <article class="store-card store-wheel-card">
                <div class="store-card-icon store-wheel-icon">🎡</div>
                <div class="store-card-copy">
                    <span class="store-kind">RULETA</span>
                    <h3><?=View::e($item['name'])?></h3>
                    <p><?=View::e($item['description'])?></p>
                    <div class="store-wheel-jackpot"><span>🏆 Premio mayor</span><strong>Cupón de 20% OFF</strong></div>
                    <?php if ($item['stock'] !== null): ?>
                        <small>Giros disponibles: <?=max(0,(int)$item['stock'])?></small>
                    <?php endif; ?>
                </div>

                <div class="store-wheel-layout">
                    <ul class="store-wheel-legend" aria-label="Premios de la ruleta">
                        <?php foreach ($wheelPrizes as $prize): ?>
                            <li><i class="wheel-color" style="background: <?=View::e($prize['color'])?>"></i><span><?=View::e($prize['name'])?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="store-card-footer">
                    <strong>⭐ <?=number_format($wheelCost)?> pts por giro</strong>
                    <button class="primary" type="button" data-open-wheel <?=$available?'':'disabled'?>><?=$available?'Abrir ruleta':(!$inStock?'Agotada':'Faltan puntos')?></button>
                </div>
            </article>
            <?php else: ?>
                <article class="store-card store-wheel-locked"><div class="store-card-icon">🎡</div><div><span class="store-kind">RULETA</span><h3>La ruleta se activa con puntos</h3><p>Acumula <?=number_format($wheelCost)?> puntos para ver y girar por sus premios.</p></div></article>
            <?php endif; ?>
        <?php else: ?>
            <article class="store-card">
                <div class="store-card-icon">🎟️</div>
                <div class="store-card-copy">
                    <span class="store-kind">CUPÓN</span>
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
                    <form method="post" action="/tienda/canjear/<?=$item['id']?>" data-confirm="¿Canjear <?=number_format($cost)?> puntos por <?=View::e($item['name'])?>?">
                        <?=Csrf::field()?>
                        <button class="primary" <?=$available?'':'disabled'?>><?=$available?'Canjear':(!$inStock?'Agotado':'Faltan puntos')?></button>
                    </form>
                </div>
            </article>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php if ($balance >= $wheelCost || $spinResult): ?>
<dialog class="wheel-modal" aria-labelledby="wheel-modal-title" data-wheel-modal data-wheel-stock="<?=$wheelStock===null?'':(int)$wheelStock?>"<?php if ($spinResult): ?> data-open-on-load data-pending-code="<?=View::e($spinResult['code'])?>"<?php endif; ?>>
    <div class="wheel-modal__top">
        <div><span class="eyebrow">DULCE NARANJA</span><h2 id="wheel-modal-title">Ruleta de premios</h2></div>
        <button class="wheel-modal__close" type="button" aria-label="Cerrar ruleta" data-close-wheel>×</button>
    </div>
    <div class="wheel-modal__content">
        <div class="wheel-modal__stage">
            <span class="store-wheel-pointer" aria-hidden="true">▼</span>
            <div class="store-wheel" role="img" aria-label="Ruleta con siete sectores; los tres premios comunes aparecen dos veces y el premio mayor una vez"
                style="background: <?=$wheelGradient?>;<?php if ($spinResult): ?> --wheel-stop: <?=number_format(2160+(float)$spinResult['stop_angle'],1,'.','')?>deg<?php endif; ?>"
                data-wheel-disc>
                <?php $wheelSegmentStart=0.0; foreach ($wheelSegments as $segment): $segmentKey=$segment['key']; $labelAngle=$wheelSegmentStart+((float)$segment['size']*1.8); ?>
                    <span class="wheel-sector-label" style="--label-angle:<?=number_format($labelAngle,2,'.','')?>deg;--label-counter-angle:-<?=number_format($labelAngle,2,'.','')?>deg;--label-width:<?=$segmentKey==='twenty'?'46px':'54px'?>"><span class="wheel-sector-label__text"><?=View::e(match($segmentKey){'muffin'=>'Muffin gratis','five'=>'Cupón 5%','ten'=>'Cupón 10%','twenty'=>'Cupón 20%'})?></span></span>
                <?php $wheelSegmentStart+=(float)$segment['size']*3.6; endforeach; ?>
            </div>
            <form class="wheel-modal__spin-form" method="post" action="/tienda/ruleta" data-wheel-spin>
                <?=Csrf::field()?>
                <button class="wheel-spin-button" type="submit" data-wheel-spin-button <?=$wheelStock===null||(int)$wheelStock>0?'':'disabled'?>><?=$spinResult?'Otra vez':'Girar'?></button>
            </form>
        </div>
        <div class="wheel-modal__side">
            <div class="store-wheel-jackpot"><span>🏆 Premio mayor</span><strong>Cupón de 20% OFF</strong></div>
            <ul class="store-wheel-legend" aria-label="Premios de la ruleta">
                <?php foreach ($wheelPrizes as $prize): ?>
                    <li><i class="wheel-color" style="background: <?=View::e($prize['color'])?>"></i><span><?=View::e($prize['name'])?></span></li>
                <?php endforeach; ?>
            </ul>
            <section class="wheel-modal__result" data-wheel-result hidden role="status" aria-live="polite">
                <span class="eyebrow">¡GANASTE!</span>
                <h3 data-wheel-result-name><?=$spinResult?View::e($spinResult['name']):''?></h3>
                <span>Guarda tu código</span><code data-wheel-result-code><?=$spinResult?View::e($spinResult['code']):''?></code>
                <small data-wheel-result-points><?=$spinResult?'Se descontaron '.number_format((int)$spinResult['points_spent']).' puntos.':''?></small>
            </section>
            <p class="wheel-modal__cost" data-wheel-cost>⭐ <span data-wheel-cost-label><?=$spinResult?'Cambiar el premio cuesta':'El giro cuesta'?></span> <strong><span data-wheel-cost-value><?=number_format($wheelCost)?></span> puntos</strong></p>
            <p class="wheel-modal__error" data-wheel-error hidden></p>
        </div>
    </div>
    <form class="wheel-modal__accept-form" method="post" action="/tienda/ruleta/aceptar" data-wheel-accept-form>
        <?=Csrf::field()?>
        <input type="hidden" name="code" value="<?=$spinResult?View::e($spinResult['code']):''?>">
    </form>
</dialog>
<dialog class="app-modal" aria-labelledby="wheel-first-confirm-title" aria-describedby="wheel-first-confirm-message" data-wheel-first-confirm>
    <div class="app-modal__icon" aria-hidden="true">⭐</div>
    <h2 id="wheel-first-confirm-title">¿Confirmas tu giro?</h2>
    <p class="app-modal__message" id="wheel-first-confirm-message">Se descontarán <?=number_format($wheelCost)?> puntos de tu saldo para girar la ruleta. ¿Deseas continuar?</p>
    <div class="app-modal__actions">
        <button class="secondary" type="button" data-wheel-first-cancel>Cancelar</button>
        <button class="primary" type="button" data-wheel-first-accept>Sí, girar por <?=number_format($wheelCost)?> puntos</button>
    </div>
</dialog>
<dialog class="app-modal" aria-labelledby="wheel-confirm-title" aria-describedby="wheel-confirm-message" data-wheel-confirm>
    <div class="app-modal__icon" aria-hidden="true">?</div>
    <h2 id="wheel-confirm-title">¿Quieres salir y aceptar este premio?</h2>
    <p class="app-modal__message" id="wheel-confirm-message">Si aceptas, este premio quedará guardado en tu cuenta. Para cambiarlo después tendrás que pagar otros 150 puntos.</p>
    <p class="app-modal__notice error" data-wheel-confirm-error hidden></p>
    <div class="app-modal__actions">
        <button class="secondary" type="button" data-wheel-confirm-cancel>Seguir en la ruleta</button>
        <button class="primary" type="button" data-wheel-confirm-accept>Aceptar premio y salir</button>
    </div>
</dialog>
<?php endif; ?>

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
            <h3>Aún no tienes premios</h3>
            <p class="muted">Los premios y códigos que ganes aparecerán aquí.</p>
        </div>
    <?php else: ?>
        <div class="store-redemptions">
            <?php foreach ($redemptions as $redemption): ?>
                <article class="store-ticket">
                    <div>
                        <span class="store-kind"><?=View::e($redemption['kind']==='COUPON'?'Cupón':'Premio')?></span>
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
