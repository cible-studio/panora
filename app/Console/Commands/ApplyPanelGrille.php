<?php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Models\PanelCategory;
use App\Models\PanelFormat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Applique la GRILLE TARIFAIRE HT 2026 sur tous les panneaux internes.
 *
 * Règle métier : prix MAX pour les fourchettes, distinction Abidjan vs
 * Intérieur sur les 12m² classique uniquement.
 *
 * Idempotent : peut être relancé sans risque. Dry-run par défaut.
 *
 *   php artisan panels:apply-grille                # dry-run
 *   php artisan panels:apply-grille --apply        # applique réellement
 *   php artisan panels:apply-grille --apply --force  # sans confirmation
 *
 * Avant d'appliquer, la commande s'assure que :
 *   - La catégorie VIP existe (créée vide pour les futures créations).
 *   - La catégorie Panoramique a été reclassée → Classique puis supprimée
 *     (cf. grille : "Entrée de ville" = 50m² = prix Classique 50m²).
 */
class ApplyPanelGrille extends Command
{
    protected $signature   = 'panels:apply-grille
        {--apply  : Applique les changements (sinon dry-run)}
        {--force  : Saute la confirmation interactive}';
    protected $description = 'Applique la grille tarifaire HT 2026 sur les panneaux internes (Panel.monthly_rate).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $mode  = $apply ? '<fg=red;options=bold>APPLY</>' : '<fg=yellow>DRY-RUN</>';

        $this->line("  Mode : {$mode}");
        $this->newLine();

        // ═══ ÉTAPE 1 — Nettoyage catégories ═══════════════════════
        $this->info('▶ Étape 1 — Nettoyage catégories');
        $vip       = $this->ensureCategoryVip($apply);
        $reclassed = $this->retirePanoramique($apply);
        $this->newLine();

        // ═══ ÉTAPE 2 — Application des prix ═══════════════════════
        $this->info('▶ Étape 2 — Application de la grille tarifaire');

        $panels = Panel::with(['format:id,name,surface', 'category:id,name', 'commune:id,city'])
            ->get();

        $stats = [
            'updated'    => 0,
            'unchanged'  => 0,
            'skipped'    => 0, // formats rares laissés à 0
        ];
        $bySurface = []; // surface => count
        $skippedRefs = [];

        foreach ($panels as $p) {
            $newRate = $this->resolveRate($p);

            if ($newRate === null) {
                $stats['skipped']++;
                $skippedRefs[] = $p->reference . ' (' . ($p->format?->name ?? '?') . ')';
                continue;
            }

            if ((float) $p->monthly_rate === (float) $newRate) {
                $stats['unchanged']++;
                continue;
            }

            $bySurface[$p->format?->surface ?? 0] = ($bySurface[$p->format?->surface ?? 0] ?? 0) + 1;
            $stats['updated']++;

            if ($apply) {
                // bypass touch / observers : on ne veut pas spammer les alertes
                Panel::where('id', $p->id)->update(['monthly_rate' => $newRate]);
            }
        }

        // ═══ RAPPORT ═══════════════════════════════════════════════
        $this->newLine();
        $this->info('▶ Récapitulatif');
        $this->line(sprintf('  À mettre à jour : <fg=green>%d</> panneau(x)', $stats['updated']));
        $this->line(sprintf('  Déjà à jour     : %d', $stats['unchanged']));
        $this->line(sprintf('  Laissés à 0     : <fg=yellow>%d</> (formats hors grille)', $stats['skipped']));

        if (!empty($bySurface)) {
            ksort($bySurface);
            $this->newLine();
            $this->line('  Détail par format :');
            foreach ($bySurface as $surface => $cnt) {
                $this->line(sprintf('    %4.0fm² → %d panneau(x)', $surface, $cnt));
            }
        }

        if (!empty($skippedRefs)) {
            $this->newLine();
            $this->warn('  Panneaux laissés à 0 (à fixer manuellement) :');
            foreach ($skippedRefs as $r) {
                $this->line('    · ' . $r);
            }
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('  ▶ DRY-RUN : aucune écriture. Relancez avec --apply pour appliquer.');
            return self::SUCCESS;
        }

