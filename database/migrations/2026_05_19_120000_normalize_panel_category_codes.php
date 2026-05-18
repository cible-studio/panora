<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise les codes catégorie selon une convention unifiée.
 *
 * Le code intègre désormais le SÉPARATEUR (tiret avant/après) pour
 * que la référence finale soit simplement
 *     commune.code + category.code + numéro + face
 * sans logique ad-hoc d'insertion de tiret côté générateur.
 *
 * Convention extraite du parc existant :
 *   - Catégories "courtes" (CAIS, LUP, P, T) : tiret APRÈS le code →
 *       PBT + "CAIS-" + 05 + A  = PBTCAIS-05A
 *       CDY + "LUP-"  + 001     = CDYLUP-001
 *       BKE + "P-"    + 008     = BKEP-008
 *   - Catégories "longues" ou récentes (PAN, PM, CH, PUB) : tiret
 *     AVANT le code →
 *       YOP + "-PAN-" + 01 + A  = YOP-PAN-01A
 *       YOP + "-PM"   + 01      = YOP-PM01
 *       ABO + "-CH"   + 001     = ABO-CH001
 *   - Classique (pas de catégorie) : tiret simple →
 *       ADJ + "-" + 002 = ADJ-002
 *
 * Idempotent : peut être relancée — n'écrase pas un code déjà
 * normalisé (qui contient déjà un tiret).
 */
return new class extends Migration {
    public function up(): void
    {
        // Catégorie Chevalet : présente en logique métier
        // (cf. ApplyPanelGrille) mais absente du seeder initial.
        $hasChevalet = DB::table('panel_categories')
            ->where('name', 'Chevalet')
            ->exists();
        if (!$hasChevalet) {
            DB::table('panel_categories')->insert([
                'name'        => 'Chevalet',
                'code'        => '-CH',
                'description' => 'Chevalet publicitaire 4×6m² (MOVE IT) ou multi-faces 15-20m²',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // Map : code stocké actuellement → code normalisé avec séparateur
        $remap = [
            // Catégories joined (tiret après le code)
            'CAIS' => 'CAIS-',
            'LUP'  => 'LUP-',
            'P'    => 'P-',
            'T'    => 'T-',
            'PLN'  => 'PLN-',

            // Catégories prefixed (tiret avant le code)
            'PAN'  => '-PAN-',
            'PM'   => '-PM',
            'PUB'  => 'PUB-',
            'BK'   => '-BK',
            'CH'   => '-CH',
        ];

        foreach ($remap as $old => $new) {
            // N'écrase pas un code déjà normalisé (contenant un tiret).
            DB::table('panel_categories')
                ->where('code', $old)
                ->update(['code' => $new]);
        }

        // Classique = code "-" (juste le tiret)
        DB::table('panel_categories')
            ->where('name', 'Classique')
            ->where(function ($q) {
                $q->whereNull('code')->orWhere('code', '');
            })
            ->update(['code' => '-']);
    }

    public function down(): void
    {
        // Le rollback retire les séparateurs pour revenir au format
        // précédent. Note : pour les catégories prefixed (PAN, CH…),
        // on perd l'information de positionnement — elles redevien-
        // nent "joined" par défaut. Le up() est la vérité.
        $reverse = [
            'CAIS-' => 'CAIS',
            'LUP-'  => 'LUP',
            'P-'    => 'P',
            'T-'    => 'T',
            'PLN-'  => 'PLN',
            '-PAN-' => 'PAN',
            '-PM'   => 'PM',
            'PUB-'  => 'PUB',
            '-BK'   => 'BK',
            '-CH'   => 'CH',
        ];
        foreach ($reverse as $new => $old) {
            DB::table('panel_categories')->where('code', $new)->update(['code' => $old]);
        }
        DB::table('panel_categories')->where('name', 'Classique')->update(['code' => '']);
    }
};
