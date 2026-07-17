<?php
// app/Services/DelayReasonsService.php

namespace App\Services;

use App\Enums\DelayReason;
use App\Models\PoseTaskAction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DelayReasonsService — source unique des stats motifs de retard / signalements.
 *
 * Consommé par :
 *   - Admin\SlaDelaysController       (page /admin/sla/retards)
 *   - Admin\RapportController         (onglet "SLA & Retards" dans /admin/rapports)
 *   - Admin\CampaignController        (bandeau retard sur fiche campagne)
 *
 * Tous les calculs prennent en compte :
 *   - Le motif effectif (= dernier amendement OU motif d'origine — cf. PoseTaskAction::effectiveMotif)
 *   - L'isResolved étendu (resolved_at OR maintenance_id) — Précision B du brief
 *   - Les filtres dimensionnels Rapports (zone, commune, client, période)
 */
class DelayReasonsService
{
    /** Tous les motifs disponibles (raccourci). */
    public function all(): array
    {
        return DelayReason::cases();
    }

    /**
     * Comptage par motif sur une période, filtres optionnels.
     * Retourne Collection<{motif:DelayReason, count:int, label, icon, color}>.
     *
     * Note : compte les signalements OUVERTS uniquement par défaut. Passer
     * $includeResolved=true pour inclure tout l'historique.
     */
    public function countByMotif(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $filters = [],
        bool $includeResolved = false,
    ): Collection {
        $q = $this->baseQuery($from, $to, $filters, $includeResolved);
        $rows = $q->get();

        // Group par motif effectif (calculé en PHP car effectiveMotif est non-SQL)
        $byMotif = [];
        foreach ($rows as $row) {
            $motif = $row->effectiveMotif();
            if (!$motif) continue;
            $byMotif[$motif->value] = ($byMotif[$motif->value] ?? 0) + 1;
        }

        return collect(DelayReason::cases())->map(fn (DelayReason $m) => [
            'motif' => $m,
            'value' => $m->value,
            'label' => $m->label(),
            'icon'  => $m->icon(),
            'color' => $m->color(),
            'count' => $byMotif[$m->value] ?? 0,
        ])->filter(fn ($r) => $r['count'] > 0)->values();
    }

    /**
     * Croisement motif × commune.
     * Retourne Collection<{motif, commune_id, commune_name, count}>.
     */
    public function crossByCommune(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $filters = [],
        bool $includeResolved = false,
    ): Collection {
        $rows = $this->baseQuery($from, $to, $filters, $includeResolved)
            ->with(['task.panel.commune:id,name,city'])
            ->get();

        $bucket = [];
        foreach ($rows as $row) {
            $motif = $row->effectiveMotif();
            $commune = $row->task?->panel?->commune;
            if (!$motif || !$commune) continue;
            $key = $motif->value . '|' . $commune->id;
            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'motif'        => $motif,
                    'motif_value'  => $motif->value,
                    'motif_label'  => $motif->label(),
                    'motif_icon'   => $motif->icon(),
                    'commune_id'   => $commune->id,
                    'commune_name' => $commune->name,
                    'commune_city' => $commune->city,
                    'count'        => 0,
                ];
            }
            $bucket[$key]['count']++;
        }
        return collect(array_values($bucket))->sortByDesc('count')->values();
    }

    /**
     * Panneaux récurrents : ≥ $thresholdCount signalements même motif sur la période.
     */
    public function recurringByPanel(
        int $thresholdCount = 2,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $filters = [],
        bool $includeResolved = true, // historique complet pour détecter récurrence
        ?DelayReason $motifFilter = null,
    ): Collection {
        $rows = $this->baseQuery($from, $to, $filters, $includeResolved)
            ->with(['task.panel:id,reference,name,commune_id', 'task.panel.commune:id,name'])
            ->get();

        $bucket = [];
        foreach ($rows as $row) {
            $motif = $row->effectiveMotif();
            $panel = $row->task?->panel;
            if (!$motif || !$panel) continue;
            if ($motifFilter && $motif !== $motifFilter) continue;
            $key = $panel->id . '|' . $motif->value;
            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'panel_id'        => $panel->id,
                    'panel_reference' => $panel->reference,
                    'panel_name'      => $panel->name,
                    'commune_name'    => $panel->commune?->name,
                    'motif'           => $motif,
                    'motif_value'     => $motif->value,
                    'motif_label'     => $motif->label(),
                    'motif_icon'      => $motif->icon(),
                    'count'           => 0,
                ];
            }
            $bucket[$key]['count']++;
        }

        return collect(array_values($bucket))
            ->filter(fn ($r) => $r['count'] >= $thresholdCount)
            ->sortByDesc('count')
            ->values();
    }

    /** Motifs des signalements ouverts pour une campagne — utilisé par le bandeau. */
    public function topMotifsForCampaign(int $campaignId): Collection
    {
        $rows = PoseTaskAction::query()
            ->where('action', PoseTaskAction::ACTION_PROBLEM_REPORTED)
            ->whereHas('task', fn ($q) => $q->where('campaign_id', $campaignId))
            ->get()
            ->filter(fn ($a) => $a->isOpen());

        $bucket = [];
        foreach ($rows as $row) {
            $motif = $row->effectiveMotif();
            if (!$motif) continue;
            $bucket[$motif->value] = ($bucket[$motif->value] ?? 0) + 1;
        }
        return collect($bucket)
            ->map(fn ($count, $key) => [
                'motif' => DelayReason::from($key),
                'count' => $count,
            ])
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Crée un amendement de motif a posteriori.
     * Ne touche PAS le signalement original — log une nouvelle PoseTaskAction
     * action='motif_modified' avec payload {target_action_id, from, to,
     * reason_text, modified_by_id, modified_by_name}.
     *
     * @throws \DomainException si justification < 10 chars ou motif identique.
     */
    public function amend(
        PoseTaskAction $original,
        DelayReason $newMotif,
        string $reasonText,
        User $modifier,
    ): PoseTaskAction {
        if ($original->action !== PoseTaskAction::ACTION_PROBLEM_REPORTED) {
            throw new \DomainException('Seuls les signalements problem_reported peuvent être amendés.');
        }
        $reasonText = trim($reasonText);
        if (mb_strlen($reasonText) < 10) {
            throw new \DomainException('La justification doit faire au moins 10 caractères.');
        }
        $current = $original->effectiveMotif();
        if ($current === $newMotif) {
            throw new \DomainException('Le nouveau motif est identique au motif courant.');
        }

        return PoseTaskAction::create([
            'pose_task_id' => $original->pose_task_id,
            'action'       => PoseTaskAction::ACTION_MOTIF_MODIFIED,
            'payload'      => [
                'target_action_id' => $original->id,
                'from'             => $current?->value,
                'to'               => $newMotif->value,
                'reason_text'      => $reasonText,
                'modified_by_id'   => $modifier->id,
                'modified_by_name' => $modifier->name,
            ],
            'actor'      => $modifier->name,
            'created_at' => now(),
        ]);
    }

    /**
     * Synthèse complète pour la page /admin/sla/retards + onglet Rapports.
     * Renvoie un tableau exploitable directement par les vues.
     */
    public function stats(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $filters = [],
    ): array {
        $byMotifOpen     = $this->countByMotif($from, $to, $filters, includeResolved: false);
        $byMotifAll      = $this->countByMotif($from, $to, $filters, includeResolved: true);
        $crossCommune    = $this->crossByCommune($from, $to, $filters, includeResolved: false);
        $recurring       = $this->recurringByPanel(2, $from, $to, $filters);

        $totalOpen     = $byMotifOpen->sum('count');
        $totalAll      = $byMotifAll->sum('count');
        $totalResolved = $totalAll - $totalOpen;
        $dominant      = $byMotifOpen->sortByDesc('count')->first();

        return [
            'kpi' => [
                'total_all'       => $totalAll,
                'total_open'      => $totalOpen,
                'total_resolved'  => $totalResolved,
                'dominant_motif'  => $dominant ? $dominant['motif'] : null,
                'dominant_count'  => $dominant ? $dominant['count'] : 0,
                'recurring_count' => $recurring->count(),
            ],
            'by_motif_open' => $byMotifOpen,
            'by_motif_all'  => $byMotifAll,
            'cross_commune' => $crossCommune,
            'recurring'     => $recurring,
        ];
    }

    /**
     * Base query : PoseTaskAction.action = problem_reported, filtrée par
     * période (created_at) + filtres dimensionnels via task.panel/campaign.
     *
     * $filters supporté : commune_id, city, client_id, category_id, zone
     * (= mêmes clés que DashboardKpiService::setFilters).
     */
    protected function baseQuery(
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        array $filters,
        bool $includeResolved,
    ): Builder {
        $q = PoseTaskAction::query()
            ->where('action', PoseTaskAction::ACTION_PROBLEM_REPORTED);

        if ($from) $q->where('created_at', '>=', $from);
        if ($to)   $q->where('created_at', '<=', $to);

        // Filtres dimensionnels — traversent task → panel/commune ou campaign/client
        if (!empty($filters['commune_id']) || !empty($filters['city']) || !empty($filters['category_id']) || !empty($filters['zone'])) {
            $q->whereHas('task.panel', function ($p) use ($filters) {
                if (!empty($filters['commune_id']))  $p->where('commune_id', $filters['commune_id']);
                if (!empty($filters['category_id'])) $p->where('category_id', $filters['category_id']);
                if (!empty($filters['city']))        $p->whereHas('commune', fn ($c) => $c->where('city', $filters['city']));
                // 2026-07-16 : règle officielle (whitelist Commune) au lieu de city='Abidjan'
                if (($filters['zone'] ?? null) === 'abidjan')   $p->whereIn('commune_id', \App\Models\Commune::abidjanIds());
                if (($filters['zone'] ?? null) === 'interieur') $p->whereNotIn('commune_id', \App\Models\Commune::abidjanIds());
            });
        }
        if (!empty($filters['client_id'])) {
            $q->whereHas('task.campaign', fn ($c) => $c->where('client_id', $filters['client_id']));
        }

        // includeResolved=false → SQL pré-filtre (perf), PHP re-vérifie isResolved
        // pour le cas maintenance_id sans resolved_at (Précision B).
        if (!$includeResolved) {
            $q->whereNull('resolved_at')->whereNull('maintenance_id');
        }

        return $q;
    }
}
