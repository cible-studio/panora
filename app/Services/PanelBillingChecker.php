<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;

/**
 * PanelBillingChecker — Source unique de vérité pour la règle métier
 * « un panneau physique = une seule ligne de facture par période ».
 *
 * Contexte (2026-07-16, Bug 3b/3c) : jusqu'ici le formulaire de facture
 * permettait de saisir plusieurs lignes pointant vers le même panneau
 * (ex. SP-001 × 3 lignes), ou une seule ligne avec quantite > 1. Ces
 * deux cas sont ABERRANTS métier : un panneau physique unique ne peut
 * être facturé qu'une fois pour la période où il est loué. Multiplier
 * fausse toutes les taxes (ODP, TM) et invente du revenu.
 *
 * Deux niveaux de vérification :
 *
 *   1. INTRA-FACTURE (validatePayload) : dans une même facture, un
 *      panneau ne peut apparaître qu'une seule fois, avec quantite=1.
 *
 *   2. INTER-FACTURES (assertNotAlreadyBilled) : sur la période
 *      dérivée (campagne liée OU issued_at + duree_mois), le panneau
 *      ne doit pas être déjà présent dans une autre facture non
 *      annulée avec période chevauchante.
 *
 * Cas légitimes prévus (relâchements à venir si besoin) :
 *   - Panneau triangle 3 faces → décision produit à trancher (créer
 *     entité PanelFace, ou garder qté-multiple avec flag `groupage`).
 *   - Refacturation après annulation → autorisé (une facture annulée
 *     libère la ligne).
 */
class PanelBillingChecker
{
    /**
     * Validation INTRA-FACTURE — à appeler AVANT persist depuis
     * InvoiceController::store() et ::update().
     *
     * Retourne un tableau d'erreurs prêt à passer à withErrors() :
     *   [ 'lines.2.panel_id' => 'SP-001 est déjà présent en ligne 1.' ]
     *
     * @param array $lines Payload validé du form (chaque item a au moins
     *                     panel_id ou external_panel_id, quantite).
     * @return array<string, string>  Erreurs par champ (vide si OK).
     */
    public function validatePayload(array $lines): array
    {
        $errors = [];
        $seenInt = [];  // panel_id => ligne d'origine (0-indexed)
        $seenExt = [];  // external_panel_id => ligne d'origine

        foreach ($lines as $i => $l) {
            $panelId    = !empty($l['panel_id']) ? (int) $l['panel_id'] : null;
            $extPanelId = !empty($l['external_panel_id']) ? (int) $l['external_panel_id'] : null;
            $qte        = (int) ($l['quantite'] ?? 1);
            $designation= (string) ($l['designation'] ?? 'ligne '. ($i + 1));

            // Règle A : si un panneau est lié (interne ou externe), quantite DOIT être 1.
            // Un panneau physique unique ne se loue qu'une fois pour la même période.
            if (($panelId !== null || $extPanelId !== null) && $qte > 1) {
                $errors["lines.{$i}.quantite"] =
                    "« {$designation} » : quantité doit être 1 (un panneau physique ne peut être loué "
                    . "qu'une fois par période). Si tu veux facturer plusieurs panneaux, ajoute une ligne "
                    . "distincte pour chaque panneau.";
            }

            // Règle B : pas de doublon intra-facture sur panel_id
            if ($panelId !== null) {
                if (isset($seenInt[$panelId])) {
                    $originalLine = $seenInt[$panelId] + 1;
                    $errors["lines.{$i}.panel_id"] =
                        "« {$designation} » est déjà présent en ligne {$originalLine}. "
                        . "Un panneau ne peut être facturé qu'une fois par facture.";
                } else {
                    $seenInt[$panelId] = $i;
                }
            }

            // Règle C : pas de doublon intra-facture sur external_panel_id
            if ($extPanelId !== null) {
                if (isset($seenExt[$extPanelId])) {
                    $originalLine = $seenExt[$extPanelId] + 1;
                    $errors["lines.{$i}.external_panel_id"] =
                        "« {$designation} » (régie externe) est déjà présent en ligne {$originalLine}.";
                } else {
                    $seenExt[$extPanelId] = $i;
                }
            }
        }

        return $errors;
    }

