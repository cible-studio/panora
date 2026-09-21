<?php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use Illuminate\Console\Command;

/**
 * Diagnostic : état complet des tâches de pose et des piges d'un
 * panneau, pour comprendre pourquoi une pose reste visible côté
 * technicien.
 *
 * Affiche, pour chaque PoseTask du panneau :
 *   - statut, type (initiale / rechange / retouche), date planifiée
 *   - si elle a été remplacée (replaced_at) et par quelle tâche
 *   - le nombre de piges rattachées et leur statut
 *   - qui a clôturé la tâche (completed_by / completed_source)
 *
 * Et pour chaque pige : à quelle tâche elle est rattachée.
 *
 * Usage :
 *   php artisan poses:diagnose CDYLUP-007
 *   php artisan poses:diagnose CDYLUP-007 --campaign=2
 */
class DiagnosePosePanel extends Command
{
    protected $signature = 'poses:diagnose
                            {reference : Référence du panneau (ex: CDYLUP-007)}
                            {--campaign= : Filtrer sur une campagne précise}';

    protected $description = "Diagnostic des poses et piges d'un panneau (pourquoi une tâche reste visible)";

    public function handle(): int
    {
        $ref = trim($this->argument('reference'));

        $panel = Panel::where('reference', $ref)->first();
        if (!$panel) {
            $this->error("Panneau « {$ref} » introuvable.");
            return self::FAILURE;
        }

        $this->line('');
        $this->info("═══ PANNEAU {$panel->reference} (#{$panel->id}) ═══");
        $this->line('  ' . ($panel->name ?? '—'));
        $this->line('');

        // ── TÂCHES DE POSE ────────────────────────────────────────
        $tasksQuery = PoseTask::where('panel_id', $panel->id)
            ->with(['campaign:id,name', 'technicien:id,name', 'completedBy:id,name'])
            ->orderBy('id');

        if ($campaignId = $this->option('campaign')) {
            $tasksQuery->where('campaign_id', (int) $campaignId);
        }

        $tasks = $tasksQuery->get();

        if ($tasks->isEmpty()) {
            $this->warn('  Aucune tâche de pose pour ce panneau.');
            return self::SUCCESS;
        }

        $this->info("─── TÂCHES DE POSE (" . $tasks->count() . ") ───");
        $this->line('');

        foreach ($tasks as $t) {
            $pigeCount = Pige::where('pose_task_id', $t->id)->count();
            $pigeOk    = Pige::where('pose_task_id', $t->id)->where('status', '!=', 'rejete')->count();

            // Est-elle encore rattachée à sa campagne ?
            $stillLinked = \DB::table('campaign_panels')
                ->where('campaign_id', $t->campaign_id)
                ->where('panel_id', $t->panel_id)
                ->exists();

            $flags = [];
            if ($t->replaced_at)  $flags[] = 'REMPLACÉE le ' . $t->replaced_at->format('d/m/Y');
            if (!$stillLinked)    $flags[] = '⚠ PANNEAU DÉTACHÉ DE LA CAMPAGNE';
            if ($t->replaces_pose_task_id) $flags[] = 'remplace la tâche #' . $t->replaces_pose_task_id;

            $visibleTech = !in_array($t->status, ['realisee', 'annulee'], true);

            $this->line(sprintf(
                '  <fg=cyan>Tâche #%d</> — %s | type: %s | campagne: %s',
                $t->id,
                strtoupper($t->status),
                $t->pose_kind ?? 'initiale',
                $t->campaign?->name ?? '#' . $t->campaign_id
            ));
            $this->line(sprintf(
                '      planifiée : %s   |   tech : %s',
                $t->scheduled_at?->format('d/m/Y H:i') ?? '—',
                $t->technicien?->name ?? $t->tech_name_self ?? '(non assigné)'
            ));
            $this->line(sprintf(
                '      piges : %d rattachée(s) dont %d valide(s)',
                $pigeCount,
                $pigeOk
            ));

            if ($t->done_at) {
                $this->line(sprintf(
                    '      terminée le %s par %s (source: %s)',
                    $t->done_at->format('d/m/Y H:i'),
                    $t->completedBy?->name ?? '(inconnu)',
                    $t->completed_source ?? '—'
                ));
            }

            foreach ($flags as $f) {
                $this->line('      <fg=yellow>' . $f . '</>');
            }

            $this->line(
                $visibleTech
                    ? '      <fg=red>➜ VISIBLE dans les « à faire » du technicien</>'
                    : '      <fg=green>➜ masquée côté technicien (OK)</>'
            );
            $this->line('');
        }

        // ── PIGES ─────────────────────────────────────────────────
        $pigesQuery = Pige::where('panel_id', $panel->id)
            ->with(['campaign:id,name'])
            ->orderBy('id');

        if ($campaignId = $this->option('campaign')) {
            $pigesQuery->where('campaign_id', (int) $campaignId);
        }

        $piges = $pigesQuery->get();

        $this->info("─── PIGES (" . $piges->count() . ") ───");
        $this->line('');

        if ($piges->isEmpty()) {
            $this->line('  Aucune pige.');
        }

        foreach ($piges as $p) {
            $orphan = $p->pose_task_id === null;
            $this->line(sprintf(
                '  Pige #%d — %s | prise le %s | → tâche %s%s',
                $p->id,
                str_pad($p->status, 10),
                $p->taken_at?->format('d/m/Y H:i') ?? $p->created_at?->format('d/m/Y H:i') ?? '—',
                $orphan ? '<fg=red>AUCUNE</>' : '#' . $p->pose_task_id,
                $orphan ? ' <fg=red>⚠ pige non rattachée</>' : ''
            ));
        }

        $this->line('');
        $this->info('─── LECTURE ───');
        $this->line('  Une tâche reste visible côté tech si son statut');
        $this->line("  n'est ni « realisee » ni « annulee ».");
        $this->line('  Si une tâche RECHANGE est en « planifiee » sans pige,');
        $this->line("  c'est normal : le rechange physique n'est pas encore");
        $this->line('  constaté. Si le MP a déjà uploadé la pige du rechange,');
        $this->line('  vérifier ci-dessus à quelle tâche elle est rattachée.');
        $this->line('');

        return self::SUCCESS;
    }
}
