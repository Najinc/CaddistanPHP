<?php /** @var array $order @var array $items @var array $statuses */ ?>
{!! partial('admin-nav') !!}
<a href="/admin/commandes" class="btn-link">&larr; Commandes</a>
<div class="mt-2 flex flex-wrap items-center gap-4">
    <h1>Commande n°{{ $order['id'] }}</h1>
    {!! partial('order-status', ['status' => $order['status']]) !!}
</div>
<p class="mt-1 text-sm text-slate-500">
    Passée le {{ date('d/m/Y à H:i', strtotime($order['created_at'])) }} par
    {{ $order['first_name'] . ' ' . $order['last_name'] }} ({{ $order['email'] }})
</p>

<div class="mt-6 grid gap-8 lg:grid-cols-[1fr_300px]">
    <div>{!! partial('order-details', ['order' => $order, 'items' => $items]) !!}</div>

    <form action="/admin/commandes/{{ $order['id'] }}/statut" method="post" class="card h-fit space-y-4 p-5">
        <h2 class="text-lg">Changer le statut</h2>
        <select name="status" class="input">
            <?php foreach ($statuses as $s): ?>
                <option value="{{ $s }}" {{ $order['status'] === $s ? 'selected' : '' }}>{{ order_status_label($s) }}</option>
            <?php endforeach ?>
        </select>
        <button type="submit" class="btn-primary w-full">Mettre à jour</button>
    </form>
</div>