    /**
     * Validation INTER-FACTURES — à appeler AVANT persist depuis
     * InvoiceController::store() et ::update().
     *
     * Vérifie que chaque panneau lié dans le payload n'est PAS déjà
     * facturé sur période chevauchante dans une AUTRE facture non
     * annulée. On dérive la période de facturation depuis la campagne
     * liée (période campagne fait foi) ou, à défaut, depuis issued_at +
     * duree_mois par ligne.
     *
     * @param array $lines            Lignes du payload
     * @param \Carbon\Carbon $issuedAt Date d'émission (fallback si pas de campagne)
     * @param int|null $campaignId    Campagne liée (donne la période officielle si présente)
     * @param int|null $excludeInvoiceId Facture en cours d'édition (exclure d'elle-même)
     * @return array<string, string>  Erreurs par champ (vide si OK).
     */
    public function assertNotAlreadyBilled(
        array $lines,
        \Carbon\Carbon $issuedAt,
        ?int $campaignId = null,
        ?int $excludeInvoiceId = null
    ): array {
        $errors = [];

        // Période officielle si campagne liée
        [$periodStart, $periodEnd] = $this->resolvePeriod($issuedAt, $campaignId);

        foreach ($lines as $i => $l) {
            $panelId    = !empty($l['panel_id']) ? (int) $l['panel_id'] : null;
            $extPanelId = !empty($l['external_panel_id']) ? (int) $l['external_panel_id'] : null;
            if ($panelId === null && $extPanelId === null) continue;

            // Fallback période au niveau ligne : issued_at → issued_at + duree_mois
            $lineStart = $periodStart ?? $issuedAt->copy();
            $lineEnd   = $periodEnd   ?? $issuedAt->copy()->addMonths((int) ceil((float) ($l['duree_mois'] ?? 1)));

            $conflict = $this->findConflictingLine($panelId, $extPanelId, $lineStart, $lineEnd, $excludeInvoiceId);

            if ($conflict !== null) {
                $designation = (string) ($l['designation'] ?? 'ligne '. ($i + 1));
                $ref         = $conflict['invoice_reference'];
                $errors["lines.{$i}." . ($panelId !== null ? 'panel_id' : 'external_panel_id')] =
                    "« {$designation} » est déjà facturé sur la période "
                    . $lineStart->format('d/m/Y') . ' → ' . $lineEnd->format('d/m/Y')
                    . " dans la facture {$ref}. Annule-la ou ajuste les périodes avant de refacturer.";
            }
        }

        return $errors;
    }

    /**
     * Résout la période "officielle" de facturation. La campagne liée
     * fait foi si présente (start_date/end_date). Sinon retourne
     * [null, null] et l'appelant fallback sur issued_at + duree_mois
     * par ligne.
     */
    protected function resolvePeriod(\Carbon\Carbon $issuedAt, ?int $campaignId): array
    {
        if (!$campaignId) return [null, null];

        $camp = DB::table('campaigns')
            ->where('id', $campaignId)
            ->first(['start_date', 'end_date']);

        if (!$camp || !$camp->start_date || !$camp->end_date) return [null, null];

        return [
            \Carbon\Carbon::parse($camp->start_date),
            \Carbon\Carbon::parse($camp->end_date),
        ];
    }

    /**
     * Cherche une ligne de facture existante qui facture le même
     * panneau sur une période chevauchante. Ignore les factures
     * annulées (elles libèrent le panneau).
     *
     * Formule de chevauchement : deux périodes [aStart, aEnd] et
     * [bStart, bEnd] se chevauchent ssi aStart <= bEnd ET bStart <= aEnd.
     * On approxime la période d'une ligne existante à celle de sa
     * facture (issued_at + duree_mois) si pas de campagne, sinon
     * campagne.start_date/end_date.
     */
    protected function findConflictingLine(
        ?int $panelId,
        ?int $extPanelId,
        \Carbon\Carbon $lineStart,
        \Carbon\Carbon $lineEnd,
        ?int $excludeInvoiceId
    ): ?array {
        // Note : la table invoices n'utilise pas de soft delete — pas de
        // whereNull('deleted_at') nécessaire. On exclut uniquement les
        // factures annulées (statut = 'annulee') qui libèrent le panneau.
        $q = DB::table('invoice_lines')
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->leftJoin('campaigns', 'invoices.campaign_id', '=', 'campaigns.id')
            ->where('invoices.status', '!=', 'annulee')
            ->select(
                'invoice_lines.id as line_id',
                'invoice_lines.duree_mois',
                'invoices.id as invoice_id',
                'invoices.reference as invoice_reference',
                'invoices.issued_at',
                'campaigns.start_date as camp_start',
                'campaigns.end_date as camp_end',
            );

        if ($panelId !== null)    $q->where('invoice_lines.panel_id', $panelId);
        if ($extPanelId !== null) $q->where('invoice_lines.external_panel_id', $extPanelId);
        if ($excludeInvoiceId !== null) $q->where('invoices.id', '!=', $excludeInvoiceId);

        $candidates = $q->get();

        foreach ($candidates as $c) {
            $cStart = $c->camp_start ? \Carbon\Carbon::parse($c->camp_start) : \Carbon\Carbon::parse($c->issued_at);
            $cEnd   = $c->camp_end   ? \Carbon\Carbon::parse($c->camp_end)
                                     : \Carbon\Carbon::parse($c->issued_at)->addMonths((int) ceil((float) $c->duree_mois));

            // Chevauchement ?
            if ($lineStart->lte($cEnd) && $cStart->lte($lineEnd)) {
                return [
                    'invoice_id'        => (int) $c->invoice_id,
                    'invoice_reference' => (string) $c->invoice_reference,
                    'line_id'           => (int) $c->line_id,
                    'period_start'      => $cStart->format('Y-m-d'),
                    'period_end'        => $cEnd->format('Y-m-d'),
                ];
            }
        }

        return null;
    }
}
