<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Commune;
use App\Models\CommuneTaxPayment;
use App\Models\Panel;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * TaxCalculationService — calcul des taxes communales TM / ODP / DB
 * selon les règles d'éligibilité spécifiées (Évolution 4).
 *
 * Règles d'éligibilité (synthèse spec) :
 *
 *   | Statut panneau                  | TM | ODP |
 *   | ──────────────────────────────  | ── | ─── |
 *   | Exploité avec campagne active   | ✓  |  ✓  |
 *   | Interne/maint. AVEC campagne    | ✓  |  ✓  |
 *   | Interne/maint. SANS campagne    | ✗  |  ✓  |
 *   | Vacant                          | ✗  |  ✓  |
 *
 * → ODP : tous les panneaux internes (catalogue), peu importe le
 *   statut ou l'occupation.
 * → TM : uniquement si une campagne ACTIVE existe sur la période,
 *   couvre le panneau (interne ou maintenance peu importe).
 *
 * Les tarifs sont résolus via Commune::ratesAt(date) — utilise
 * l'historique tarifaire pour garantir la cohérence rétroactive.
 */
class TaxCalculationService
{
    public const TYPE_TM  = 'tm';
    public const TYPE_ODP = 'odp';
    public const TYPES    = [self::TYPE_TM, self::TYPE_ODP];

    public const PERIOD_MONTHLY    = 'mensuel';
    public const PERIOD_QUARTERLY  = 'trimestriel';
    public const PERIOD_ANNUAL     = 'annuel';

