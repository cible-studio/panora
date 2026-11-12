<?php

namespace App\Services\Piges;

use App\Mail\PigeValidatedMail;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\PublicLink;
use App\Services\Axes\AxeService;
use App\Services\NotificationMailer;
use App\Services\PublicLinkService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * L'e-mail « photo validée » au client — UNE source pour les trois
 * validations de l'admin (une photo, un lot, la modification d'une pige).
 *
 * Décision du 2026-11-12 : un pied d'axe n'envoie rien tant que l'axe
 * n'est pas complet ; le DERNIER pied validé envoie UN seul e-mail avec
 * les photos de tous les pieds, et un lien qui les montre toutes (lien
 * « pige_bundle »). Avant : un e-mail par pied, donc 5 e-mails pour un axe
 * de 5 pieds. Un panneau ordinaire garde un e-mail par photo.
 */
class AvisPigeValidee
{
    public const ENVOYE            = 'envoye';
    public const AXE_ENVOYE        = 'axe_envoye';
    public const AXE_EN_ATTENTE    = 'axe_en_attente';
    public const AXE_DEJA_ENVOYE   = 'axe_deja_envoye';
    public const SANS_DESTINATAIRE = 'sans_destinataire';
    public const ECHEC             = 'echec';

    public function apresValidation(Pige $pige): string
    {
        $pige->loadMissing('panel.commune', 'campaign.client');
        $client = $pige->campaign?->client;
        // Fiche + interlocuteur de la campagne (Client::destinatairesDocuments, 2026-10-29).
        $adresses = $client ? array_column($client->destinatairesDocuments($pige->campaign?->interlocuteur), 'email') : [];
        if (!$adresses) {
            Log::info('pige.notify.skipped', ['pige_id' => $pige->id, 'reason' => 'no_client_email']);
            return self::SANS_DESTINATAIRE;
        }

        try {
            $axeId = app(AxeService::class)->colonnesPresentes() ? $pige->panel?->axe_id : null;
            if ($axeId && $pige->campaign_id) {
                return $this->pourLAxe($pige, (int) $axeId, $adresses);
            }

            $link = PublicLinkService::create(
                target:   $pige,
                type:     'pige',
                expiresAt: now()->addDays(30),
                clientId: $client->id,
                metadata: ['campaign_id' => $pige->campaign_id, 'panel_id' => $pige->panel_id],
            );
            app(NotificationMailer::class)->sendSilently(
                $adresses,
                new PigeValidatedMail($pige, $link),
                cc: null,
                context: ['pige_id' => $pige->id, 'client_id' => $client->id],
            );

            return self::ENVOYE;
        } catch (\Throwable $e) {
            Log::error('pige.notify.failed', ['pige_id' => $pige->id, 'error' => $e->getMessage()]);
            return self::ECHEC;
        }
    }

    /**
     * Les pieds que le client a sur cette campagne : ceux rattachés à la
     * campagne, sinon (pige saisie à la main) ceux qui y ont une pose.
     *
     * @return Collection<int, int>
     */
    public function piedsDeLaCampagne(int $campagneId, int $axeId): Collection
    {
        $pieds = DB::table('campaign_panels')
            ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
            ->where('campaign_panels.campaign_id', $campagneId)
            ->where('panels.axe_id', $axeId)
            ->pluck('panels.id');
        if ($pieds->isEmpty()) {
            $pieds = PoseTask::where('campaign_id', $campagneId)
                ->where('status', '!=', \App\Enums\PoseTaskStatus::CANCELLED->value)
                ->whereHas('panel', fn ($q) => $q->where('axe_id', $axeId))
                ->pluck('panel_id');
        }

        return $pieds->map(fn ($id) => (int) $id)->unique()->values();
    }

    /** @param list<string> $adresses */
    private function pourLAxe(Pige $pige, int $axeId, array $adresses): string
    {
        $campagneId = (int) $pige->campaign_id;
        $pieds = $this->piedsDeLaCampagne($campagneId, $axeId);
        if ($pieds->isEmpty()) {
            $pieds = collect([(int) $pige->panel_id]);
        }

        // La dernière photo validée de chaque pied.
        $photos = Pige::active()
            ->where('campaign_id', $campagneId)
            ->whereIn('panel_id', $pieds)
            ->where('status', 'verifie')
            ->with('panel:id,reference,commune_id,pied_numero')
            ->orderByDesc('verified_at')
            ->get()
            ->unique('panel_id');

        if ($photos->count() < $pieds->count()) {
            Log::info('pige.notify.axe_en_attente', [
                'pige_id' => $pige->id, 'axe_id' => $axeId, 'valides' => $photos->count(), 'pieds' => $pieds->count(),
            ]);
            return self::AXE_EN_ATTENTE;
        }

        // Un seul e-mail par axe et par campagne (une rechange plus tard ne
        // renvoie pas tout l'axe).
        $deja = PublicLink::where('type', 'pige_bundle')
            ->where('target_type', Campaign::class)
            ->where('target_id', $campagneId)
            ->where('metadata->axe_id', $axeId)
            ->exists();
        if ($deja) {
            return self::AXE_DEJA_ENVOYE;
        }

        $photos = $photos->sortBy(fn ($p) => $p->panel?->pied_numero ?? 0)->values();
        $axe = Panel::find($axeId, ['id', 'reference', 'name', 'commune_id']);
        $client = $pige->campaign->client;

        $link = PublicLinkService::create(
            target:   $pige->campaign,
            type:     'pige_bundle',
            expiresAt: now()->addDays(30),
            clientId: $client->id,
            metadata: ['axe_id' => $axeId, 'pige_ids' => $photos->pluck('id')->all()],
        );
        app(NotificationMailer::class)->sendSilently(
            $adresses,
            new PigeValidatedMail($pige, $link, $photos, $axe),
            cc: null,
            context: ['pige_id' => $pige->id, 'axe_id' => $axeId, 'client_id' => $client->id],
        );

        return self::AXE_ENVOYE;
    }
}
