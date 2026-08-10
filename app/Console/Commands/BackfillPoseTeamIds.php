<?php

namespace App\Console\Commands;

use App\Models\PoseTask;
use App\Models\PoseTeam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill des pose_tasks.pose_team_id depuis le champ historique team_name.
 *
 * Contexte (2026-08-10) : la migration
 * `add_pose_team_id_to_pose_tasks` ajoute une FK vers pose_teams. Cette
 * command tente de renseigner rétroactivement le lien pour les poses
 * anciennes dont `team_name` matche exactement (case-insensitive) le
 * nom d'une équipe existante — remet en cohérence l'historique perf
 * équipe sans casser l'audit (`team_name` VARCHAR reste conservé
 * comme snapshot de secours).
 *
 * Cas non-résolus (loggés en warning, non bloquants) :
 *   - `team_name` renseigné mais aucune équipe correspondante en base
 *   - Ambiguïté : 2 équipes portent le même nom (peut arriver si un
 *     ancien pose_teams.name a été renommé). On skip et on affiche
 *     les candidats pour arbitrage manuel.
 *
 * Idempotent : ne touche jamais les poses qui ont DÉJÀ un pose_team_id
 * renseigné (protection contre double-run ou saisie manuelle admin).
 *
 * Usage :
 *   php artisan posetasks:backfill-team-ids           # dry-run par défaut
 *   php artisan posetasks:backfill-team-ids --apply   # écrit en base
 */
class BackfillPoseTeamIds extends Command
{
    protected $signature = 'posetasks:backfill-team-ids
                            {--apply : Écrit les changements en base (sinon dry-run)}';

    protected $description = 'Renseigne pose_tasks.pose_team_id à partir du team_name historique';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // 1. Indexer les équipes par nom (lowercase pour matching insensitive)
        $teamsByName = PoseTeam::query()
            ->select('id', 'name')
            ->get()
            ->groupBy(fn ($t) => mb_strtolower(trim($t->name)));

        if ($teamsByName->isEmpty()) {
            $this->warn('Aucune équipe en base. Rien à faire.');
            return self::SUCCESS;
        }

        // 2. Récupérer les poses candidates : team_name renseigné + pose_team_id NULL.
        $candidates = PoseTask::query()
            ->whereNotNull('team_name')
            ->where('team_name', '!=', '')
            ->whereNull('pose_team_id')
            ->select('id', 'team_name')
            ->get();

        $this->info("Analyse de {$candidates->count()} pose(s) avec team_name renseigné et pose_team_id NULL…");

        $stats = ['matched' => 0, 'ambiguous' => 0, 'not_found' => 0];
        $updates = []; // [poseTaskId => poseTeamId]
        $unmatched = []; // team_name → count (pour rapport)

        foreach ($candidates as $task) {
            $key = mb_strtolower(trim($task->team_name));
            $matches = $teamsByName->get($key);

            if (!$matches || $matches->isEmpty()) {
                $stats['not_found']++;
                $unmatched[$task->team_name] = ($unmatched[$task->team_name] ?? 0) + 1;
                continue;
            }

            if ($matches->count() > 1) {
                $stats['ambiguous']++;
                $ids = $matches->pluck('id')->join(', ');
                $this->warn("  ⚠ Pose #{$task->id} team_name='{$task->team_name}' → ambigu (équipes: {$ids}), skip");
                continue;
            }

            $updates[$task->id] = $matches->first()->id;
            $stats['matched']++;
        }

        // 3. Rapport dry-run
        $this->line('');
        $this->line('── Résultat ──────────────────────────────────');
        $this->line("  Poses matchées (backfillable) : {$stats['matched']}");
        $this->line("  Poses ambiguës (skip)         : {$stats['ambiguous']}");
        $this->line("  Poses sans équipe trouvée     : {$stats['not_found']}");

        if (!empty($unmatched)) {
            $this->line('');
            $this->line('  Team_name non résolus (top 10) :');
            $sorted = collect($unmatched)->sortDesc()->take(10);
            foreach ($sorted as $name => $count) {
                $this->line("    • '{$name}' ({$count} pose(s))");
            }
        }

        if (!$apply) {
            $this->line('');
            $this->info('DRY-RUN : aucune modification. Relancer avec --apply pour écrire en base.');
            return self::SUCCESS;
        }

        if (empty($updates)) {
            $this->info('Rien à écrire.');
            return self::SUCCESS;
        }

        // 4. Écriture par batch (grouper par team_id → 1 UPDATE par équipe).
        $this->line('');
        $this->info('Écriture en base…');
        $byTeam = collect($updates)->groupBy(fn ($teamId) => $teamId);
        $writtenTotal = 0;

        DB::transaction(function () use ($byTeam, &$writtenTotal) {
            foreach ($byTeam as $teamId => $poseIdsMap) {
                $poseIds = array_keys($poseIdsMap->toArray()); // clés = pose IDs
                $written = PoseTask::whereIn('id', $poseIds)
                    ->whereNull('pose_team_id') // garde-fou concurrent
                    ->update(['pose_team_id' => $teamId]);
                $writtenTotal += $written;
            }
        });

        $this->info("✅ {$writtenTotal} pose(s) mise(s) à jour.");

        \Illuminate\Support\Facades\Log::info('posetasks.backfill_team_ids.applied', [
            'matched'    => $stats['matched'],
            'ambiguous'  => $stats['ambiguous'],
            'not_found'  => $stats['not_found'],
            'written'    => $writtenTotal,
        ]);

        return self::SUCCESS;
    }
}