    /**
     * Produit la liste détaillée des lignes de taxes pour une période
     * donnée — une ligne par (panneau × type taxe éligible). Source
     * unique de vérité pour la vue détaillée, les rapports et les
     * exports.
     *
     * @param  string $periodType    'mensuel' | 'trimestriel' | 'annuel'
     * @param  int    $periodValue   1-12 (mois) | 1-4 (trim) | 0 (annuel)
     * @param  int    $year
     * @param  array  $filters       ['commune_id'=>?, 'client_id'=>?, 'campaign_id'=>?, 'type'=>?]
     * @return Collection            Collection de lignes :
     *      {commune, commune_id, panel_id, reference, name, dimensions,
     *       surface, type, statut, client_name, client_id, campaign_name,
     *       campaign_id, period_start, period_end, months, rate, amount}
     */
    public function generateLines(string $periodType, int $periodValue, int $year, array $filters = []): Collection
    {
        [$periodStart, $periodEnd, $months] = $this->resolvePeriod($periodType, $periodValue, $year);

        // Pré-charge toutes les communes (pour leur historique tarifaire)
        // — peu nombreuses (~30) donc OK en mémoire.
        $communes = Commune::all()->keyBy('id');

        // Pré-charge les rates au début de la période pour chaque commune.
        // Choix design : on utilise les tarifs du début de période. Si on
        // change de tarif au milieu d'un mois, le calcul reste sur l'ancien
        // — cohérent avec la pratique fiscale (date d'émission de la base).
        $ratesByCommune = $communes->mapWithKeys(
            fn($c) => [$c->id => $c->ratesAt($periodStart)]
        );

        // Charge panneaux avec leur commune, format, dimensions.
        // On ne prend PAS les soft-deleted (parc actuel uniquement).
        $panelsQuery = Panel::with(['commune:id,name', 'format:id,name,width,height,surface'])
            ->whereNull('deleted_at');
        if (!empty($filters['commune_id'])) {
            $panelsQuery->where('commune_id', $filters['commune_id']);
        }
        $panels = $panelsQuery->get();

        // Pour la règle TM : on a besoin de savoir si chaque panneau a
        // une campagne active sur la période. Une seule requête pour tous.
        $campaignAssignmentMap = $this->buildCampaignAssignmentMap(
            $panels->pluck('id')->all(),
            $periodStart,
            $periodEnd,
            $filters['campaign_id'] ?? null,
            $filters['client_id']   ?? null
        );

        $filterType = $filters['type'] ?? null;

        $lines = collect();
        foreach ($panels as $panel) {
            $commune = $communes[$panel->commune_id] ?? null;
            if (!$commune) continue;
            $rates = $ratesByCommune[$commune->id];

            // Si filtre client/campagne actif → on ne garde le panneau que
            // s'il a une campagne assignée correspondante. Sinon (pas de
            // filtre client/campagne) on garde tous les panneaux pour
            // ODP et on filtre TM individuellement.
            $assignment = $campaignAssignmentMap[$panel->id] ?? null;
            $hasClientCampaignFilter = !empty($filters['client_id']) || !empty($filters['campaign_id']);
            if ($hasClientCampaignFilter && !$assignment) continue;

            $surface = (float) ($panel->format?->surface ?? 0);
            $dimensions = $panel->format?->name ?? '—';

            foreach (self::TYPES as $type) {
                if ($filterType && $filterType !== $type) continue;
                if (!$this->isEligible($panel, $type, $assignment)) continue;

                $unitRate = (float) ($rates[$type] ?? 0);
                if ($unitRate <= 0) continue; // pas tarif → ligne ignorée

                // BUG TX-1 corrigé 2026-06-22 :
                // Les tarifs ODP/TM stockés dans communes.odp_rate et tm_rate
                // sont des tarifs ANNUELS (FCFA/m²/an). Multiplier directement
                // par $months (=12 pour annuel) donnait ×12 le bon montant :
                //   Plateau 246 m² × 15 000 × 12 = 44 280 000 FCFA (faux)
                //   alors que l'attendu annuel est 246 × 15 000 = 3 690 000.
                //
                // Formule correcte : tarif_annuel × surface × (nb_mois / 12)
                //   annuel        : ×(12/12) = ×1     → 246 × 15 000 = 3 690 000 ✓
                //   trimestriel   : ×(3/12)  = ×0.25
                //   mensuel       : ×(1/12)
                $amount = round($unitRate * $surface * ($months / 12), 2);

                $lines->push([
                    'commune'        => $commune->name,
                    'commune_id'     => $commune->id,
                    'panel_id'       => $panel->id,
                    'reference'      => $panel->reference,
                    'name'           => $panel->name,
                    'dimensions'     => $dimensions,
                    'surface'        => $surface,
                    'type'           => $type,
                    'statut'         => $panel->status?->value ?? 'libre',
                    'client_name'    => $assignment['client_name']    ?? null,
                    'client_id'      => $assignment['client_id']      ?? null,
                    'campaign_name'  => $assignment['campaign_name']  ?? null,
                    'campaign_id'    => $assignment['campaign_id']    ?? null,
                    'period_start'   => $periodStart,
                    'period_end'     => $periodEnd,
                    'months'         => $months,
                    'rate'           => $unitRate,
                    'amount'         => $amount,
                ]);
            }
        }

        return $lines;
    }

    /**
     * Règle d'éligibilité par type. Réutilise une assignment précomputée
     * (résultat de buildCampaignAssignmentMap) pour éviter les requêtes
     * N+1 sur le statut campagne.
     */
    public function isEligible(Panel $panel, string $type, ?array $assignment): bool
    {
        return match ($type) {
            self::TYPE_TM  => $assignment !== null,  // campagne active requise
            self::TYPE_ODP => true,                  // tous panneaux internes
            default        => false,
        };
    }

