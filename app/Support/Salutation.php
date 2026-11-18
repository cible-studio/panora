<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientContact;

/**
 * Nom à saluer dans les mails au client (règle de la direction, 2026-10-29) :
 * l'interlocuteur quand le mail lui est adressé, et TOUJOURS le nom du
 * client à côté — « Bonjour Awa Koné (ORANGE CI), ». Sans interlocuteur :
 * « Bonjour ORANGE CI, ». Source unique pour tous les mails de suivi.
 *
 * 2026-11-18 (validé par l'utilisateur pour tous les mails) : un « nom »
 * d'interlocuteur qui n'est pas celui d'une personne (« contact »,
 * « audreyasseke », « Comptabilité RCG ») compte comme pas de nom :
 * « Bonjour MANOOVA, » et non « Bonjour contact (MANOOVA), ».
 */
final class Salutation
{
    /**
     * « Awa Koné (ORANGE CI) », ou le client seul s'il n'y a personne à nommer.
     *
     * @param ?string $email  adresse de l'interlocuteur, si on l'a : aide à
     *        repérer un nom fabriqué à partir d'elle (personneNommable)
     */
    public static function nom(?Client $client, ?string $personne, ?string $email = null): string
    {
        $societe  = trim((string) $client?->name);
        $personne = (string) self::personneNommable($personne, $email, $societe !== '' ? $societe : null);

        if ($personne === '' || mb_strtolower($personne) === mb_strtolower($societe)) {
            return $societe !== '' ? $societe : 'Madame, Monsieur';
        }

        return $societe !== '' ? "{$personne} ({$societe})" : $personne;
    }

    /** Mots qui désignent un service, pas une personne (personneNommable). */
    public const MOTS_DE_SERVICE = [
        'contact', 'contacts', 'info', 'infos', 'information', 'informations', 'direction', 'directeur', 'directrice',
        'comptabilite', 'compta', 'accueil', 'admin', 'administration', 'administratif', 'commercial', 'commerciale',
        'commerciaux', 'marketing', 'communication', 'comm', 'service', 'services', 'secretariat', 'rh', 'support',
        'hello', 'team', 'equipe', 'sales', 'finance', 'finances', 'achat', 'achats', 'dg', 'general', 'generale',
        'siege', 'bureau', 'office', 'mail', 'email', 'courrier', 'reception', 'factures', 'facturation',
        'de', 'du', 'des', 'la', 'le', 'les', 'l', 'et', 'd',
    ];

    /**
     * Le nom d'un interlocuteur est-il celui d'une personne ? (2026-11-17,
     * baromètre — « oui, préférable » de l'utilisateur). null quand ce n'en
     * est pas un, et le mail salue alors l'entreprise seule :
     *   · vide, ou une adresse e-mail ;
     *   · un seul mot tout en minuscules (« audreyasseke », « esta.fatokun »,
     *     « contact ») : c'est le début d'une adresse, recopié à l'import
     *     des contacts Gmail — « Koffi » reste un prénom ;
     *   · tout en minuscules ET identique au début de son adresse ;
     *   · contient le nom de l'entreprise (« Ledcom Côte d'Ivoire »,
     *     « Prodigecom » pour PRODIGE COM) ;
     *   · que des mots de service et le nom de l'entreprise (« contact »,
     *     « Comptabilité RCG », « Direction LIGHT MEDIA »).
     */
    public static function personneNommable(?string $personne, ?string $email = null, ?string $societe = null): ?string
    {
        $personne = trim((string) $personne);
        if ($personne === '' || str_contains($personne, '@')) {
            return null;
        }

        $lettres = fn (?string $t) => (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(\Illuminate\Support\Str::ascii((string) $t)));
        $mots    = fn (?string $t) => preg_split('/[^a-z0-9]+/', mb_strtolower(\Illuminate\Support\Str::ascii((string) $t)), -1, PREG_SPLIT_NO_EMPTY);

        $nom = $lettres($personne);
        $debutAdresse = $lettres(strstr((string) $email, '@', true) ?: '');
        $soc = $lettres($societe);

        if ($nom === ''
            || (mb_strtolower($personne) === $personne && !preg_match('/\s/u', $personne))
            || ($debutAdresse !== '' && $nom === $debutAdresse && mb_strtolower($personne) === $personne)
            || (strlen($soc) >= 3 && str_contains($nom, $soc))
            || array_diff($mots($personne), self::MOTS_DE_SERVICE, $mots($societe)) === []) {
            return null;
        }

        return $personne;
    }

    /**
     * Mail de suivi d'un dossier : adressé à son interlocuteur s'il est
     * retenu (même règle que Client::destinatairesSuivi), sinon au client.
     */
    public static function suivi(?Client $client, ?ClientContact $interlocuteur): string
    {
        $retenu = $client?->interlocuteurRetenu($interlocuteur);

        return self::nom($client, $retenu?->name, $retenu?->email);
    }
}
