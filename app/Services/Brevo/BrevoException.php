<?php

namespace App\Services\Brevo;

/**
 * Erreur remontée par Brevo, avec un message lisible par l'admin
 * (affiché dans le journal des envois et dans l'alerte d'échec).
 */
class BrevoException extends \RuntimeException
{
}
