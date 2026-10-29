<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientContact;

/**
 * Nom à saluer dans les mails au client (règle de la direction, 2026-10-29) :
 * l'interlocuteur quand le mail lui est adressé, et TOUJOURS le nom du
 * client à côté — « Bonjour Awa Koné (ORANGE CI), ». Sans interlocuteur :
 * « Bonjour ORANGE CI, ». Source unique pour tous les mails de suivi.
 */
final class Salutation
{
    /** « Awa Koné (ORANGE CI) », ou le client seul s'il n'y a personne à nommer. */
    public static function nom(?Client $client, ?string $personne): string
    {
        $societe  = trim((string) $client?->name);
        $personne = trim((string) $personne);

        if ($personne === '' || mb_strtolower($personne) === mb_strtolower($societe)) {
            return $societe !== '' ? $societe : 'Madame, Monsieur';
        }

        return $societe !== '' ? "{$personne} ({$societe})" : $personne;
    }

    /**
     * Mail de suivi d'un dossier : adressé à son interlocuteur s'il est
     * retenu (même règle que Client::destinatairesSuivi), sinon au client.
     */
    public static function suivi(?Client $client, ?ClientContact $interlocuteur): string
    {
        return self::nom($client, $client?->interlocuteurRetenu($interlocuteur)?->name);
    }
}
