<?php
/** @var array $flashes */
$styles = [
    'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    'error'   => 'bg-red-50 text-red-800 ring-red-200',
    'info'    => 'bg-sky-50 text-sky-800 ring-sky-200',
];
?>
<?php if ($flashes): ?>
    <div class="mb-6 space-y-2">
        <?php foreach ($flashes as $flash): ?>
            <div class="flex items-start justify-between gap-4 rounded-lg px-4 py-3 text-sm ring-1 <?= $styles[$flash['type']] ?? $styles['info'] ?>" role="alert" data-dismissible>
                <span><?= $flash['message'] ?></span>
                <button type="button" class="cursor-pointer opacity-60 hover:opacity-100" aria-label="Fermer" data-dismiss>&times;</button>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