    /**
     * Map panel_id → ['campaign_id'=>, 'campaign_name'=>, 'client_id'=>,
     * 'client_name'=>] pour les campagnes actives ou en cours sur la
     * période. Une seule requête pour tous les panneaux passés en
     * paramètre.
     *
     * Une campagne "active sur la période" = statut ACTIF, dates qui se
     * chevauchent avec [periodStart, periodEnd] (même partiellement).
     *
     * Si un panneau a plusieurs campagnes sur la période (rare mais
     * possible avec extensions), on prend la plus récente.
     */
    private function buildCampaignAssignmentMap(array $panelIds, Carbon $periodStart, Carbon $periodEnd, ?int $campaignId = null, ?int $clientId = null): array
    {
        if (empty($panelIds)) return [];

        $q = \DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->leftJoin('clients', 'clients.id', '=', 'campaigns.client_id')
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaign_panels.panel_id', $panelIds)
            ->where('campaigns.status', CampaignStatus::ACTIF->value)
            ->where('campaigns.start_date', '<=', $periodEnd->toDateString())
            ->where('campaigns.end_date',   '>=', $periodStart->toDateString())
            ->whereNull('campaigns.deleted_at')
            ->select(
                'campaign_panels.panel_id',
                'campaigns.id as campaign_id',
                'campaigns.name as campaign_name',
                'campaigns.start_date',
                'campaigns.end_date',
                'clients.id as client_id',
                'clients.name as client_name'
            );

        if ($campaignId) $q->where('campaigns.id', $campaignId);
        if ($clientId)   $q->where('campaigns.client_id', $clientId);

        $rows = $q->orderByDesc('campaigns.start_date')->get();

        $map = [];
        foreach ($rows as $r) {
            // Premier match = plus récent (orderByDesc), on garde celui-là.
            if (!isset($map[$r->panel_id])) {
                $map[$r->panel_id] = [
                    'campaign_id'   => (int) $r->campaign_id,
                    'campaign_name' => $r->campaign_name,
                    'client_id'     => $r->client_id ? (int) $r->client_id : null,
                    'client_name'   => $r->client_name,
                ];
            }
        }
        return $map;
    }

    /**
     * Bornes d'une période + nombre de mois pour la formule.
     * @return array{0:Carbon, 1:Carbon, 2:int}
     */
    public function resolvePeriod(string $periodType, int $periodValue, int $year): array
    {
        // Hotfix TX-2 v2 (2026-06-22) : assertion bornes EXPLICITE en
        // tête de méthode. Avant : un periodValue=0 en trimestriel
        // donnait Carbon::create($year, -2, 1) avec rollover silencieux
        // sur l'année précédente, calcul incohérent en aval.
        if ($periodType === self::PERIOD_MONTHLY && ($periodValue < 1 || $periodValue > 12)) {
            throw new \InvalidArgumentException("Mois invalide pour mensuel : $periodValue (attendu 1..12).");
        }
        if ($periodType === self::PERIOD_QUARTERLY && ($periodValue < 1 || $periodValue > 4)) {
            throw new \InvalidArgumentException("Trimestre invalide : $periodValue (attendu 1..4).");
        }
        return match ($periodType) {
            self::PERIOD_MONTHLY => [
                Carbon::create($year, $periodValue, 1)->startOfDay(),
                Carbon::create($year, $periodValue, 1)->endOfMonth(),
                1,
            ],
            self::PERIOD_QUARTERLY => [
                Carbon::create($year, ($periodValue - 1) * 3 + 1, 1)->startOfDay(),
                Carbon::create($year, ($periodValue - 1) * 3 + 3, 1)->endOfMonth(),
                3,
            ],
            self::PERIOD_ANNUAL => [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
                12,
            ],
            default => throw new \InvalidArgumentException("Périodicité inconnue : $periodType"),
        };
    }

