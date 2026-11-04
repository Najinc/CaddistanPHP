<?php

namespace App\Repositories;

final class CategoryRepository extends Repository
{
    public function all(): array
    {
        return $this->fetchAll(
            'SELECT c.id, c.name, c.slug, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS products_count
             FROM categories c ORDER BY c.id'
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->fetchOne(
            'SELECT c.id, c.slug, c.name FROM categories c WHERE slug = ?',
            [$slug]
        );
    }
}
