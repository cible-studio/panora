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
    public function __construct(protected TaxPeriodCalculator $period = new TaxPeriodCalculator())
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
     * ═══ RÈGLES DE CALCUL (mises à jour 2026-07-29 — TX-9) ═══
     *
     * Trois multiplicateurs distincts selon ce qu'on calcule :
     *
     *   LOYER PANNEAU : pu_ht_mensuel × quantite × duree_mois
     *                    (duree_mois = négociation commerciale, saisie manuellement)
     *
     *   TM : tm_rate × surface × quantite × mois_TM
     *        où mois_TM = "mois de date à date entamés strictement"
     *        calculés depuis campaign_start → campaign_end SI fournies.
     *        Sinon fallback sur duree_mois (compatibilité anciennes factures).
     *
     *   ODP : (odp_rate × 3) × surface × quantite × trimestres_ODP
     *         où trimestres_ODP = nb trimestres calendaires touchés
     *         par la campagne SI dates fournies. Sinon fallback
     *         sur duree_mois (compatibilité).
     *         Le ×3 vient de "tarif stocké mensuel → forfait trimestriel".
     *
     * @param array{
     *   pu_ht_mensuel: float|int,
     *   quantite: int,
     *   duree_mois: float,
     *   dimension_m2: float,
     *   odp_rate_applique?: float,
     *   tm_rate_applique?: float,
     *   campaign_start?: string|\DateTimeInterface|null,
     *   campaign_end?: string|\DateTimeInterface|null
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

        // Le LOYER panneau utilise toujours duree_mois négocié.
        $montantHt = $pu * $qte * $duree;

        // TM et ODP : mode auto si dates campagne fournies, sinon fallback
        // sur duree_mois (comportement historique — compat totale avec les
        // factures FNE émises avant 2026-07-29).
        $cs = $line['campaign_start'] ?? null;
        $ce = $line['campaign_end']   ?? null;

        if ($cs && $ce) {
            $csDate = \Carbon\Carbon::parse($cs);
            $ceDate = \Carbon\Carbon::parse($ce);

            $moisTM         = $this->period->moisAnniversaireEntames($csDate, $ceDate);
            $trimestresODP  = $this->period->trimestresCalendairesTouches($csDate, $ceDate);

            $odp = ($odpRate * 3) * $m2 * $qte * $trimestresODP;   // forfait trimestriel ×3
            $tm  = $tmRate       * $m2 * $qte * $moisTM;
        } else {
            // Compatibilité totale : ligne saisie sans dates → on utilise
            // duree_mois (comme avant TX-9).
            $odp = $odpRate * $m2 * $qte * $duree;
            $tm  = $tmRate  * $m2 * $qte * $duree;
        }

        // RÈGLE D'OR #4 — entiers FCFA. Pas de centimes en franc CFA.
        return [
            'montant_ht_ligne' => (int) round($montantHt),
            'odp_ligne'        => (int) round($odp),
            'tm_ligne'         => (int) round($tm),
        ];
    }

    /**
     * Calcule TOUS les agrégats facture à partir d'une collection de
     * lignes (déjà calculées par calculateLine OU bruts).
     *
     * Services annexes (prompt v2) : N lignes libres avec libellé + prix HT.
     * On accepte :
     *   - opts.services = [['label' => '...', 'prix_ht' => 50000], ...]  (forme moderne)
     *   - opts.services_impression / services_pose_depose : LEGACY, agrégés en
     *     2 pseudo-services s'ils sont fournis (pour ne pas casser les tests
     *     existants et les vieilles factures non-migrées).
     *
     * @param array<int, array> $lines Lignes (avec ou sans pré-calcul)
     * @param array{
     *   remise_pct?: float,
     *   services?: array<int, array{label?: string, prix_ht?: float}>,
     *   services_impression?: float,
     *   services_pose_depose?: float
     * } $opts
     *
     * @return array Tableau d'agrégats prêts à persister sur Invoice.
     *   Inclut services_impression / services_pose_depose pour rétro-compat
     *   (somme des services côté impression/pose si fournis en LEGACY,
     *   sinon 0).
     */
    public function calculateInvoice(array $lines, array $opts = []): array
    {
        $remisePct = max(0.0, min(100.0, (float) ($opts['remise_pct'] ?? 0)));

        // ── Résolution services annexes ──
        // Priorité : opts.services (forme libre N lignes).
        // Sinon : LEGACY opts.services_impression + services_pose_depose
        //         convertis en 2 lignes service.
        $svcLines = [];
        if (!empty($opts['services']) && is_array($opts['services'])) {
            foreach ($opts['services'] as $s) {
                $prix = (float) ($s['prix_ht'] ?? 0);
                if ($prix <= 0) continue;
                $svcLines[] = [
                    'label'   => (string) ($s['label'] ?? 'Service'),
                    'prix_ht' => $prix,
                ];
            }
        } else {
            $legacyPrint = max(0.0, (float) ($opts['services_impression']  ?? 0));
            $legacyPose  = max(0.0, (float) ($opts['services_pose_depose'] ?? 0));
            if ($legacyPrint > 0) $svcLines[] = ['label' => "Frais d'impression",       'prix_ht' => $legacyPrint];
            if ($legacyPose  > 0) $svcLines[] = ['label' => 'Frais de pose et dépose', 'prix_ht' => $legacyPose];
        }

        $totalHtBrut = 0.0;
        $totalTm     = 0.0;
        $totalOdp    = 0.0;

        foreach ($lines as $l) {
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

        $servicesHt  = array_sum(array_column($svcLines, 'prix_ht'));
        $servicesTtc = $servicesHt * (1 + $this->tvaRate / 100);

        // Rétro-compat pour les champs invoices.services_impression /
        // services_pose_depose : on les remplit avec les 2 premières
        // lignes du forme libre matchant ces libellés, sinon 0. Permet
        // de garder le rapport legacy fonctionnel pendant la transition.
        $legacyPrint = 0.0;
        $legacyPose  = 0.0;
        foreach ($svcLines as $s) {
            $label = mb_strtolower($s['label']);
            if (str_contains($label, 'impression')) $legacyPrint += $s['prix_ht'];
            if (str_contains($label, 'pose'))       $legacyPose  += $s['prix_ht'];
        }

        $autresTaxes = $tspAmount + $totalTm + $totalOdp;
        $totalAPayer = $amountTtc + $autresTaxes + $servicesTtc;

        // RÈGLE D'OR #4 — tous les montants en entiers FCFA.
        // Seuls remise_pct et tva (qui sont des taux %) restent décimaux.
        return [
            'amount'               => (int) round($totalHtBrut),
            'remise_pct'           => round($remisePct, 2),
            'net_ht'               => (int) round($netHt),
            'tva'                  => round($this->tvaRate, 2),
            'tva_amount'           => (int) round($tvaAmount),
            'tsp_amount'           => (int) round($tspAmount),
            'tm_total'             => (int) round($totalTm),
            'odp_total'            => (int) round($totalOdp),
            'services_impression'  => (int) round($legacyPrint),
            'services_pose_depose' => (int) round($legacyPose),
            'services_ht_total'    => (int) round($servicesHt),
            'services_ttc_total'   => (int) round($servicesTtc),
            'amount_ttc'           => (int) round($amountTtc),
            'total_a_payer'        => (int) round($totalAPayer),
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

        $invoice->loadMissing(['lines', 'services']);
        $linesArr = $invoice->lines->map(fn(InvoiceLine $l) => $l->toArray())->all();
        $svcArr   = $invoice->services->map(fn($s) => [
            'label'   => $s->label,
            'prix_ht' => (float) $s->prix_ht,
        ])->all();

        $totals = $this->calculateInvoice($linesArr, [
            'remise_pct' => (float) $invoice->remise_pct,
            // Forme moderne (N services libres). Si vide, le calculator
            // fallback automatiquement sur les 2 champs legacy de la
            // facture (rétro-compat pour les factures non-migrées).
            'services'   => !empty($svcArr) ? $svcArr : null,
            // Toujours passer le legacy en fallback si pas de services modernes
            'services_impression'  => empty($svcArr) ? (float) $invoice->services_impression  : 0,
            'services_pose_depose' => empty($svcArr) ? (float) $invoice->services_pose_depose : 0,
        ]);

        // calculateInvoice retourne des AGRÉGATS INTERNES
        // (services_ht_total, services_ttc_total) qui ne sont PAS des
        // colonnes de la table invoices — ils sont utilisés par les vues
        // et la JS recompute. On les retire avant le forceFill pour
        // éviter "Column not found: services_ht_total" en prod.
        unset($totals['services_ht_total'], $totals['services_ttc_total']);

        $invoice->forceFill($totals)->save();
        return $invoice->fresh(['lines']);
    }

    // ─── Accesseurs utiles à la vue (tva/tsp en cours) ──────────────
    public function tvaRate(): float { return $this->tvaRate; }
    public function tspRate(): float { return $this->tspRate; }
    public function tmDefault(): float { return $this->tmDefault; }
}
