<?php

namespace App\Services\Payment;

/**
 * Paiement simulé : accepté jusqu'au plafond, refusé au-delà.
 * Le plafond permet de démontrer le ROLLBACK : une commande de plus de 10 000 € est refusée
 * et ni la commande, ni ses lignes, ni la baisse de stock ne sont enregistrées.
 */
final class SimulatedPaymentGateway implements PaymentGateway
{
    public const CEILING_CENTS = 10_000_00;

    public function charge(int $orderId, int $amountCents): void
    {
        if ($amountCents > self::CEILING_CENTS) {
            throw new PaymentDeclinedException('Paiement refusé : le plafond de paiement de 10 000 € est dépassé.');
        }
    }
}
