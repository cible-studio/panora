<?php
// app/Models/Pige.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class Pige extends Model
{
    protected $fillable = [
        'panel_id', 'campaign_id', 'pose_task_id', 'user_id', 'verified_by',
        'photo_path', 'photo_thumb',
        'gps_lat', 'gps_lng',
        'geo_distance_m', 'geo_check',
        'taken_at', 'verified_at',
        'status', 'rejection_reason', 'is_off_schedule', 'notes',
        'client_uuid',
        'archived_at',
    ];

    protected $casts = [
        'taken_at'       => 'datetime',
        'verified_at'    => 'datetime',
        'archived_at'    => 'datetime',
        'gps_lat'        => 'float',
        'gps_lng'        => 'float',
        'geo_distance_m' => 'integer',
        'is_off_schedule' => 'boolean',
    ];

    // ══════════════════════════════════════════════════════════════
    // RELATIONS
    // ══════════════════════════════════════════════════════════════

    public function panel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Lien explicite vers la tâche de pose qui a généré cette pige.
     * Peut être null pour les piges legacy (uploadées via l'ancien
     * lien campagne Campaign.pige_token avant l'unification du workflow).
     */
    public function poseTask(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PoseTask::class);
    }

    public function technicien(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verificateur(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ══════════════════════════════════════════════════════════════
    // SCOPES
    // ══════════════════════════════════════════════════════════════

    public function scopeEnAttente(Builder $q): Builder
    {
        return $q->where('status', 'en_attente');
    }

    public function scopeVerifiees(Builder $q): Builder
    {
        return $q->where('status', 'verifie');
    }

    public function scopeRejetees(Builder $q): Builder
    {
        return $q->where('status', 'rejete');
    }

    public function scopeForCampaign(Builder $q, int $campaignId): Builder
    {
        return $q->where('campaign_id', $campaignId);
    }

    public function scopeForPanel(Builder $q, int $panelId): Builder
    {
        return $q->where('panel_id', $panelId);
    }

    /**
     * Piges rattachées à UNE PoseTask précise — indispensable pour le
     * multi-poses (rechange, retouche). Ajout 2026-08-05 après bug
     * signalé : le tech ouvrait le lien d'une pose rechange fraîchement
     * créée et voyait la pige "verifie" de la pose INITIALE (leak par
     * le scoping panel_id + campaign_id historique).
     *
     * Cas gérés :
     *   1. Nouvelle pige (post-fix) : `piges.pose_task_id = X` → match.
     *   2. Pige LEGACY (créée AVANT que uploadPhoto câble pose_task_id) :
     *      `pose_task_id IS NULL` + panel+campaign matchent la pose.
     *      → on la retourne pour préserver l'historique, mais avec
     *      la nouvelle logique d'upload correcte, ce cas s'éteint
     *      naturellement (nouvelles piges toujours scopées).
     *
     * @param  Builder   $q
     * @param  PoseTask  $task  La pose dont on veut les piges
     */
    public function scopeForPoseTask(Builder $q, \App\Models\PoseTask $task): Builder
    {
        return $q->where(function ($qq) use ($task) {
            $qq->where('pose_task_id', $task->id)
               ->orWhere(function ($qqq) use ($task) {
                   $qqq->whereNull('pose_task_id')
                       ->where('panel_id', $task->panel_id)
                       ->when($task->campaign_id, fn($x) => $x->where('campaign_id', $task->campaign_id));
               });
        });
    }

    /**
     * Pige active (campagne vivante). Par défaut on n'affiche que celles-ci
     * dans la liste admin. Les piges archivées sont accessibles via la vue
     * dédiée /admin/piges/archives.
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    /**
     * Pige archivée (campagne associée supprimée ou cleanup manuel).
     * Conservée pour valeur légale + audit + facturation, mais sortie
     * de la liste active.
     */
    public function scopeArchived(Builder $q): Builder
    {
        return $q->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return !is_null($this->archived_at);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    public function isEnAttente(): bool { return $this->status === 'en_attente'; }
    public function isVerifiee(): bool  { return $this->status === 'verifie'; }
    public function isRejetee(): bool   { return $this->status === 'rejete'; }
    public function isTerminal(): bool  { return in_array($this->status, ['verifie', 'rejete']); }

    public function hasGps(): bool
    {
        return !is_null($this->gps_lat) && !is_null($this->gps_lng);
    }

    public function getPhotoUrl(): string
    {
        return Storage::url($this->photo_path);
    }

    public function getThumbUrl(): string
    {
        if ($this->photo_thumb && Storage::exists($this->photo_thumb)) {
            return Storage::url($this->photo_thumb);
        }
        return $this->getPhotoUrl();
    }

    public function getGoogleMapsUrl(): ?string
    {
        if (!$this->hasGps()) return null;
        return "https://maps.google.com/?q={$this->gps_lat},{$this->gps_lng}";
    }

    /**
     * Badge de cohérence géographique (anti-fraude) pour l'affichage MP.
     * Le libellé de distance ({distance}m) est ajouté côté vue si pertinent.
     *
     * @return array{label: string, short: string, icon: string, color: string, bg: string}
     */
    public function geoBadge(): array
    {
        return match($this->geo_check) {
            'ok'           => ['label' => 'Cohérent',                 'short' => 'OK',        'icon' => '✓', 'color' => '#16a34a', 'bg' => 'rgba(34,197,94,.12)'],
            'warn'         => ['label' => 'À vérifier',               'short' => 'À vérifier','icon' => '⚠', 'color' => '#d97706', 'bg' => 'rgba(245,158,11,.12)'],
            'out'          => ['label' => 'Hors-zone',                'short' => 'Hors-zone', 'icon' => '✖', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,.12)'],
            'no_gps'       => ['label' => 'Pas de GPS',               'short' => 'Sans GPS',  'icon' => '∅', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,.12)'],
            'no_panel_gps' => ['label' => 'Panneau non géolocalisé',  'short' => 'Panneau ?', 'icon' => '?', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,.12)'],
            default        => ['label' => '—',                        'short' => '—',         'icon' => '',  'color' => '#6b7280', 'bg' => 'rgba(107,114,128,.12)'],
        };
    }

    public function getStatusConfig(): array
    {
        return match($this->status) {
            'en_attente' => ['label'=>'En attente', 'color'=>'#f97316', 'bg'=>'rgba(249,115,22,.1)', 'bd'=>'rgba(249,115,22,.3)'],
            'verifie'    => ['label'=>'Vérifiée',   'color'=>'#22c55e', 'bg'=>'rgba(34,197,94,.1)',  'bd'=>'rgba(34,197,94,.3)'],
            'rejete'     => ['label'=>'Rejetée',    'color'=>'#ef4444', 'bg'=>'rgba(239,68,68,.1)',  'bd'=>'rgba(239,68,68,.3)'],
            default      => ['label'=>$this->status, 'color'=>'#6b7280','bg'=>'rgba(107,114,128,.1)','bd'=>'rgba(107,114,128,.3)'],
        };
    }
}