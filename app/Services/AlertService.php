<?php
// app/Services/AlertService.php

namespace App\Services;

use App\Models\Alert;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AlertService — création et gestion des alertes système.
 *
 * Catalogue des 20 motifs supportés (TYPES) avec icon, couleur, niveau par
 * défaut. Le code `type` est la clé canonique : on s'en sert dans toute la
 * stack (filtre, dedup, trigger).
 *
 * Création d'une alerte : ::notify(type, model, ['title', 'message', 'lien'])
 * — la dedup est automatique sur (type + related_*).
 *
 * Bumping : si une alerte non lue identique existe, on rafraîchit son
 * triggered_at au lieu d'en créer une nouvelle (le badge ne s'envole pas).
 */
class AlertService
{
    // ══════════════════════════════════════════════════════════════════
    // CATALOGUE DES 20 MOTIFS
    //
    // Format : 'code' => ['icon', 'niveau', 'color', 'label', 'group']
    //   - icon    : SVG path id ou emoji (utilisé par le composant blade)
    //   - niveau  : 'info' | 'warning' | 'danger' (défaut quand on appelle notify)
    //   - color   : couleur principale (mappe au design system)
    //   - label   : libellé court FR pour les filtres et la liste
    //   - group   : regroupement métier (Réservations, Campagnes, ...)
    //
    // ══════════════════════════════════════════════════════════════════
    public const TYPES = [
        // ── Réservations ──────────────────────────────────────────
        'reservation_nouvelle' => [
            'icon' => '📋', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Nouvelle réservation',
            'group' => 'Réservations',
        ],
        'reservation_confirmee' => [
            'icon' => '✅', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Réservation confirmée',
            'group' => 'Réservations',
        ],
        'reservation_annulee' => [
            'icon' => '❌', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Réservation annulée',
            'group' => 'Réservations',
        ],
        'reservation_expiree' => [
            'icon' => '⏱', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Réservation expirée',
            'group' => 'Réservations',
        ],
        'reservation_en_attente_longue' => [
            'icon' => '⌛', 'niveau' => 'warning', 'color' => '#eab308',
            'label' => 'Réservation en attente > 48h',
            'group' => 'Réservations',
        ],

        // ── Campagnes ─────────────────────────────────────────────
        'campagne_creee' => [
            'icon' => '🎯', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Campagne créée',
            'group' => 'Campagnes',
        ],
        'campagne_active' => [
            'icon' => '🚀', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Campagne active',
            'group' => 'Campagnes',
        ],
        'campagne_terminee' => [
            'icon' => '🏁', 'niveau' => 'info',    'color' => '#6b7280',
            'label' => 'Campagne terminée',
            'group' => 'Campagnes',
        ],
        'fin_campagne_j7' => [
            'icon' => '📅', 'niveau' => 'warning', 'color' => '#eab308',
            'label' => 'Fin campagne dans 7 jours',
            'group' => 'Campagnes',
        ],
        'fin_campagne_j3' => [
            'icon' => '⚠️', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Fin campagne dans 3 jours',
            'group' => 'Campagnes',
        ],
        'fin_campagne_j0' => [
            'icon' => '🔥', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Campagne expirée aujourd\'hui',
            'group' => 'Campagnes',
        ],

        // ── Panneaux ──────────────────────────────────────────────
        'panneau_libre' => [
            'icon' => '🟢', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Panneau libéré',
            'group' => 'Panneaux',
        ],
        'panneau_occupe' => [
            'icon' => '🔴', 'niveau' => 'info',    'color' => '#ef4444',
            'label' => 'Panneau occupé',
            'group' => 'Panneaux',
        ],
        'panneau_maintenance' => [
            'icon' => '🛠', 'niveau' => 'warning', 'color' => '#6b7280',
            'label' => 'Panneau en maintenance',
            'group' => 'Panneaux',
        ],
        'conflit_reservation' => [
            'icon' => '⚡', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Conflit de réservation',
            'group' => 'Panneaux',
        ],

        // ── Poses ─────────────────────────────────────────────────
        'pose_planifiee' => [
            'icon' => '📌', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Pose planifiée',
            'group' => 'Poses',
        ],
        'pose_en_cours' => [
            'icon' => '⏳', 'niveau' => 'info',    'color' => '#eab308',
            'label' => 'Pose en cours',
            'group' => 'Poses',
        ],
        'pose_terminee' => [
            'icon' => '✅', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Pose terminée',
            'group' => 'Poses',
        ],
        'avancement_pose' => [
            'icon' => '📊', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Mise à jour avancement',
            'group' => 'Poses',
        ],

        // ── Factures ──────────────────────────────────────────────
        'facture_creee' => [
            'icon' => '🧾', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Facture créée',
            'group' => 'Factures',
        ],
        'facture_envoyee' => [
            'icon' => '📤', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Facture envoyée',
            'group' => 'Factures',
        ],
        'facture_payee' => [
            'icon' => '💵', 'niveau' => 'info',    'color' => '#22c55e',
            'label' => 'Facture payée',
            'group' => 'Factures',
        ],
        'facture_annulee' => [
            'icon' => '🚫', 'niveau' => 'danger',  'color' => '#ef4444',
            'label' => 'Facture annulée',
            'group' => 'Factures',
        ],

        // ── Système ───────────────────────────────────────────────
        'taxe_echeance' => [
            'icon' => '💰', 'niveau' => 'warning', 'color' => '#f97316',
            'label' => 'Échéance taxe communale',
            'group' => 'Système',
        ],
        'nouveau_client' => [
            'icon' => '👤', 'niveau' => 'info',    'color' => '#3b82f6',
            'label' => 'Nouveau client créé',
            'group' => 'Système',
        ],
    ];

