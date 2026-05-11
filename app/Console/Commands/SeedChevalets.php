<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute les chevalets CIBLE CI (parc Abidjan).
 *
 * Idempotent : UPSERT par référence, créé les référentiels manquants
 * (commune ABOBO, format 15 m², catégorie "Chevalet") sans rien
 * supprimer. Re-lançable à volonté sans risque.
 *
 *   php artisan chevalets:seed --dry-run
 *   php artisan chevalets:seed --force
 */
class SeedChevalets extends Command
{
    protected $signature   = 'chevalets:seed
        {--dry-run : Affiche le plan sans rien modifier}
        {--force   : Saute la confirmation interactive}';
    protected $description = 'Insère/met à jour les 27 chevalets (4 faces, zone Abidjan).';

    /**
     * Liste : [reference, name, commune, surface_m2].
     * Les chevalets sont des supports 4 faces — zone Abidjan exclusivement.
     */
    private const CHEVALETS = [
        ['ABO-CH001',  'Abobo - Chevalet 4×6m² (2×3m)',                                  'ABOBO',        6],
        ['ABO-CH002',  'Abobo - Chevalet 4×20m² (3.5×4.5m)',                             'ABOBO',        20],
        ['ADJC-CH001', 'Adjamé - Chevalet 4×6m² (2×3m)',                                 'ADJAME',       6],
        ['ADJ-CH002',  'Adjamé - Chevalet 4×20m² (3.5×4.5m)',                            'ADJAME',       20],
        ['ATB-CH001',  'Attécoubé - Chevalet 4×6m² (2×3m)',                              'ATTECOUBE',    6],
        ['ATB-CHE002', 'Attécoubé - Chevalet 4×20m² (3.5×4.5m)',                         'ATTECOUBE',    20],
        ['CDY-CH001',  'Cocody - Chevalet 4×6m² (2×3m)',                                 'COCODY',       6],
        ['CDY-CH002',  'Cocody - Chevalet 4×15m² (4.5×3m)',                              'COCODY',       15],
        ['CDY-CH003',  'Cocody - Chevalet 4×2m² (1.5×1.5m)',                             'COCODY',       2],
        ['CDY-CH004',  'Cocody - Chevalet 4×20m² (4×5m)',                                'COCODY',       20],
        ['KSS-CH001',  'Koumassi - Chevalet 4×6m² (2×3m)',                               'KOUMASSI',     6],
        ['KSS-CH002',  'Koumassi - Chevalet 4×20m² (3.5×4.5m)',                          'KOUMASSI',     20],
        ['MRY-CH001',  'Marcory - Chevalet 4×2m² (1.5×1.5m)',                            'MARCORY',      2],
        ['MRY-CH002',  'Marcory - Chevalet 4×20m² (4×5m)',                               'MARCORY',      20],
        ['MRY-CH003',  'Marcory - Chevalet 4×6m² (2L × 3H)',                             'MARCORY',      6],
        ['PLA-007',    'Plateau - Chevalet Avenue Chardy (4×15m²)',                      'PLATEAU',      15],
        ['PLA-008',    'Plateau - Chevalet Descente Pont De Gaulle (4×15m²)',            'PLATEAU',      15],
        ['PLA-009',    'Plateau - Chevalet Gare Lagunaire (4×15m²)',                     'PLATEAU',      15],
        ['PLA-010',    'Plateau - Chevalet CCIA face Cathédrale St Paul (4×15m²)',       'PLATEAU',      15],
        ['PLA-011',    'Plateau - Pyramide (4×15m²)',                                    'PLATEAU',      15],
        ['PLA-012',    'Plateau - Chevalet Place de la République (4×15m²)',             'PLATEAU',      15],
        ['PBT-CH001',  'Port-Bouët - Chevalet 4×6m² (2×3m)',                             'PORT-BOUET',   6],
        ['PBT-CH002',  'Port-Bouët - Chevalet 4×20m² (3.5×4.5m)',                        'PORT-BOUET',   20],
        ['TVIL-CH001', 'Treichville - Chevalet 4×6m² (2×3m)',                            'TREICH-VILLE', 6],
        ['TVIL-CH002', 'Treichville - Chevalet 4×20m² (3.5×4.5m)',                       'TREICH-VILLE', 20],
        ['YOP-CH001',  'Yopougon - Chevalet 4×6m² (2×3m)',                               'YOPOUGON',     6],
        ['YOP-CH002',  'Yopougon - Chevalet 4×20m² (3.5×4.5m)',                          'YOPOUGON',     20],
    ];

