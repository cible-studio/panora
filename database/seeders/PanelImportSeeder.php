<?php

namespace Database\Seeders;

use App\Models\Commune;
use App\Models\Panel;
use App\Models\PanelFormat;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Import idempotent des panneaux CIBLE CI (Abidjan + Intérieur).
 *
 * UPSERT par `reference` → ne supprime jamais : préserve réservations,
 * campagnes et tâches de pose existantes. Les communes et formats
 * manquants sont créés à la volée.
 *
 * Modes :
 *   php artisan db:seed --class=PanelImportSeeder              (apply)
 *   DRY_RUN=1 php artisan db:seed --class=PanelImportSeeder    (dry-run)
 *
 * Sur Windows / PowerShell :
 *   $env:DRY_RUN=1; php artisan db:seed --class=PanelImportSeeder
 */
class PanelImportSeeder extends Seeder
{
    /**
     * Surface → [width, height] en mètres. Choix orientés "verticaux"
     * (paysage classique de l'affichage urbain). Toute surface absente
     * de cette table sera laissée à width/height = null et seul `surface`
     * sera persisté.
     */
    private const DIMENSIONS = [
        2  => [2,    1],
        3  => [1.5,  2],
        6  => [3,    2],
        8  => [4,    2],
        10 => [5,    2],
        12 => [4,    3],
        18 => [6,    3],
        20 => [5,    4],
        24 => [6,    4],
        32 => [8,    4],
        36 => [6,    6],
        50 => [10,   5],
        54 => [9,    6],
        70 => [10,   7],
    ];

    public function run(): void
    {
        $dryRun = (bool) env('DRY_RUN', false);

        $rows = require database_path('data/PanelImportData.php');
        if (!is_array($rows) || empty($rows)) {
            $this->command->error('Aucune donnée trouvée dans database/data/PanelImportData.php');
            return;
        }

        // Auteur par défaut : premier admin disponible (created_by NOT NULL en DB).
        $admin = User::whereIn('role', ['admin', 'super_admin'])->orderBy('id')->first();
        if (!$admin) {
            $this->command->error('Aucun admin trouvé pour le champ created_by.');
            return;
        }

        $stats = [
            'panels_created'    => 0,
            'panels_updated'    => 0,
            'panels_unchanged'  => 0,
            'communes_created'  => 0,
            'formats_created'   => 0,
            'errors'            => [],
        ];

        // Cache des communes (clé : nom normalisé) pour limiter les requêtes.
        $communeCache = [];
        foreach (Commune::all() as $c) {
            $communeCache[$this->normalize($c->name)] = $c;
        }

        // Cache des formats par surface (m²).
        $formatCache = [];
        foreach (PanelFormat::all() as $f) {
            $key = $this->formatKey((float) $f->surface);
            $formatCache[$key] = $f;
        }

        if ($dryRun) {
            $this->command->warn('▶ MODE DRY-RUN — aucune écriture ne sera effectuée.');
            DB::beginTransaction();
        }

        try {
            foreach ($rows as $i => $row) {
                if (count($row) < 4) {
                    $stats['errors'][] = "Ligne $i mal formée : " . json_encode($row);
                    continue;
                }
                [$reference, $name, $communeName, $surface] = $row;

                // ── Commune (UPSERT case-insensitive) ─────────────────────
                $key = $this->normalize($communeName);
                if (!isset($communeCache[$key])) {
                    $commune = Commune::create([
                        'name'     => $communeName,
                        'city'     => $communeName,
                        'odp_rate' => 0,
                        'tm_rate'  => 0,
                    ]);
                    $communeCache[$key] = $commune;
                    $stats['communes_created']++;
                    $this->command->line("  + commune créée : <fg=green>$communeName</>");
                }
                $commune = $communeCache[$key];

                // ── Format (UPSERT par surface) ───────────────────────────
                $fkey = $this->formatKey((float) $surface);
                if (!isset($formatCache[$fkey])) {
                    [$w, $h] = self::DIMENSIONS[(int) $surface] ?? [null, null];
                    $format  = PanelFormat::create([
                        'name'    => $w && $h
                            ? rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.') . 'x' .
                              rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.') . 'm'
                            : $surface . ' m²',
                        'width'   => $w,
                        'height'  => $h,
                        'surface' => $surface,
                    ]);
                    $formatCache[$fkey] = $format;
                    $stats['formats_created']++;
                    $this->command->line("  + format créé : <fg=green>{$format->name}</> ({$surface} m²)");
                }
                $format = $formatCache[$fkey];

                // ── Panel (UPSERT par reference) ──────────────────────────
                $existing = Panel::where('reference', $reference)->first();
                $payload  = [
                    'name'       => $name,
                    'commune_id' => $commune->id,
                    'format_id'  => $format->id,
                    'created_by' => $existing->created_by ?? $admin->id,
                ];

                if (!$existing) {
                    Panel::create([
                        'reference' => $reference,
                    ] + $payload);
                    $stats['panels_created']++;
                } else {
                    // Ne touche au monthly_rate / status / coordonnées que si
                    // jamais saisis : on respecte les enrichissements admin.
                    $dirty = false;
                    foreach ($payload as $k => $v) {
                        if ($existing->{$k} != $v) {
                            $existing->{$k} = $v;
                            $dirty = true;
                        }
                    }
                    if ($dirty) {
                        $existing->save();
                        $stats['panels_updated']++;
                    } else {
                        $stats['panels_unchanged']++;
                    }
                }
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }

        $this->report($stats, $dryRun);
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = str_replace(
            ['à','â','ä','é','è','ê','ë','î','ï','ô','ö','ù','û','ü','ç',"'",'-',' '],
            ['a','a','a','e','e','e','e','i','i','o','o','u','u','u','c','','',''],
            $s
        );
        return $s;
    }

    private function formatKey(float $surface): string
    {
        return number_format($surface, 2, '.', '');
    }

    private function report(array $stats, bool $dryRun): void
    {
        $this->command->info('');
        $this->command->info('═════════════════ Récapitulatif import ═════════════════');
        $this->command->line('  Panneaux créés    : <fg=green>' . $stats['panels_created'] . '</>');
        $this->command->line('  Panneaux mis à jour: <fg=yellow>' . $stats['panels_updated'] . '</>');
        $this->command->line('  Panneaux inchangés : <fg=gray>' . $stats['panels_unchanged'] . '</>');
        $this->command->line('  Communes créées   : <fg=green>' . $stats['communes_created'] . '</>');
        $this->command->line('  Formats créés     : <fg=green>' . $stats['formats_created'] . '</>');

        if (!empty($stats['errors'])) {
            $this->command->error('  Erreurs (' . count($stats['errors']) . ') :');
            foreach ($stats['errors'] as $e) {
                $this->command->line('    ✕ ' . $e);
            }
        }

        $this->command->info('═════════════════════════════════════════════════════════');
        if ($dryRun) {
            $this->command->warn('Dry-run terminé — aucune modification persistée.');
            $this->command->warn('Relance sans DRY_RUN pour appliquer.');
        } else {
            $this->command->info('✓ Import terminé.');
        }
    }
}
