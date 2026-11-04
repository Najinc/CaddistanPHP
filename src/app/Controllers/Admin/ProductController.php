<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Services\ProductImageService;
use RuntimeException;

final class ProductController extends Controller
{
    public function index(): Response
    {
        $this->authorize('caddy.edit');

        return $this->view('admin/products/index', [
            'title'    => 'Produits',
            'products' => (new ProductRepository())->allForAdmin(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('caddy.create');

        return $this->view('admin/products/form', [
            'title'      => 'Nouveau produit',
            'product'    => null,
            'categories' => (new CategoryRepository())->all(),
        ]);
    }

    public function store(): Response
    {
        $this->authorize('caddy.create');
        $products = new ProductRepository();
        $data = $this->formData($products);

        try {
            $data['image'] = $this->handleImage() ?? '/images/products/default.svg';
        } catch (RuntimeException $e) {
            return $this->back('/admin/produits/nouveau', $e->getMessage());
        }

        $products->create($data);
        Session::flash('success', 'Produit « ' . $data['name'] . ' » créé.');

        return $this->redirect('/admin/produits');
    }

    public function edit(string $id): Response
    {
        $this->authorize('caddy.edit');

        return $this->view('admin/products/form', [
            'title'      => 'Modifier un produit',
            'product'    => $this->findOrFail($id),
            'categories' => (new CategoryRepository())->all(),
        ]);
    }

    public function update(string $id): Response
    {
        $this->authorize('caddy.edit');
        $products = new ProductRepository();
        $product = $this->findOrFail($id);
        $data = $this->formData($products, $product['id']);

        try {
            $data['image'] = $this->handleImage() ?? $product['image'];
        } catch (RuntimeException $e) {
            return $this->back('/admin/produits/' . $product['id'] . '/modifier', $e->getMessage());
        }

        $products->update($product['id'], $data);
        Session::flash('success', 'Produit « ' . $data['name'] . ' » mis à jour.');

        return $this->redirect('/admin/produits');
    }

    public function destroy(string $id): Response
    {
        $this->authorize('caddy.delete');
        $product = $this->findOrFail($id);

        (new ProductRepository())->delete($product['id']);
        Session::flash('success', 'Produit supprimé.');

        return $this->redirect('/admin/produits');
    }

    private function findOrFail(string $id): array
    {
        return (new ProductRepository())->find($this->routeId($id)) ?? throw HttpException::notFound('Produit introuvable.');
    }

    /** Récupère et valide les champs du formulaire produit. */
    private function formData(ProductRepository $products, ?int $exceptId = null): array
    {
        $categoryIds = array_map('intval', array_column((new CategoryRepository())->all(), 'id'));

        $v = $this->validator();
        $categoryId = $v->int('category_id', 'La catégorie', 1, PHP_INT_MAX);
        $name       = $v->string('name', 'Le nom', 2, 150);
        $slugInput  = $v->string('slug', 'Le slug', 1, 170, required: false);
        $short      = $v->string('short_description', 'La description courte', 0, 255, required: false) ?? '';
        $long       = $v->string('description', 'La description', 0, 5000, required: false) ?? '';
        $price      = $v->string('price', 'Le prix', 1, 10, '/^[0-9]{1,7}([.,][0-9]{1,2})?$/');
        $stock      = $v->int('stock', 'Le stock', 0, 100000);
        $isActive   = $v->bool('is_active');

        // Liste blanche : la catégorie doit exister
        if ($categoryId !== null && !in_array($categoryId, $categoryIds, true)) {
            $v->addError('category_id', "Cette catégorie n'existe pas.");
        }

        $slug = slugify($slugInput ?? $name ?? '');
        if ($slug === '') {
            $v->addError('slug', 'Le slug est invalide.');
        } elseif ($products->slugExists($slug, $exceptId)) {
            $v->addError('slug', 'Ce slug est déjà utilisé.');
        }

        $v->validate();

        return [
            'category_id'       => $categoryId,
            'name'              => $name,
            'slug'              => $slug,
            'short_description' => $short,
            'description'       => $long,
            'price_cents'       => (int) round((float) str_replace(',', '.', $price) * 100),
            'stock'             => $stock,
            'is_active'         => $isActive ? 1 : 0,
        ];
    }

    /** Image envoyée en fichier ou via une URL distante. Retourne null si aucune. */
    private function handleImage(): ?string
    {
        $images = new ProductImageService();

        if ($file = $this->request->file('image')) {
            return $images->storeUpload($file);
        }

        if ($url = $this->request->string('image_url')) {
            return $images->storeFromUrl($url);
        }

        return null;
    }
}
