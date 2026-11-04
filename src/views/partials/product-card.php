<?php /** @var array $product */ ?>
<article class="card group flex flex-col overflow-hidden transition-shadow hover:shadow-md">
    <a href="/produits/{{ $product['slug'] }}" class="block aspect-[4/3] overflow-hidden bg-slate-100">
        <img src="{{ $product['image'] ?: '/images/products/default.svg' }}" alt="{{ $product['name'] }}"
             class="h-full w-full object-contain p-6 transition-transform duration-300 group-hover:scale-105" loading="lazy">
    </a>
    <div class="flex flex-1 flex-col p-4">
        <?php if (isset($product['category_name'])): ?>
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ $product['category_name'] }}</p>
        <?php endif ?>
        <h3 class="mt-1">
            <a href="/produits/{{ $product['slug'] }}" class="hover:text-brand-700">{{ $product['name'] }}</a>
        </h3>
        <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $product['short_description'] }}</p>
        <div class="mt-auto flex items-center justify-between pt-4">
            <span class="text-lg font-bold text-ink">{{ money((int) $product['price_cents']) }}</span>
            <?php if ((int) $product['stock'] > 0): ?>
                <form action="/panier/ajouter" method="post" data-add-to-cart>
                    <input type="hidden" name="product_id" value="{{ (int) $product['id'] }}">
                    <button type="submit" class="btn-primary">Ajouter</button>
                </form>
            <?php else: ?>
                <span class="badge bg-slate-100 text-slate-500">Rupture</span>
            <?php endif ?>
        </div>
    </div>
</article>
