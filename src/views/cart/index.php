<?php /** @var array $lines @var int $total */ ?>
<h1>Mon panier</h1>

<?php if ($lines === []): ?>
    <div class="card mt-6 p-10 text-center">
        <p class="text-slate-500">Votre panier est vide… un caddy sans rien dedans, c'est triste.</p>
        <a href="/produits" class="btn-primary mt-4">Découvrir le catalogue</a>
    </div>
<?php else: ?>
    <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_320px]">
        <form action="/panier/modifier" method="post" id="cart-form" class="card overflow-x-auto">
            {!! csrf_field() !!}
            <table class="table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th class="w-28">Quantité</th>
                        <th class="text-right">Sous-total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): $product = $line['product']; ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-4">
                                    <img src="{{ $product['image'] ?: '/images/products/default.svg' }}" alt="" class="h-14 w-14 rounded-lg bg-slate-100 object-contain p-1">
                                    <div>
                                        <a href="/produits/{{ $product['slug'] }}" class="font-semibold text-ink hover:text-brand-700">{{ $product['name'] }}</a>
                                        <p class="text-slate-500">{{ money((int) $product['price_cents']) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <input type="number" name="quantities[{{ (int) $product['id'] }}]" value="{{ (int) $line['quantity'] }}"
                                       min="0" max="99" class="input w-20" aria-label="Quantité">
                            </td>
                            <td class="text-right font-semibold">{{ money($line['subtotal']) }}</td>
                            <td class="text-right">
                                <button type="submit" form="remove-{{ (int) $product['id'] }}" class="btn-link text-red-600">Retirer</button>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
            <div class="p-4 text-right">
                <button type="submit" class="btn-secondary">Mettre à jour le panier</button>
            </div>
        </form>

        <?php foreach ($lines as $line): ?>
            <form action="/panier/retirer" method="post" id="remove-{{ (int) $line['product']['id'] }}" class="hidden">
                {!! csrf_field() !!}
                <input type="hidden" name="product_id" value="{{ (int) $line['product']['id'] }}">
            </form>
        <?php endforeach ?>

        <aside class="card h-fit p-5">
            <h2 class="text-lg">Récapitulatif</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt>Sous-total</dt><dd>{{ money($total) }}</dd></div>
                <div class="flex justify-between"><dt>Livraison</dt><dd>{{ $total >= 15000 ? 'Offerte' : money(990) }}</dd></div>
            </dl>
            <div class="mt-4 flex justify-between border-t border-slate-200 pt-4 text-lg font-bold text-ink">
                <span>Total</span><span>{{ money($total) }}</span>
            </div>
            <p class="mt-1 text-xs text-slate-400">Frais de livraison offerts pendant la phase de lancement.</p>
            <a href="/commande" class="btn-primary mt-6 w-full py-3">Passer commande</a>
        </aside>
    </div>
<?php endif ?>
