<?php

declare(strict_types=1);

/*
 * Mini-application servie par `php -S` pendant NativeSessionHttpTest.
 * Fichier de test uniquement : il n'est pas dans public/, il n'est jamais exposé.
 *
 * Le paramètre "now" simule l'horloge pour tester le timeout d'inactivité sans attendre 30 minutes.
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Auth\Session\CookiePolicy;
use App\Auth\Session\NativeSession;

$now = is_string($_GET['now'] ?? null) && ctype_digit($_GET['now']) ? (int) $_GET['now'] : time();
$session = new NativeSession(new CookiePolicy(secure: true), idleTimeout: 1800, clock: static fn (): int => $now);

$destroyed = false;
switch ($_GET['action'] ?? 'show') {
    case 'login':
        $session->regenerate();
        $session->set('user', 42);
        break;
    case 'logout':
        $session->destroy();
        $destroyed = true;
        break;
    default:
        $session->start();
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'id' => $destroyed ? null : session_id(),
    'user' => $destroyed ? null : ($_SESSION['user'] ?? null),
    'expired' => $session->expiredDueToInactivity(),
], JSON_THROW_ON_ERROR);
