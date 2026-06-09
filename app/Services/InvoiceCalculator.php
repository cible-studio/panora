<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLine;

/**
 * Calculateur FNE — source UNIQUE de vérité pour les montants facture.
 *
 * Logique métier (cf. prompt module facturation validé 2026-06-08) :
 *
 *   Par ligne :
 *     Montant HT = PU × quantité × durée
 *     ODP        = odp_rate × m² × quantité × durée
 *     TM         = tm_rate (1000) × m² × quantité × durée
 *
 *   Facture :
 *     Total HT brut = Σ Montant HT
 *     Net HT        = Total HT brut × (1 - remise%)
 *     TVA           = Net HT × tva%
 *     TSP           = Net HT × tsp%       (Taxe Soutien Production, 3 %)
 *     Total TM      = Σ TM lignes
 *     Total ODP     = Σ ODP lignes
 *     Services TTC  = (impression + pose-dépose) × (1 + tva/100)
 *
 *   Total à payer (FNE) =
 *     Net HT + TVA + TSP + Total TM + Total ODP + Services TTC
 *
 *   Affichage FNE :
 *     TOTAL HT      = Net HT
 *     TVA (18 %)
 *     TOTAL TTC     = Net HT + TVA
 *     AUTRES TAXES  = TSP + Total TM + Total ODP
 *     TOTAL À PAYER = TOTAL TTC + AUTRES TAXES + Services TTC
 *
 * Test de référence (Treichville, 1 panneau 4 mois 12 m², PU 130k) :
 *   HT = 520 000, TVA = 93 600, TTC = 613 600,
 *   ODP = 48 000, TM = 48 000, TSP = 15 600,
 *   AUTRES TAXES = 111 600, TOTAL = 725 200.
 *
 * Cette classe est SANS effet de bord — elle calcule et retourne un
 * payload. C'est l'appelant qui décide de persister via
 * Invoice::forceFill($payload['totals']).
 */
class InvoiceCalculator
{
    public function __construct()
    {
        // Lecture des taux depuis config/billing.php — chaque taux peut
        // être surchargé par ENV sans toucher au code.
        $this->tvaRate   = (float) config('billing.tva_rate', 18.0);
        $this->tspRate   = (float) config('billing.tsp_rate', 3.0);
        $this->tmDefault = (float) config('billing.tm_default', 1000.0);
    }

    protected float $tvaRate;
    protected float $tspRate;
    protected float $tmDefault;

    /**
     * Calcule UNE ligne. Retourne un tableau prêt à être persisté ou
     * affiché — montant_ht_ligne, odp_ligne, tm_ligne.
     *
     * @param array{
     *   pu_ht_mensuel: float|int,
     *   quantite: int,
     *   duree_mois: float,
     *   dimension_m2: float,
     *   odp_rate_applique?: float,
     *   tm_rate_applique?: float
     * } $line
     */
    public function calculateLine(array $line): array
    {
        $pu       = (float) ($line['pu_ht_mensuel'] ?? 0);
        $qte      = (int)   ($line['quantite'] ?? 1);
        $duree    = (float) ($line['duree_mois'] ?? 1);
        $m2       = (float) ($line['dimension_m2'] ?? 0);
        $odpRate  = (float) ($line['odp_rate_applique'] ?? 0);

        // TM : base FIXE 1000 F/m²/mois sur tout le territoire (cf. prompt
        // 2025). On lit tm_rate_applique pour permettre une dérogation
        // commune (si le législateur introduit un tarif différencié plus
        // tard), MAIS si la ligne arrive avec tm = 0 (commune mal seedée,
        // oubli admin), on FALLBACK sur tm_default config = 1000.
        // Sinon on facturait 0 de TM par erreur — pénalité fiscale CI.
        $tmRaw    = $line['tm_rate_applique'] ?? null;
        $tmRate   = ($tmRaw !== null && (float) $tmRaw > 0)
            ? (float) $tmRaw
            : $this->tmDefault;

        $montantHt = $pu * $qte * $duree;
        $odp       = $odpRate * $m2 * $qte * $duree;
        $tm        = $tmRate  * $m2 * $qte * $duree;

        return [
            'montant_ht_ligne' => round($montantHt, 2),
            'odp_ligne'        => round($odp, 2),
            'tm_ligne'         => round($tm, 2),
        ];
    }

