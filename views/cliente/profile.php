<?php use App\Helpers\{View,Csrf};?>
<link rel="stylesheet" href="/css/profile.css">
<script defer src="/js/profile.js"></script>

<section class="page-head profile-head">
    <div>
        <span class="eyebrow">MI CUENTA</span>
        <h1><?=View::e(trim($auth['first_name'].' '.$auth['last_name']))?></h1>
        <p>Consulta y administra la información de tu cuenta.</p>
    </div>
    <div class="role-badge"><?=View::e($auth['role'])?></div>
</section>

<div class="points-hero">
    <div>
        <small>PUNTOS DISPONIBLES</small>
        <strong>⭐ <?=$auth['points_balance']?></strong>
    </div>
    <p>Úsalos para obtener descuentos en tu próximo pedido.</p>
</div>

<div class="two-col profile-activity">
    <section class="panel">
        <h2>Historial de puntos</h2>
        <?php if(!$movements):?><p class="muted">Todavía no tienes movimientos.</p><?php endif;?>
        <?php foreach($movements as $m):?>
            <div class="movement <?=($m['points']>=0?'plus':'minus')?>">
                <strong><?=($m['points']>0?'+':'').$m['points']?> pts</strong>
                <span><?=View::e($m['description'])?><?= $m['order_no']?' · Pedido #'.$m['order_no']:''?></span>
                <small><?=date('d/m/Y H:i',strtotime($m['created_at']))?></small>
            </div>
        <?php endforeach;?>
    </section>

    <section class="panel">
        <h2>Mis pedidos</h2>
        <p><a href="/cart">Ver compras y carrito →</a></p>
        <?php if(!$orders):?><p class="muted">Todavía no tienes pedidos.</p><?php endif;?>
        <?php foreach($orders as $o):?>
            <div class="orderrow">
                <span><b>#<?=$o['id']?></b><small><?=date('d/m/Y',strtotime($o['created_at']))?></small></span>
                <span class="status"><?=View::e($o['customer_status'])?></span>
                <strong>$<?=number_format($o['total'],2)?></strong>
            </div>
        <?php endforeach;?>
    </section>
</div>

<section class="account-grid">
    <article class="panel account-card">
        <div class="account-card-head">
            <div>
                <span class="eyebrow">DATOS DEL USUARIO</span>
                <h2>Información personal</h2>
            </div>
        </div>

        <div class="account-info">
            <form class="account-field editable-account-field" method="post" action="/profile/update" data-editable-profile-field data-label="nombre">
                <?=Csrf::field()?>
                <input type="hidden" name="field" value="first_name">
                <div class="field-topline">
                    <span>NOMBRE</span>
                    <button class="field-action" type="button" data-field-action aria-label="Editar nombre" title="Editar nombre">
                        <span class="icon-pencil" aria-hidden="true">✎</span>
                        <span class="icon-save" aria-hidden="true">💾</span>
                    </button>
                </div>
                <input
                    class="profile-inline-input"
                    type="text"
                    name="value"
                    maxlength="80"
                    required
                    readonly
                    autocomplete="given-name"
                    data-original="<?=View::e($auth['first_name'])?>"
                    value="<?=View::e($auth['first_name'])?>"
                >
                <small data-field-help>Presiona el lápiz para editar</small>
            </form>

            <form class="account-field editable-account-field" method="post" action="/profile/update" data-editable-profile-field data-label="apellido">
                <?=Csrf::field()?>
                <input type="hidden" name="field" value="last_name">
                <div class="field-topline">
                    <span>APELLIDO</span>
                    <button class="field-action" type="button" data-field-action aria-label="Editar apellido" title="Editar apellido">
                        <span class="icon-pencil" aria-hidden="true">✎</span>
                        <span class="icon-save" aria-hidden="true">💾</span>
                    </button>
                </div>
                <input
                    class="profile-inline-input"
                    type="text"
                    name="value"
                    maxlength="80"
                    required
                    readonly
                    autocomplete="family-name"
                    data-original="<?=View::e($auth['last_name'])?>"
                    value="<?=View::e($auth['last_name'])?>"
                >
                <small data-field-help>Presiona el lápiz para editar</small>
            </form>

            <div class="account-field">
                <span>USUARIO</span>
                <strong>@<?=View::e($auth['username'])?></strong>
                <small>No editable</small>
            </div>

            <div class="account-field email-summary-field">
                <span>CORREO</span>
                <strong><?=View::e($auth['email'])?></strong>
                <small>Se cambia mediante verificación</small>
            </div>

            <div class="account-field">
                <span>ROL</span>
                <strong><?=View::e($auth['role'])?></strong>
                <small>Asignado por el sistema</small>
            </div>
        </div>
    </article>

    <article class="panel email-card">
        <span class="eyebrow">SEGURIDAD</span>
        <h2>Cambiar correo electrónico</h2>
        <p class="muted">Primero verificaremos que todavía tengas acceso a tu correo actual. Después verificaremos el nuevo correo antes de cambiarlo.</p>

        <div class="current-email">
            <span>Correo actual</span>
            <strong><?=View::e($auth['email'])?></strong>
        </div>

        <form method="post" action="/profile/email/request-current" data-email-change-start>
            <?=Csrf::field()?>
            <button class="primary wide" type="submit">Cambiar correo de forma segura</button>
        </form>

        <div class="security-note">🔐 El correo solo cambiará después de validar correctamente ambos códigos.</div>
    </article>
</section>

<div class="profile-confirm-backdrop" data-profile-confirm hidden>
    <div class="profile-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="profile-confirm-title" aria-describedby="profile-confirm-message">
        <div class="confirm-icon" aria-hidden="true">?</div>
        <h3 id="profile-confirm-title">Confirmar cambio</h3>
        <p id="profile-confirm-message" data-confirm-message></p>
        <div class="confirm-actions">
            <button type="button" class="secondary" data-confirm-no>No, cancelar</button>
            <button type="button" class="primary" data-confirm-yes>Sí, cambiar</button>
        </div>
    </div>
</div>
