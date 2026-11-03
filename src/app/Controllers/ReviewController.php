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
        $slug = $this->routeSlug($slug);
        $product = (new ProductRepository())->findActiveBySlug($slug) ?? throw HttpException::notFound();

        $v = $this->validator();
        $rating  = $v->int('rating', 'La note', 1, 5);
        $comment = $v->string('comment', 'Le commentaire', 3, 1000);
        $v->validate();

        (new ReviewRepository())->create($product['id'], Auth::id(), $rating, $comment);
        Session::flash('success', 'Merci pour votre avis !');

        return $this->redirect('/produits/' . $slug . '#avis');
    }
}