    /**
     * Calcule TOUS les agrégats facture à partir d'une collection de
     * lignes (déjà calculées par calculateLine OU bruts).
     *
     * @param array<int, array> $lines Lignes (avec ou sans pré-calcul)
     * @param array{
     *   remise_pct?: float,
     *   services_impression?: float,
     *   services_pose_depose?: float
     * } $opts
     *
     * @return array Tableau d'agrégats prêts à persister sur Invoice :
     *   amount (= total HT brut avant remise),
     *   net_ht, tva, tva_amount, amount_ttc,
     *   tsp_amount, tm_total, odp_total,
     *   services_impression, services_pose_depose,
     *   total_a_payer.
     */
    public function calculateInvoice(array $lines, array $opts = []): array
    {
        $remisePct = max(0.0, min(100.0, (float) ($opts['remise_pct'] ?? 0)));
        $svcPrint  = max(0.0, (float) ($opts['services_impression'] ?? 0));
        $svcPose   = max(0.0, (float) ($opts['services_pose_depose'] ?? 0));

        $totalHtBrut = 0.0;
        $totalTm     = 0.0;
        $totalOdp    = 0.0;

        foreach ($lines as $l) {
            // Auto-calcul si la ligne n'a pas encore ses montants
            if (!isset($l['montant_ht_ligne'])) {
                $calc = $this->calculateLine($l);
                $totalHtBrut += $calc['montant_ht_ligne'];
                $totalTm     += $calc['tm_ligne'];
                $totalOdp    += $calc['odp_ligne'];
            } else {
                $totalHtBrut += (float) $l['montant_ht_ligne'];
                $totalTm     += (float) ($l['tm_ligne']  ?? 0);
                $totalOdp    += (float) ($l['odp_ligne'] ?? 0);
            }
        }

        $netHt      = $totalHtBrut * (1 - $remisePct / 100);
        $tvaAmount  = $netHt * $this->tvaRate / 100;
        $tspAmount  = $netHt * $this->tspRate / 100;
        $amountTtc  = $netHt + $tvaAmount;
        $servicesHt = $svcPrint + $svcPose;
        $servicesTtc = $servicesHt * (1 + $this->tvaRate / 100);
        $autresTaxes = $tspAmount + $totalTm + $totalOdp;
        $totalAPayer = $amountTtc + $autresTaxes + $servicesTtc;

        return [
            'amount'               => round($totalHtBrut, 2),
            'remise_pct'           => round($remisePct, 2),
            'net_ht'               => round($netHt, 2),
            'tva'                  => round($this->tvaRate, 2),
            'tva_amount'           => round($tvaAmount, 2),
            'tsp_amount'           => round($tspAmount, 2),
            'tm_total'             => round($totalTm, 2),
            'odp_total'            => round($totalOdp, 2),
            'services_impression'  => round($svcPrint, 2),
            'services_pose_depose' => round($svcPose, 2),
            'amount_ttc'           => round($amountTtc, 2),
            'total_a_payer'        => round($totalAPayer, 2),
        ];
    }

    /**
     * Recalcule UNE facture persistée à partir de ses lignes en base
     * et écrase les agrégats. Utile après ajout/suppression de ligne.
     *
     * Bloque si la facture est verrouillée — sinon on enfreint la
     * promesse "facture envoyée = immuable".
     *
     * @return Invoice La facture refraîchie.
     * @throws \LogicException si verrouillée
     */
    public function recalculateAndPersist(Invoice $invoice): Invoice
    {
        if ($invoice->isLocked()) {
            throw new \LogicException(
                "Facture verrouillée (envoyée le {$invoice->locked_at?->format('d/m/Y')}). "
                . "Déverrouille-la d'abord ou émets un avoir."
            );
        }

        $invoice->loadMissing('lines');
        $linesArr = $invoice->lines->map(fn(InvoiceLine $l) => $l->toArray())->all();

        $totals = $this->calculateInvoice($linesArr, [
            'remise_pct'           => (float) $invoice->remise_pct,
            'services_impression'  => (float) $invoice->services_impression,
            'services_pose_depose' => (float) $invoice->services_pose_depose,
        ]);

        $invoice->forceFill($totals)->save();
        return $invoice->fresh(['lines']);
    }

    // ─── Accesseurs utiles à la vue (tva/tsp en cours) ──────────────
    public function tvaRate(): float { return $this->tvaRate; }
    public function tspRate(): float { return $this->tspRate; }
    public function tmDefault(): float { return $this->tmDefault; }
}
