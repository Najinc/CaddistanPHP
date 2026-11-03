<?php /** @var array $product @var array $reviews @var array $rating @var array $related */ ?>
<nav class="mb-6 text-sm text-slate-500">
    <a href="/produits" class="hover:text-brand-700">Catalogue</a> /
    <a href="/produits?categorie=<?= $product['category_slug'] ?>" class="hover:text-brand-700"><?= $product['category_name'] ?></a> /
    <span class="text-slate-700"><?= $product['name'] ?></span>
</nav>

<div class="grid gap-10 md:grid-cols-2">
    <div class="card flex aspect-square items-center justify-center p-10">
        <img src="<?= $product['image'] ?: '/images/products/default.svg' ?>" alt="<?= $product['name'] ?>" class="max-h-full object-contain">
    </div>

    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-brand-700"><?= $product['category_name'] ?></p>
        <h1 class="mt-2"><?= $product['name'] ?></h1>

        <?php if ($rating['count'] > 0): ?>
            <a href="#avis" class="mt-2 flex items-center gap-2 text-sm text-slate-500">
                <?= partial('stars', ['rating' => $rating['average']]) ?>
                <?= $rating['average'] ?>/5 (<?= $rating['count'] ?> avis)
            </a>
        <?php endif ?>

        <p class="mt-6 text-3xl font-extrabold text-ink"><?= money((int) $product['price_cents']) ?></p>
        <p class="mt-1 text-sm <?= $product['stock'] > 0 ? 'text-emerald-700' : 'text-red-600' ?>">
            <?= $product['stock'] > 0 ? 'En stock (' . (int) $product['stock'] . ' disponibles)' : 'Rupture de stock' ?>
        </p>

        <p class="mt-6 text-slate-600"><?= nl2br($product['description']) ?></p>

        <?php if ($product['stock'] > 0): ?>
            <form action="/panier/ajouter" method="post" class="mt-8 flex items-end gap-3">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <div>
                    <label for="quantity" class="label">Quantité</label>
                    <input id="quantity" type="number" name="quantity" value="1" min="1" max="<?= min(99, (int) $product['stock']) ?>" class="input w-24">
                </div>
                <button type="submit" class="btn-primary px-6 py-2.5">Ajouter au panier</button>
            </form>
        <?php endif ?>

        <?php if ($product['datasheet']): ?>
            <a href="/documents/<?= $product['datasheet'] ?>" class="btn-secondary mt-6">
                Télécharger la fiche technique
            </a>
        <?php endif ?>
    </div>
</div>

<section id="avis" class="mt-14 grid gap-8 lg:grid-cols-[1fr_360px]">
    <div>
        <h2>Avis clients</h2>
        <?php if ($reviews === []): ?>
            <p class="mt-4 text-slate-500">Aucun avis pour le moment. Soyez le premier !</p>
        <?php endif ?>
        <div class="mt-4 space-y-4">
            <?php foreach ($reviews as $review): ?>
                <article class="card p-5">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold text-ink"><?= $review['first_name'] . ' ' . mb_substr($review['last_name'], 0, 1) . '.' ?></p>
                        <?= partial('stars', ['rating' => $review['rating']]) ?>
                    </div>
                    <p class="mt-2 text-slate-600"><?= nl2br($review['comment']) ?></p>
                    <p class="mt-2 text-xs text-slate-400"><?= date('d/m/Y', strtotime($review['created_at'])) ?></p>
                </article>
            <?php endforeach ?>
        </div>
    </div>

    <div class="card h-fit p-5">
        <h3>Donner votre avis</h3>
        <?php if (auth()): ?>
            <form action="/produits/<?= $product['slug'] ?>/avis" method="post" class="mt-4 space-y-4">
                <div>
                    <label for="rating" class="label">Note</label>
                    <select id="rating" name="rating" class="input" required>
                        <option value="">Choisir…</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>"><?= $i ?> étoile<?= $i > 1 ? 's' : '' ?></option>
                        <?php endfor ?>
                    </select>
                </div>
                <div>
                    <label for="comment" class="label">Commentaire</label>
                    <textarea id="comment" name="comment" rows="4" class="input" required><?= old('comment') ?></textarea>
                </div>
                <button type="submit" class="btn-primary w-full">Publier</button>
            </form>
        <?php else: ?>
            <p class="mt-2 text-sm text-slate-500"><a href="/connexion" class="btn-link">Connectez-vous</a> pour laisser un avis.</p>
        <?php endif ?>
    </div>
</section>

<?php if ($related): ?>
    <section class="mt-14">
        <h2>Dans la même catégorie</h2>
        <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($related as $item): ?>
                <?= partial('product-card', ['product' => $item]) ?>
            <?php endforeach ?>
        </div>
    </section>
<?php endif ?>