        if (!$force && !$this->confirm('  Confirmer l\'application sur la BD ?', false)) {
            $this->line('  Annulé.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("  ✓ {$stats['updated']} panneau(x) mis à jour.");
        return self::SUCCESS;
    }

    /**
     * S'assure que la catégorie VIP existe. Vide au départ (à remplir via UI).
     */
    private function ensureCategoryVip(bool $apply): PanelCategory
    {
        $existing = PanelCategory::where('name', 'VIP')->first();
        if ($existing) {
            $this->line('  ✓ Catégorie VIP existe déjà.');
            return $existing;
        }

        $this->line('  + Création catégorie « VIP »');
        if ($apply) {
            return PanelCategory::create([
                'name'        => 'VIP',
                'description' => 'Panneaux premium emplacement stratégique (12/18/36m² VIP)',
            ]);
        }
        // Dry-run : retourne un placeholder pour ne pas casser la suite
        return new PanelCategory(['id' => 0, 'name' => 'VIP']);
    }

    /**
     * Reclasse les panneaux Panoramique → Classique, puis supprime la catégorie.
     * "Panoramique" n'existe pas dans la grille — ses panneaux sont des
     * "Entrée de ville" 50m² → prix = 50m² Classique = 1 500 000 F.
     */
    private function retirePanoramique(bool $apply): int
    {
        $pano = PanelCategory::where('name', 'Panoramique')->first();
        if (!$pano) {
            $this->line('  ✓ Catégorie Panoramique déjà supprimée.');
            return 0;
        }

        $classique = PanelCategory::where('name', 'Classique')->firstOrFail();
        $count     = Panel::where('category_id', $pano->id)->count();

        $this->line("  ↪ Reclasser {$count} panneau(x) Panoramique → Classique");
        $this->line('  − Supprimer la catégorie « Panoramique »');

        if ($apply && $count > 0) {
            Panel::where('category_id', $pano->id)->update(['category_id' => $classique->id]);
        }
        if ($apply) {
            $pano->delete();
        }

        return $count;
    }

    /**
     * Logique grille HT 2026 — prix MAX pour les fourchettes.
     * Retourne null si le panneau doit être laissé à 0 (format hors grille).
     */
    private function resolveRate(Panel $p): ?float
    {
        $cat     = $p->category?->name;
        $surface = (int) round((float) ($p->format?->surface ?? 0));
        $city    = $p->commune?->city;
        $isAbj   = $city === 'Abidjan';

        // Catégories à prix uniforme peu importe le format
        if ($cat === 'Lumipub')   return 130_000;
        if ($cat === 'Trivision') return 120_000;

        // VIP : prix selon format
        if ($cat === 'VIP') {
            return match ($surface) {
                12      => 150_000,
                18      => 850_000,
                36      => 1_300_000,
                default => null, // VIP autre format non prévu → manuel
            };
        }

        // Chevalet : 12m² = MOVE IT (800k), 15m²/20m² = ×4 faces (2.5M)
        // Tout autre format : fallback prix format classique.
        if ($cat === 'Chevalet') {
            return match ($surface) {
                12      => 800_000,
                15, 20  => 2_500_000,
                default => $this->classiqueByFormat($surface, $isAbj),
            };
        }

        // Borne Kilométrique → 6m² à 100k peu importe le reste
        if ($cat === 'Borne Kilométrique') {
            return 100_000;
        }

        // Planimètre → 95k (catégorie créée mais probablement vide en BD)
        if ($cat === 'Planimètre') {
            return 95_000;
        }

        // ── Autres catégories : prix du format classique ──────
        // (Classique, Caisson, Murale, Petit format, etc.)
        return $this->classiqueByFormat($surface, $isAbj);
    }

    /**
     * Prix classique par surface — grille HT 2026, valeurs MAX.
     * Retourne null pour les surfaces hors grille (15/24/32/70m²).
     */
    private function classiqueByFormat(int $surface, bool $isAbj): ?float
    {
        return match ($surface) {
            2       => 100_000,
            3       => 100_000,
            6       => 100_000,
            10      => 120_000,
            12      => $isAbj ? 120_000 : 90_000, // 12m² Intérieur du pays = 90k
            18      => 750_000,
            20      => 1_000_000,
            36      => 1_200_000,
            50      => 1_500_000,
            54      => 1_500_000,
            default => null, // 15/24/32/70m² → manuel
        };
    }
}
