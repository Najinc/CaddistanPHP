<?php /** @var array $order @var array $items */ ?>
<a href="/compte" class="btn-link">&larr; Mon compte</a>
<div class="mt-4 flex flex-wrap items-center gap-4">
    <h1>Commande n°<?= (int) $order['id'] ?></h1>
    <?= partial('order-status', ['status' => $order['status']]) ?>
</div>
<p class="mt-1 text-sm text-slate-500">Passée le <?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></p>

<div class="mt-6 max-w-3xl">
    <?= partial('order-details', ['order' => $order, 'items' => $items]) ?>
</div>
