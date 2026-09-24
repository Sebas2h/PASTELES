<?php use App\Helpers\{View,Csrf};?>
<link rel="stylesheet" href="/css/profile.css">
<script defer src="/js/profile.js"></script>

<section class="verify-wrap">
    <div class="verify-card">
        <a class="back-link" href="/profile">← Volver a Mi cuenta</a>

        <?php if($step==='current'):?>
            <div class="verify-icon">✉️</div>
            <span class="eyebrow">PASO 1 DE 3</span>
            <h1>Verifica tu correo actual</h1>
            <p>Enviamos un código de 6 dígitos a <strong><?=View::e($maskedEmail)?></strong>. Debes demostrar que todavía tienes acceso a ese correo antes de poder indicar uno nuevo.</p>
            <form method="post" action="/profile/email/verify-current" class="code-form">
                <?=Csrf::field()?>
                <label>Código de verificación
                    <input class="code-input" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus>
                </label>
                <button class="primary wide">Verificar correo actual</button>
            </form>
            <form method="post" action="/profile/email/request-current" class="resend-form">
                <?=Csrf::field()?>
                <button class="linkbtn" type="submit">Reenviar código</button>
            </form>

        <?php elseif($step==='new'):?>
            <div class="verify-icon">📮</div>
            <span class="eyebrow">PASO 2 DE 3</span>
            <h1>Ingresa tu nuevo correo</h1>
            <p>Tu correo actual ya fue verificado. Ahora escribe la nueva dirección que quieres usar.</p>
            <form method="post" action="/profile/email/request-new" class="code-form">
                <?=Csrf::field()?>
                <label>Nuevo correo electrónico
                    <input type="email" name="new_email" maxlength="160" placeholder="nuevo@correo.com" required autofocus autocomplete="email">
                </label>
                <button class="primary wide">Enviar código al nuevo correo</button>
            </form>

        <?php else:?>
            <div class="verify-icon">✅</div>
            <span class="eyebrow">PASO 3 DE 3</span>
            <h1>Confirma el nuevo correo</h1>
            <p>Enviamos otro código de 6 dígitos a <strong><?=View::e($maskedEmail)?></strong>. El correo solo se cambiará si este código es correcto.</p>
            <form method="post" action="/profile/email/verify-new" class="code-form">
                <?=Csrf::field()?>
                <label>Código del nuevo correo
                    <input class="code-input" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus>
                </label>
                <button class="primary wide">Confirmar y cambiar correo</button>
            </form>
            <form method="post" action="/profile/email/request-new" class="resend-form">
                <?=Csrf::field()?>
                <input type="hidden" name="new_email" value="<?=View::e($newEmail??'')?>">
                <button class="linkbtn" type="submit">Reenviar código al nuevo correo</button>
            </form>
        <?php endif;?>

        <div class="verify-security">
            <strong>Protección activa</strong>
            <span>Los códigos expiran en 10 minutos y se bloquean después de varios intentos incorrectos.</span>
        </div>
    </div>
</section>
