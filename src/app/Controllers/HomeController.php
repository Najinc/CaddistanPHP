<?php

namespace App\Controllers;

use App\Core\Response;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;

final class HomeController extends Controller
{
    public function index(): Response
    {
        return $this->view('home/index', [
            'title'      => 'Accueil',
            'categories' => (new CategoryRepository())->all(),
            'products'   => (new ProductRepository())->latest(8),
        ]);
    }
}
