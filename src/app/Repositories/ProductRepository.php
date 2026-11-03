<?php

namespace App\Repositories;

final class ProductRepository extends Repository
{
    public const SORTS = [
        'recent'    => 'p.created_at DESC, p.id DESC',
        'prix-asc'  => 'p.price_cents ASC',
        'prix-desc' => 'p.price_cents DESC',
        'nom'       => 'p.name ASC',
    ];

    private const SELECT = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
                            FROM products p JOIN categories c ON c.id = p.category_id';

    /**
     * Recherche paginée dans le catalogue public.
     *
     * @return array{items: array, total: int}
     */
    public function search(?string $query, $categoryId, string $sort, $page, $perPage): array
    {
        $where = 'p.is_active = 1';

        if ($query !== null && $query !== '') {
            $where .= " AND (p.name LIKE '%$query%' OR p.short_description LIKE '%$query%')";
        }

        if ($categoryId !== null) {
            $where .= " AND p.category_id = $categoryId";
        }

        $order  = self::SORTS[$sort] ?? self::SORTS['recent'];
        $offset = ($page - 1) * $perPage;

        return [
            'items' => $this->fetchAll(self::SELECT . " WHERE $where ORDER BY $order LIMIT $perPage OFFSET $offset"),
            'total' => (int) $this->db->query("SELECT COUNT(*) FROM products p WHERE $where")->fetchColumn(),
        ];
    }

    public function latest(int $limit): array
    {
        return $this->fetchAll(self::SELECT . " WHERE p.is_active = 1 ORDER BY p.created_at DESC, p.id DESC LIMIT $limit");
    }

    public function find($id): ?array
    {
        return $this->fetchOne("SELECT * FROM products WHERE id = $id");
    }

    public function findActiveBySlug(string $slug): ?array
    {
        return $this->fetchOne(self::SELECT . " WHERE p.slug = '$slug' AND p.is_active = 1");
    }

    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->fetchAll('SELECT * FROM products WHERE is_active = 1 AND id IN (' . implode(',', $ids) . ')');

        return array_column($rows, null, 'id');
    }

    public function related($categoryId, $excludeId, int $limit = 4): array
    {
        return $this->fetchAll(
            self::SELECT . " WHERE p.category_id = $categoryId AND p.id <> $excludeId AND p.is_active = 1
                             ORDER BY RAND() LIMIT $limit"
        );
    }

    /** Liste complète pour l'administration (y compris produits désactivés). */
    public function allForAdmin(): array
    {
        return $this->fetchAll(
            'SELECT p.*, c.name AS category_name FROM products p
             JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC'
        );
    }

    public function slugExists(string $slug, $exceptId = null): bool
    {
        $exceptId ??= 0;

        return $this->fetchOne("SELECT id FROM products WHERE slug = '$slug' AND id <> $exceptId") !== null;
    }

    public function create(array $d): int
    {
        $this->execute(
            "INSERT INTO products (category_id, name, slug, short_description, description, price_cents, stock, image, is_active)
             VALUES ({$d['category_id']}, '{$d['name']}', '{$d['slug']}', '{$d['short_description']}', '{$d['description']}',
                     {$d['price_cents']}, {$d['stock']}, '{$d['image']}', {$d['is_active']})"
        );

        return (int) $this->db->lastInsertId();
    }

    public function update($id, array $d): void
    {
        $this->execute(
            "UPDATE products SET category_id = {$d['category_id']}, name = '{$d['name']}', slug = '{$d['slug']}',
                short_description = '{$d['short_description']}', description = '{$d['description']}',
                price_cents = {$d['price_cents']}, stock = {$d['stock']}, image = '{$d['image']}', is_active = {$d['is_active']}
             WHERE id = $id"
        );
    }

    public function delete($id): void
    {
        $this->execute("DELETE FROM products WHERE id = $id");
    }

    /** Décrémente le stock si suffisant. Retourne false sinon. */
    public function decrementStock($id, $quantity): bool
    {
        return $this->execute("UPDATE products SET stock = stock - $quantity WHERE id = $id AND stock >= $quantity") === 1;
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    }

    public function lowStock(int $threshold = 10): array
    {
        return $this->fetchAll("SELECT id, name, stock FROM products WHERE stock < $threshold AND is_active = 1 ORDER BY stock ASC");
    }
}
