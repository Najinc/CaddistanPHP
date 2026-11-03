<?php /** @var array $categories @var array $products */ ?>
<section class="relative overflow-hidden rounded-2xl bg-ink px-6 py-14 text-white sm:px-12">
    <div class="relative z-10 max-w-xl">
        <p class="text-sm font-semibold uppercase tracking-widest text-brand-500">Le pays du caddy</p>
        <h1 class="mt-3 text-4xl text-white md:text-5xl">Tous les caddies du monde, au même endroit.</h1>
        <p class="mt-4 text-slate-300">Supermarché, golf, entrepôt, marché du dimanche ou fond du jardin&nbsp;: chez Caddistan, chaque course a son chariot.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="/produits" class="btn-primary px-6 py-3 text-base">Voir le catalogue</a>
            <a href="/produits?categorie=golf" class="btn bg-white/10 px-6 py-3 text-base text-white hover:bg-white/20">Espace golf</a>
        </div>
    </div>
    <img src="/images/products/supermarche.svg" alt="" class="absolute -right-10 bottom-0 hidden h-80 opacity-90 md:block">
</section>

<section class="mt-12">
    <h2>Nos univers</h2>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <?php foreach ($categories as $category): ?>
            <a href="/produits?categorie=<?= $category['slug'] ?>" class="card group p-5 transition-shadow hover:shadow-md">
                <img src="/images/products/<?= $category['slug'] ?>.svg" alt="" class="h-16 transition-transform group-hover:-rotate-6">
                <h3 class="mt-3 group-hover:text-brand-700"><?= $category['name'] ?></h3>
                <p class="mt-1 text-sm text-slate-500"><?= (int) $category['products_count'] ?> produit(s)</p>
            </a>
        <?php endforeach ?>
    </div>
</section>

<section class="mt-12">
    <div class="flex items-end justify-between">
        <h2>Nouveautés</h2>
        <a href="/produits" class="btn-link">Tout voir &rarr;</a>
    </div>
    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($products as $product): ?>
            <?= partial('product-card', ['product' => $product]) ?>
        <?php endforeach ?>
    </div>
</section>
