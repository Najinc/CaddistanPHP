<?php /** @var array $user @var array $orders */ ?>
<h1>Mon compte</h1>

<div class="mt-6 grid gap-8 lg:grid-cols-[1fr_360px]">
    <section>
        <h2 class="text-lg">Mes commandes</h2>
        <?php if ($orders === []): ?>
            <div class="card mt-4 p-8 text-center text-slate-500">
                Aucune commande pour l'instant. <a href="/produits" class="btn-link">Allons faire les courses !</a>
            </div>
        <?php else: ?>
            <div class="card mt-4 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>N°</th><th>Date</th><th>Articles</th><th>Total</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="font-semibold">#{{ (int) $order['id'] }}</td>
                                <td>{{ date('d/m/Y', strtotime($order['created_at'])) }}</td>
                                <td>{{ (int) $order['items_count'] }}</td>
                                <td>{{ money((int) $order['total_cents']) }}</td>
                                <td>{!! partial('order-status', ['status' => $order['status']]) !!}</td>
                                <td class="text-right"><a href="/compte/commandes/{{ (int) $order['id'] }}" class="btn-link">Détails</a></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <section class="card h-fit p-6">
        <h2 class="text-lg">Mes informations</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $user['email'] }}</p>
        <form action="/compte" method="post" class="mt-4 space-y-4">
            <div>
                <label for="first_name" class="label">Prénom</label>
                <input id="first_name" name="first_name" class="input" required value="{{ old('first_name', $user['first_name']) }}">
            </div>
            <div>
                <label for="last_name" class="label">Nom</label>
                <input id="last_name" name="last_name" class="input" required value="{{ old('last_name', $user['last_name']) }}">
            </div>
            <fieldset class="space-y-4 border-t border-slate-200 pt-4">
                <legend class="text-sm font-semibold text-slate-700">Changer de mot de passe (optionnel)</legend>
                <div>
                    <label for="current_password" class="label">Mot de passe actuel</label>
                    <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password">
                </div>
                <div>
                    <label for="password" class="label">Nouveau mot de passe</label>
                    <input id="password" name="password" type="password" class="input" minlength="8" autocomplete="new-password">
                </div>
            </fieldset>
            <button type="submit" class="btn-primary w-full">Enregistrer</button>
        </form>
    </section>
</div>
