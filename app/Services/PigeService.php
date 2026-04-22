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
    // ══════════════════════════════════════════════════════════════
    public function verify(Pige $pige, User $supervisor): array
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
        return ['ok' => true];
    }

    // ══════════════════════════════════════════════════════════════
    // REJETER une pige
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