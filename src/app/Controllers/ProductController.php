<?php

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Response;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ReviewRepository;

final class ProductController extends Controller
{
    private const PER_PAGE = 9;

    public function index(): Response
    {
        $categories = new CategoryRepository();
        $q = trim((string) $this->request->query('q', ''));
        $sort = (string) $this->request->query('tri', 'recent');
        $page = $this->request->query('page', 1);

        $category = null;
        if ($slug = $this->request->query('categorie')) {
            $category = $categories->findBySlug($slug);
        }

        $result = (new ProductRepository())->search($q, $category['id'] ?? null, $sort, $page, self::PER_PAGE);

        return $this->view('products/index', [
            'title'      => $category['name'] ?? 'Catalogue',
            'products'   => $result['items'],
            'total'      => $result['total'],
            'pages'      => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'page'       => (int) $page,
            'q'          => $q,
            'sort'       => $sort,
            'category'   => $category,
            'categories' => $categories->all(),
        ]);
    }

    public function show(string $slug): Response
    {
        $products = new ProductRepository();
        $product = $products->findActiveBySlug($slug) ?? throw HttpException::notFound('Ce caddy n\'existe pas (ou plus).');
        $reviews = new ReviewRepository();

        return $this->view('products/show', [
            'title'   => $product['name'],
            'product' => $product,
            'reviews' => $reviews->forProduct($product['id']),
            'rating'  => $reviews->summary($product['id']),
            'related' => $products->related($product['category_id'], $product['id']),
        ]);
    }
}
