<?php /** @var array $stats @var int $productsCount @var int $usersCount @var array $latestOrders @var array $lowStock */ ?>
{!! partial('admin-nav') !!}
<h1>Tableau de bord</h1>

<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ([
        ['Chiffre d\'affaires', money($stats['revenue'])],
        ['Commandes', $stats['count']],
        ['Produits', $productsCount],
        ['Utilisateurs', $usersCount],
    ] as [$label, $value]): ?>
        <div class="card p-5">
            <p class="text-sm text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-bold text-ink">{{ $value }}</p>
        </div>
    <?php endforeach ?>
</div>

<div class="mt-8 grid gap-8 lg:grid-cols-[1fr_320px]">
    <section>
        <div class="flex items-end justify-between">
            <h2 class="text-lg">Dernières commandes</h2>
            <a href="/admin/commandes" class="btn-link">Toutes &rarr;</a>
        </div>
        <div class="card mt-4 overflow-x-auto">
            <table class="table">
                <thead><tr><th>N°</th><th>Client</th><th>Total</th><th>Statut</th></tr></thead>
                <tbody>
                    <?php foreach ($latestOrders as $order): ?>
                        <tr>
                            <td><a href="/admin/commandes/{{ $order['id'] }}" class="btn-link">#{{ $order['id'] }}</a></td>
                            <td>{{ $order['email'] }}</td>
                            <td>{{ money((int) $order['total_cents']) }}</td>
                            <td>{!! partial('order-status', ['status' => $order['status']]) !!}</td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($latestOrders === []): ?>
                        <tr><td colspan="4" class="text-center text-slate-500">Aucune commande.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card h-fit p-5">
        <h2 class="text-lg">Stock faible</h2>
        <ul class="mt-3 divide-y divide-slate-100 text-sm">
            <?php foreach ($lowStock as $product): ?>
                <li class="flex justify-between py-2">
                    <a href="/admin/produits/{{ $product['id'] }}/modifier" class="hover:text-brand-700">{{ $product['name'] }}</a>
                    <span class="badge {{ $product['stock'] == 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800' }}">{{ $product['stock'] }}</span>
                </li>
            <?php endforeach ?>
            <?php if ($lowStock === []): ?>
                <li class="py-2 text-slate-500">Tout est en stock.</li>
            <?php endif ?>
        </ul>
    </section>
</div>
