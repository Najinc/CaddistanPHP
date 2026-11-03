<?php
/** @var string $status */
$colors = [
    'pending'   => 'bg-amber-100 text-amber-800',
    'paid'      => 'bg-sky-100 text-sky-800',
    'shipped'   => 'bg-indigo-100 text-indigo-800',
    'delivered' => 'bg-emerald-100 text-emerald-800',
    'cancelled' => 'bg-slate-200 text-slate-600',
];
?>
<span class="badge <?= $colors[$status] ?? 'bg-slate-100' ?>"><?= order_status_label($status) ?></span>
