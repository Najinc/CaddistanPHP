<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

abstract class Repository
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function fetchOne(string $sql): ?array
    {
        return $this->db->query($sql)->fetch() ?: null;
    }

    protected function fetchAll(string $sql): array
    {
        return $this->db->query($sql)->fetchAll();
    }

    protected function execute(string $sql): int
    {
        return (int) $this->db->exec($sql);
    }
}
