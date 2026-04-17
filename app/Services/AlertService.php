<?php
// app/Services/AlertService.php

namespace App\Services;

use App\Models\Alert;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\Reservation;

class AlertService
{
    // ══════════════════════════════════════════════════════════════
    // GÉNÉRATION GLOBALE — appelée par artisan alerts:generate
    // ══════════════════════════════════════════════════════════════
    public function generateAll(): array
    {
        return [
            'reservations' => $this->alertesReservationsEnAttente(),
            'maintenances' => $this->alertesMaintenancesUrgentes(),
            'campagnes'    => $this->alertesCampagnesExpirantBientot(),
            'panneaux'     => $this->alertesPanneauxEnMaintenance(),
            'poses'        => $this->alertesPosesEnRetard(),
            'piges'        => $this->alertesPosesSansPige(),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE : RÉSERVATIONS en attente > 48h
    // ══════════════════════════════════════════════════════════════
    public function alertesReservationsEnAttente(): int
    {
        $count = 0;

        $reservations = Reservation::with('client')
            ->where('status', 'en_attente')
            ->where('created_at', '<=', now()->subHours(48))
            ->get();

        foreach ($reservations as $reservation) {
            if ($this->_exists('reservation', 'reservation', $reservation->id, 'en_attente_48h')) continue;

            $this->_create([
                'type'         => 'reservation',
                'niveau'       => 'warning',
                'title'        => "Réservation en attente — {$reservation->client?->name}",
                'message'      => "La réservation {$reservation->reference} est en attente de confirmation depuis plus de 48h.",
                'related_type' => 'reservation',
                'related_id'   => $reservation->id,
            ]);
            $count++;
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE : MAINTENANCES urgentes non résolues > 24h
    // ══════════════════════════════════════════════════════════════
    public function alertesMaintenancesUrgentes(): int
    {
        $count = 0;

        // Vérifier si le modèle Maintenance existe dans ce projet
        if (!class_exists(\App\Models\Maintenance::class)) return 0;

        $maintenances = \App\Models\Maintenance::with('panel')
            ->where('priorite', 'urgente')
            ->where('statut', '!=', 'resolu')
            ->where('created_at', '<=', now()->subHours(24))
            ->get();

        foreach ($maintenances as $m) {
            if ($this->_exists('maintenance', 'maintenance', $m->id, 'urgente_24h')) continue;

            $this->_create([
                'type'         => 'maintenance',
                'niveau'       => 'danger',
                'title'        => "Maintenance urgente — {$m->panel?->reference}",
                'message'      => "Panne urgente non résolue : {$m->type_panne}. Panneau {$m->panel?->reference} hors service.",
                'related_type' => 'maintenance',
                'related_id'   => $m->id,
            ]);
            $count++;
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE : CAMPAGNES expirant dans <= 14 jours
    // ══════════════════════════════════════════════════════════════
    public function alertesCampagnesExpirantBientot(): int
    {
        $count = 0;

        $campagnes = Campaign::with('client')
            ->where('status', 'actif')
            ->whereBetween('end_date', [now(), now()->addDays(14)])
            ->get();

        foreach ($campagnes as $c) {
            $jours = (int) now()->startOfDay()->diffInDays($c->end_date->startOfDay());
            $key   = "expire_{$jours}j";
            if ($this->_exists('campagne', 'campaign', $c->id, $key)) continue;

            $this->_create([
                'type'         => 'campagne',
                'niveau'       => $jours <= 7 ? 'danger' : 'warning',
                'title'        => "Campagne expire bientôt — {$c->name}",
                'message'      => "La campagne \"{$c->name}\" se termine dans {$jours} jour(s) ({$c->end_date->format('d/m/Y')}). Pensez au renouvellement.",
                'related_type' => 'campaign',
                'related_id'   => $c->id,
            ]);
            $count++;
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE : PANNEAUX en maintenance > 7 jours
    // ══════════════════════════════════════════════════════════════
    public function alertesPanneauxEnMaintenance(): int
    {
        $count = 0;

        $panneaux = Panel::where('status', 'maintenance')
            ->where('updated_at', '<=', now()->subDays(7))
            ->get(['id', 'reference', 'name', 'updated_at']);

        foreach ($panneaux as $p) {
            if ($this->_exists('panneau', 'panel', $p->id, 'maintenance_7j')) continue;

            $jours = (int) $p->updated_at->diffInDays(now());
            $this->_create([
                'type'         => 'panneau',
                'niveau'       => 'warning',
                'title'        => "Panneau en maintenance prolongée — {$p->reference}",
                'message'      => "Le panneau {$p->reference} ({$p->name}) est en maintenance depuis {$jours} jours.",
                'related_type' => 'panel',
                'related_id'   => $p->id,
            ]);
            $count++;
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE : POSES en retard (date planifiée passée)
    // ══════════════════════════════════════════════════════════════
    public function alertesPosesEnRetard(): int
    {
        $count = 0;

        $tasks = PoseTask::where('status', 'planifiee')
            ->where('scheduled_at', '<', now())
            ->with(['panel:id,reference', 'campaign:id,name'])
            ->get(['id', 'panel_id', 'campaign_id', 'scheduled_at']);

        foreach ($tasks as $t) {
            if ($this->_exists('pose', 'PoseTask', $t->id, 'en_retard')) continue;

            $ref = $t->panel?->reference ?? "#{$t->panel_id}";
            $this->_create([
                'type'         => 'pose',
                'niveau'       => 'warning',
                'title'        => "Pose OOH en retard — {$ref}",
                'message'      => "La tâche de pose du panneau {$ref}"
                    . ($t->campaign ? " (campagne « {$t->campaign->name} »)" : '')
                    . " était planifiée le {$t->scheduled_at->format('d/m/Y à H:i')} et n'a pas encore été réalisée.",
                'related_type' => 'PoseTask',
                'related_id'   => $t->id,
            ]);
            $count++;
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE : POSES réalisées sans pige > 24h
    // ══════════════════════════════════════════════════════════════
    public function alertesPosesSansPige(): int
    {
        $count = 0;

        $tasks = PoseTask::where('status', 'realisee')
            ->whereNotNull('campaign_id')
            ->where('done_at', '<', now()->subHours(24))
            ->get(['id', 'panel_id', 'campaign_id', 'done_at']);

        foreach ($tasks as $t) {
            $hasPige = Pige::where('panel_id', $t->panel_id)
                ->where('campaign_id', $t->campaign_id)
                ->where('status', '!=', 'rejete')
                ->exists();

            if ($hasPige) continue;
            if ($this->_exists('pige', 'PoseTask', $t->id, 'sans_pige_24h')) continue;

            $task = $t->load(['panel:id,reference', 'campaign:id,name']);
            $ref  = $task->panel?->reference ?? "#{$t->panel_id}";

            $this->_create([
                'type'         => 'pige',
                'niveau'       => 'warning',
                'title'        => "Pose réalisée sans pige — {$ref}",
                'message'      => "Le panneau {$ref}"
                    . ($task->campaign ? " (campagne « {$task->campaign->name} »)" : '')
                    . " a été posé le {$t->done_at->format('d/m/Y')} mais aucune pige photo n'a été enregistrée.",
                'related_type' => 'PoseTask',
                'related_id'   => $t->id,
            ]);
            $count++;
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    // ALERTES INSTANTANÉES — appelées depuis les controllers
    // ══════════════════════════════════════════════════════════════

    /**
     * Appelée dans PoseController::markComplete()
     * Alerte si la pose est réalisée sans pige photo
     */
    public function notifyPoseComplete(PoseTask $task, bool $hasPige): void
    {
        if ($hasPige || !$task->campaign_id) return;

        $ref = $task->panel?->reference ?? "#{$task->panel_id}";

        // Ne pas créer de doublon si une alerte existe déjà pour cette tâche
        if ($this->_exists('pige', 'PoseTask', $task->id, 'complete_sans_pige')) return;

        $this->_create([
            'type'         => 'pige',
            'niveau'       => 'info',
            'title'        => "Pose réalisée — pige manquante · {$ref}",
            'message'      => "Panneau {$ref} posé avec succès. Aucune photo de pige enregistrée. Pensez à uploader la preuve d'affichage.",
            'related_type' => 'PoseTask',
            'related_id'   => $task->id,
        ]);
    }

    /**
     * Appelée dans PigeController::reject()
     */
    public function notifyPigeRejected(Pige $pige, string $reason): void
    {
        $ref = $pige->panel?->reference ?? "#{$pige->panel_id}";

        $this->_create([
            'type'         => 'pige',
            'niveau'       => 'warning',
            'title'        => "Pige rejetée — {$ref}",
            'message'      => "La pige du panneau {$ref} a été rejetée : {$reason}",
            'related_type' => 'Pige',
            'related_id'   => $pige->id,
        ]);
    }

    /**
     * Appelée depuis n'importe quel module pour créer une alerte manuelle
     */
    public static function create(
        string $type,
        string $niveau,
        string $title,
        string $message,
        $model = null
    ): Alert {
        return Alert::create([
            'type'         => $type,
            'niveau'       => $niveau,
            'title'        => $title,
            'message'      => $message,
            'related_type' => $model ? class_basename($model) : null,
            'related_id'   => $model?->id,
            'is_read'      => false,
            'triggered_at' => now(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // STATS — pour les badges sidebar et pages modules
    // ══════════════════════════════════════════════════════════════

    /**
     * Alertes non lues pour un modèle précis
     * Usage : $this->alertService->getForModel(PoseTask::class, $id)
     */
    public function getForModel(string $modelClass, int $modelId, int $limit = 5)
    {
        $basename = class_basename($modelClass);

        return Alert::where('related_type', $basename)
            ->where('related_id', $modelId)
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Count alertes non lues par module (pour les badges)
     */
    public function countForModule(string $type): int
    {
        return Alert::where('type', $type)->where('is_read', false)->count();
    }

    /**
     * Résumé global par type + niveau
     */
    public function getModuleSummary(): array
    {
        $raw = Alert::where('is_read', false)
            ->selectRaw('type, niveau, COUNT(*) as count')
            ->groupBy('type', 'niveau')
            ->get();

        $summary = [];
        foreach ($raw as $row) {
            $summary[$row->type][$row->niveau] = $row->count;
        }

        return $summary;
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════

    /**
     * Vérifie si une alerte similaire (non lue) existe déjà
     * pour éviter les doublons à chaque run de la commande
     *
     * @param string $alertType   → valeur de la colonne 'type'
     * @param string $relatedType → valeur de la colonne 'related_type'
     * @param int    $relatedId   → valeur de la colonne 'related_id'
     * @param string $titleKey    → mot-clé présent dans le titre
     */
    private function _exists(
        string $alertType,
        string $relatedType,
        int    $relatedId,
        string $titleKey
    ): bool {
        return Alert::where('type', $alertType)
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->where('title', 'like', "%{$titleKey}%")
            ->where('is_read', false)
            ->exists();
    }

    private function _create(array $data): ?Alert
    {
        try {
            return Alert::create(array_merge([
                'is_read'      => false,
                'triggered_at' => now(),
            ], $data));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('AlertService._create failed', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
            return null;
        }
    }
}