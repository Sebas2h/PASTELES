<?php use App\Helpers\{View,Csrf}; ?>
<link rel="stylesheet" href="/css/catalogo.css">

<section class="catalogo-page" aria-labelledby="catalogo-title">
    <div class="catalogo-head">
        <div>
            <span class="eyebrow">NUESTRO CATÁLOGO</span>
            <h1 id="catalogo-title">Elige tu favorito</h1>
        </div>

        <div class="catalogo-search-wrap">
            <label class="sr-only" for="catalogoSearch">Buscar pastel</label>
            <input
                id="catalogoSearch"
                class="catalogo-search"
                type="search"
                placeholder="Buscar pastel..."
                autocomplete="off"
            >
        </div>
    </div>

    <div class="catalogo-chips" role="group" aria-label="Filtrar por categoría">
        <button type="button" class="catalog-chip active" data-category="all">Todos</button>
        <?php foreach ($categories as $c): ?>
            <button
                type="button"
                class="catalog-chip"
                data-category="<?= View::e($c['id']) ?>"
            ><?= View::e($c['name']) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="catalogo-count" aria-live="polite">
        <span id="catalogoCount"><?= count($products) ?></span>
        <span id="catalogoCountLabel"><?= count($products) === 1 ? 'pastel disponible' : 'pasteles disponibles' ?></span>
    </div>

    <div class="cards catalogo-grid" id="catalogoGrid">
        <?php foreach ($products as $p): ?>
            <article
                class="product-card catalog-card"
                data-name="<?= View::e(mb_strtolower($p['name'])) ?>"
                data-category="<?= View::e($p['category_id']) ?>"
            >
                <a href="/product/<?= (int)$p['id'] ?>" class="product-image">
                    <?php if ($p['image']): ?>
                        <img src="<?= View::e($p['image']) ?>" alt="<?= View::e($p['name']) ?>">
                    <?php else: ?>
                        <span aria-hidden="true">🍰</span>
                    <?php endif; ?>
                </a>

                <div class="product-body">
                    <small><?= View::e($p['category_name']) ?></small>
                    <h3><?= View::e($p['name']) ?></h3>
                    <p><?= View::e(mb_strimwidth($p['description'], 0, 90, '…')) ?></p>

                    <div class="product-meta">
                        <strong>$<?= number_format((float)$p['price'], 2) ?></strong>
                        <span>⭐ +<?= (int)$p['display_points'] ?> pts</span>
                    </div>

                    <form method="post" action="/cart/add">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                        <button class="secondary wide" type="submit">Agregar al carrito</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="catalogo-empty" id="catalogoEmpty" hidden>
        <span aria-hidden="true">🍰</span>
        <h2>No encontramos pasteles</h2>
        <p>Prueba con otro nombre o selecciona una categoría diferente.</p>
        <button type="button" class="secondary" id="catalogoClear">Ver todos</button>
    </div>
</section>

<script defer src="/js/catalogo.js"></script>
