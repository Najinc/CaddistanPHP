<?php

/** Quelques fonctions utilitaires */

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;

if (!function_exists('dd')) {
    function dd(mixed $value) {
        var_dump($value);
        die();
    }
}

/** Lit une variable d'environnement (définie par Docker Compose). */
function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);

    return $value === false ? $default : $value;
}

/**
 * Bloque l'application au démarrage si une variable obligatoire (secret, config) est absente ou vide.
 * Le détail part dans les logs serveur, le visiteur ne voit qu'un message générique.
 */
function require_env(array $keys): void
{
    $missing = array_filter($keys, fn (string $key) => in_array(env($key), [null, ''], true));

    if ($missing !== []) {
        error_log('Configuration incomplète, variable(s) manquante(s) : ' . implode(', ', $missing));
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Application mal configurée.');
    }
}

/**
 * Échappe une valeur pour l'afficher dans du HTML (contenu ou attribut entre guillemets).
 * ENT_QUOTES : échappe " ET ' (sinon on peut sortir d'un attribut value='...').
 * ENT_SUBSTITUTE : une séquence UTF-8 invalide est remplacée au lieu de vider toute la chaîne.
 * 'UTF-8' : charset explicite, identique à celui annoncé dans le Content-Type.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formate un montant en centimes : 18900 => "189,00 €". */
function money(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ') . ' €';
}

/** Rendu d'un fragment de vue (views/partials/...). */
function partial(string $name, array $data = []): string
{
    return View::renderFile('partials/' . $name, $data);
}

function old(string $key, string $default = ''): string
{
    return Session::old($key, $default);
}

function auth(): ?array
{
    return Auth::user();
}

/** À mettre dans chaque formulaire POST : {!! csrf_field() !!} */
function csrf_field(): string
{
    return Auth::csrf()->field();
}

/** Génère un slug URL : "Caddy Maxi 240 L" => "caddy-maxi-240-l". */
function slugify(string $text): string
{
    $text = strtr(mb_strtolower($text), [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y', 'æ' => 'ae',
    ]);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    return trim((string) $text, '-');
}

/** Ajoute la classe "actif" au lien de navigation courant. */
function is_current(string $prefix): bool
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    return $prefix === '/' ? $path === '/' : str_starts_with($path, $prefix);
}

function order_status_label(string $status): string
{
    return match ($status) {
        'pending'   => 'En attente',
        'paid'      => 'Payée',
        'shipped'   => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
        default     => $status,
    };
}
