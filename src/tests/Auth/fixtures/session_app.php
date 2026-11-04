<?php

declare(strict_types=1);

// Mini-application servie par `php -S` pendant NativeSessionHttpTest.
// Le paramètre "now" remplace l'horloge pour tester le timeout sans attendre 30 minutes.

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Auth\Session\NativeSession;

$now = isset($_GET['now']) ? (int) $_GET['now'] : time();
$session = new NativeSession(secure: true, idleTimeout: 1800, clock: fn (): int => $now);

switch ($_GET['action'] ?? 'show') {
    case 'login':
        $session->regenerate();
        $session->set('user', 42);
        break;
    case 'logout':
        $session->destroy();
        break;
    default:
        $session->start();
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'id' => session_status() === PHP_SESSION_ACTIVE ? session_id() : null,
    'user' => $_SESSION['user'] ?? null,
], JSON_THROW_ON_ERROR);
