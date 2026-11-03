<?php

namespace App\Repositories;

final class CategoryRepository extends Repository
{
    public function all(): array
    {
        return $this->fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS products_count
             FROM categories c ORDER BY c.id'
        );
    }

    public function find($id): ?array
    {
        return $this->fetchOne("SELECT * FROM categories WHERE id = $id");
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->fetchOne("SELECT * FROM categories WHERE slug = '$slug'");
    }
}
