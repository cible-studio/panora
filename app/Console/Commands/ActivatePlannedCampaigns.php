<?php
namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\AlertService;
use App\Services\CampaignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActivatePlannedCampaigns extends Command
{
    protected $signature   = 'campaigns:activate-planned';
    protected $description = 'Passe les campagnes planifiées en actif si start_date <= aujourd\'hui (envoie le mail au client)';

    public function handle(CampaignService $service): int
    {
        $today = now()->toDateString();

        $campaigns = Campaign::where('status', CampaignStatus::PLANIFIE->value)
            ->where('start_date', '<=', $today)
            ->get();

        $activated = 0;
        $blocked   = 0;
        $deferred  = 0;

        foreach ($campaigns as $campaign) {
            // ── Garde "démarrage effectif" ────────────────────────────
            // Si TOUS les panneaux de la campagne ont un `panel_start_date`
            // dans le futur (cas démarrage différé), la campagne n'a aucun
            // panneau réellement actif aujourd'hui — on reporte l'activation.
            // Sinon on aurait une campagne marquée "active" sans aucun
            // affichage physique sur le terrain.
            if (!$this->hasActivePanelToday($campaign, $today)) {
                $this->line(" ↪ #{$campaign->id} ({$campaign->name}) reportée — tous les panneaux démarrent plus tard.");
                $deferred++;
                continue;
            }
            // CampaignService::activate vérifie la garde "au moins 1 panneau",
            // bascule en ACTIF et envoie le mail au client.
            $result = $service->activate($campaign);

            if (!$result['ok']) {
                $this->warn(" ! Campagne #{$campaign->id} ({$campaign->name}) : " . $result['error']);

                // Alerte admin pour signaler une campagne planifiée
                // sans panneau (l'admin doit intervenir).
                AlertService::create(
                    'campagne',
                    'warning',
                    '⚠️ Campagne planifiée sans panneau — ' . $campaign->name,
                    'La date de début est atteinte mais aucun panneau n\'est attaché. Ajoutez des panneaux puis activez manuellement.',
                    $campaign
                );
                $blocked++;
                continue;
            }

            AlertService::create(
                'campagne',
                'info',
                '🎯 Campagne activée automatiquement — ' . $campaign->name,
                'La campagne "' . $campaign->name . '" a débuté aujourd\'hui'
                    . ($result['mail_sent'] ? ' (mail envoyé au client).' : ' (mail non envoyé — vérifier les coordonnées).'),
                $campaign
            );
            $activated++;
        }

        $msg = "$activated campagne(s) activée(s)";
        if ($blocked)  $msg .= ", $blocked bloquée(s) sans panneau";
        if ($deferred) $msg .= ", $deferred reportée(s) (démarrages différés)";
        $this->info($msg . '.');
        return self::SUCCESS;
    }

    /**
     * Vérifie qu'au moins UN panneau de la campagne est effectivement
     * actif aujourd'hui (sa date effective de démarrage ≤ today).
     *
     * Cas du panneau différé : `reservation_panels.panel_start_date`
     * est non NULL et postérieur à `campaign.start_date`. Tant que
     * cette date n'est pas atteinte, le panneau n'est pas posable.
     */
    private function hasActivePanelToday(Campaign $campaign, string $today): bool
    {
        // Pas de résa parente (campagne créée manuellement sans lien
        // proposition) → on ne peut pas vérifier le pivot, on assume
        // que tous les panneaux démarrent à campaign.start_date.
        if (!$campaign->reservation_id) {
            return true;
        }

        return DB::table('reservation_panels')
            ->where('reservation_id', $campaign->reservation_id)
            ->where('source', 'interne')
            ->where(function ($q) use ($today) {
                $q->whereNull('panel_start_date')
                  ->orWhere('panel_start_date', '<=', $today);
            })
            ->exists();
    }
}
