<?php
// app/Services/PigeService.php
namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\PigeStatus;
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
    public const MAX_FILE_SIZE_BYTES = 30 * 1024 * 1024;
    public const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    private const CAMPAIGN_STATUSES_ALLOW_UPLOAD = [
        CampaignStatus::ACTIF->value,
        CampaignStatus::POSE->value,
    ];

    /**
     * Upload d'une pige avec toutes les validations métier
     */
    public function upload(array $data, UploadedFile $file, User $uploader): array
    {
        // Validation panneau
        $panel = Panel::withTrashed()->find($data['panel_id']);
        if (!$panel) {
            return $this->error('Panneau introuvable.');
        }

        if ($panel->trashed()) {
            return $this->error('Ce panneau a été supprimé du système.');
        }

        // Validation campagne
        $campaign = null;
        if (!empty($data['campaign_id'])) {
            $campaign = Campaign::with('client')->find($data['campaign_id']);
            if (!$campaign) {
                return $this->error('Campagne introuvable.');
            }

            if ($campaign->status->isTerminal()) {
                return $this->error(
                    "Impossible d'ajouter une pige : la campagne est « {$campaign->status->label()} »."
                );
            }

            if (!in_array($campaign->status->value, self::CAMPAIGN_STATUSES_ALLOW_UPLOAD)) {
                return $this->error(
                    "Statut de campagne « {$campaign->status->label()} » non compatible avec l'ajout de piges."
                );
            }

            $panelInCampaign = $campaign->panels()->where('panels.id', $panel->id)->exists();
            if (!$panelInCampaign) {
                return $this->error(
                    "Le panneau {$panel->reference} ne fait pas partie de la campagne « {$campaign->name} »."
                );
            }
        }

        // Validation fichier
        if (!$this->validateFile($file)) {
            return $this->error('Fichier invalide ou trop volumineux.');
        }

        // Nettoyage GPS
        $gpsLat = $this->sanitizeGps($data['gps_lat'] ?? null, -90, 90);
        $gpsLng = $this->sanitizeGps($data['gps_lng'] ?? null, -180, 180);

        // Upload et création
        return DB::transaction(function () use ($file, $panel, $campaign, $uploader, $data, $gpsLat, $gpsLng) {
            $path = $file->store('piges/' . now()->format('Y/m'), 'public');
            if (!$path) {
                throw new \RuntimeException('Erreur de stockage du fichier.');
            }

            $pige = Pige::create([
                'panel_id'    => $panel->id,
                'campaign_id' => $campaign?->id,
                'user_id'     => $uploader->id,
                'photo_path'  => $path,
                'taken_at'    => $data['taken_at'],
                'gps_lat'     => $gpsLat,
                'gps_lng'     => $gpsLng,
                'notes'       => $data['notes'] ?? null,
                'status'      => PigeStatus::PENDING->value,
            ]);

            Log::info('pige.uploaded', [
                'pige_id'     => $pige->id,
                'panel_id'    => $panel->id,
                'campaign_id' => $campaign?->id,
                'user_id'     => $uploader->id,
                'has_gps'     => $gpsLat !== null,
            ]);

            return $this->success('Pige enregistrée avec succès.', ['pige' => $pige]);
        });
    }

    /**
     * Validation de la pige
     */
    public function verify(Pige $pige, User $verifier): array
    {
        if ($pige->status !== PigeStatus::PENDING->value) {
            $msg = $pige->status === PigeStatus::VERIFIED->value 
                ? 'Cette pige est déjà validée.' 
                : 'Cette pige a été rejetée et ne peut pas être validée.';
            return $this->success($msg, ['already' => true]);
        }

        $updated = Pige::where('id', $pige->id)
            ->where('status', PigeStatus::PENDING->value)
            ->update([
                'status'      => PigeStatus::VERIFIED->value,
                'verified_by' => $verifier->id,
                'verified_at' => now(),
            ]);

        if (!$updated) {
            return $this->success('Pige déjà traitée.', ['already' => true]);
        }

        Log::info('pige.verified', [
            'pige_id'     => $pige->id,
            'verified_by' => $verifier->id,
        ]);

        return $this->success('Pige validée avec succès. ✅');
    }

    /**
     * Rejet de la pige
     */
    public function reject(Pige $pige, User $rejector, string $reason): array
    {
        if ($pige->status !== PigeStatus::PENDING->value) {
            return $this->error('Cette pige ne peut plus être rejetée.');
        }

        $updated = Pige::where('id', $pige->id)
            ->where('status', PigeStatus::PENDING->value)
            ->update([
                'status'           => PigeStatus::REJECTED->value,
                'verified_by'      => $rejector->id,
                'verified_at'      => now(),
                'rejection_reason' => trim($reason),
            ]);

        if (!$updated) {
            return $this->error('Impossible de rejeter cette pige.');
        }

        Log::info('pige.rejected', [
            'pige_id'     => $pige->id,
            'rejected_by' => $rejector->id,
            'reason'      => $reason,
        ]);

        return $this->success('Pige rejetée. Le technicien doit soumettre une nouvelle photo.');
    }

    /**
     * Suppression d'une pige
     */
    public function destroy(Pige $pige, User $actor, bool $force = false): array
    {
        if ($pige->status === PigeStatus::VERIFIED->value && !$force) {
            return $this->error('Les piges validées ne peuvent pas être supprimées sans autorisation spéciale.');
        }

        $photoPath = $pige->photo_path;

        DB::transaction(function () use ($pige) {
            $pige->delete();
        });

        if ($photoPath && Storage::disk('public')->exists($photoPath)) {
            Storage::disk('public')->delete($photoPath);
        }

        Log::info('pige.deleted', [
            'pige_id'    => $pige->id,
            'deleted_by' => $actor->id,
            'forced'     => $force,
        ]);

        return $this->success('Pige supprimée.');
    }

    /**
     * Statistiques globales
     */
    public function globalStats(): object
    {
        $stats = Pige::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
            SUM(CASE WHEN status = 'verifie'    THEN 1 ELSE 0 END) as verifie,
            SUM(CASE WHEN status = 'rejete'     THEN 1 ELSE 0 END) as rejete
        ")->first();

        return (object) [
            'total'      => (int) ($stats->total ?? 0),
            'en_attente' => (int) ($stats->en_attente ?? 0),
            'verifie'    => (int) ($stats->verifie ?? 0),
            'rejete'     => (int) ($stats->rejete ?? 0),
        ];
    }

    /**
     * Contexte pour l'UI (campagne et panneau)
     */
    public function resolveContext(?int $campaignId, ?int $panelId): array
    {
        $ctx = [
            'can_upload'     => true,
            'upload_blocked' => false,
            'block_message'  => null,
            'warning'        => null,
        ];

        if ($campaignId) {
            $campaign = Campaign::find($campaignId);
            if ($campaign && $campaign->status->isTerminal()) {
                $ctx['can_upload'] = false;
                $ctx['upload_blocked'] = true;
                $ctx['block_message'] = "Campagne « {$campaign->status->label()} » — ajout de piges impossible.";
            }
        }

        if ($panelId) {
            $panel = Panel::withTrashed()->find($panelId);
            if ($panel && $panel->trashed()) {
                $ctx['can_upload'] = false;
                $ctx['upload_blocked'] = true;
                $ctx['block_message'] = 'Ce panneau a été supprimé.';
            }
        }

        return $ctx;
    }

    private function validateFile(UploadedFile $file): bool
    {
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            return false;
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            return false;
        }

        $imageInfo = @getimagesize($file->getRealPath());
        return $imageInfo !== false;
    }

    private function sanitizeGps(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = str_replace(',', '.', (string) $value);
        if (!is_numeric($clean)) {
            return null;
        }

        $float = (float) $clean;
        if ($float < $min || $float > $max) {
            return null;
        }

        return round($float, 7);
    }

    private function success(string $message, array $extra = []): array
    {
        return array_merge(['ok' => true, 'message' => $message], $extra);
    }

    private function error(string $message, array $extra = []): array
    {
        return array_merge(['ok' => false, 'error' => $message], $extra);
    }
}