<?php

namespace App\Rules;

use App\Models\Client;
use App\Models\ClientContact;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Interlocuteur d'un dossier (campagne, réservation, devis — 2026-10-29) :
 * un interlocuteur actif de CE client. Obligatoire dès que le client en a
 * un (règle de la direction : « obligatoire si possible »).
 */
class InterlocuteurDuClient implements ValidationRule
{
    public function __construct(private ?int $clientId) {}

    /** Règles du champ client_contact_id, pour les trois formulaires. */
    public static function regles(?int $clientId): array
    {
        return [
            Rule::requiredIf(fn () => $clientId && ClientContact::where('client_id', $clientId)->exists()),
            'nullable',
            'integer',
            new self($clientId),
        ];
    }

    public const MESSAGES = [
        'client_contact_id.required' => "Choisissez l'interlocuteur du client qui suit ce dossier.",
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (!ClientContact::where('id', $value)->where('client_id', $this->clientId)->exists()) {
            $fail("Cet interlocuteur n'appartient pas au client choisi.");
        }
    }
}
