<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\OrderRepository;

final class OrderController extends Controller
{
    public function index(): Response
    {
        $v = $this->queryValidator();
        $status = $v->in('statut', 'Le statut', OrderRepository::STATUSES, required: false);
        $v->validate();

        return $this->view('admin/orders/index', [
            'title'    => 'Commandes',
            'orders'   => (new OrderRepository())->all($status),
            'status'   => $status,
            'statuses' => OrderRepository::STATUSES,
        ]);
    }

    public function show(string $id): Response
    {
        $orders = new OrderRepository();
        $order = $orders->find($this->routeId($id)) ?? throw HttpException::notFound('Commande introuvable.');

        return $this->view('admin/orders/show', [
            'title'    => 'Commande n°' . $order['id'],
            'order'    => $order,
            'items'    => $orders->items($order['id']),
            'statuses' => OrderRepository::STATUSES,
        ]);
    }

    public function updateStatus(string $id): Response
    {
        $orders = new OrderRepository();
        $order = $orders->find($this->routeId($id)) ?? throw HttpException::notFound('Commande introuvable.');
        $v = $this->validator();
        $status = $v->in('status', 'Le statut', OrderRepository::STATUSES);
        $v->validate();

        $orders->updateStatus($order['id'], $status);
        Session::flash('success', 'Statut mis à jour : ' . order_status_label($status) . '.');

        return $this->redirect('/admin/commandes/' . $order['id']);
    }
}
