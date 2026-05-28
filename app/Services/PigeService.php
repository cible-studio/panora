<?php
// app/Services/PigeService.php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PigeService
{
    // ══════════════════════════════════════════════════════════════
    // UPLOAD — 1 ou plusieurs photos pour un panneau
    // ══════════════════════════════════════════════════════════════
    /**
     * @param  UploadedFile[]  $photos     Tableau de fichiers uploadés
     * @param  int             $panelId
     * @param  int|null        $campaignId
     * @param  User            $uploader
     * @param  array           $meta       [taken_at, gps_lat, gps_lng, notes]
     * @return array           ['ok', 'count', 'pige_ids', 'error']
     */
    public function upload(
        array   $photos,
        int     $panelId,
        ?int    $campaignId,
        User    $uploader,
        array   $meta = []
    ): array {
        $panel = Panel::find($panelId);
        if (!$panel) return $this->error('Panneau introuvable.');

        if ($campaignId) {
            $campaign = Campaign::find($campaignId);
            if (!$campaign) return $this->error('Campagne introuvable.');
            if ($campaign->status->isTerminal()) {
                return $this->error("Campagne {$campaign->status->label()} — impossible d'ajouter des piges.");
            }
        }

        return DB::transaction(function () use ($photos, $panelId, $campaignId, $uploader, $meta) {
            $pigeIds = [];

            foreach ($photos as $photo) {
                $path = $this->_storePhoto($photo, $panelId, $campaignId);

                $pige = Pige::create([
                    'panel_id'    => $panelId,
                    'campaign_id' => $campaignId,
                    'user_id'     => $uploader->id,
                    'photo_path'  => $path,
                    'taken_at'    => $meta['taken_at'] ?? now(),
                    'gps_lat'     => $meta['gps_lat'] ?? null,
                    'gps_lng'     => $meta['gps_lng'] ?? null,
                    'notes'       => $meta['notes'] ?? null,
                    'status'      => 'en_attente',
                ]);

                $pigeIds[] = $pige->id;

                Log::info('pige.uploaded', [
                    'pige_id'     => $pige->id,
                    'panel_id'    => $panelId,
                    'campaign_id' => $campaignId,
                    'by'          => $uploader->id,
                ]);
            }

            return ['ok' => true, 'count' => count($pigeIds), 'pige_ids' => $pigeIds];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // VÉRIFIER une pige
    //
    // $autoLocate : déclenche l'auto-géolocalisation du panneau juste après
    //   la validation. Le batch (verifyBatch) passe false et recalcule chaque
    //   panneau UNE seule fois après la boucle (cf. recomputePanelsGeo) pour
    //   éviter N recalculs redondants sur le même panneau.
    // ══════════════════════════════════════════════════════════════
    public function verify(Pige $pige, User $supervisor, bool $autoLocate = true): array
    {
        if ($pige->isVerifiee()) {
            return $this->error('Cette pige est déjà vérifiée.');
        }

        // Lock optimiste
        $updated = Pige::where('id', $pige->id)
            ->whereNotIn('status', ['verifie'])
            ->update([
                'status'      => 'verifie',
                'verified_by' => $supervisor->id,
                'verified_at' => now(),
            ]);

        if (!$updated) return $this->error('Cette pige a déjà été traitée.');

        Log::info('pige.verified', ['pige_id' => $pige->id, 'by' => $supervisor->id]);

        // Auto-géolocalisation du panneau à partir de ses piges validées.
        // Best-effort : un échec ne doit jamais faire échouer la validation.
        if ($autoLocate && $pige->gps_lat !== null && $pige->gps_lng !== null) {
            try {
                if ($panel = $pige->panel) {
                    app(PanelGeoLocator::class)->recomputeFromPiges($panel);
                }
            } catch (\Throwable $e) {
                Log::warning('pige.verify.geo_autolocate_failed', [
                    'pige_id' => $pige->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return ['ok' => true];
    }

    /**
     * Recalcule le GPS de plusieurs panneaux, 1 seule fois chacun.
     * Best-effort par panneau (un échec n'interrompt pas les autres).
     * Utilisé par les validations groupées pour éviter les recalculs
     * redondants quand plusieurs piges d'un même panneau sont validées.
     *
     * @param  array<int, int>  $panelIds
     */
    public function recomputePanelsGeo(array $panelIds): void
    {
        $panelIds = array_values(array_unique(array_filter($panelIds)));
        if (empty($panelIds)) {
            return;
        }

        $locator = app(PanelGeoLocator::class);
        foreach (Panel::whereIn('id', $panelIds)->get() as $panel) {
            try {
                $locator->recomputeFromPiges($panel);
            } catch (\Throwable $e) {
                Log::warning('pige.geo_autolocate_failed', [
                    'panel_id' => $panel->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    // ══════════════════════════════════════════════════════════════
    // REJETER une pige
    //
    // En plus du marquage 'rejete', on déclenche le "Re-pige auto" :
    //   1. La PoseTask liée repasse à 'en_cours' (était COMPLETED après
    //      l'upload via Observer) → le lien public se réactive.
    //   2. Notification WhatsApp au technicien avec le motif + le lien
    //      pour reprendre la photo.
    //
    // Élimine un aller-retour MP↔tech : avant, le MP devait demander
    // manuellement au tech de refaire la photo (téléphone/SMS séparé).
    // ══════════════════════════════════════════════════════════════
    public function reject(Pige $pige, User $supervisor, string $reason): array
    {
        if ($pige->isVerifiee()) {
            return $this->error('Impossible de rejeter une pige déjà vérifiée.');
        }
        if (trim($reason) === '') {
            return $this->error('Le motif de rejet est obligatoire.');
        }

        $updated = Pige::where('id', $pige->id)
            ->whereNotIn('status', ['verifie'])
            ->update([
                'status'           => 'rejete',
                'rejection_reason' => $reason,
                'verified_by'      => $supervisor->id,
                'verified_at'      => now(),
            ]);

        if (!$updated) return $this->error('Cette pige a déjà été traitée.');

        Log::info('pige.rejected', ['pige_id' => $pige->id, 'reason' => $reason, 'by' => $supervisor->id]);

        // ─── Re-pige auto : réactiver la pose + notifier le tech ───
        try {
            $pige->loadMissing(['poseTask.technicien', 'poseTask.panel']);
            $poseTask = $pige->poseTask;

            if ($poseTask) {
                // Réactive la pose (était passée à COMPLETED via Observer
                // à l'upload). Le tech peut maintenant re-uploader via le
                // même lien public.
                $currentStatus = $poseTask->status?->value ?? $poseTask->status;
                if ($currentStatus === \App\Enums\PoseTaskStatus::COMPLETED->value) {
                    $poseTask->forceFill([
                        'status'  => \App\Enums\PoseTaskStatus::IN_PROGRESS->value,
                        'done_at' => null,
                    ])->save();
                }

                // WhatsApp ciblé au technicien avec motif + lien
                $tech = $poseTask->technicien;
                if ($tech?->whatsapp_number) {
                    try {
                        app(\App\Services\PoseService::class)
                            ->notifyTechnicianOnWhatsApp($poseTask, [
                                'preamble' => "❌ Photo refusée — motif : {$reason}\n📷 Reprends une photo et upload-la via le lien :",
                            ]);
                    } catch (\Throwable $e) {
                        Log::warning('pige.reject.whatsapp_failed', [
                            'pige_id' => $pige->id,
                            'tech_id' => $tech->id,
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('pige.reject.repige_auto_failed', [
                'pige_id' => $pige->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return ['ok' => true];
    }

    // ══════════════════════════════════════════════════════════════
    // SUPPRIMER une pige (+ fichier)
    // ══════════════════════════════════════════════════════════════
    public function delete(Pige $pige): array
    {
        if ($pige->isVerifiee()) {
            return $this->error('Impossible de supprimer une pige déjà vérifiée.');
        }

        // Supprimer le fichier du storage
        if (Storage::exists($pige->photo_path)) {
            Storage::delete($pige->photo_path);
        }
        if ($pige->photo_thumb && Storage::exists($pige->photo_thumb)) {
            Storage::delete($pige->photo_thumb);
        }

        $pige->delete();

        Log::info('pige.deleted', ['pige_id' => $pige->id]);
        return ['ok' => true];
    }

    // ══════════════════════════════════════════════════════════════
    // STATS globales pour le dashboard
    // ══════════════════════════════════════════════════════════════
    public function getStats(): array
    {
        $raw = Pige::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
            SUM(CASE WHEN status = 'verifie'    THEN 1 ELSE 0 END) as verifie,
            SUM(CASE WHEN status = 'rejete'     THEN 1 ELSE 0 END) as rejete
        ")->first();

        return [
            'total'      => (int) ($raw->total      ?? 0),
            'en_attente' => (int) ($raw->en_attente ?? 0),
            'verifie'    => (int) ($raw->verifie    ?? 0),
            'rejete'     => (int) ($raw->rejete     ?? 0),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // STATS pour une campagne
    // ══════════════════════════════════════════════════════════════
    public function getStatsForCampaign(int $campaignId): array
    {
        $raw = Pige::where('campaign_id', $campaignId)
            ->selectRaw("
                COUNT(DISTINCT panel_id) as panneaux_piges,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                SUM(CASE WHEN status = 'verifie'    THEN 1 ELSE 0 END) as verifie,
                SUM(CASE WHEN status = 'rejete'     THEN 1 ELSE 0 END) as rejete
            ")->first();

        return [
            'panneaux_piges' => (int) ($raw->panneaux_piges ?? 0),
            'total'          => (int) ($raw->total          ?? 0),
            'en_attente'     => (int) ($raw->en_attente     ?? 0),
            'verifie'        => (int) ($raw->verifie        ?? 0),
            'rejete'         => (int) ($raw->rejete         ?? 0),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // HELPER privé — stockage photo
    // ══════════════════════════════════════════════════════════════
    private function _storePhoto(UploadedFile $file, int $panelId, ?int $campaignId): string
    {
        $ext      = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid() . '.' . $ext;
        $folder   = 'piges/' . ($campaignId ? "campaigns/{$campaignId}" : 'libre') . "/panel_{$panelId}";

        return $file->storeAs($folder, $filename, 'public');
    }

    private function error(string $msg): array
    {
        return ['ok' => false, 'error' => $msg];
    }
}