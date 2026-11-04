<?php

namespace App\Repositories;

final class ReviewRepository extends Repository
{
    public function forProduct($productId): array
    {
        return $this->fetchAll(
            'SELECT r.rating, r.comment, r.created_at, u.first_name, u.last_name FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ? ORDER BY r.created_at DESC',
            [$productId]
        );
    }

    /** @return array{average: float, count: int} */
    public function summary($productId): array
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(AVG(rating), 0) AS average, COUNT(*) AS count FROM reviews WHERE product_id = ?',
            [$productId]
        );

        return ['average' => round((float) $row['average'], 1), 'count' => (int) $row['count']];
    }

    public function create($productId, $userId, $rating, string $comment): void
    {
        $this->execute(
            'INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)',
            [$productId, $userId, $rating, $comment]
        );
    }
}
