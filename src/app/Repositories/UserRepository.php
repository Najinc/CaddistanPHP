<?php

namespace App\Repositories;

final class UserRepository extends Repository
{
    public function find($id): ?array
    {
        return $this->fetchOne('SELECT id, first_name, last_name, role, email FROM users WHERE id = ?', [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne('SELECT id, first_name, last_name, role, email, password FROM users WHERE email = ?', [$email]);
    }

    public function all(): array
    {
        return $this->fetchAll(
            'SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.created_at,
                    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count
             FROM users u ORDER BY u.created_at DESC'
        );
    }

    public function create(string $email, string $password, string $firstName, string $lastName): int
    {
        $this->execute(
            'INSERT INTO users (email, password, first_name, last_name)
             VALUES (?, ?, ?, ?)',
            [$email, $password, $firstName, $lastName]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateProfile($id, string $firstName, string $lastName): void
    {
        $this->execute(
            'UPDATE users SET first_name = ?, last_name = ? WHERE id = ?',
            [$firstName, $lastName, $id]
        );
    }

    public function updatePassword($id, string $password): void
    {
        $this->execute(
            'UPDATE users SET password = ? WHERE id = ?',
            [$password, $id]
        );
    }

    public function updateRole($id, string $role): void
    {
        $this->execute(
            'UPDATE users SET role = ? WHERE id = ?',
            [$role, $id]
        );
    }

    public function count(): int
    {
        return (int) $this->executeQuery('SELECT COUNT(*) FROM users', [])->fetchColumn();
    }
}
