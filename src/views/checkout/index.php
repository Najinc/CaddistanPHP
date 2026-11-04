<?php /** @var array $lines @var int $total @var array $user */ ?>
<h1>Finaliser ma commande</h1>

<div class="mt-6 grid gap-8 lg:grid-cols-[1fr_360px]">
    <form action="/commande" method="post" class="card space-y-4 p-6">
        {!! csrf_field() !!}
        <h2 class="text-lg">Adresse de livraison</h2>
        <div>
            <label for="name" class="label">Nom complet</label>
            <input id="name" name="name" class="input" required
                   value="{{ old('name', $user['first_name'] . ' ' . $user['last_name']) }}">
        </div>
        <div>
            <label for="address" class="label">Adresse</label>
            <input id="address" name="address" class="input" required value="{{ old('address') }}">
        </div>
        <div class="grid gap-4 sm:grid-cols-[140px_1fr]">
            <div>
                <label for="zip" class="label">Code postal</label>
                <input id="zip" name="zip" class="input" required value="{{ old('zip') }}">
            </div>
            <div>
                <label for="city" class="label">Ville</label>
                <input id="city" name="city" class="input" required value="{{ old('city') }}">
            </div>
        </div>
        <div>
            <label for="phone" class="label">Téléphone</label>
            <input id="phone" name="phone" type="tel" class="input" required value="{{ old('phone') }}">
        </div>

        <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200">
            <strong>Paiement simulé.</strong> Aucun moyen de paiement n'est demandé : la commande est directement marquée comme payée.
        </div>

        <button type="submit" class="btn-primary w-full py-3">Confirmer et payer {{ money($total) }}</button>
    </form>

    <aside class="card h-fit p-5">
        <h2 class="text-lg">Votre panier</h2>
        <ul class="mt-4 divide-y divide-slate-100 text-sm">
            <?php foreach ($lines as $line): ?>
                <li class="flex justify-between gap-4 py-2">
                    <span>{{ $line['product']['name'] }} <span class="text-slate-400">× {{ (int) $line['quantity'] }}</span></span>
                    <span class="font-medium">{{ money($line['subtotal']) }}</span>
                </li>
            <?php endforeach ?>
        </ul>
        <div class="mt-4 flex justify-between border-t border-slate-200 pt-4 text-lg font-bold text-ink">
            <span>Total</span><span>{{ money($total) }}</span>
        </div>
    </aside>
</div>
