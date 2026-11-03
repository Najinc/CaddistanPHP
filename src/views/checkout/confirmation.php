<?php /** @var array $order @var array $items */ ?>
<div class="mx-auto max-w-2xl text-center">
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl text-emerald-600">✓</div>
    <h1 class="mt-4">Merci pour votre commande !</h1>
    <p class="mt-2 text-slate-500">Commande n°<?= (int) $order['id'] ?> — un e-mail de confirmation vous sera envoyé (un jour).</p>
</div>

<div class="mx-auto mt-8 max-w-2xl">
    <?= partial('order-details', ['order' => $order, 'items' => $items]) ?>
    <div class="mt-6 flex justify-center gap-3">
        <a href="/compte" class="btn-secondary">Voir mes commandes</a>
        <a href="/produits" class="btn-primary">Continuer mes achats</a>
    </div>
</div>
