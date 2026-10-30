<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;

/**
 * Identité du client sur une facture (décision de la direction, 2026-10-30)
 * — source unique de la règle.
 *
 *   · Brouillon : la facture lit la fiche client telle qu'elle est.
 *   · Dès qu'elle sort du brouillon (générée, validée, envoyée, avoir…),
 *     l'identité est figée sur la facture : nom, NCC, RCCM, régime
 *     d'imposition, adresse postale (mentions légales du document).
 *   · Remise en brouillon pour correction : l'identité figée est effacée,
 *     puis figée à nouveau à la prochaine émission.
 *
 * L'e-mail et le téléphone restent ceux de la fiche (coordonnées de
 * contact). Les écrans de suivi (liste, recouvrement, relances) affichent le
 * client tel qu'il est aujourd'hui ; les DOCUMENTS (PDF, page publique,
 * fiche facture, exports de factures) passent par lire().
 */
final class IdentiteClientFacture
{
    /** Colonne de la facture → champ de la fiche client. */
    public const CHAMPS = [
        'client_nom'               => 'name',
        'client_ncc'               => 'ncc',
        'client_rccm'              => 'rccm',
        'client_regime_imposition' => 'regime_imposition',
        'client_adresse'           => 'address',
    ];

    /** Appelé à chaque enregistrement d'une facture (Invoice::booted). */
    public static function appliquer(Invoice $facture): void
    {
        // Colonnes absentes (migration pas encore jouée, base de test réduite) : rien à figer.
        if (!once(fn () => \Illuminate\Support\Facades\Schema::hasColumn('invoices', 'client_fige_le'))) {
            return;
        }
        $brouillon = $facture->status === 'brouillon';

        if ($brouillon && $facture->client_fige_le !== null) {
            foreach (array_keys(self::CHAMPS) as $colonne) {
                $facture->{$colonne} = null;
            }
            $facture->client_fige_le = null;

            return;
        }

        if (!$brouillon && $facture->client_fige_le === null && $facture->client_id) {
            $client = Client::withTrashed()->find($facture->client_id);
            foreach (self::CHAMPS as $colonne => $champ) {
                $facture->{$colonne} = $client?->getRawOriginal($champ);
            }
            $facture->client_fige_le = now();
        }
    }

    /**
     * Identité à imprimer sur la facture.
     *
     * @return array{nom: ?string, ncc: ?string, rccm: ?string, regime_imposition: ?string, adresse: ?string, fige: bool, nom_actuel: ?string}
     */
    public static function lire(Invoice $facture): array
    {
        $client = $facture->client; // relation withTrashed()
        $fige = $facture->client_fige_le !== null;
        $identite = ['fige' => $fige, 'nom_actuel' => $client?->name];
        foreach (self::CHAMPS as $colonne => $champ) {
            $identite[substr($colonne, 7)] = $fige ? $facture->{$colonne} : $client?->{$champ};
        }

        return $identite;
    }
}
