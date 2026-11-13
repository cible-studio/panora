<?php
namespace App\Services;

use App\Enums\PanelStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\BookingConflictException;
use App\Models\ExternalPanel;
use App\Models\Panel;
use App\Models\ReservationPanel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AvailabilityService — Source de vérité unique pour la disponibilité des panneaux.
 *
 * RÈGLE FONDAMENTALE :
 * Un panneau est BLOQUÉ si reservation_panels contient une ligne
 * liée à une réservation (en_attente OU confirme) dont la période
 * chevauche la période demandée.
 *
 * panels.status = cache de lecture — JAMAIS utilisé pour décider
 * de la disponibilité réelle.
 *
 * Depuis le 2026-11-11, il est aussi bloqué pour les AUTRES clients quand
 * le renouvellement de sa campagne est confirmé : du lendemain de la fin
 * jusqu'à 30 jours après (RenouvellementService::retenues()).
 *
 * ─── ANTI DOUBLE-BOOKING (pattern d'usage) ──────────────────────────────
 * À CHAQUE création/ajout de panneau qui pourrait entrer en conflit, le
 * code appelant DOIT respecter ce pattern (verrouillage pessimiste) :
 *
 *   DB::transaction(function () use ($panelIds, ...) {
 *       Panel::whereIn('id', $panelIds)->lockForUpdate()->get();    // ① verrou
 *       $conflicts = $availability->getUnavailablePanelIds(...);     // ② re-check
 *       if (!empty($conflicts)) { throw new RuntimeException(...); } // ③ rollback
 *       // ... attach + create
 *   });
 *
 * Le verrou pessimiste (SELECT ... FOR UPDATE) bloque toute autre transaction
 * concurrente qui tenterait `lockForUpdate()` sur les mêmes panneaux jusqu'au
 * COMMIT/ROLLBACK. Combiné au re-check après verrou, on a une garantie forte :
 *   - Avec 2 utilisateurs simultanés sur le même panneau, 1 seul peut commiter.
 *   - Le second voit `getUnavailablePanelIds()` détecter le conflit et rollback.
 *
 * Niveau d'isolation requis : REPEATABLE READ (défaut MySQL InnoDB) ou supérieur.
 * Sous READ COMMITTED, le re-check pourrait passer entre 2 transactions concurrentes.
 *
 * Index DB optimal pour la requête de chevauchement (cf. migration
 * 2026_05_01_120000_add_overlap_index_for_anti_double_booking) :
 *   `idx_reservations_overlap (status, start_date, end_date)`
 * ────────────────────────────────────────────────────────────────────────
 */
class AvailabilityService
{
    /**
     * Axes multi-pieds (2026-11-11). Dépendance facultative : les 22
     * appelants passent par le conteneur, mais un `new AvailabilityService()`
     * doit continuer de fonctionner.
     */
    public function __construct(private ?\App\Services\Axes\AxeService $axes = null) {}

    private function axes(): \App\Services\Axes\AxeService
    {
        return $this->axes ??= new \App\Services\Axes\AxeService();
    }

    // ══════════════════════════════════════════════════════════════
    // RETENUE DES RENOUVELLEMENTS CONFIRMÉS (2026-11-11)
    //
    // Après « Renouvellement confirmé », les panneaux de la campagne sont
    // retenus pour SON client du lendemain de la fin jusqu'à 30 jours
    // après. La règle (période, statuts, axes) vit dans
    // RenouvellementService::retenues() ; ici on l'applique au contrôle.
    //
    // Le paramètre $clientId des méthodes de décision : le client pour
    // lequel on veut prendre le panneau. Son propre renouvellement ne le
    // bloque pas. null = client inconnu → la retenue bloque (prudence).
    // ══════════════════════════════════════════════════════════════

    /** Mémorisé sur l'instance (cf. AxeService::colonnesPresentes). */
    private ?bool $tableRenouvellements = null;

    /**
     * Retenues sur la période, client par client.
     *
     * @param  bool $avecDemandes  inclure « Veut renouveler » (information seule)
     * @return array<int, array>  panel_id => retenue (cf. RenouvellementService::retenues)
     */
    public function retenuesRenouvellement(array $panelIds, string $startDate, string $endDate, bool $avecDemandes = false): array
    {
        $panelIds = array_values(array_filter(array_map('intval', $panelIds)));
        if (empty($panelIds)) return [];

        $this->tableRenouvellements ??= \Illuminate\Support\Facades\Schema::hasTable('renouvellements');
        if (!$this->tableRenouvellements) return [];

        $statuts = [\App\Models\Renouvellement::CONFIRME];
        if ($avecDemandes) {
            $statuts[] = \App\Models\Renouvellement::RENOUVELER;
        }

        try {
            return \App\Services\Diffusion\RenouvellementService::retenues($panelIds, $startDate, $endDate, $statuts);
        } catch (\Throwable $e) {
            // Même choix que le catalogue : une erreur ici ne doit pas
            // empêcher toute réservation. Elle est tracée.
            Log::error('availability.retenues_renouvellement', ['erreur' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * AXE ENTIER → PIEDS LIBRES (règle validée le 2026-11-12, point 1a).
     *
     * Les contrats signés avant le découpage tiennent la LIGNE d'un axe,
     * c'est-à-dire tous ses pieds d'un coup. Reprendre cette ligne telle
     * quelle (renouvellement, import) revendrait les pieds déjà pris par un
     * autre client sur la période — le contrôle ne les voit pas, puisqu'il
     * interroge la ligne d'axe. On la remplace donc par ses pieds encore
     * libres, et on dit lesquels manquent.
     *
     * @return array{ids: int[], axes: list<array{axe: string, total: int, repris: int, pris: list<string>}>}
     */
    public function remplacerAxesParPiedsLibres(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $clientId,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null,
    ): array {
        $panelIds = array_values(array_unique(array_filter(array_map('intval', $panelIds))));
        if (!$panelIds || !$this->axes()->colonnesPresentes()) {
            return ['ids' => $panelIds, 'axes' => []];
        }

        $axes = Panel::whereIn('id', $panelIds)->where('est_axe', true)->get(['id', 'reference']);
        if ($axes->isEmpty()) {
            return ['ids' => $panelIds, 'axes' => []];
        }

        $ids   = array_values(array_diff($panelIds, $axes->pluck('id')->all()));
        $bilan = [];
        foreach ($axes as $axe) {
            $pieds = Panel::where('axe_id', $axe->id)->orderBy('pied_numero')->pluck('reference', 'id');
            if ($pieds->isEmpty()) {
                // Axe sans pieds (découpage pas joué) : on garde la ligne.
                $ids[] = (int) $axe->id;
                continue;
            }

            $bloques = $this->getUnavailablePanelIds(
                $pieds->keys()->all(), $startDate, $endDate, $excludeReservationId, $excludeCampaignId, $clientId
            );
            $motifs = $bloques
                ? $this->getInternalConflictMap($bloques, $startDate, $endDate, $excludeReservationId, $excludeCampaignId, $clientId)
                : collect();

            $pris = [];
            foreach ($pieds as $pid => $ref) {
                if (in_array((int) $pid, $bloques, true)) {
                    $m = $motifs->get($pid);
                    $pris[] = $ref . ($m && $m->conflicting_ref ? ' (' . $m->conflicting_ref . ')' : '');
                } else {
                    $ids[] = (int) $pid;
                }
            }

            $bilan[] = [
                'axe'    => $axe->reference,
                'total'  => $pieds->count(),
                'repris' => $pieds->count() - count($pris),
                'pris'   => $pris,
            ];
        }

        return ['ids' => array_values(array_unique($ids)), 'axes' => $bilan];
    }

    /** « CDYLUP-001 : 6 pieds sur 10 repris, 4 déjà pris (P02 (RES-12), …) ». */
    public static function messageAxesRemplaces(array $axes): ?string
    {
        $parts = [];
        foreach ($axes as $a) {
            $parts[] = $a['pris']
                ? "{$a['axe']} : {$a['repris']} pied(s) sur {$a['total']} repris, " . count($a['pris']) . ' déjà pris (' . implode(', ', $a['pris']) . ')'
                : "{$a['axe']} : les {$a['total']} pieds repris un par un";
        }

        return $parts ? 'Axes : ' . implode(' · ', $parts) . '.' : null;
    }

    /**
     * Panneaux retenus par le renouvellement confirmé d'un AUTRE client.
     *
     * @return array<int, array>  panel_id => retenue
     */
    public function retenuesPourAutreClient(array $panelIds, string $startDate, string $endDate, ?int $clientId): array
    {
        return array_filter(
            $this->retenuesRenouvellement($panelIds, $startDate, $endDate),
            fn (array $r) => $clientId === null || $r['client_id'] !== $clientId
        );
    }

    // Statuts qui bloquent un panneau — source unique
    public const BLOCKING_STATUSES = [
        ReservationStatus::EN_ATTENTE->value,
        ReservationStatus::CONFIRME->value,
    ];

    // ── 1. Vérifier UN panneau ────────────────────────────────────
    public function isPanelAvailable(
        int    $panelId,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): bool {
        return empty($this->getUnavailablePanelIds(
            [$panelId], $startDate, $endDate, $excludeReservationId
        ));
    }

    /**
     * FILET FINAL — lève BookingConflictException avec détails si conflit.
     *
     * À appeler JUSTE AVANT tout insert dans reservation_panels (transaction
     * en cours). Sécurise les chemins qui contournent la pré-validation UI.
     * Détaille chaque panneau conflictuel (source, ref bloqueuse, date de
     * libération) pour que le frontend affiche un message exploitable.
     *
     * @throws BookingConflictException
     */
    public function assertCanReserve(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null,
        ?int   $clientId             = null,
    ): void {
        if (empty($panelIds)) return;

        $conflicts = $this->getInternalConflictMap(
            $panelIds, $startDate, $endDate, $excludeReservationId, $excludeCampaignId, $clientId
        );

        if ($conflicts->isEmpty()) return;

        $refs = Panel::whereIn('id', $conflicts->keys()->all())->pluck('reference', 'id');
        $details = [];
        foreach ($conflicts as $pid => $c) {
            $rd = $c->release_date ? \Carbon\Carbon::parse($c->release_date) : null;
            $details[] = [
                'panel_id'         => $pid,
                'reference'        => $refs[$pid] ?? null,
                'blocking_source'  => self::sourceDuBlocage($c->blocking_status),
                'blocking_ref'     => $c->conflicting_ref,
                'release_date'     => $rd?->format('d/m/Y'),
                'next_free'        => $rd?->copy()->addDay()->format('d/m/Y'),
            ];
        }

        // Log pour traçabilité — on doit savoir si ce filet se déclenche
        // souvent (= la pré-validation UI laisse passer trop de cas).
        Log::warning('availability.assertCanReserve.conflict', [
            'panel_ids'          => $panelIds,
            'period'             => "$startDate → $endDate",
            'conflicts_count'    => count($details),
            'exclude_resa_id'    => $excludeReservationId,
            'exclude_camp_id'    => $excludeCampaignId,
        ]);

        throw new BookingConflictException($details);
    }

    /**
     * blocking_status de getInternalConflictMap() → blocking_source des
     * conflits (BookingConflictException, conflitsFermes).
     */
    public static function sourceDuBlocage(?string $blockingStatus): string
    {
        return match ($blockingStatus) {
            'campagne_active' => 'campaign',
            'renouvellement'  => 'renouvellement',
            default           => 'reservation',
        };
    }

    /**
     * Panneaux déjà pris FERMEMENT sur la période (2026-10-30) : réservation
     * confirmée ou campagne planifiée / active / en pause. Une option (en
     * attente) ne compte pas : plusieurs options peuvent coexister, la
     * première confirmée gagne (cf. PropositionService::confirmer).
     *
     * Règle validée par la direction le 2026-10-30 — refus si un panneau est
     * déjà pris fermement, au moment de :
     *   · confirmer une réservation (fiche, action en masse) ;
     *   · accepter une proposition (le client) ;
     *   · importer une campagne depuis un PDF.
     * Exclure la réservation concernée ET sa campagne liée, sinon le dossier
     * se trouve en conflit avec lui-même (cf. TECHNICAL_DEBT 2026-10-23).
     *
     * @return list<array{panel_id: int, reference: ?string, blocking_source: string, blocking_ref: string, release_date: ?string, next_free: ?string}>
     */
    public function conflitsFermes(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null,
        ?int   $clientId             = null,
    ): array {
        $panelIds = array_values(array_filter(array_map('intval', $panelIds)));
        if (empty($panelIds)) return [];

        // Axes multi-pieds (2026-11-11) : un pied est pris fermement si son
        // axe l'est. Cf. App\Services\Axes\AxeService.
        $axeParPied     = $this->axes()->axeParPied($panelIds);
        $idsAInterroger = $this->axes()->avecAxesParents($panelIds);

        $reservations = DB::table('reservation_panels as rp')
            ->join('reservations as r', 'r.id', '=', 'rp.reservation_id')
            ->leftJoin('clients as cl', 'cl.id', '=', 'r.client_id')
            ->whereIn('rp.panel_id', $idsAInterroger)
            ->where('r.status', ReservationStatus::CONFIRME->value)
            ->whereNull('r.deleted_at')
            ->where('r.start_date', '<=', $endDate)
            ->where('r.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) => $q->where('r.id', '!=', $excludeReservationId))
            ->get(['rp.panel_id', 'r.reference as dossier', 'cl.name as client', 'r.end_date']);

        $campagnes = DB::table('campaign_panels as cp')
            ->join('campaigns as c', 'c.id', '=', 'cp.campaign_id')
            ->leftJoin('clients as cl', 'cl.id', '=', 'c.client_id')
            ->whereIn('cp.panel_id', $idsAInterroger)
            ->where('cp.type', 'interne')
            ->whereIn('c.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->whereNull('c.deleted_at')
            ->where('c.start_date', '<=', $endDate)
            ->where('c.end_date',   '>=', $startDate)
            ->when($excludeCampaignId, fn($q) => $q->where('c.id', '!=', $excludeCampaignId))
            ->get(['cp.panel_id', 'c.name as dossier', 'cl.name as client', 'c.end_date']);

        // Un panneau, un motif : la campagne d'abord (engagement signé).
        $parPanneau = [];
        foreach ([['campaign', $campagnes], ['reservation', $reservations]] as [$source, $lignes]) {
            foreach ($lignes as $l) {
                $parPanneau[(int) $l->panel_id] ??= ['source' => $source, 'ligne' => $l];
            }
        }

        // Le motif de l'axe retombe sur ses pieds, puis on ne garde que les
        // panneaux réellement demandés (pas les axes interrogés au passage).
        $parPanneau = $this->axes()->reporterMotifsDesAxes($parPanneau, $axeParPied);
        $parPanneau = array_intersect_key($parPanneau, array_flip($panelIds));

        // Puis la retenue d'un renouvellement confirmé d'un autre client
        // (2026-11-11). Les axes sont déjà traités par la règle.
        foreach ($this->retenuesPourAutreClient($panelIds, $startDate, $endDate, $clientId) as $pid => $r) {
            $parPanneau[$pid] ??= ['source' => 'renouvellement', 'ligne' => (object) [
                'dossier'  => $r['client'] ?? 'un autre client',
                'client'   => null,
                'end_date' => $r['jusqu_au'],
            ]];
        }
        if (!$parPanneau) return [];

        $refs = Panel::whereIn('id', array_keys($parPanneau))->pluck('reference', 'id');
        $conflits = [];
        foreach ($parPanneau as $pid => $motif) {
            ['source' => $source, 'ligne' => $l] = $motif;
            $fin = $l->end_date ? \Carbon\Carbon::parse($l->end_date) : null;

            // Un pied bloqué par son axe : le dire, sinon l'utilisateur
            // cherche en vain quelle campagne occupe CE pied.
            $dossier = trim($l->dossier . ($l->client ? ' — ' . $l->client : ''));
            if (!empty($motif['herite_de_axe'])) {
                $dossier .= ' — axe vendu en entier';
            }

            $conflits[] = [
                'panel_id'        => $pid,
                'reference'       => $refs[$pid] ?? null,
                'blocking_source' => $source,
                'blocking_ref'    => $dossier,
                'release_date'    => $fin?->format('d/m/Y'),
                'next_free'       => $fin?->copy()->addDay()->format('d/m/Y'),
            ];
        }

        return $conflits;
    }

    /**
     * Refus si un panneau est déjà pris fermement (cf. conflitsFermes()).
     * Message : « Panneau(x) déjà pris sur cette période : P-001 (campagne
     * Lancement — ORANGE CI), P-002 (réservation RES-12 — MOOV). »
     *
     * @throws BookingConflictException
     */
    public function assertLibresFermement(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null,
        ?int   $clientId             = null,
    ): void {
        $conflits = $this->conflitsFermes($panelIds, $startDate, $endDate, $excludeReservationId, $excludeCampaignId, $clientId);
        if (!$conflits) return;

        throw new BookingConflictException($conflits, self::messageConflitsFermes($conflits));
    }

    /** Texte commun du refus (écrans, fenêtres, imports). */
    public static function messageConflitsFermes(array $conflits): string
    {
        $parts = array_map(fn ($c) => ($c['reference'] ?? '#' . $c['panel_id'])
            . ' (' . self::motifDuConflit($c) . ')', $conflits);

        return (count($conflits) > 1 ? 'Panneaux déjà pris' : 'Panneau déjà pris') . ' sur cette période : ' . implode(', ', $parts) . '.';
    }

    /**
     * « campagne Lancement — MOOV », « réservation RES-12 — ORANGE »,
     * « retenu pour le renouvellement de BEM » (2026-11-11).
     */
    public static function motifDuConflit(array $c): string
    {
        return match ($c['blocking_source'] ?? null) {
            'campaign'       => 'campagne ' . $c['blocking_ref'],
            'renouvellement' => 'retenu pour le renouvellement de ' . $c['blocking_ref'],
            default          => 'réservation ' . $c['blocking_ref'],
        };
    }

    // Statuts de CAMPAGNE qui bloquent un panneau. Une fois la résa
    // confirmée et la campagne créée, c'est cette dernière qui porte
    // l'engagement sur la période. Sans inclure ce check, on perd la
    // trace après confirmation (bug critique de double-booking).
    public const BLOCKING_CAMPAIGN_STATUSES = [
        'planifie',
        'actif',
        'pause',  // pause = engagement maintenu, retour possible
    ];

    // ── 2. IDs bloqués parmi une liste — requête optimisée ────────
    public function getUnavailablePanelIds(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null,
        ?int   $clientId             = null
    ): array {
        if (empty($panelIds)) return [];

        // ── Axes multi-pieds (2026-11-11) ───────────────────────────
        // Un pied n'est pas libre tant que SON AXE est vendu en entier :
        // les contrats signés avant le découpage portent sur la ligne de
        // l'axe. Sans ce report, on revendrait un axe déjà vendu.
        // Règle : App\Services\Axes\AxeService.
        $axeParPied     = $this->axes()->axeParPied($panelIds);
        $idsAInterroger = $this->axes()->avecAxesParents($panelIds);

        // Check 1 — Réservations actives (en_attente / confirme) qui chevauchent
        $fromReservations = ReservationPanel::select('reservation_panels.panel_id')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $idsAInterroger)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            // Chevauchement INCLUSIF : end_date est compté comme journée occupée
            // (cohérent avec la facturation jusqu'au end_date inclus). Deux
            // réservations 1→5 et 5→10 chevauchent sur le 5.
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->distinct()
            ->pluck('reservation_panels.panel_id')
            ->toArray();

        // Check 2 — Campagnes (planifiée / active / pause) qui chevauchent
        // Une résa peut être confirmée mais sa campagne pas encore active —
        // ou la résa peut transiter (ex: TERMINE) tandis que la campagne
        // reste engagée. Sans ce 2e check, le panneau apparaît libre alors
        // qu'il est déjà attribué à une campagne contractuellement actée.
        $fromCampaigns = DB::table('campaign_panels')
            ->select('campaign_panels.panel_id')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->whereIn('campaign_panels.panel_id', $idsAInterroger)
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('campaigns.start_date', '<=', $endDate)
            ->where('campaigns.end_date',   '>=', $startDate)
            ->whereNull('campaigns.deleted_at')
            ->when($excludeCampaignId, fn($q) =>
                $q->where('campaigns.id', '!=', $excludeCampaignId)
            )
            ->distinct()
            ->pluck('campaign_panels.panel_id')
            ->toArray();

        // Le blocage d'un axe retombe sur ses pieds ; un axe interrogé par
        // ricochet ne ressort pas si l'appelant ne l'avait pas demandé.
        $bloques = $this->axes()->reporterBlocageDesAxes(
            array_merge($fromReservations, $fromCampaigns),
            $panelIds,
            $axeParPied,
        );

        // Check 3 — retenue d'un renouvellement confirmé d'un autre client
        // (2026-11-11), cf. retenuesPourAutreClient().
        $retenus = array_keys($this->retenuesPourAutreClient($panelIds, $startDate, $endDate, $clientId));

        return $retenus ? array_values(array_unique(array_merge($bloques, $retenus))) : $bloques;
    }

    // ── 2.b — Map panel_id → ['end_date'=>Carbon, 'source'=>'reservation|campaign']
    // Utilisé pour suggérer "Libre à partir du XX/XX" en UI quand un
    // panneau est partiellement occupé sur la période demandée.
    public function getOccupationDetails(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null
    ): array {
        if (empty($panelIds)) return [];

        // Axes multi-pieds (2026-11-11) : « libre à partir du … » d'un pied
        // doit tenir compte du contrat qui porte sur son axe entier.
        $axeParPied     = $this->axes()->axeParPied($panelIds);
        $idsAInterroger = $this->axes()->avecAxesParents($panelIds);

        $details = [];

        // Réservations qui occupent
        $rResults = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $idsAInterroger)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->select('reservation_panels.panel_id',
                     'reservations.id as res_id',
                     'reservations.reference',
                     'reservations.start_date',
                     'reservations.end_date',
                     'reservations.status')
            ->get();

        foreach ($rResults as $r) {
            $pid = (int) $r->panel_id;
            $end = \Carbon\Carbon::parse($r->end_date);
            if (!isset($details[$pid]) || $end->gt($details[$pid]['end_date'])) {
                $details[$pid] = [
                    'end_date'  => $end,
                    'next_free' => $end->copy()->addDay(),
                    'source'    => 'reservation',
                    'reference' => $r->reference,
                    'status'    => $r->status,
                ];
            }
        }

        // Campagnes qui occupent
        $cResults = DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->whereIn('campaign_panels.panel_id', $idsAInterroger)
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('campaigns.start_date', '<=', $endDate)
            ->where('campaigns.end_date',   '>=', $startDate)
            ->whereNull('campaigns.deleted_at')
            ->when($excludeCampaignId, fn($q) =>
                $q->where('campaigns.id', '!=', $excludeCampaignId)
            )
            ->select('campaign_panels.panel_id',
                     'campaigns.id as camp_id',
                     'campaigns.name',
                     'campaigns.start_date',
                     'campaigns.end_date',
                     'campaigns.status')
            ->get();

        foreach ($cResults as $c) {
            $pid = (int) $c->panel_id;
            $end = \Carbon\Carbon::parse($c->end_date);
            if (!isset($details[$pid]) || $end->gt($details[$pid]['end_date'])) {
                $details[$pid] = [
                    'end_date'  => $end,
                    'next_free' => $end->copy()->addDay(),
                    'source'    => 'campaign',
                    'reference' => $c->name,
                    'status'    => $c->status,
                ];
            }
        }

        // Le pied reprend la date de libération de son axe si elle est plus
        // tardive que la sienne, puis les axes interrogés au passage sont
        // retirés : l'appelant ne les avait pas demandés.
        foreach ($axeParPied as $piedId => $axeId) {
            if (! isset($details[$axeId])) {
                continue;
            }
            if (! isset($details[$piedId])
                || $details[$axeId]['end_date']->gt($details[$piedId]['end_date'])) {
                $details[(int) $piedId] = $details[$axeId];
            }
        }

        return array_intersect_key($details, array_flip(array_map('intval', $panelIds)));
    }

    // ── 3. Panneaux disponibles avec données de libération ────────
    public function getAvailablePanels(
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        array  $filters = []
    ): Collection {
        // Sous-requête : IDs bloqués via RÉSERVATIONS (overlap inclusif)
        $blockedByReservations = ReservationPanel::select('panel_id')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            );

        // Sous-requête : IDs bloqués via CAMPAGNES — indispensable car
        // après confirmation d'une résa et création de campagne, c'est
        // cette dernière qui porte l'engagement actif. Sans ce filtre,
        // le panneau réapparaît "disponible" sur la dispo.
        $blockedByCampaigns = DB::table('campaign_panels')
            ->select('panel_id')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('campaigns.start_date', '<=', $endDate)
            ->where('campaigns.end_date',   '>=', $startDate)
            ->whereNull('campaigns.deleted_at');

        $query = Panel::whereNotIn('id', $blockedByReservations)
            ->whereNotIn('id', $blockedByCampaigns)
            // Axes multi-pieds (2026-11-11) : une ligne d'axe ne se vend
            // pas — sinon l'axe ET ses pieds seraient proposés deux fois.
            ->vendables()
            // Et un pied suit son axe : tant que l'axe est vendu en entier,
            // aucun de ses pieds n'est libre. `whereNotIn` seul exclurait
            // les panneaux normaux (axe_id NULL → NOT IN rend NULL), d'où
            // la parenthèse.
            ->where(fn ($q) => $q
                ->whereNull('axe_id')
                ->orWhere(fn ($q2) => $q2
                    ->whereNotIn('axe_id', $blockedByReservations)
                    ->whereNotIn('axe_id', $blockedByCampaigns)
                )
            )
            ->where('status', '!=', PanelStatus::MAINTENANCE->value)
            ->whereNull('deleted_at')
            ->with(['commune:id,name', 'format:id,name,width,height', 'zone:id,name']);

        if ($filters['commune_id'] ?? null) {
            $query->where('commune_id', (int)$filters['commune_id']);
        }
        if ($filters['zone_id'] ?? null) {
            $query->where('zone_id', (int)$filters['zone_id']);
        }
        if ($filters['format_id'] ?? null) {
            $query->where('format_id', (int)$filters['format_id']);
        }
        if (($filters['format_width'] ?? null) && ($filters['format_height'] ?? null)) {
            $query->whereHas('format', fn($q) =>
                $q->whereBetween('width',  [(float)$filters['format_width']  - 0.01, (float)$filters['format_width']  + 0.01])
                  ->whereBetween('height', [(float)$filters['format_height'] - 0.01, (float)$filters['format_height'] + 0.01])
            );
        }

        return $query->orderBy('reference')->get();
    }    

    // ── 5. Sync statuts — transaction atomique, 4 UPDATE max ──────
    public function syncPanelStatuses(array $panelIds): void
    {
        if (empty($panelIds)) return;

        $today = now()->toDateString();

        // Axes multi-pieds (2026-11-11) : le statut est le cache lu par
        // tous les badges d'inventaire. Un pied dont l'axe est vendu en
        // entier doit porter le statut de son axe, sinon l'écran affiche
        // « libre » un pied qui ne l'est pas.
        $axeParPied     = $this->axes()->axeParPied($panelIds);
        $idsAInterroger = $this->axes()->avecAxesParents($panelIds);

        // Réservations actives (en_attente / confirme) qui couvrent today
        $activeBookings = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $idsAInterroger)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.end_date', '>=', $today)
            ->select(
                'reservation_panels.panel_id',
                DB::raw('MAX(CASE WHEN reservations.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(CASE WHEN reservations.status = "en_attente" THEN 1 ELSE 0 END) as has_option')
            )
            ->groupBy('reservation_panels.panel_id')
            ->get()
            ->keyBy('panel_id');

        // Lot 6 : campagnes actuellement EN COURS (actif / pose) couvrant today.
        // Un panneau réellement affiché = statut "occupé" (≠ "confirme" qui
        // signifie booké mais pas encore en pose). Cette nouvelle requête
        // permet de propager l'occupation effective dans l'inventaire.
        $occupiedByCampaign = DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->whereIn('campaign_panels.panel_id', $idsAInterroger)
            ->where('campaign_panels.type', 'interne')
            ->where('campaigns.status', 'actif')
            ->where('campaigns.start_date', '<=', $today)
            ->where('campaigns.end_date',   '>=', $today)
            ->pluck('campaign_panels.panel_id')
            ->flip();

        // Report axe → pieds, avant le calcul de priorité ci-dessous.
        foreach ($axeParPied as $piedId => $axeId) {
            if ($occupiedByCampaign->has($axeId)) {
                $occupiedByCampaign->put((int) $piedId, true);
            }
            if ($activeBookings->has($axeId) && ! $activeBookings->has($piedId)) {
                $activeBookings->put((int) $piedId, $activeBookings->get($axeId));
            }
        }

        // Maintenance → intouchable par la sync (l'admin contrôle manuellement)
        $maintenanceIds = Panel::whereIn('id', $panelIds)
            ->where('status', PanelStatus::MAINTENANCE->value)
            ->pluck('id')
            ->flip();

        $toOccupe   = [];
        $toConfirme = [];
        $toOption   = [];
        $toLibre    = [];

        // Priorité : maintenance > occupe (campagne en cours) > confirme
        // (réservation confirmée pour plus tard) > option > libre.
        foreach ($panelIds as $id) {
            if (isset($maintenanceIds[$id])) continue;

            if (isset($occupiedByCampaign[$id])) {
                $toOccupe[] = $id;
                continue;
            }

            $booking = $activeBookings->get($id);
            if ($booking?->has_confirmed) {
                $toConfirme[] = $id;
            } elseif ($booking?->has_option) {
                $toOption[]   = $id;
            } else {
                $toLibre[]    = $id;
            }
        }

        // 4 UPDATE groupés max — atomique
        DB::transaction(function () use ($toOccupe, $toConfirme, $toOption, $toLibre) {
            if (!empty($toOccupe)) {
                Panel::whereIn('id', $toOccupe)
                    ->update(['status' => PanelStatus::OCCUPE->value]);
            }
            if (!empty($toConfirme)) {
                Panel::whereIn('id', $toConfirme)
                    ->update(['status' => PanelStatus::CONFIRME->value]);
            }
            if (!empty($toOption)) {
                Panel::whereIn('id', $toOption)
                    ->update(['status' => PanelStatus::OPTION->value]);
            }
            if (!empty($toLibre)) {
                Panel::whereIn('id', $toLibre)
                    ->update(['status' => PanelStatus::LIBRE->value]);
            }
        });

        Log::debug('availability.sync_done', [
            'occupe'   => count($toOccupe),
            'confirme' => count($toConfirme),
            'option'   => count($toOption),
            'libre'    => count($toLibre),
        ]);
    }

    // ── 5b. Sync statuts EXTERNES — même règle, table external_panels ──────
    public function syncExternalPanelStatuses(array $externalPanelIds): void
    {
        if (empty($externalPanelIds)) return;

        $today = now()->toDateString();

        $activeBookings = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.external_panel_id', $externalPanelIds)
            ->where('reservation_panels.source', 'externe')
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.end_date', '>=', $today)
            ->select(
                'reservation_panels.external_panel_id',
                DB::raw('MAX(CASE WHEN reservations.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(CASE WHEN reservations.status = "en_attente" THEN 1 ELSE 0 END) as has_option')
            )
            ->groupBy('reservation_panels.external_panel_id')
            ->get()
            ->keyBy('external_panel_id');

        // Maintenance → intouchable
        $maintenanceIds = ExternalPanel::whereIn('id', $externalPanelIds)
            ->where('availability_status', 'maintenance')
            ->pluck('id')
            ->flip();

        $toConfirme = [];
        $toOption   = [];
        $toLibre    = [];

        foreach ($externalPanelIds as $id) {
            if (isset($maintenanceIds[$id])) continue;

            $booking = $activeBookings->get($id);
            if ($booking?->has_confirmed) {
                $toConfirme[] = $id;
            } elseif ($booking?->has_option) {
                $toOption[]   = $id;
            } else {
                $toLibre[]    = $id;
            }
        }

        DB::transaction(function () use ($toConfirme, $toOption, $toLibre) {
            if (!empty($toConfirme)) {
                ExternalPanel::whereIn('id', $toConfirme)
                    ->update(['availability_status' => 'confirme']);
            }
            if (!empty($toOption)) {
                ExternalPanel::whereIn('id', $toOption)
                    ->update(['availability_status' => 'option']);
            }
            if (!empty($toLibre)) {
                ExternalPanel::whereIn('id', $toLibre)
                    ->update(['availability_status' => 'disponible']);
            }
        });

        Log::debug('availability.sync_external_done', [
            'confirme' => count($toConfirme),
            'option'   => count($toOption),
            'libre'    => count($toLibre),
        ]);
    }

    /**
     * Panneaux externes occupés sur la période (par leurs propres réservations).
     * Renvoie l'ID externe → état dominant ('confirme' / 'en_attente').
     */
    // ══════════════════════════════════════════════════════════════
    // STATUT DE DISPONIBILITÉ SUR LA PÉRIODE DEMANDÉE — source unique
    //
    // Écran web, PDF images, PDF liste, export Excel et diffusion
    // automatique aux clients passent tous par ici.
    //
    // Bug corrigé le 2026-09-24 : les trois exports réimplémentaient
    // chacun leur version et aucun ne remettait le statut à « libre »
    // quand le panneau n'était bloqué par RIEN sur la période demandée.
    // Ils héritaient donc de panels.status, c'est-à-dire l'état du jour :
    // une recherche sur novembre sortait « Occupé » pour un panneau libre
    // en novembre mais occupé en septembre.
    //
    // 2026-09-29 : déplacée depuis ReservationController (qui délègue
    // désormais ici) pour que les services — la diffusion automatique —
    // n'aient pas à dépendre d'un contrôleur.
    // ══════════════════════════════════════════════════════════════

    /**
     * @param  string|null $rawStatus   Statut courant en base (panels.status)
     * @param  bool        $hasPeriod   Une période valide est-elle demandée ?
     * @param  bool        $isOccupied  Réservation confirmée / campagne active sur CETTE période
     * @param  bool        $isOption    Option en attente sur CETTE période
     * @param  bool        $isRetenu    Retenu par le renouvellement confirmé d'un
     *                                  client (2026-11-11) — écran Disponibilités
     *                                  seulement : les exports, qui partent chez
     *                                  des clients, le passent en « occupé ».
     * @return string                   libre | occupe | renouvellement | option_periode | maintenance | <statut brut>
     */
    public static function displayStatusForPeriod(
        ?string $rawStatus,
        bool $hasPeriod,
        bool $isOccupied,
        bool $isOption,
        bool $isRetenu = false
    ): string {
        $rawStatus = $rawStatus ?: 'libre';

        return match (true) {
            // Maintenance : prime sur tout, le panneau est indisponible
            // quelle que soit la période demandée (HS terrain).
            $rawStatus === 'maintenance' => 'maintenance',
            // Chevauchement sur la période demandée.
            $hasPeriod && $isOccupied    => 'occupe',
            // La retenue bloque : elle passe avant une simple option.
            $hasPeriod && $isRetenu      => 'renouvellement',
            $hasPeriod && $isOption      => 'option_periode',
            // Période valide ET aucun chevauchement → LIBRE SUR LA PÉRIODE,
            // même si le statut courant est 'confirme' ou 'occupe' : le
            // booking en cours se termine AVANT le début de la fenêtre.
            $hasPeriod                   => 'libre',
            // Pas de période demandée → le statut courant fait foi.
            default                      => in_array($rawStatus, ['disponible'], true)
                ? 'libre'
                : $rawStatus,
        };
    }

    /**
     * Verdict d'un panneau dans un SÉLECTEUR COMMERCIAL (devis), pour une
     * période donnée : quoi afficher, et a-t-on le droit de le choisir.
     *
     * ═══ RÈGLE MÉTIER VALIDÉE PAR ÉCRIT — 2026-11-13 ═══
     *   « Quand le panneau est réellement occupé sur la période visée, on
     *     l'affiche occupé mais pas moyen de le sélectionner. Mais s'il est
     *     occupé et sera libre dans la période saisie, on l'affiche et on
     *     indique la date de libération. »
     *
     * Traduction, pour une fenêtre [S, E] :
     *   - aucun chevauchement               → LIBRE, sélectionnable
     *   - chevauchement se terminant < E    → LIBRE DÈS le lendemain,
     *                                         sélectionnable, date affichée
     *   - chevauchement courant jusqu'à E   → OCCUPÉ, bloqué
     *   - maintenance                       → bloqué quelle que soit la période
     *   - aucune période saisie             → statut du jour, sélectionnable
     *     (on ne bloque jamais sur une période inconnue — sinon le
     *      commercial ne pourrait plus rien saisir avant d'avoir les dates)
     *
     * Complète displayStatusForPeriod() : celle-ci dit quoi AFFICHER sur un
     * écran de consultation, celle-là ajoute le droit de SÉLECTIONNER.
     *
     * @param  string|null $rawStatus    panels.status (cache de lecture)
     * @param  bool        $hasPeriod    une période est-elle saisie
     * @param  bool        $available    sortie de getPanelAvailabilityData()
     * @param  string|null $releaseDate  max(end_date) des occupations (Y-m-d)
     * @param  string|null $periodEnd    fin de la fenêtre demandée (Y-m-d)
     * @return array{display_status:string, selectable:bool, libre_le:?string}
     */
    public static function verdictSelection(
        ?string $rawStatus,
        bool    $hasPeriod,
        bool    $available,
        ?string $releaseDate = null,
        ?string $periodEnd   = null
    ): array {
        $rawStatus = $rawStatus ?: 'libre';

        // Maintenance : HS terrain, aucune période n'y change rien.
        if ($rawStatus === 'maintenance') {
            return ['display_status' => 'maintenance', 'selectable' => false, 'libre_le' => null];
        }

        // Pas de période → on ne peut rien affirmer, donc on ne bloque rien.
        if (!$hasPeriod || !$periodEnd) {
            return ['display_status' => 'sans_periode', 'selectable' => true, 'libre_le' => null];
        }

        if ($available) {
            return ['display_status' => 'libre', 'selectable' => true, 'libre_le' => null];
        }

        // Occupé sur la fenêtre. Se libère-t-il AVANT la fin de celle-ci ?
        $libreLe = $releaseDate
            ? \Carbon\Carbon::parse($releaseDate)->addDay()->startOfDay()
            : null;

        if ($libreLe && $libreLe->lte(\Carbon\Carbon::parse($periodEnd)->startOfDay())) {
            return [
                'display_status' => 'libre_des',
                'selectable'     => true,
                'libre_le'       => $libreLe->toDateString(),
            ];
        }

        return [
            'display_status' => 'occupe',
            'selectable'     => false,
            'libre_le'       => $libreLe?->toDateString(),
        ];
    }

    public function getExternalPanelBookingMap(
        array  $externalPanelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): Collection {
        if (empty($externalPanelIds)) return collect();

        return DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.external_panel_id', $externalPanelIds)
            ->where('reservation_panels.source', 'externe')
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->select(
                'reservation_panels.external_panel_id as id',
                DB::raw('MAX(CASE WHEN reservations.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(CASE WHEN reservations.status = "en_attente" THEN 1 ELSE 0 END) as has_option'),
                DB::raw('MAX(reservations.end_date) as release_date')
            )
            ->groupBy('reservation_panels.external_panel_id')
            ->get()
            ->keyBy('id');
    }

    /**
     * Pendant interne de getExternalPanelBookingMap — renvoie pour chaque
     * panel_id : has_confirmed, has_option, release_date (dernière fin de
     * réservation bloquante). Utilisé par les vues d'export pour rétablir
     * le `display_status` réel sur la période demandée (sinon on perd
     * l'info "En option" entre la sélection et le PDF).
     */
    public function getInternalPanelBookingMap(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): Collection {
        if (empty($panelIds)) return collect();

        // Axes multi-pieds (2026-11-11) : la carte sert au PDF des
        // disponibilités et aux écrans de réservation. Un pied doit y
        // apparaître occupé quand son axe est vendu en entier.
        $axeParPied = $this->axes()->axeParPied($panelIds);

        $carte = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $this->axes()->avecAxesParents($panelIds))
            ->where('reservation_panels.source', 'interne')
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->select(
                'reservation_panels.panel_id',
                DB::raw('MAX(CASE WHEN reservations.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(CASE WHEN reservations.status = "en_attente" THEN 1 ELSE 0 END) as has_option'),
                DB::raw('MAX(reservations.end_date) as release_date')
            )
            ->groupBy('reservation_panels.panel_id')
            ->get()
            ->keyBy('panel_id');

        foreach ($axeParPied as $piedId => $axeId) {
            if ($carte->has($axeId) && ! $carte->has($piedId)) {
                $carte->put($piedId, $carte->get($axeId));
            }
        }

        // Retenue d'un renouvellement confirmé (2026-11-11) : dans les PDF et
        // l'Excel, le panneau est « Occupé » jusqu'à la fin de la retenue. Ces
        // documents partent chez des clients : le nom du client qui renouvelle
        // n'y apparaît pas.
        foreach ($this->retenuesRenouvellement($panelIds, $startDate, $endDate) as $pid => $r) {
            // Copie : un pied peut partager l'objet de son axe (report ci-dessus).
            $ligne = $carte->has($pid) ? clone $carte->get($pid) : null;
            if ($ligne) {
                $carte->put($pid, $ligne);
                $ligne->has_confirmed = 1;
                if (!$ligne->release_date || $r['jusqu_au'] > $ligne->release_date) {
                    $ligne->release_date = $r['jusqu_au'];
                }
            } else {
                $carte->put($pid, (object) [
                    'panel_id'      => $pid,
                    'has_confirmed' => 1,
                    'has_option'    => 0,
                    'release_date'  => $r['jusqu_au'],
                ]);
            }
        }

        return $carte->only(array_map('intval', $panelIds));
    }

    // ── 6. Cartes de conflits avec référence — pour sauvegarde partielle ──

    /**
     * Retourne pour chaque panneau interne bloqué : statut bloquant,
     * référence de la réservation conflictuelle, date de libération.
     * Utilisé par confirmerSelection() pour identifier les panneaux à exclure
     * avec le motif précis (confirme vs en_attente + quelle réservation bloque).
     *
     * Keyed by panel_id. Priorité : confirme > en_attente.
     */
    public function getInternalConflictMap(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null,
        ?int   $clientId             = null
    ): Collection {
        if (empty($panelIds)) return collect();

        // Axes multi-pieds (2026-11-11) : un pied est en conflit si son axe
        // l'est. Cf. App\Services\Axes\AxeService.
        $axeParPied     = $this->axes()->axeParPied($panelIds);
        $idsAInterroger = $this->axes()->avecAxesParents($panelIds);

        // ── Source 1 : RÉSERVATIONS en_attente / confirme ─────────
        $fromReservations = DB::table('reservation_panels as rp')
            ->join('reservations as r', 'r.id', '=', 'rp.reservation_id')
            ->whereIn('rp.panel_id', $idsAInterroger)
            ->whereIn('r.status', self::BLOCKING_STATUSES)
            ->where('r.start_date', '<=', $endDate)
            ->where('r.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) => $q->where('r.id', '!=', $excludeReservationId))
            ->select(
                'rp.panel_id',
                DB::raw('MAX(CASE WHEN r.status = "confirme"   THEN r.reference ELSE NULL END) as confirme_ref'),
                DB::raw('MAX(CASE WHEN r.status = "en_attente" THEN r.reference ELSE NULL END) as option_ref'),
                DB::raw('MAX(CASE WHEN r.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(r.end_date) as release_date'),
            )
            ->groupBy('rp.panel_id')
            ->get()
            ->keyBy('panel_id');

        // ── Source 2 : CAMPAGNES planifie / actif / pause ─────────
        // Indispensable : une résa confirmée crée une campagne, et
        // c'est CETTE campagne qui porte ensuite l'engagement. Sans
        // ce check, le panneau "disparaît" après confirmation et le
        // double-booking devient possible (bug critique terrain).
        // Note : la table `campaigns` n'a pas de colonne `reference`
        // (seulement `name`) — contrairement à `reservations`. On
        // utilise donc `name` directement comme identifiant lisible.
        $fromCampaigns = DB::table('campaign_panels as cp')
            ->join('campaigns as c', 'c.id', '=', 'cp.campaign_id')
            ->whereIn('cp.panel_id', $idsAInterroger)
            ->where('cp.type', 'interne')
            ->whereIn('c.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('c.start_date', '<=', $endDate)
            ->where('c.end_date',   '>=', $startDate)
            ->whereNull('c.deleted_at')
            ->when($excludeCampaignId, fn($q) => $q->where('c.id', '!=', $excludeCampaignId))
            ->select(
                'cp.panel_id',
                DB::raw('MAX(c.name) as camp_name'),
                DB::raw('MAX(c.end_date) as release_date'),
            )
            ->groupBy('cp.panel_id')
            ->get()
            ->keyBy('panel_id');

        // Le conflit d'un axe retombe sur ses pieds, avant la fusion.
        foreach ($axeParPied as $piedId => $axeId) {
            foreach ([$fromReservations, $fromCampaigns] as $lot) {
                if ($lot->has($axeId) && ! $lot->has($piedId)) {
                    $lot->put($piedId, $lot->get($axeId));
                }
            }
        }

        // ── Source 3 : retenue d'un renouvellement confirmé d'un autre
        // client (2026-11-11). Les axes sont déjà traités par la règle.
        $fromRetenues = $this->retenuesPourAutreClient($panelIds, $startDate, $endDate, $clientId);

        // ── Merge : union des sources, plus tardif gagne ──
        $merged = collect();
        $allKeys = $fromReservations->keys()
            ->merge($fromCampaigns->keys())
            ->unique()
            // Les axes interrogés par ricochet ne ressortent pas.
            ->intersect(array_map('intval', $panelIds))
            ->merge(array_keys($fromRetenues))
            ->unique();
        foreach ($allKeys as $pid) {
            $r = $fromReservations->get($pid);
            $c = $fromCampaigns->get($pid);
            $t = $fromRetenues[$pid] ?? null;
            // Campagne = engagement signé → priorité sur réservation
            if ($c) {
                $merged->put($pid, (object)[
                    'blocking_status' => 'campagne_active',
                    'conflicting_ref' => $c->camp_name,
                    'release_date'    => $r && $r->release_date > $c->release_date
                        ? $r->release_date
                        : $c->release_date,
                ]);
            } elseif ($r && (bool) $r->has_confirmed) {
                $merged->put($pid, (object)[
                    'blocking_status' => 'confirme',
                    'conflicting_ref' => $r->confirme_ref,
                    'release_date'    => $r->release_date,
                ]);
            } elseif ($t) {
                // Retenue avant une simple option : elle, elle bloque.
                $merged->put($pid, (object)[
                    'blocking_status' => 'renouvellement',
                    'conflicting_ref' => $t['client'] ?? 'un autre client',
                    'release_date'    => $r && $r->release_date > $t['jusqu_au'] ? $r->release_date : $t['jusqu_au'],
                ]);
            } else {
                $merged->put($pid, (object)[
                    'blocking_status' => 'en_attente',
                    'conflicting_ref' => $r->option_ref,
                    'release_date'    => $r->release_date,
                ]);
            }

            // La retenue court parfois plus loin que l'occupation : le
            // panneau n'est libre qu'après les deux.
            if ($t && $merged->get($pid)->release_date < $t['jusqu_au']) {
                $merged->get($pid)->release_date = $t['jusqu_au'];
            }
        }
        return $merged;
    }

    /**
     * Pendant externe de getInternalConflictMap.
     * Keyed by external_panel_id. Priorité : confirme > en_attente.
     */
    public function getExternalConflictMap(
        array  $externalPanelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): Collection {
        if (empty($externalPanelIds)) return collect();

        return DB::table('reservation_panels as rp')
            ->join('reservations as r', 'r.id', '=', 'rp.reservation_id')
            ->whereIn('rp.external_panel_id', $externalPanelIds)
            ->where('rp.source', 'externe')
            ->whereIn('r.status', self::BLOCKING_STATUSES)
            ->where('r.start_date', '<=', $endDate)
            ->where('r.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) => $q->where('r.id', '!=', $excludeReservationId))
            ->select(
                'rp.external_panel_id',
                DB::raw('MAX(CASE WHEN r.status = "confirme"   THEN r.reference ELSE NULL END) as confirme_ref'),
                DB::raw('MAX(CASE WHEN r.status = "en_attente" THEN r.reference ELSE NULL END) as option_ref'),
                DB::raw('MAX(CASE WHEN r.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(r.end_date) as release_date'),
            )
            ->groupBy('rp.external_panel_id')
            ->get()
            ->keyBy('external_panel_id')
            ->map(fn($row) => (object)[
                'blocking_status' => (bool) $row->has_confirmed ? 'confirme' : 'en_attente',
                'conflicting_ref' => (bool) $row->has_confirmed ? $row->confirme_ref : $row->option_ref,
                'release_date'    => $row->release_date,
            ]);
    }

    // ── 7. Vérification rapide sans chargement modèle ────────────
    /**
     * Détecte les conflits ACTIFS actuellement en BD (panneaux engagés
     * sur 2+ entités — réservations et/ou campagnes — qui se chevauchent
     * RÉELLEMENT en tenant compte du démarrage différé par panneau).
     *
     * Date EFFECTIVE prise en compte :
     *   - Réservation : `COALESCE(panel_start_date, reservation.start_date)`
     *   - Campagne    : `COALESCE(rp.panel_start_date, campaign.start_date)`
     *     (lookup `reservation_panels` via campaign.reservation_id)
     *
     * Sans ce COALESCE, une résa du 19→22 sur le même panneau qu'une résa
     * du 19→21 serait flaguée — alors qu'avec un panel_start_date=22 sur
     * la 2e, elles ne se chevauchent PAS réellement (panneau démarre
     * après la libération de la 1re).
     *
     * Optimisation : pré-filtre SQL des panneaux ayant ≥ 2 engagements
     * (UNION ALL + GROUP BY + HAVING). Évite de tirer 10 000 lignes
     * d'engagements quand seuls quelques panneaux sont en conflit.
     *
     * Retourne :
     *   [
     *     'panel_id'   => int,
     *     'reference'  => string,
     *     'engagements'=> [{
     *        type, id, ref, client, start (effective), end, status,
     *        deferred: bool  // true si panel_start_date écrase start_date
     *     }]
     *   ]
     */
    public function detectActiveConflicts(): array
    {
        // ── ÉTAPE 1 — Pré-filtre SQL : panneaux à ≥ 2 engagements ─────
        // Évite de charger tous les engagements actifs en mémoire si
        // 99 % des panneaux n'en ont qu'un seul (cas normal).
        $candidateIds = DB::query()
            ->fromSub(function ($q) {
                $q->from('reservation_panels')
                  ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                  ->where('reservation_panels.source', 'interne')
                  ->whereIn('reservations.status', self::BLOCKING_STATUSES)
                  ->select('reservation_panels.panel_id')
                  ->unionAll(
                      DB::table('campaign_panels')
                          ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                          ->where('campaign_panels.type', 'interne')
                          ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
                          ->whereNull('campaigns.deleted_at')
                          ->select('campaign_panels.panel_id')
                  );
            }, 'engagements')
            ->select('panel_id')
            ->groupBy('panel_id')
            ->havingRaw('COUNT(*) >= 2')
            ->pluck('panel_id')
            ->all();

        if (empty($candidateIds)) return [];

        // ── ÉTAPE 2 — Détails des engagements pour ces panneaux ──────
        // On utilise COALESCE pour calculer la date EFFECTIVE de
        // démarrage de chaque engagement (panel_start_date prime).
        $resas = DB::table('reservation_panels as rp')
            ->join('reservations as r', 'r.id', '=', 'rp.reservation_id')
            ->whereIn('rp.panel_id', $candidateIds)
            ->where('rp.source', 'interne')
            ->whereIn('r.status', self::BLOCKING_STATUSES)
            ->select('rp.panel_id',
                     'r.id as eid',
                     'r.reference as ref',
                     'r.client_id',
                     DB::raw('COALESCE(rp.panel_start_date, r.start_date) as effective_start'),
                     'r.start_date as original_start',
                     'r.end_date',
                     'r.status',
                     DB::raw("'reservation' as type"))
            ->get();

        // Pour les campagnes, le panel_start_date n'est PAS stocké
        // dans campaign_panels (qui n'a pas cette colonne) — il vit
        // dans reservation_panels via campaign.reservation_id. LEFT JOIN
        // pour retomber sur campaign.start_date si pas de pivot trouvé.
        //
        // On sélectionne aussi `c.reservation_id` (alias `linked_resa_id`)
        // pour détecter les campagnes adossées à une réservation déjà
        // listée dans $resas — ce ne sont PAS deux engagements distincts,
        // c'est la même chose vue sous deux angles (filtrage en étape 4).
        $camps = DB::table('campaign_panels as cp')
            ->join('campaigns as c', 'c.id', '=', 'cp.campaign_id')
            ->leftJoin('reservation_panels as rpc', function ($j) {
                $j->on('rpc.reservation_id', '=', 'c.reservation_id')
                  ->on('rpc.panel_id', '=', 'cp.panel_id')
                  ->where('rpc.source', '=', 'interne');
            })
            ->whereIn('cp.panel_id', $candidateIds)
            ->where('cp.type', 'interne')
            ->whereIn('c.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->whereNull('c.deleted_at')
            ->select('cp.panel_id',
                     'c.id as eid',
                     'c.reservation_id as linked_resa_id',
                     'c.name as ref',
                     'c.client_id',
                     DB::raw('COALESCE(rpc.panel_start_date, c.start_date) as effective_start'),
                     'c.start_date as original_start',
                     'c.end_date',
                     'c.status',
                     DB::raw("'campaign' as type"))
            ->get();

        // Normalise : les réservations n'ont pas de linked_resa_id (on en
        // ajoute un null pour éviter les accès propriété non définie).
        $resas = $resas->map(function ($r) {
            $r->linked_resa_id = null;
            return $r;
        });

        $all = $resas->concat($camps)->groupBy('panel_id');

        // ── ÉTAPE 3 — Bulk-load clients + références panel ───────────
        $panelRefs = Panel::whereIn('id', $candidateIds)->pluck('reference', 'id');
        $clientIds = $all->flatten()->pluck('client_id')->filter()->unique()->all();
        $clients   = empty($clientIds)
            ? collect()
            : DB::table('clients')->whereIn('id', $clientIds)->pluck('name', 'id');

        // ── ÉTAPE 4 — Détection paire-à-paire avec date effective ────
        $conflicts = [];
        foreach ($all as $pid => $engagements) {
            if ($engagements->count() < 2) continue;

            // ── Dédoublonnage résa ↔ campagne adossée ─────────────────
            // Si une campagne référence (via reservation_id) une réservation
            // également listée dans les engagements, ce n'est PAS un conflit :
            // c'est le même engagement vu sous deux angles (campaign_panels
            // + reservation_panels). On retire la réservation source et on
            // ne garde que la campagne, qui est l'entité "active".
            $linkedResaIds = $engagements
                ->where('type', 'campaign')
                ->pluck('linked_resa_id')
                ->filter()
                ->all();

            $engagements = $engagements->reject(function ($e) use ($linkedResaIds) {
                return $e->type === 'reservation'
                    && in_array((int) $e->eid, array_map('intval', $linkedResaIds), true);
            })->values();

            if ($engagements->count() < 2) continue;

            $sorted = $engagements->sortBy('effective_start')->values();
            $hasOverlap = false;
            for ($i = 0; $i < $sorted->count() - 1 && !$hasOverlap; $i++) {
                for ($j = $i + 1; $j < $sorted->count(); $j++) {
                    $a = $sorted[$i];
                    $b = $sorted[$j];
                    // Overlap réel si l'intervalle effectif chevauche
                    if ($a->effective_start <= $b->end_date
                        && $a->end_date >= $b->effective_start) {
                        $hasOverlap = true;
                        break;
                    }
                }
            }
            if (!$hasOverlap) continue;

            $conflicts[] = [
                'panel_id'    => (int) $pid,
                'reference'   => $panelRefs[$pid] ?? "#{$pid}",
                'engagements' => $engagements->map(fn($e) => [
                    'type'     => $e->type,
                    'id'       => (int) $e->eid,
                    'ref'      => $e->ref,
                    'client'   => $e->client_id ? ($clients[$e->client_id] ?? null) : null,
                    'start'    => $e->effective_start,
                    'end'      => $e->end_date,
                    'status'   => $e->status,
                    // Flag visuel pour l'UI : indique si la date affichée
                    // diffère de la start_date "officielle" de l'entité
                    // (cas démarrage différé). Permet au front d'ajouter
                    // un badge "Démarrage différé du DD/MM".
                    'deferred' => $e->effective_start !== $e->original_start,
                ])->values()->all(),
            ];
        }

        return $conflicts;
    }

    /**
     * Compte rapide pour les badges dashboard.
     * Optimisé : utilise le pré-filtre SQL de detectActiveConflicts
     * sans matérialiser le résultat complet. Mais comme le check
     * d'overlap se fait en PHP avec COALESCE, on doit quand même
     * exécuter le pipeline complet — gardé en cache 5 min côté
     * appelant (cf. layout admin.blade.php).
     */
    public function countActiveConflicts(): int
    {
        return count($this->detectActiveConflicts());
    }

    public function quickCheck(int $panelId, string $start, string $end): bool
    {
        // Axes multi-pieds (2026-11-11) : un pied est pris si son axe l'est.
        $ids = $this->axes()->avecAxesParents([$panelId]);

        $blockedByResa = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $ids)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $end)
            ->where('reservations.end_date',   '>=', $start)
            ->exists();
        if ($blockedByResa) return false;

        // 2e source : campagnes actives (planifie/actif/pause)
        $blockedByCamp = DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->whereIn('campaign_panels.panel_id', $ids)
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('campaigns.start_date', '<=', $end)
            ->where('campaigns.end_date',   '>=', $start)
            ->whereNull('campaigns.deleted_at')
            ->exists();

        return !$blockedByCamp;
    }

    /**
     * Récupère les données de disponibilité complètes pour une liste de panneaux.
     * Retourne pour chaque panneau : disponible, date de libération, statut bloquant.
     *
     * Paramètres :
     *   - $includeCampaignBlockings : si true, intègre aussi les blocages via
     *     campaign_panels (statuts planifie/actif/pause). Indispensable pour
     *     éviter le double-booking ; off par défaut pour ne pas modifier le
     *     comportement existant des appels Reservation tant qu'ils ne sont
     *     pas explicitement migrés.
     *   - $excludeCampaignId : exclut une campagne précise du calcul de
     *     blocage (cas : on cherche des panneaux libres pour AJOUTER à cette
     *     campagne, donc elle ne doit pas s'auto-bloquer ses propres futurs).
     */
    public function getPanelAvailabilityData(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        bool   $includeCampaignBlockings = false,
        ?int   $excludeCampaignId = null,
        ?int   $clientId = null
    ): Collection {
        if (empty($panelIds)) return collect();

        // Retenue d'un renouvellement confirmé d'un autre client (2026-11-11).
        $retenues = $this->retenuesPourAutreClient($panelIds, $startDate, $endDate, $clientId);

        // Axes multi-pieds (2026-11-11) : on interroge aussi les axes
        // parents, et chaque pied reprend l'occupation de son axe plus bas.
        $axeParPied     = $this->axes()->axeParPied($panelIds);
        $idsAInterroger = $this->axes()->avecAxesParents($panelIds);

        $bookings = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->join('clients', 'clients.id', '=', 'reservations.client_id')
            // Campagne supprimée (soft delete) ignorée DANS la jointure
            // (2026-10-30) : son nom ne s'affiche plus, et la réservation
            // reste listée (jointure gauche conservée).
            ->leftJoin('campaigns', function ($j) {
                $j->on('campaigns.reservation_id', '=', 'reservations.id')
                  ->whereNull('campaigns.deleted_at');
            })
            ->whereIn('reservation_panels.panel_id', $idsAInterroger)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->select(
                'reservation_panels.panel_id',
                'reservations.status as res_status',
                'reservations.start_date',
                'reservations.end_date',
                'reservations.reference as reservation_ref',
                'clients.name as client_name',
                'campaigns.name as campaign_name',
                DB::raw('MAX(reservations.end_date) as release_date')
            )
            ->groupBy(
                'reservation_panels.panel_id',
                'reservations.status',
                'reservations.start_date',
                'reservations.end_date',
                'reservations.reference',
                'clients.name',
                'campaigns.name'
            )
            ->get()
            ->groupBy('panel_id');

        // Blocages via campagnes directes (campaign_panels, type=interne)
        // — utile pour les campagnes créées sans réservation, où la trace
        // n'apparaît pas dans reservation_panels.
        $campaignBookings = collect();
        if ($includeCampaignBlockings) {
            $campaignBookings = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->leftJoin('clients', 'clients.id', '=', 'campaigns.client_id')
                ->whereIn('campaign_panels.panel_id', $idsAInterroger)
                ->where('campaign_panels.type', 'interne')
                ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
                ->where('campaigns.start_date', '<=', $endDate)
                ->where('campaigns.end_date',   '>=', $startDate)
                ->whereNull('campaigns.deleted_at')
                ->when($excludeCampaignId, fn($q) =>
                    $q->where('campaigns.id', '!=', $excludeCampaignId)
                )
                ->select(
                    'campaign_panels.panel_id',
                    'campaigns.status as camp_status',
                    'campaigns.start_date',
                    'campaigns.end_date',
                    'campaigns.name as campaign_name',
                    'clients.name as client_name'
                )
                ->get()
                ->groupBy('panel_id');
        }

        // Chaque pied hérite des occupations de son axe. On recopie sous la
        // clé du pied : tout le calcul qui suit (date de libération, statut
        // bloquant, détail) fonctionne alors sans changement.
        foreach ($axeParPied as $piedId => $axeId) {
            foreach ([$bookings, $campaignBookings] as $lot) {
                $lignesAxe = $lot->get($axeId);
                if ($lignesAxe === null || $lignesAxe->isEmpty()) {
                    continue;
                }
                $lot->put($piedId, ($lot->get($piedId) ?? collect())->concat($lignesAxe));
            }
        }

        return collect($panelIds)->mapWithKeys(function ($id) use ($bookings, $campaignBookings, $retenues) {
            $resBookings = $bookings->get($id) ?? collect();
            $campBookings = $campaignBookings->get($id) ?? collect();
            $retenue = $retenues[(int) $id] ?? null;

            // Aucune occupation → libre
            if ($resBookings->isEmpty() && $campBookings->isEmpty() && !$retenue) {
                return [$id => [
                    'available'       => true,
                    'release_date'    => null,
                    'blocking_status' => null,
                    'occupations'     => [],
                ]];
            }

            // Toutes les occupations (résa + campagne) pour affichage détaillé
            $occupations = collect();
            foreach ($resBookings as $b) {
                $occupations->push([
                    'source'          => 'reservation',
                    'client_name'     => $b->client_name,
                    'campaign_name'   => $b->campaign_name,
                    'reservation_ref' => $b->reservation_ref,
                    'start_date'      => \Carbon\Carbon::parse($b->start_date)->format('d/m/Y'),
                    'end_date'        => \Carbon\Carbon::parse($b->end_date)->format('d/m/Y'),
                    'end_date_raw'    => $b->end_date,
                    'status'          => $b->res_status,
                ]);
            }
            foreach ($campBookings as $b) {
                $occupations->push([
                    'source'          => 'campaign',
                    'client_name'     => $b->client_name,
                    'campaign_name'   => $b->campaign_name,
                    'reservation_ref' => null,
                    'start_date'      => \Carbon\Carbon::parse($b->start_date)->format('d/m/Y'),
                    'end_date'        => \Carbon\Carbon::parse($b->end_date)->format('d/m/Y'),
                    'end_date_raw'    => $b->end_date,
                    'status'          => $b->camp_status,
                ]);
            }
            if ($retenue) {
                $occupations->push([
                    'source'          => 'renouvellement',
                    'client_name'     => $retenue['client'],
                    'campaign_name'   => $retenue['campagne'],
                    'reservation_ref' => null,
                    'start_date'      => \Carbon\Carbon::parse($retenue['du'])->format('d/m/Y'),
                    'end_date'        => \Carbon\Carbon::parse($retenue['jusqu_au'])->format('d/m/Y'),
                    'end_date_raw'    => $retenue['jusqu_au'],
                    'status'          => 'renouvellement',
                ]);
            }

            // Date de libération = max(end_date) parmi toutes les occupations.
            // Statut bloquant = priorité confirme (résa) / actif (campagne) > pause
            // > retenue d'un renouvellement > en_attente / planifie.
            $releaseDate = $occupations->max('end_date_raw');
            $priority = ['confirme' => 5, 'actif' => 5, 'renouvellement' => 4, 'pause' => 3, 'en_attente' => 2, 'planifie' => 2];
            $blockingStatus = $occupations
                ->sortByDesc(fn($o) => $priority[$o['status']] ?? 0)
                ->first()['status'] ?? null;

            return [$id => [
                'available'       => false,
                'release_date'    => $releaseDate,
                'blocking_status' => $blockingStatus,
                'occupations'     => $occupations->map(fn($o) => collect($o)->except('end_date_raw')->all())->values()->all(),
            ]];
        });
    }
}