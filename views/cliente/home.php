<?php
use App\Helpers\View;

/**
 * Las imágenes se guardan fuera de /public, en /images en la raíz del proyecto.
 * Puedes usar .jpg, .jpeg, .png o .webp sin cambiar este archivo.
 */
$rootImages = dirname(__DIR__, 2) . '/images';
$imageUrl = static function (string $name) use ($rootImages): ?string {
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
        if (is_file($rootImages . '/' . $name . '.' . $ext)) {
            return '/images/' . $name . '.' . $ext;
        }
    }
    return null;
};

$heroImage = $imageUrl('hero');
$panelImages = [];
for ($i = 1; $i <= 5; $i++) {
    $panelImages[$i] = $imageUrl('panel' . $i);
}
?>

<div class="landing-page">
    <section class="landing-hero" id="inicio">
        <div class="landing-hero__content">
            <span class="landing-kicker">PASTELERÍA DULCE NARANJA</span>
            <h1>Momentos especiales merecen algo <em>realmente dulce.</em></h1>
            <p class="landing-lead">Conoce nuestra pastelería, descubre lo que hacemos y encuentra el pastel perfecto para celebrar tus mejores momentos.</p>

        </div>

        <div class="landing-hero__media<?= $heroImage ? ' has-image' : '' ?>">
            <?php if ($heroImage): ?>
                <img src="<?=View::e($heroImage)?>" alt="Imagen principal de la pastelería Dulce Naranja">
            <?php else: ?>
                <div class="landing-placeholder">
                    <span>🎂</span>
                    <strong>Tu imagen principal</strong>
                    <small>images/hero.jpg</small>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="landing-intro" id="nuestra-historia">
        <span class="landing-kicker">CONÓCENOS</span>
        <h2>Una página para contar la historia de tu pastelería</h2>
        <p>Estos textos son de ejemplo. Puedes cambiarlos cuando quieras sin alterar el diseño.</p>
    </div>

    <section class="landing-panel landing-panel--image-left">
        <div class="landing-panel__media<?= $panelImages[1] ? ' has-image' : '' ?>">
            <?php if ($panelImages[1]): ?>
                <img src="<?=View::e($panelImages[1])?>" alt="Nuestra historia">
            <?php else: ?>
                <div class="landing-placeholder"><span>🧁</span><strong>Imagen 1</strong><small>images/panel1.jpg</small></div>
            <?php endif; ?>
        </div>
        <div class="landing-panel__content">
            <span class="landing-number">01</span>
            <span class="landing-kicker">NUESTRA HISTORIA</span>
            <h2>Pasión por crear recuerdos que también se pueden saborear.</h2>
            <p>En Dulce Naranja creemos que un pastel no es solo un postre. Es parte de un cumpleaños, una reunión, un detalle especial y de esos momentos que queremos recordar.</p>
            <p class="landing-note">Puedes reemplazar este texto por la historia real de tu negocio.</p>
        </div>
    </section>

    <section class="landing-panel">
        <div class="landing-panel__content">
            <span class="landing-number">02</span>
            <span class="landing-kicker">HECHO CON CARIÑO</span>
            <h2>Detalles cuidados desde la idea hasta la última decoración.</h2>
            <p>Presenta aquí la forma en la que preparan sus productos, los ingredientes que utilizan, el cuidado en la decoración o aquello que hace diferente a la pastelería.</p>
            <div class="landing-mini-features">
                <span>🍊 Sabor</span><span>✨ Detalle</span><span>🎁 Presentación</span>
            </div>
        </div>
        <div class="landing-panel__media<?= $panelImages[2] ? ' has-image' : '' ?>">
            <?php if ($panelImages[2]): ?>
                <img src="<?=View::e($panelImages[2])?>" alt="Preparación artesanal de pasteles">
            <?php else: ?>
                <div class="landing-placeholder"><span>🍰</span><strong>Imagen 2</strong><small>images/panel2.jpg</small></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="landing-panel landing-panel--image-left">
        <div class="landing-panel__media<?= $panelImages[3] ? ' has-image' : '' ?>">
            <?php if ($panelImages[3]): ?>
                <img src="<?=View::e($panelImages[3])?>" alt="Variedad de sabores y diseños">
            <?php else: ?>
                <div class="landing-placeholder"><span>🍓</span><strong>Imagen 3</strong><small>images/panel3.jpg</small></div>
            <?php endif; ?>
        </div>
        <div class="landing-panel__content">
            <span class="landing-number">03</span>
            <span class="landing-kicker">SABORES Y DISEÑOS</span>
            <h2>Desde lo clásico hasta una idea creada especialmente para ti.</h2>
            <p>Utiliza este panel para hablar de sabores, tamaños, estilos, decoraciones personalizadas o las opciones que más les gustan a tus clientes.</p>
            <?php if ($auth): ?>
                <a class="landing-arrow-link" href="/catalogo">Explorar nuestros pasteles →</a>
            <?php else: ?>
                <a class="landing-arrow-link" href="/login">Ingresa para explorar el catálogo →</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="landing-panel">
        <div class="landing-panel__content">
            <span class="landing-number">04</span>
            <span class="landing-kicker">PARA CADA OCASIÓN</span>
            <h2>Un pastel para cumpleaños, reuniones y celebraciones especiales.</h2>
            <p>Aquí puedes explicar cómo realizan los pedidos, cuánto tiempo de anticipación recomiendan o qué tipos de celebraciones atienden.</p>
            <div class="landing-tags">
                <span>Cumpleaños</span><span>Aniversarios</span><span>Detalles</span><span>Eventos</span>
            </div>
        </div>
        <div class="landing-panel__media<?= $panelImages[4] ? ' has-image' : '' ?>">
            <?php if ($panelImages[4]): ?>
                <img src="<?=View::e($panelImages[4])?>" alt="Pasteles para ocasiones especiales">
            <?php else: ?>
                <div class="landing-placeholder"><span>🎉</span><strong>Imagen 4</strong><small>images/panel4.jpg</small></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="landing-panel landing-panel--image-left landing-panel--last">
        <div class="landing-panel__media<?= $panelImages[5] ? ' has-image' : '' ?>">
            <?php if ($panelImages[5]): ?>
                <img src="<?=View::e($panelImages[5])?>" alt="Experiencia Dulce Naranja">
            <?php else: ?>
                <div class="landing-placeholder"><span>💛</span><strong>Imagen 5</strong><small>images/panel5.jpg</small></div>
            <?php endif; ?>
        </div>
        <div class="landing-panel__content">
            <span class="landing-number">05</span>
            <span class="landing-kicker">DULCE NARANJA</span>
            <h2>Tu próxima celebración puede empezar aquí.</h2>
            <p>Finaliza la presentación con un mensaje sobre tu marca, tus promociones, el sistema de puntos o cualquier información que quieras destacar.</p>
            <div class="landing-actions">
                <?php if ($auth): ?>
                    <a class="primary btn" href="/catalogo">Elegir mi pastel</a>
                    <a class="secondary btn" href="/profile">Ver mi cuenta</a>
                <?php else: ?>
                    <a class="primary btn" href="/register">Registrarme</a>
                    <a class="secondary btn" href="/login">Ya tengo cuenta</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
