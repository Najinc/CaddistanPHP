<?php /** @var array $orders @var ?string $status @var array $statuses */ ?>
<?= partial('admin-nav') ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1>Commandes</h1>
    <form method="get" class="flex items-center gap-2">
        <label for="statut" class="text-sm text-slate-500">Statut</label>
        <select id="statut" name="statut" class="input w-auto" data-autosubmit>
            <option value="">Tous</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= order_status_label($s) ?></option>
            <?php endforeach ?>
        </select>
    </form>
</div>

<div class="card mt-6 overflow-x-auto">
    <table class="table">
        <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Total</th><th>Statut</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td class="font-semibold">#<?= $order['id'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                    <td><?= $order['email'] ?></td>
                    <td><?= money((int) $order['total_cents']) ?></td>
                    <td><?= partial('order-status', ['status' => $order['status']]) ?></td>
                    <td class="text-right"><a href="/admin/commandes/<?= $order['id'] ?>" class="btn-link">Gérer</a></td>
                </tr>
            <?php endforeach ?>
            <?php if ($orders === []): ?>
                <tr><td colspan="6" class="text-center text-slate-500">Aucune commande.</td></tr>
            <?php endif ?>
        </tbody>
    </table>
</div>
