<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Response;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $orders = new OrderRepository();
        $products = new ProductRepository();

        return $this->view('admin/dashboard', [
            'title'         => 'Administration',
            'stats'         => $orders->stats(),
            'productsCount' => $products->count(),
            'usersCount'    => (new UserRepository())->count(),
            'latestOrders'  => $orders->latest(5),
            'lowStock'      => $products->lowStock(),
        ]);
    }
}
