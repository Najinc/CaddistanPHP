<?php /** @var array $order @var array $items */ ?>
<div class="card overflow-x-auto">
    <table class="table">
        <thead>
            <tr><th>Produit</th><th>Prix</th><th>Qté</th><th class="text-right">Total</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?php if ($item['slug']): ?>
                            <a href="/produits/{{ $item['slug'] }}" class="font-medium hover:text-brand-700">{{ $item['product_name'] }}</a>
                        <?php else: ?>
                            {{ $item['product_name'] }}
                        <?php endif ?>
                    </td>
                    <td>{{ money((int) $item['unit_price_cents']) }}</td>
                    <td>{{ (int) $item['quantity'] }}</td>
                    <td class="text-right">{{ money($item['unit_price_cents'] * $item['quantity']) }}</td>
                </tr>
            <?php endforeach ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="font-bold">Total</td>
                <td class="text-right font-bold">{{ money((int) $order['total_cents']) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="card mt-4 p-5 text-sm">
    <h3 class="text-base">Livraison</h3>
    <p class="mt-2 text-slate-600">
        {{ $order['shipping_name'] }}<br>
        {{ $order['shipping_address'] }}<br>
        {{ $order['shipping_zip'] }} {{ $order['shipping_city'] }}<br>
        {{ $order['shipping_phone'] }}
    </p>
</div>
