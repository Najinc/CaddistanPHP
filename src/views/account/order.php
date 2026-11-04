<?php /** @var array $order @var array $items @var string $trackingUrl */ ?>
<a href="/compte" class="btn-link">&larr; Mon compte</a>
<div class="mt-4 flex flex-wrap items-center gap-4">
    <h1>Commande n°{{ (int) $order['id'] }}</h1>
    {!! partial('order-status', ['status' => $order['status']]) !!}
</div>
<p class="mt-1 text-sm text-slate-500">Passée le {{ date('d/m/Y à H:i', strtotime($order['created_at'])) }}</p>

<div class="mt-6 max-w-3xl">
    {!! partial('order-details', ['order' => $order, 'items' => $items]) !!}

    <div class="card mt-4 p-5 text-sm">
        <h3 class="text-base">Lien de suivi à partager</h3>
        <p class="mt-1 text-slate-500">Permet de suivre la commande sans compte pendant 7 jours. Il ne fonctionne plus si on le modifie.</p>
        <input class="input mt-3" readonly value="{{ $trackingUrl }}" aria-label="Lien de suivi de la commande">
    </div>
</div>
