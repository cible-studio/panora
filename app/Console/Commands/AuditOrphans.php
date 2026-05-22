<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rapport lecture seule sur les entités orphelines / archivées du système.
 *
 * Aide l'admin à comprendre l'état de la base avant de lancer un
 * panora:cleanup. N'effectue AUCUNE modification — juste un rapport
 * texte en sortie console.
 *
 * Exemples d'orphelins détectés :
 *   - pose_tasks dont campaign_id est NULL (FK set null après delete)
 *   - pose_tasks dont la campaign est soft-deleted
 *   - piges archivées (archived_at NOT NULL)
 *   - piges dont la campagne est soft-deleted mais archived_at NULL (bug)
 *   - réservations techniques soft-deleted depuis > 90 j
 */
class AuditOrphans extends Command
{
    protected $signature   = 'panora:audit-orphans';
    protected $description = 'Rapport texte des entités orphelines / archivées (lecture seule)';

    public function handle(): int
    {
        $this->info('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  AUDIT ORPHELINS — état actuel de la base Panora');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // ── Campagnes ────────────────────────────────────────────────
        $campTotal     = Campaign::withTrashed()->count();
        $campActive    = Campaign::count();
        $campDeleted   = Campaign::onlyTrashed()->count();
        $campDeletedOld = Campaign::onlyTrashed()->where('deleted_at', '<', now()->subDays(90))->count();

        $this->line(" 📊 Campagnes");
        $this->line("    Total      : " . str_pad($campTotal,     6, ' ', STR_PAD_LEFT));
        $this->line("    Actives    : " . str_pad($campActive,    6, ' ', STR_PAD_LEFT));
        $this->line("    Supprimées : " . str_pad($campDeleted,   6, ' ', STR_PAD_LEFT) . ($campDeleted ? " (dont {$campDeletedOld} > 90 j)" : ''));
        $this->newLine();

        // ── PoseTask ─────────────────────────────────────────────────
        $poseTotal      = PoseTask::count();
        $poseOrphanNull = PoseTask::whereNull('campaign_id')->count();
        $poseOrphanSoft = PoseTask::whereHas('campaign', fn($q) => $q->onlyTrashed())->count();
        $poseAnnuleeOld = PoseTask::where('status', 'annulee')
            ->where('updated_at', '<', now()->subDays(90))
            ->count();

        $this->line(" 🪧 Tâches de pose");
        $this->line("    Total                   : " . str_pad($poseTotal,      6, ' ', STR_PAD_LEFT));
        $this->line("    Orphelines (cid NULL)   : " . str_pad($poseOrphanNull, 6, ' ', STR_PAD_LEFT)
            . ($poseOrphanNull ? ' ⚠️' : ''));
        $this->line("    Campagne soft-deleted   : " . str_pad($poseOrphanSoft, 6, ' ', STR_PAD_LEFT)
            . ($poseOrphanSoft ? ' ⚠️' : ''));
        $this->line("    Annulées > 90 j         : " . str_pad($poseAnnuleeOld, 6, ' ', STR_PAD_LEFT)
            . ($poseAnnuleeOld ? ' (purgeables si sans pige)' : ''));
        $this->newLine();

        // ── Piges ────────────────────────────────────────────────────
        $pigeTotal       = Pige::count();
        $pigeActive      = Pige::active()->count();
        $pigeArchived    = Pige::archived()->count();
        $pigeOrphanNull  = Pige::whereNull('campaign_id')->count();
        $pigeShouldBeArchived = Pige::whereNull('archived_at')
            ->whereHas('campaign', fn($q) => $q->onlyTrashed())
            ->count();

        $this->line(" 📸 Piges photo");
        $this->line("    Total                          : " . str_pad($pigeTotal,    6, ' ', STR_PAD_LEFT));
        $this->line("    Actives                        : " . str_pad($pigeActive,   6, ' ', STR_PAD_LEFT));
        $this->line("    Archivées                      : " . str_pad($pigeArchived, 6, ' ', STR_PAD_LEFT));
        $this->line("    Orphelines (cid NULL)          : " . str_pad($pigeOrphanNull, 6, ' ', STR_PAD_LEFT)
            . ($pigeOrphanNull ? ' ⚠️' : ''));
        $this->line("    À archiver (camp. supprimée)   : " . str_pad($pigeShouldBeArchived, 6, ' ', STR_PAD_LEFT)
            . ($pigeShouldBeArchived ? ' ⚠️ panora:cleanup --apply' : ''));
        $this->newLine();

        // ── Réservations techniques ──────────────────────────────────
        $techActive    = Reservation::where('is_technical', true)->count();
        $techTrashed   = Reservation::onlyTrashed()->where('is_technical', true)->count();
        $techPurgeable = Reservation::onlyTrashed()
            ->where('is_technical', true)
            ->where('deleted_at', '<', now()->subDays(90))
            ->count();

        $this->line(" 🤖 Réservations techniques (auto)");
        $this->line("    Actives                : " . str_pad($techActive,    6, ' ', STR_PAD_LEFT));
        $this->line("    Soft-deleted           : " . str_pad($techTrashed,   6, ' ', STR_PAD_LEFT));
        $this->line("    > 90 j (purgeables)    : " . str_pad($techPurgeable, 6, ' ', STR_PAD_LEFT)
            . ($techPurgeable ? ' (cleanup --apply)' : ''));
        $this->newLine();

        // ── Récapitulatif actions recommandées ───────────────────────
        $actions = [];
        if ($pigeShouldBeArchived > 0) {
            $actions[] = "Archiver {$pigeShouldBeArchived} pige(s) dont la campagne est supprimée";
        }
        if ($techPurgeable > 0) {
            $actions[] = "Purger {$techPurgeable} réservation(s) technique(s) soft-deleted > 90 j";
        }
        if ($poseAnnuleeOld > 0) {
            $actions[] = "Évaluer purge de {$poseAnnuleeOld} pose(s) annulée(s) > 90 j (uniquement celles sans pige)";
        }

        if (empty($actions)) {
            $this->info(' ✅ Aucune action de nettoyage recommandée — la base est propre.');
        } else {
            $this->warn(' Actions recommandées (cleanup) :');
            foreach ($actions as $a) {
                $this->warn('   · ' . $a);
            }
            $this->newLine();
            $this->line(' Pour les effectuer :  php artisan panora:cleanup --apply');
            $this->line(' Pour simuler avant   :  php artisan panora:cleanup');
        }

        $this->newLine();
        return Command::SUCCESS;
    }
}
