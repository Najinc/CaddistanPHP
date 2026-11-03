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
        return $this->view('admin/products/index', [
            'title'    => 'Produits',
            'products' => (new ProductRepository())->allForAdmin(),
        ]);
    }

    public function create(): Response
    {
        return $this->view('admin/products/form', [
            'title'      => 'Nouveau produit',
            'product'    => null,
            'categories' => (new CategoryRepository())->all(),
        ]);
    }

    public function store(): Response
    {
        $products = new ProductRepository();

        try {
            $data = $this->formData($products);
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
        return $this->view('admin/products/form', [
            'title'      => 'Modifier un produit',
            'product'    => $this->findOrFail($id),
            'categories' => (new CategoryRepository())->all(),
        ]);
    }

    public function update(string $id): Response
    {
        $products = new ProductRepository();
        $product = $this->findOrFail($id);

        try {
            $data = $this->formData($products, $product['id']);
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
        $product = $this->findOrFail($id);

        (new ProductRepository())->delete($product['id']);
        Session::flash('success', 'Produit supprimé.');

        return $this->redirect('/admin/produits');
    }

    private function findOrFail(string $id): array
    {
        return (new ProductRepository())->find($id) ?? throw HttpException::notFound('Produit introuvable.');
    }

    /** Récupère les champs du formulaire produit. */
    private function formData(ProductRepository $products, $exceptId = null): array
    {
        $name = $this->request->string('name');
        $slug = slugify($this->request->string('slug') ?: $name);

        if ($products->slugExists($slug, $exceptId)) {
            throw new RuntimeException('Ce slug est déjà utilisé.');
        }

        return [
            'category_id'       => $this->request->input('category_id'),
            'name'              => $name,
            'slug'              => $slug,
            'short_description' => $this->request->string('short_description'),
            'description'       => $this->request->string('description'),
            'price_cents'       => (int) round((float) str_replace(',', '.', $this->request->string('price')) * 100),
            'stock'             => $this->request->input('stock', 0),
            'is_active'         => $this->request->input('is_active') ? 1 : 0,
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