    public function handle(): int
    {
        $dry   = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('═══════════════════════════════════════════════');
        $this->info('  chevalets:seed — ajout du parc chevalets');
        $this->info('═══════════════════════════════════════════════');

        $adminId = DB::table('users')->whereIn('role', ['admin', 'super_admin'])->orderBy('id')->value('id');
        if (!$adminId) { $this->error('Aucun admin pour created_by.'); return self::FAILURE; }

        // ── Analyse référentiels manquants ──────────────────────────
        $missing = [];
        if (!DB::table('communes')->whereRaw('LOWER(name)=?', ['abobo'])->exists()) {
            $missing['commune ABOBO'] = "à créer (zone Abidjan, ODP/TM 1 000)";
        }
        if (!DB::table('panel_formats')->where('surface', 15)->exists()) {
            $missing['format 15 m²'] = "à créer (5×3m)";
        }
        if (!DB::table('panel_categories')->whereRaw('LOWER(name)=?', ['chevalet'])->exists()) {
            $missing['catégorie Chevalet'] = "à créer";
        }

        $this->line('  Référentiels manquants : '.(count($missing) ? '' : 'aucun'));
        foreach ($missing as $k => $v) $this->line("    + $k → $v");

        // Existants / à créer parmi les 27 chevalets
        $existing = DB::table('panels')->whereIn('reference', array_column(self::CHEVALETS, 0))->pluck('reference')->all();
        $this->line('  Chevalets déjà en base : '.count($existing).' / '.count(self::CHEVALETS));
        $this->line('  Chevalets à créer      : '.(count(self::CHEVALETS) - count($existing)));

        if ($dry) {
            $this->warn('  ▶ DRY-RUN : aucune écriture.');
            return self::SUCCESS;
        }
        if (!$force && !$this->confirm('  Procéder ?', false)) {
            $this->line('  Annulé.');
            return self::SUCCESS;
        }

        // ── Création référentiels manquants ─────────────────────────
        // Commune ABOBO (zone Abidjan)
        $aboboId = DB::table('communes')->whereRaw('LOWER(name)=?', ['abobo'])->value('id');
        if (!$aboboId) {
            $aboboId = DB::table('communes')->insertGetId([
                'name'     => 'ABOBO',
                'city'     => 'ABOBO',
                'region'   => 'Abidjan',
                'odp_rate' => 1000,
                'tm_rate'  => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info('  ✓ Commune ABOBO créée (id='.$aboboId.')');
        }

        // Format 15 m² (5×3m)
        $format15Id = DB::table('panel_formats')->where('surface', 15)->value('id');
        if (!$format15Id) {
            $format15Id = DB::table('panel_formats')->insertGetId([
                'name'    => '15m²',
                'width'   => 5,
                'height'  => 3,
                'surface' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info('  ✓ Format 15 m² créé (id='.$format15Id.')');
        }

        // Catégorie Chevalet
        $catId = DB::table('panel_categories')->whereRaw('LOWER(name)=?', ['chevalet'])->value('id');
        if (!$catId) {
            $catId = DB::table('panel_categories')->insertGetId([
                'name'        => 'Chevalet',
                'description' => 'Support 4 faces type chevalet urbain',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $this->info('  ✓ Catégorie Chevalet créée (id='.$catId.')');
        }

        // Zone Abidjan (créée par parc:seed ou zones:simplify)
        $zoneAbidjanId = DB::table('zones')->where('name', 'Abidjan')->value('id') ?: 1;

        // Cache communes / formats par clé normalisée
        $communeByName = [];
        foreach (DB::table('communes')->get(['id', 'name']) as $c) {
            $communeByName[strtolower($c->name)] = $c->id;
        }
        $formatBySurface = [];
        foreach (DB::table('panel_formats')->get(['id', 'surface']) as $f) {
            $formatBySurface[(int) $f->surface] = $f->id;
        }

        // ── UPSERT chevalets ─────────────────────────────────────────
        $created = 0;
        $updated = 0;
        $errors  = [];

        foreach (self::CHEVALETS as $row) {
            [$ref, $name, $communeName, $surface] = $row;

            $cid = $communeByName[strtolower($communeName)] ?? null;
            $fid = $formatBySurface[(int) $surface] ?? null;
            if (!$cid || !$fid) {
                $errors[] = "$ref : commune=$communeName ou surface=$surface introuvable";
                continue;
            }

            $payload = [
                'name'               => $name,
                'commune_id'         => $cid,
                'zone_id'            => $zoneAbidjanId,
                'format_id'          => $fid,
                'category_id'        => $catId,
                'status'             => 'libre',
                'is_lit'             => 0,
                'nombre_faces'       => 4,
                'maintenance_status' => 'bon',
                'created_by'         => $adminId,
                'updated_at'         => now(),
            ];

            $existing = DB::table('panels')->where('reference', $ref)->first();
            if ($existing) {
                DB::table('panels')->where('reference', $ref)->update($payload);
                $updated++;
            } else {
                $payload['reference']  = $ref;
                $payload['created_at'] = now();
                DB::table('panels')->insert($payload);
                $created++;
            }
        }

        $this->info('');
        $this->info('  ✓ '.$created.' chevalet(s) créé(s)');
        $this->info('  ✓ '.$updated.' chevalet(s) mis à jour');
        if ($errors) {
            $this->error('  Erreurs ('.count($errors).') :');
            foreach ($errors as $e) $this->line('    ✕ '.$e);
        }
        return self::SUCCESS;
    }
}
