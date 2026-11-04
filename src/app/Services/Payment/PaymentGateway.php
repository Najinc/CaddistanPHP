<?php

namespace App\Services\Payment;

interface PaymentGateway
{
    /** Débite le montant (en centimes) pour la commande. Lève PaymentDeclinedException si refusé. */
    public function charge(int $orderId, int $amountCents): void;
}
