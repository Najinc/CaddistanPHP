<?php
/** @var array $products @var array $categories @var ?array $category */
$baseQuery = array_filter(['q' => $q, 'categorie' => $category['slug'] ?? null, 'tri' => $sort !== 'recent' ? $sort : null]);
$sortLabels = ['recent' => 'Nouveautés', 'prix-asc' => 'Prix croissant', 'prix-desc' => 'Prix décroissant', 'nom' => 'Nom (A-Z)'];
?>
<div class="grid gap-8 lg:grid-cols-[220px_1fr]">
    <aside>
        <h2 class="text-base">Catégories</h2>
        <ul class="mt-3 space-y-1 text-sm">
            <li>
                <a href="/produits{{ $q !== '' ? '?q=' . urlencode($q) : '' }}"
                   class="block rounded-md px-3 py-2 {{ $category === null ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-100' }}">
                    Tous les caddies
                </a>
            </li>
            <?php foreach ($categories as $cat): ?>
                <li>
                    <a href="/produits?{{ http_build_query(array_filter(['categorie' => $cat['slug'], 'q' => $q])) }}"
                       class="flex justify-between rounded-md px-3 py-2 {{ ($category['id'] ?? null) === $cat['id'] ? 'bg-brand-50 font-semibold text-brand-700' : 'hover:bg-slate-100' }}">
                        {{ $cat['name'] }} <span class="text-slate-400">{{ (int) $cat['products_count'] }}</span>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    </aside>

    <div>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1>{{ $category['name'] ?? 'Catalogue' }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $total }} résultat(s)<?php if ($q !== ''): ?> pour « <strong>{{ $q }}</strong> »<?php endif ?>
                </p>
            </div>
            <form method="get" action="/produits" class="flex items-center gap-2">
                <?php if ($category): ?><input type="hidden" name="categorie" value="{{ $category['slug'] }}"><?php endif ?>
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="{{ $q }}"><?php endif ?>
                <label for="tri" class="text-sm text-slate-500">Trier</label>
                <select id="tri" name="tri" class="input w-auto" data-autosubmit>
                    <?php foreach ($sortLabels as $value => $label): ?>
                        <option value="{{ $value }}" {{ $sort === $value ? 'selected' : '' }}>{{ $label }}</option>
                    <?php endforeach ?>
                </select>
            </form>
        </div>

        <?php if ($products === []): ?>
            <div class="card mt-6 p-10 text-center text-slate-500">
                Aucun caddy ne correspond à votre recherche.
            </div>
        <?php else: ?>
            <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($products as $product): ?>
                    {!! partial('product-card', ['product' => $product]) !!}
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <?php if ($pages > 1): ?>
            <nav class="mt-8 flex justify-center gap-1" aria-label="Pagination">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a href="/produits?{{ http_build_query([...$baseQuery, 'page' => $i]) }}"
                       class="btn {{ $i === $page ? 'bg-brand-600 text-white' : 'bg-white ring-1 ring-slate-300 hover:bg-slate-100' }}">{{ $i }}</a>
                <?php endfor ?>
            </nav>
        <?php endif ?>
    </div>
</div>
