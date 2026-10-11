<?php
use App\Helpers\View;

$heroImage = '/images/muffins-hero.png';
?>

<div class="landing-page">
    <section class="landing-hero" id="inicio">
        <div class="landing-hero__content">
            <p class="landing-eyebrow"><span aria-hidden="true"></span> MUFFINS DULCE NARANJA</p>
            <h1>Tu pausa merece un <em>muffin.</em></h1>
            <p class="landing-lead">Suaves, doraditos y perfectos para darte un gusto, compartir o regalar.</p>

            <div class="landing-actions">
                <?php if ($auth): ?>
                    <a class="btn primary" href="/catalogo">Ver muffins <span aria-hidden="true">→</span></a>
                    <a class="landing-text-link" href="#momentos">Encuentra el tuyo</a>
                <?php else: ?>
                    <a class="btn primary" href="/register">Ver muffins y pedir <span aria-hidden="true">→</span></a>
                    <a class="landing-text-link" href="#momentos">Conoce nuestros sabores</a>
                <?php endif; ?>
            </div>

            <div class="landing-signature">
                <span class="landing-signature__mark" aria-hidden="true">✳</span>
                <span>Algo rico para ti,<br><strong>o para compartir.</strong></span>
            </div>
        </div>

        <figure class="landing-hero__visual">
            <img src="<?=View::e($heroImage)?>" alt="Imagen de referencia: muffins con fruta y chocolate">
            <figcaption class="landing-photo-note">
                <span class="landing-photo-note__icon" aria-hidden="true">✳</span>
                <span><small>IMAGEN REFERENCIAL</small><strong>Muffins Dulce Naranja</strong></span>
            </figcaption>
        </figure>
        <span class="landing-spark landing-spark--one" aria-hidden="true">✳</span>
        <span class="landing-spark landing-spark--two" aria-hidden="true">✴</span>
    </section>

    <section class="landing-menu" id="momentos" aria-labelledby="landing-menu-title">
        <div class="landing-menu__heading">
            <div>
                <span class="landing-eyebrow">NUESTROS SABORES</span>
                <h2 id="landing-menu-title">¿Cuál se te antoja?</h2>
            </div>
            <p>Dos sabores para empezar. Pronto habrá más para elegir.</p>
        </div>
        <div class="landing-menu__grid landing-flavors">
            <article class="landing-menu__card landing-flavor landing-flavor--choco">
                <div class="landing-flavor__art" aria-hidden="true"><img src="/images/muffin-choconuez.png" alt=""></div>
                <div class="landing-flavor__copy">
                    <span class="landing-menu__number">01 / PARA LOS CHOCOLATEROS</span>
                    <h3>Choconuez</h3>
                    <p>Chocolate y nuez en un muffin hecho para disfrutar cada bocado.</p>
                    <small>IMAGEN REFERENCIAL</small>
                </div>
            </article>
            <article class="landing-menu__card landing-menu__card--highlight landing-flavor landing-flavor--berry">
                <div class="landing-flavor__art" aria-hidden="true"><img src="/images/muffin-arandano-yogur.png" alt=""></div>
                <div class="landing-flavor__copy">
                    <span class="landing-menu__number">02 / FRESCO Y DELICADO</span>
                    <h3>Arándano con yogur</h3>
                    <p>El toque frutal del arándano con la suavidad del yogur.</p>
                    <small>IMAGEN REFERENCIAL</small>
                </div>
            </article>
        </div>
        <?php if ($auth): ?>
            <a class="landing-arrow-link landing-menu__link" href="/catalogo">Ver el catálogo <span aria-hidden="true">↗</span></a>
        <?php else: ?>
            <a class="landing-arrow-link landing-menu__link" href="/register">Crea tu cuenta y explora el catálogo <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
    </section>

    <section class="landing-story" id="nuestra-historia">
        <div class="landing-story__heading">
            <span class="landing-eyebrow">PEQUEÑOS PLACERES</span>
            <h2>Los buenos días también caben en un <em>muffin.</em></h2>
        </div>
        <div class="landing-story__body">
            <p>Hay días que piden una pausa, otros que se disfrutan mejor en compañía y otros que solo necesitan algo rico para mejorar.</p>
            <p>Por eso ponemos a los muffins en el centro: un bocado sencillo, delicioso y listo para acompañar el momento que tú elijas.</p>
            <?php if ($auth): ?>
                <a class="landing-arrow-link" href="/catalogo">Descubre los muffins disponibles <span aria-hidden="true">↗</span></a>
            <?php else: ?>
                <a class="landing-arrow-link" href="/register">Crea tu cuenta para pedir <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </div>
    </section>

    <section class="landing-cta">
        <div>
            <span class="landing-eyebrow">EL ANTOJO YA ESTÁ</span>
            <h2>Encuentra tu muffin favorito y disfruta la pausa.</h2>
        </div>
        <?php if ($auth): ?>
            <a class="btn landing-cta__button" href="/catalogo">Ver muffins <span aria-hidden="true">→</span></a>
        <?php else: ?>
            <div class="landing-cta__actions">
                <a class="btn landing-cta__button" href="/register">Crear cuenta y pedir <span aria-hidden="true">→</span></a>
            </div>
        <?php endif; ?>
        <span class="landing-cta__flower" aria-hidden="true">✳</span>
    </section>
</div>
