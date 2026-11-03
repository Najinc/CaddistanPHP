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

        $v = $this->queryValidator();
        $q    = $v->string('q', 'La recherche', 1, 100, required: false) ?? '';
        $sort = $v->in('tri', 'Le tri', array_keys(ProductRepository::SORTS), required: false) ?? 'recent';
        $page = $v->int('page', 'Le numéro de page', 1, 1000, required: false) ?? 1;
        $slug = $v->string('categorie', 'La catégorie', 1, 120, '/^[a-z0-9-]+$/', required: false);
        $v->validate();

        $category = $slug !== null ? $categories->findBySlug($slug) : null;

        $result = (new ProductRepository())->search($q, $category['id'] ?? null, $sort, $page, self::PER_PAGE);

        return $this->view('products/index', [
            'title'      => $category['name'] ?? 'Catalogue',
            'products'   => $result['items'],
            'total'      => $result['total'],
            'pages'      => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'page'       => $page,
            'q'          => $q,
            'sort'       => $sort,
            'category'   => $category,
            'categories' => $categories->all(),
        ]);
    }

    public function show(string $slug): Response
    {
        $slug = $this->routeSlug($slug);
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