    /**
     * Agrégation des lignes pour les KPI / résumés. Évite d'avoir à
     * reparcourir la collection manuellement à chaque vue.
     *
     * Hotfix TX-4 (2026-06-22) : ajout des alias 'odp_total' et 'tm_total'
     * lus par TaxController::showCommune et computeAnnualTotalDue (qui
     * retournaient 0 partout car ces clés n'étaient jamais émises — la
     * matrice mensuelle de la fiche commune affichait donc 0 DÛ partout).
     * Les anciennes clés 'total', 'by_type', 'by_commune', 'panels_count',
     * 'lines_count' restent intactes (rétro-compat details.blade.php).
     */
    public function summarize(Collection $lines): array
    {
        $byType = $lines->groupBy('type')->map->sum('amount');
        return [
            'total'        => (float) $lines->sum('amount'),
            'by_type'      => $byType,
            'by_commune'   => $lines->groupBy('commune')->map->sum('amount'),
            'panels_count' => $lines->pluck('panel_id')->unique()->count(),
            'lines_count'  => $lines->count(),
            // Alias pour les consommateurs TaxController qui les attendaient.
            'odp_total'    => (float) ($byType[self::TYPE_ODP] ?? 0),
            'tm_total'     => (float) ($byType[self::TYPE_TM]  ?? 0),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  PHASE 1 (2026-06-22) — Refonte API "calcul par commune sur
    //  période [debut, fin]". Source unique pour les calculs ODP/TM
    //  panneau-par-panneau (mois d'existence × mois d'occupation),
    //  remplace l'ancien calcul "tarif × surface × nbMois" qui était
    //  faux pour 3 raisons :
    //    1. ×12 parasite (TX-1) sur les périodes annuelles
    //    2. Pas de prise en compte de la date d'installation /
    //       démantèlement (TX-cas réels)
    //    3. TM toujours comptée même si panneau jamais occupé
    //
    //  Les méthodes ci-dessous travaillent par panneau (prorata mois)
    //  et reflètent la règle métier OOH formellement (cf. brief
    //  RÈGLES MÉTIER de la mission refonte taxes).
    // ══════════════════════════════════════════════════════════════

    /**
     * ODP totale due pour une commune sur la période [debut, fin].
     * Somme par panneau : tarif_ODP × surface × (mois_existence / 12).
     */
    public function calculODPCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $rate = (float) $commune->ratesAt($debut)['odp'] ?? 0;
        if ($rate <= 0) return 0;
        $total = 0.0;
        foreach ($this->panneauxPourCalcul($commune) as $panel) {
            $surface = (float) ($panel->format?->surface ?? 0);
            if ($surface <= 0) continue;
            $moisExistence = $this->moisExistencePanneau($panel, $debut, $fin);
            if ($moisExistence === 0) continue;
            $total += $rate * $surface * ($moisExistence / 12);
        }
        return (int) round($total);
    }

    /**
     * TM totale due pour une commune sur la période [debut, fin].
     * Somme par panneau : tarif_TM × surface × (mois_occupation / 12).
     * Un panneau jamais occupé sur la période contribue 0.
     */
    public function calculTMCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $rate = (float) ($commune->ratesAt($debut)['tm'] ?? 0);
        if ($rate <= 0) return 0;
        $total = 0.0;
        foreach ($this->panneauxPourCalcul($commune) as $panel) {
            $surface = (float) ($panel->format?->surface ?? 0);
            if ($surface <= 0) continue;
            $moisOccupation = $this->moisOccupationPanneau($panel, $debut, $fin);
            if ($moisOccupation === 0) continue;
            $total += $rate * $surface * ($moisOccupation / 12);
        }
        return (int) round($total);
    }

