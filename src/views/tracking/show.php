<?php /** @var array $order @var array $items */ ?>
<div class="mx-auto max-w-2xl">
    <div class="flex flex-wrap items-center gap-4">
        <h1>Commande n°{{ (int) $order['id'] }}</h1>
        {!! partial('order-status', ['status' => $order['status']]) !!}
    </div>
    <p class="mt-1 text-sm text-slate-500">Passée le {{ date('d/m/Y à H:i', strtotime($order['created_at'])) }}</p>

    <div class="card mt-6 overflow-x-auto">
        <table class="table">
            <thead><tr><th>Produit</th><th class="text-right">Quantité</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>{{ $item['product_name'] }}</td>
                        <td class="text-right">{{ (int) $item['quantity'] }}</td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-xs text-slate-400">
        Lien de suivi sécurisé : il expire automatiquement et l'adresse de livraison n'est pas affichée.
    </p>
</div>
