<?php
/** @var string $content */
use App\Core\Session;
use App\Vite;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ($title ?? '') !== '' ? $title . ' · Caddistan' : 'Caddistan' ?></title>
    <link rel="icon" href="/images/favicon.svg" type="image/svg+xml">
    <?= Vite::vite('resources/main.ts') ?>
</head>
<body>
    <?= partial('header') ?>

    <main class="flex-1 py-8">
        <div class="container-page">
            <?= partial('flash', ['flashes' => Session::pullFlashes()]) ?>
            <?= $content ?>
        </div>
    </main>

    <?= partial('footer') ?>
</body>
</html>