    /**
     * Total DÛ (ODP + TM) pour une commune sur une période.
     */
    public function totalDuCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        return $this->calculODPCommune($commune, $debut, $fin)
             + $this->calculTMCommune($commune, $debut, $fin);
    }

    /**
     * Total PAYÉ par la régie à cette commune sur la période, filtré
     * STRICTEMENT par la date de versement (paid_at). Hotfix TX-6 :
     * c'est cette méthode qui évite qu'un versement 22/06/2026 apparaisse
     * dans le total payé de l'année 2021.
     */
    public function totalPayeCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $payments = CommuneTaxPayment::query()
            ->where('commune_id', $commune->id)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->get(['odp_paye', 'tm_paye']);
        return (int) $payments->sum(fn($p) => (int) $p->odp_paye + (int) $p->tm_paye);
    }

    /**
     * Solde restant = dû − payé. Peut être négatif (trop-payé).
     */
    public function soldeRestant(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        return $this->totalDuCommune($commune, $debut, $fin)
             - $this->totalPayeCommune($commune, $debut, $fin);
    }

    /**
     * Statut d'une commune sur une période : 'non_paye' | 'partiel' | 'paye'.
     */
    public function statutCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): string
    {
        $du   = $this->totalDuCommune($commune, $debut, $fin);
        $paye = $this->totalPayeCommune($commune, $debut, $fin);
        if ($paye <= 0)      return 'non_paye';
        if ($paye >= $du)    return 'paye';
        return 'partiel';
    }

    /**
     * Taux de couverture (0..100). Si rien n'est dû, on retourne 100
     * (par convention : pas de dette = "couvert" → bouton vert).
     */
    public function tauxCouverture(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $du = $this->totalDuCommune($commune, $debut, $fin);
        if ($du <= 0) return 100;
        $paye = $this->totalPayeCommune($commune, $debut, $fin);
        return min(100, (int) round(($paye / $du) * 100));
    }

    /**
     * Compte les mois calendaires d'existence d'un panneau dans une
     * période. Règle simple "1 jour dans le mois = 1 mois compté" :
     * - début effectif = max(periode.debut, panneau.created_at)
     * - fin effective  = min(periode.fin, panneau.deleted_at OR ∞)
     * - retourne 0 si fin < début (panneau hors période)
     *
     * Le panneau démantelé = panneau soft-deleted (deleted_at posé).
     * On reste compatible avec l'existant qui n'a pas de colonne
     * dismantled_at dédiée — décision validée en Phase α.
     */
    public function moisExistencePanneau(Panel $panel, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $createdAt = $panel->created_at ? Carbon::parse($panel->created_at) : null;
        $deletedAt = $panel->deleted_at ? Carbon::parse($panel->deleted_at) : null;

        $existeDepuis = $createdAt ?: $debut;
        $existeJusqua = $deletedAt;

        $debutEff = Carbon::parse($debut)->max($existeDepuis);
        $finEff   = $existeJusqua
            ? Carbon::parse($fin)->min($existeJusqua)
            : Carbon::parse($fin);

        if ($finEff->lessThan($debutEff)) return 0;

        return $this->compteMoisCalendaires($debutEff, $finEff);
    }

    /**
     * Compte les mois où un panneau a été occupé par au moins 1
     * campagne ACTIVE sur la période. Règle simple : 1 jour de campagne
     * dans le mois = 1 mois compté (alignée avec la pratique métier).
     *
     * Hotfix TM (clé règles métier) : un panneau jamais occupé contribue
     * 0 à la TM, conformément à la spec OOH ivoirienne.
     */
    public function moisOccupationPanneau(Panel $panel, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $moisOccupes = 0;
        $current = Carbon::parse($debut)->startOfMonth();
        $finC    = Carbon::parse($fin)->endOfMonth();

        while ($current->lessThanOrEqualTo($finC)) {
            $moisFinC = $current->copy()->endOfMonth();
            $aCampagne = \DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->where('campaign_panels.panel_id', $panel->id)
                ->where('campaigns.status', CampaignStatus::ACTIF->value)
                ->whereNull('campaigns.deleted_at')
                ->where('campaigns.start_date', '<=', $moisFinC->toDateString())
                ->where('campaigns.end_date',   '>=', $current->toDateString())
                ->exists();
            if ($aCampagne) $moisOccupes++;
            $current->addMonth();
        }
        return $moisOccupes;
    }

    /**
     * Compte le nombre de mois calendaires couverts par [a, b] (1 jour
     * dans un mois suffit pour compter le mois).
     */
    protected function compteMoisCalendaires(CarbonInterface $a, CarbonInterface $b): int
    {
        $a = Carbon::parse($a)->startOfMonth();
        $b = Carbon::parse($b)->startOfMonth();
        if ($b->lessThan($a)) return 0;
        return (int) ($a->diffInMonths($b)) + 1;
    }

    /**
     * Sélection des panneaux d'une commune pour le calcul fiscal.
     * Décision Phase α : on prend TOUS les panneaux internes existants
     * (exclut soft-deleted MAIS leur deleted_at est ré-utilisé via
     * withTrashed() pour les calculs au prorata "panneau démantelé en
     * juillet" — voir moisExistencePanneau).
     *
     * À ce stade pragmatique : on prend les panneaux NON soft-deleted +
     * les soft-deleted dont deleted_at est >= debut de la période (sinon
     * impossible d'avoir des mois d'existence dans la fenêtre).
     */
    protected function panneauxPourCalcul(Commune $commune)
    {
        return Panel::withTrashed()
            ->with('format:id,name,width,height,surface')
            ->where('commune_id', $commune->id)
            ->get();
    }
}
