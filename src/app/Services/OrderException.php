<?php

namespace App\Services;

/**
 * Erreur métier de commande, dont le message PEUT être montré au client (stock, paiement refusé…).
 * Toute autre exception (PDOException…) n'est jamais affichée : elle donne une page 500 générique.
 */
class OrderException extends \RuntimeException
{
}
