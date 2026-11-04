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

    private const SELECT = 'SELECT p.slug, p.name, p.price_cents, p.short_description, p.image, p.id, p.stock, p.description, p.datasheet, p.category_id, c.name AS category_name, c.slug AS category_slug
                            FROM products p JOIN categories c ON c.id = p.category_id';

    /**
     * Recherche paginée dans le catalogue public.
     *
     * @return array{items: array, total: int}
     */
    public function search(?string $query, $categoryId, string $sort, $page, $perPage): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        if ($query !== null && $query !== '') {
            $where[] = '(p.name LIKE ? OR p.short_description LIKE ?)';
            $searchTerm = '%' . $query . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($categoryId !== null) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        $whereSql = implode(' AND ', $where);
        $order = self::SORTS[$sort] ?? self::SORTS['recent'];
        
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $offset = ($page - 1) * $perPage;

        $itemsSql = self::SELECT . " WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset";
        
        $countSql = "SELECT COUNT(*) FROM products p WHERE $whereSql";
        
        $totalRow = $this->fetchOne($countSql, $params);

        return [
            'items' => $this->fetchAll($itemsSql, $params),
            'total' => (int) ($totalRow['COUNT(*)'] ?? 0),
        ];
    }

    public function latest(int $limit): array
    {
        $limit = (int) $limit;
        return $this->fetchAll(self::SELECT . " WHERE p.is_active = 1 ORDER BY p.created_at DESC, p.id DESC LIMIT $limit");
    }

    public function find($id): ?array
    {
        return $this->fetchOne('SELECT p.slug, p.name, p.price_cents, p.short_description, p.image, p.id, p.stock, p.description, p.datasheet, p.category_id, p.is_active, p.created_at, p.updated_at FROM products WHERE id = ?', [$id]);
    }

    public function findActiveBySlug(string $slug): ?array
    {
        return $this->fetchOne(self::SELECT . ' WHERE p.slug = ? AND p.is_active = 1', [$slug]);
    }

    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $rows = $this->fetchAll(
            "SELECT p.slug, p.name, p.price_cents, p.short_description, p.image, p.id, p.stock, p.description, p.datasheet, p.category_id, p.is_active, p.created_at, p.updated_at FROM products WHERE is_active = 1 AND id IN ($placeholders)",
            $ids
        );

        return array_column($rows, null, 'id');
    }

    public function related($categoryId, $excludeId, int $limit = 4): array
    {
        $limit = (int) $limit;

        return $this->fetchAll(
            self::SELECT . ' WHERE p.category_id = ? AND p.id <> ? AND p.is_active = 1 ORDER BY RAND() LIMIT ' . $limit,
            [$categoryId, $excludeId]
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

        $row = $this->fetchOne(
            'SELECT id FROM products WHERE slug = ? AND id <> ?',
            [$slug, $exceptId]
        );

        return $row !== null;
    }

    public function create(array $d): int
    {
        $this->execute(
            'INSERT INTO products (category_id, name, slug, short_description, description, price_cents, stock, image, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['category_id'],
                $d['name'],
                $d['slug'],
                $d['short_description'],
                $d['description'],
                $d['price_cents'],
                $d['stock'],
                $d['image'],
                $d['is_active'],
            ]
        );

        return (int) $this->lastInsertId();
    }

    public function update($id, array $d): void
    {
        $this->execute(
            'UPDATE products SET category_id = ?, name = ?, slug = ?,
                short_description = ?, description = ?,
                price_cents = ?, stock = ?, image = ?, is_active = ?
             WHERE id = ?',
            [
                $d['category_id'],
                $d['name'],
                $d['slug'],
                $d['short_description'],
                $d['description'],
                $d['price_cents'],
                $d['stock'],
                $d['image'],
                $d['is_active'],
                $id,
            ]
        );
    }

    public function delete($id): void
    {
        $this->execute('DELETE FROM products WHERE id = ?', [$id]);
    }

    /** Décrémente le stock si suffisant. Retourne false sinon. */
    public function decrementStock($id, $quantity): bool
    {
        return $this->execute(
            'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
            [$quantity, $id, $quantity]
        ) === 1;
    }

    public function count(): int
    {
        return (int) $this->executeQuery('SELECT COUNT(*) FROM products', [])->fetchColumn();
    }

    public function lowStock(int $threshold = 10): array
    {
        return $this->fetchAll(
            'SELECT id, name, stock FROM products WHERE stock < ? AND is_active = 1 ORDER BY stock ASC',
            [$threshold]
        );
    }
}