    public const DEFAULT_META = [
        'icon'   => '🔔',
        'niveau' => 'info',
        'color'  => '#6b7280',
        'label'  => 'Notification',
        'group'  => 'Autre',
    ];

    // ══════════════════════════════════════════════════════════════════
    // API PUBLIQUE — création
    // ══════════════════════════════════════════════════════════════════

    /**
     * Crée (ou bump) une alerte. Source unique de création.
     *
     * @param  string      $type     Code de TYPES (ex: 'reservation_confirmee')
     * @param  string      $title    Libellé court
     * @param  string      $message  Message détaillé
     * @param  Model|null  $related  Modèle lié (Reservation, Campaign, …)
     * @param  array       $opts     [niveau?, lien?, user_id?, dedup_extra?]
     */
    public static function notify(
        string  $type,
        string  $title,
        string  $message,
        ?Model  $related = null,
        array   $opts = []
    ): ?Alert {
        $meta   = self::TYPES[$type] ?? self::DEFAULT_META;
        $niveau = $opts['niveau'] ?? $meta['niveau'];

        $relatedType = $related ? class_basename($related) : null;
        $relatedId   = $related?->getKey();

        // dedup_key : par défaut type+model+id, surchargeable via opts['dedup_extra']
        // (utile pour différencier "fin_campagne_j7" de "fin_campagne_j3" sur la même campagne).
        $dedupKey = sprintf(
            '%s:%s:%s:%s',
            $type,
            $relatedType ?? '-',
            $relatedId ?? '-',
            $opts['dedup_extra'] ?? '-'
        );

        try {
            // Bump si une alerte non lue existe déjà avec ce dedup_key
            $existing = Alert::query()
                ->where('dedup_key', $dedupKey)
                ->where('is_read', false)
                ->whereNull('archived_at')
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'title'        => $title,
                    'message'      => $message,
                    'niveau'       => $niveau,
                    'lien'         => $opts['lien'] ?? $existing->lien,
                    'triggered_at' => now(),
                ])->save();
                return $existing;
            }

            return Alert::create([
                'type'         => $type,
                'niveau'       => $niveau,
                'title'        => $title,
                'message'      => $message,
                'related_type' => $relatedType,
                'related_id'   => $relatedId,
                'dedup_key'    => $dedupKey,
                'user_id'      => $opts['user_id'] ?? null,
                'lien'         => $opts['lien'] ?? null,
                'is_read'      => false,
                'triggered_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('alert.notify.failed', [
                'type'    => $type,
                'error'   => $e->getMessage(),
                'related' => $relatedType.'#'.$relatedId,
            ]);
            return null;
        }
    }

    /**
     * Helper rétro-compat : signature ancienne ::create($type, $niveau, …)
     * encore utilisée par 30+ controllers. On mappe (type+niveau) vers un
     * code du catalogue TYPES pour bénéficier de l'icon/couleur uniformes,
     * sinon fallback générique.
     */
    public static function create(
        string $type,
        string $niveau,
        string $title,
        string $message,
        $model = null
    ): ?Alert {
        $code = self::resolveLegacyCode($type, $niveau, $title);

        return self::notify($code, $title, $message, $model, [
            'niveau' => $niveau,
        ]);
    }

    /**
     * Mappe (type historique, niveau, indices dans le titre) vers un code
     * du catalogue TYPES. Heuristique simple, suffisante pour 95% des cas.
     */
    private static function resolveLegacyCode(string $type, string $niveau, string $title): string
    {
        // Si le type est déjà un code catalogué, on l'utilise tel quel
        if (isset(self::TYPES[$type])) return $type;

        $titleLc = mb_strtolower($title);

        return match ($type) {
            'reservation' => match (true) {
                str_contains($titleLc, 'confirm')                          => 'reservation_confirmee',
                str_contains($titleLc, 'annul')                            => 'reservation_annulee',
                str_contains($titleLc, 'expir')                            => 'reservation_expiree',
                str_contains($titleLc, 'attente')                          => 'reservation_en_attente_longue',
                $niveau === 'success'                                      => 'reservation_confirmee',
                $niveau === 'danger'                                       => 'reservation_annulee',
                $niveau === 'warning'                                      => 'reservation_en_attente_longue',
                default                                                    => 'reservation_nouvelle',
            },

            'campagne', 'campaign' => match (true) {
                str_contains($titleLc, 'expire') || str_contains($titleLc, 'fin')
                    => match (true) {
                        $niveau === 'danger'  => 'fin_campagne_j0',
                        $niveau === 'warning' => 'fin_campagne_j3',
                        default               => 'fin_campagne_j7',
                    },
                str_contains($titleLc, 'termin')                           => 'campagne_terminee',
                str_contains($titleLc, 'activ') || $niveau === 'success'   => 'campagne_active',
                default                                                    => 'campagne_creee',
            },

            'panneau', 'panel' => match (true) {
                str_contains($titleLc, 'maintenance')                      => 'panneau_maintenance',
                str_contains($titleLc, 'conflit') || $niveau === 'danger'  => 'conflit_reservation',
                $niveau === 'success'                                      => 'panneau_libre',
                default                                                    => 'panneau_occupe',
            },

            'maintenance' => 'panneau_maintenance',

            'pose' => match (true) {
                str_contains($titleLc, 'termin') || str_contains($titleLc, 'réalis') || $niveau === 'success'
                    => 'pose_terminee',
                str_contains($titleLc, 'cours') || str_contains($titleLc, 'avance')
                    => 'pose_en_cours',
                default                                                    => 'pose_planifiee',
            },

            'pige' => 'avancement_pose',

            'client' => 'nouveau_client',

            'taxe' => 'taxe_echeance',

            // Fallback : on garde le type tel quel mais on saura via meta
            // qu'il sort du catalogue.
            default => $type,
        };
    }

    // ══════════════════════════════════════════════════════════════════
    // STATS / READ — lecture optimisée
    // ══════════════════════════════════════════════════════════════════

    public function unreadCount(): int
    {
        return Alert::unread()->count();
    }

    /**
     * Résumé par niveau pour le badge cloche (compte les NON LUES).
     * Utilisé uniquement par /api/alerts/count (polling navigation).
     */
    public function unreadSummary(): array
    {
        $row = Alert::unread()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN niveau = 'danger'  THEN 1 ELSE 0 END) as danger,
                SUM(CASE WHEN niveau = 'warning' THEN 1 ELSE 0 END) as warning,
                SUM(CASE WHEN niveau = 'info'    THEN 1 ELSE 0 END) as info
            ")->first();

        return [
            'total'   => (int) ($row->total   ?? 0),
            'danger'  => (int) ($row->danger  ?? 0),
            'warning' => (int) ($row->warning ?? 0),
            'info'    => (int) ($row->info    ?? 0),
        ];
    }

    /**
     * Résumé par niveau sur toutes les alertes actives (lues OU non lues,
     * non archivées). C'est cette stat qu'on affiche dans les KPI de la
     * page index — elles restent significatives après le mark-all-as-read
     * automatique à l'ouverture.
     *
     * Accepte des filtres optionnels (type, niveau, non_lues) pour rester
     * cohérent avec la liste affichée juste en dessous.
     */
    public function activeSummary(array $filters = []): array
    {
        $q = Alert::active();

        if (!empty($filters['type']))   $q->ofType($filters['type']);
        if (!empty($filters['niveau'])) $q->ofNiveau($filters['niveau']);
        if (!empty($filters['non_lues'])) $q->where('is_read', false);

        $row = $q->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN niveau = 'danger'  THEN 1 ELSE 0 END) as danger,
                SUM(CASE WHEN niveau = 'warning' THEN 1 ELSE 0 END) as warning,
                SUM(CASE WHEN niveau = 'info'    THEN 1 ELSE 0 END) as info
            ")->first();

        return [
            'total'   => (int) ($row->total   ?? 0),
            'danger'  => (int) ($row->danger  ?? 0),
            'warning' => (int) ($row->warning ?? 0),
            'info'    => (int) ($row->info    ?? 0),
        ];
    }

    public function latest(int $limit = 8)
    {
        return Alert::unread()
            ->latest('triggered_at')
            ->limit($limit)
            ->get(['id', 'type', 'niveau', 'title', 'message', 'lien', 'triggered_at']);
    }

    /**
     * Marque toutes les alertes non lues comme lues — atomique.
     * Retourne le nombre de lignes affectées.
     */
    public function markAllAsRead(): int
    {
        return Alert::unread()->update(['is_read' => true]);
    }

    /**
     * Archivage de masse des alertes lues > N jours — pour purge périodique.
     */
    public function archiveOldRead(int $olderThanDays = 30): int
    {
        return Alert::read()
            ->whereNull('archived_at')
            ->where('updated_at', '<', now()->subDays($olderThanDays))
            ->update(['archived_at' => now()]);
    }

    public function getForModel(string $modelClass, int $modelId, int $limit = 5)
    {
        return Alert::unread()
            ->where('related_type', class_basename($modelClass))
            ->where('related_id', $modelId)
            ->orderByDesc('triggered_at')
            ->limit($limit)
            ->get();
    }

    public function countForModule(string $type): int
    {
        return Alert::unread()->ofType($type)->count();
    }

    // ══════════════════════════════════════════════════════════════════
    // GÉNÉRATION BATCH — appelée par artisan alerts:generate (cron)
    // ══════════════════════════════════════════════════════════════════

    public function generateAll(): array
    {
        return [
            'reservations_attente' => $this->triggerReservationsEnAttenteLongue(),
            'maintenances'         => $this->triggerMaintenancesUrgentes(),
            'campagnes_fin'        => $this->triggerFinDeCampagne(),
            'panneaux_maintenance' => $this->triggerPanneauxMaintenancePourLongtemps(),
            'poses_retard'         => $this->triggerPosesEnRetard(),
            'piges_manquantes'     => $this->triggerPosesSansPige(),
        ];
    }

    public function triggerReservationsEnAttenteLongue(): int
    {
        $count = 0;
        $reservations = Reservation::with('client')
            ->where('status', 'en_attente')
            ->where('created_at', '<=', now()->subHours(48))
            ->get();

        foreach ($reservations as $r) {
            $alert = self::notify(
                'reservation_en_attente_longue',
                "Réservation en attente — {$r->client?->name}",
                "La réservation {$r->reference} est en attente de confirmation depuis plus de 48h.",
                $r,
                ['lien' => route('admin.reservations.show', $r)]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerMaintenancesUrgentes(): int
    {
        if (!class_exists(\App\Models\Maintenance::class)) return 0;

        $count = 0;
        $maintenances = \App\Models\Maintenance::with('panel')
            ->where('priorite', 'urgente')
            ->where('statut', '!=', 'resolu')
            ->where('created_at', '<=', now()->subHours(24))
            ->get();

        foreach ($maintenances as $m) {
            $alert = self::notify(
                'panneau_maintenance',
                "Maintenance urgente — {$m->panel?->reference}",
                "Panne urgente non résolue : {$m->type_panne}. Panneau {$m->panel?->reference} hors service.",
                $m,
                ['niveau' => 'danger']
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerFinDeCampagne(): int
    {
        $count = 0;
        $today = now()->startOfDay();
        $campaigns = Campaign::with('client')
            ->where('status', 'actif')
            ->whereBetween('end_date', [$today, $today->copy()->addDays(8)])
            ->get();

        foreach ($campaigns as $c) {
            $jours = (int) $today->diffInDays($c->end_date->copy()->startOfDay());
            $type  = match (true) {
                $jours <= 0 => 'fin_campagne_j0',
                $jours <= 3 => 'fin_campagne_j3',
                $jours <= 7 => 'fin_campagne_j7',
                default     => null,
            };
            if (!$type) continue;

            $alert = self::notify(
                $type,
                "Fin de campagne — {$c->name}",
                $jours <= 0
                    ? "La campagne « {$c->name} » se termine aujourd'hui."
                    : "La campagne « {$c->name} » se termine dans {$jours} jour(s) (le {$c->end_date->format('d/m/Y')}).",
                $c,
                [
                    'lien'        => route('admin.campaigns.show', $c),
                    'dedup_extra' => $type, // dedup différent par seuil j7/j3/j0
                ]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerPanneauxMaintenancePourLongtemps(): int
    {
        $count = 0;
        $panels = Panel::where('status', 'maintenance')
            ->where('updated_at', '<=', now()->subDays(7))
            ->get(['id', 'reference', 'name', 'updated_at']);

        foreach ($panels as $p) {
            $jours = (int) $p->updated_at->diffInDays(now());
            $alert = self::notify(
                'panneau_maintenance',
                "Maintenance prolongée — {$p->reference}",
                "Le panneau {$p->reference} ({$p->name}) est en maintenance depuis {$jours} jours.",
                $p,
                [
                    'niveau' => 'warning',
                    'lien'   => route('admin.panels.show', $p),
                ]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerPosesEnRetard(): int
    {
        $count = 0;
        $tasks = PoseTask::where('status', 'planifiee')
            ->where('scheduled_at', '<', now())
            ->with(['panel:id,reference', 'campaign:id,name'])
            ->get(['id', 'panel_id', 'campaign_id', 'scheduled_at']);

        foreach ($tasks as $t) {
            $ref = $t->panel?->reference ?? "#{$t->panel_id}";
            $alert = self::notify(
                'pose_planifiee',
                "Pose en retard — {$ref}",
                "La pose du panneau {$ref}"
                    . ($t->campaign ? " (campagne « {$t->campaign->name} »)" : '')
                    . " était planifiée le {$t->scheduled_at->format('d/m/Y à H:i')} et n'a pas été réalisée.",
                $t,
                [
                    'niveau' => 'warning',
                    'lien'   => route('admin.pose-tasks.show', $t),
                ]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    public function triggerPosesSansPige(): int
    {
        $count = 0;
        $tasks = PoseTask::where('status', 'realisee')
            ->whereNotNull('campaign_id')
            ->where('done_at', '<', now()->subHours(24))
            ->with(['panel:id,reference', 'campaign:id,name'])
            ->get(['id', 'panel_id', 'campaign_id', 'done_at']);

        foreach ($tasks as $t) {
            $hasPige = Pige::where('panel_id', $t->panel_id)
                ->where('campaign_id', $t->campaign_id)
                ->where('status', '!=', 'rejete')
                ->exists();

            if ($hasPige) continue;

            $ref = $t->panel?->reference ?? "#{$t->panel_id}";
            $alert = self::notify(
                'pose_terminee',
                "Pose sans pige — {$ref}",
                "Le panneau {$ref}"
                    . ($t->campaign ? " (campagne « {$t->campaign->name} »)" : '')
                    . " a été posé le {$t->done_at->format('d/m/Y')} mais aucune pige n'a été enregistrée.",
                $t,
                [
                    'niveau'      => 'warning',
                    'lien'        => route('admin.pose-tasks.show', $t),
                    'dedup_extra' => 'sans_pige_24h',
                ]
            );
            if ($alert) $count++;
        }
        return $count;
    }

    // ══════════════════════════════════════════════════════════════════
    // ALERTES INSTANTANÉES — appelées depuis les controllers / observers
    // ══════════════════════════════════════════════════════════════════

    public function notifyPoseComplete(PoseTask $task, bool $hasPige): void
    {
        if (!$task->campaign_id) return;

        $ref = $task->panel?->reference ?? "#{$task->panel_id}";

        if ($hasPige) {
            self::notify(
                'pose_terminee',
                "Pose réalisée — {$ref}",
                "Le panneau {$ref} a été posé avec succès. Pige photo enregistrée.",
                $task,
                ['lien' => route('admin.pose-tasks.show', $task)]
            );
            return;
        }

        self::notify(
            'pose_terminee',
            "Pose terminée — pige manquante · {$ref}",
            "Panneau {$ref} posé avec succès, mais aucune photo de pige enregistrée. Pensez à uploader la preuve.",
            $task,
            [
                'niveau'      => 'warning',
                'lien'        => route('admin.pose-tasks.show', $task),
                'dedup_extra' => 'complete_sans_pige',
            ]
        );
    }

    public function notifyPigeRejected(Pige $pige, string $reason): void
    {
        $ref = $pige->panel?->reference ?? "#{$pige->panel_id}";

        self::notify(
            'avancement_pose',
            "Pige rejetée — {$ref}",
            "La pige du panneau {$ref} a été rejetée : {$reason}",
            $pige,
            ['niveau' => 'warning']
        );
    }
}
