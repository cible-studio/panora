<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\PoseTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill — crée les PoseTask manquantes pour les campagnes existantes.
 *
 * Cas typique :
 *   - Campagne créée directement (sans réservation)
 *   - Panneaux ajoutés après création via CampaignService::addPanels
 *     (le hook ensurePoseTasksAutoCreated() n'était pas appelé avant ce fix)
 *   → Résultat : 4 panneaux mais seulement 3 pose tasks en base
 *
 * Cette commande est idempotente (re-exécutable sans risque) :
 *   ensurePoseTasksAutoCreated() skip ce qui existe déjà.
 *
 * Usage :
 *   php artisan poses:backfill          # toutes les campagnes actif/planifié
 *   php artisan poses:backfill --dry-run # affiche sans créer
 *   php artisan poses:backfill --campaign=42  # une campagne précise
 */
class BackfillMissingPoseTasks extends Command
{
    protected $signature = 'poses:backfill
                            {--dry-run : Affiche sans créer}
                            {--campaign= : ID campagne précise (sinon toutes)}';

    protected $description = 'Crée les pose tasks manquantes pour les campagnes existantes';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $campaignId = $this->option('campaign');

        $query = Campaign::query()
            ->whereIn('status', ['planifie', 'actif', 'pause', 'termine'])
            ->whereNull('deleted_at')
            ->with('panels:id');

        if ($campaignId) {
            $query->where('id', $campaignId);
        }

        $campaigns = $query->get();
        $this->info("Audit de {$campaigns->count()} campagne(s)…");

        $createdTotal = 0;
        $touched = 0;

        foreach ($campaigns as $campaign) {
            $panelIds  = $campaign->panels->pluck('id')->toArray();
            $taskCount = PoseTask::where('campaign_id', $campaign->id)
                ->whereIn('panel_id', $panelIds)
                ->whereNotIn('status', ['annulee'])
                ->count();
            $missing = count($panelIds) - $taskCount;

            if ($missing <= 0) continue;

            $this->line(sprintf(
                "  #%d %s → %d panneau(x), %d pose(s) → %d manquante(s)",
                $campaign->id,
                \Illuminate\Support\Str::limit($campaign->name, 40),
                count($panelIds),
                $taskCount,
                $missing
            ));

            if (!$dryRun) {
                $created = $campaign->ensurePoseTasksAutoCreated();
                $createdTotal += $created;
                if ($created > 0) $touched++;
            }
        }

        $this->line('');
        if ($dryRun) {
            $this->info("DRY-RUN : aucune modification effectuée.");
        } else {
            $this->info("✅ {$createdTotal} pose task(s) créée(s) sur {$touched} campagne(s).");
        }
        return self::SUCCESS;
    }
}
