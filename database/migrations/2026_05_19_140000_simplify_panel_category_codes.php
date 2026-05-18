<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Simplifie les codes catégorie pour le nouveau format uniforme :
 *
 *     reference = commune.code + "-" + category.code + numéro + face
 *
 * Le séparateur (tiret) est désormais TOUJOURS placé entre le code
 * commune et le code catégorie. Les codes catégorie sont donc des
 * lettres pures (sans tirets), et la concaténation finale produit :
 *
 *     PBT-CAIS001A   (Caisson Port-Bouët face A)
 *     ABO-CH001      (Chevalet Abobo)
 *     CDY-LUP001     (Lampadaire Cocody)
 *     BKE-P001       (Pylône Bouaké)
 *     ADJ-001        (Classique Adjamé — code catégorie vide)
 *
 * Cette migration succède à `normalize_panel_category_codes` (qui
 * avait intégré les tirets aux codes pour gérer DEUX conventions).
 * Décision business confirmée : un seul format uniforme partout.
 *
 * Les anciennes références (PBTCAIS-05A, YOP-PAN-01, etc.) restent
 * intactes en BD — le générateur sait les détecter pour continuer
 * la numérotation au bon niveau sans collision.
 */
return new class extends Migration {
    public function up(): void
    {
        // Codes simples (lettres pures, sans tirets)
        $codes = [
            'Classique'          => '',     // pas de code — format ADJ-001
            'Lumipub'            => 'LUP',
            'Lampadaire'         => 'LUP',
            'Trivision'          => 'T',
            'Borne Kilométrique' => 'BK',
            'Planimètre'         => 'PLN',
            'Caisson'            => 'CAIS',
            'Pylône'             => 'P',
            'Pylone'             => 'P',
            'Panneau Géant'      => 'PAN',
            'Panneau Geant'      => 'PAN',
            'Pont-Maquis'        => 'PM',
            'Pont Maquis'        => 'PM',
            'Publicité'          => 'PUB',
            'Chevalet'           => 'CH',
        ];

        foreach ($codes as $name => $code) {
            DB::table('panel_categories')
                ->where('name', $name)
                ->update(['code' => $code]);
        }

        // S'assure que la catégorie Chevalet existe
        $hasChevalet = DB::table('panel_categories')
            ->where('name', 'Chevalet')
            ->exists();
        if (!$hasChevalet) {
            DB::table('panel_categories')->insert([
                'name'        => 'Chevalet',
                'code'        => 'CH',
                'description' => 'Chevalet publicitaire 4×6m² (MOVE IT) ou multi-faces 15-20m²',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Restaure les séparateurs (état post normalize_panel_category_codes)
        $reverse = [
            'CAIS' => 'CAIS-',
            'LUP'  => 'LUP-',
            'P'    => 'P-',
            'T'    => 'T-',
            'PLN'  => 'PLN-',
            'PAN'  => '-PAN-',
            'PM'   => '-PM',
            'PUB'  => 'PUB-',
            'BK'   => '-BK',
            'CH'   => '-CH',
        ];
        foreach ($reverse as $simple => $withSep) {
            DB::table('panel_categories')->where('code', $simple)->update(['code' => $withSep]);
        }
        DB::table('panel_categories')->where('name', 'Classique')->update(['code' => '-']);
    }
};
