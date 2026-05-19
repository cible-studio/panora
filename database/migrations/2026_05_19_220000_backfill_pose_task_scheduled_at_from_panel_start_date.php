<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Migration de rattrapage — corrige les `pose_tasks.scheduled_at` qui
 * avaient été créés à `campaign.start_date` au lieu du `panel_start_date`
 * effectif du panneau (lecture depuis `reservation_panels`).
 *
 * Contexte : avant le fix CampaignObserver (commit suivant), une campagne
 * créée à partir d'une résa avec des `panel_start_date` différés générait
 * des pose-tasks avec `scheduled_at = campaign.start_date` pour TOUS les
 * panneaux — y compris ceux qui démarraient plus tard. Ces pose-tasks
 * étaient ensuite affichées "en retard" alors que le panneau n'était
 * pas encore disponible physiquement.
 *
 * Le rattrapage :
 *   - Ne touche QUE les pose-tasks PLANNED (pas posées encore) — on ne
 *     reécrit pas les historiques `realisee` / `annulee`.
 *   - Pour chaque pose-task, lit le `panel_start_date` correspondant
 *     dans le pivot `reservation_panels` (via campaign.reservation_id +
 *     panel_id) et l'utilise comme nouveau `scheduled_at` SI postérieur
 *     à l'actuel.
 *   - Log de traçabilité pour audit.
 */
return new class extends Migration {
    public function up(): void
    {
        $updated = 0;
        $rows = DB::table('pose_tasks as pt')
            ->join('campaigns as c', 'c.id', '=', 'pt.campaign_id')
            ->join('reservation_panels as rp', function ($join) {
                $join->on('rp.reservation_id', '=', 'c.reservation_id')
                     ->on('rp.panel_id', '=', 'pt.panel_id')
                     ->where('rp.source', '=', 'interne');
            })
            ->where('pt.status', 'planifiee')
            ->whereNotNull('rp.panel_start_date')
            ->whereColumn('rp.panel_start_date', '>', 'pt.scheduled_at')
            ->select(
                'pt.id as task_id',
                'pt.scheduled_at as old_scheduled_at',
                'rp.panel_start_date as new_scheduled_at',
                'pt.panel_id',
                'pt.campaign_id',
            )
            ->get();

        foreach ($rows as $r) {
            DB::table('pose_tasks')
                ->where('id', $r->task_id)
                ->update([
                    'scheduled_at' => $r->new_scheduled_at,
                    'updated_at'   => now(),
                ]);
            $updated++;

            Log::info('pose_task.scheduled_at.backfilled', [
                'task_id' => $r->task_id,
                'old'     => $r->old_scheduled_at,
                'new'     => $r->new_scheduled_at,
                'reason'  => 'panel_start_date_deferred',
            ]);
        }

        Log::info('migration.pose_tasks.backfill_completed', ['updated' => $updated]);
    }

    public function down(): void
    {
        // Pas de rollback : les anciennes `scheduled_at` étaient incorrectes.
        // Restaurer ces valeurs ré-introduirait le bug "fausse alerte retard".
        // No-op volontaire — la migration up() est idempotente (elle ne touche
        // qu'aux pose-tasks dont scheduled_at < panel_start_date).
    }
};
