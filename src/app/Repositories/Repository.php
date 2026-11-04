<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOStatement;

abstract class Repository
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $requestSql = $this->executeQuery($sql, $params);
        return $requestSql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $requestSql = $this->executeQuery($sql, $params);
        return $requestSql->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function execute(string $sql, array $params = []): int
    {
        $requestSql = $this->executeQuery($sql, $params);
        return $requestSql->rowCount();
    }

    protected function lastInsertId(): string
    {
        return $this->db->lastInsertId();
    }

    protected function executeQuery(string $sql, array $params): PDOStatement
    {
        $requestSql = $this->db->prepare($sql);
        $requestSql->execute($params);
        return $requestSql;
    }
}