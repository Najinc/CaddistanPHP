<?php

namespace App\Core;

use PDO;

/**
 * Connexion PDO partagée (MariaDB).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                env('DB_HOST', 'db'),
                (int) env('DB_PORT', 3306),
                env('DB_DATABASE', 'caddistan'),
            );

            self::$pdo = new PDO($dsn, env('DB_USERNAME'), env('DB_PASSWORD'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$pdo;
    }
}
