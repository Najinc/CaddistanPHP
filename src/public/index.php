<?php

/**
 * Point d'entrée de l'application Web
 * Pour le reste, débrouillez-vous !
 */

use App\Vite;
require __DIR__.'/../vendor/autoload.php';

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Docker Secuweb</title>
    <!-- Intégration des balises CSS/JS via la classe utilitaire PHP -->
    <?= Vite::vite('resources/main.ts'); ?>
</head>
<body>
     <h1>Hello, ESGI !</h1>

    <p>Si le projet est fonctionnel et bien compilé, un carré rouge devrait apparaitre :</p>
    <div class="h-8 w-8 bg-red-400"></div>
</body>
</html>