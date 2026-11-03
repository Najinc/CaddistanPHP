<?php

/** Quelques fonctions utilitaires */

if (!function_exists('dd')) {
    function dd(mixed $value) {
        var_dump($value);
        die();
    }
}