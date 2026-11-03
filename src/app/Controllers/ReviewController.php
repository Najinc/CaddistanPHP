<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ProductRepository;
use App\Repositories\ReviewRepository;

final class ReviewController extends Controller
{
    public function store(string $slug): Response
    {
        $this->requireLogin();

        $product = (new ProductRepository())->findActiveBySlug($slug) ?? throw HttpException::notFound();
        $rating  = $this->request->input('rating');
        $comment = $this->request->string('comment');

        (new ReviewRepository())->create($product['id'], Auth::id(), $rating, $comment);
        Session::flash('success', 'Merci pour votre avis !');

        return $this->redirect('/produits/' . $slug . '#avis');
    }
}
