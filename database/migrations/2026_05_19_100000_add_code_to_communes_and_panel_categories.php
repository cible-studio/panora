<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute `code` aux communes et aux catégories de panneaux pour
 * permettre la génération automatique des références panneaux selon
 * le pattern historique {COMMUNE_CODE}{CATEGORY_CODE}-{NN}{FACE?}.
 *
 * Exemples extraits du parc existant :
 *   PBTCAIS-05A = Port-Bouët (PBT) + Caisson (CAIS) + 05 + Face A
 *   YOPCAIS-02B = Yopougon  (YOP) + Caisson (CAIS) + 02 + Face B
 *   ADJ-002     = Adjamé    (ADJ) + Classique ()    + 002
 *   CDYLUP-001  = Cocody    (CDY) + Lampadaire (LUP)+ 001
 *
 * Backfill basé sur les préfixes les plus utilisés dans le parc.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── Schéma ─────────────────────────────────────────────────
        Schema::table('communes', function (Blueprint $table) {
            if (!Schema::hasColumn('communes', 'code')) {
                $table->string('code', 8)->nullable()->after('name');
                $table->index('code');
            }
        });

        Schema::table('panel_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('panel_categories', 'code')) {
                $table->string('code', 8)->nullable()->after('name');
                $table->index('code');
            }
        });

        // ── Backfill codes commune ─────────────────────────────────
        // Extraits du parc actuel (PanelImportData.php). Pour toute
        // commune absente de cette table, le code reste NULL et le
        // générateur proposera un fallback à partir du nom.
        $communeCodes = [
            'Adjamé'         => 'ADJ',
            'Attécoubé'      => 'ATB',
            'Assinie'        => 'ASS',
            'Grand-Bassam'   => 'BSM',
            'Bingerville'    => 'BING',
            'Cocody'         => 'CDY',
            'Koumassi'       => 'KSS',
            'Marcory'        => 'MRY',
            'Plateau'        => 'PLT',
            'Port-Bouët'     => 'PBT',
            'Port-Bouet'     => 'PBT',
            'Songon'         => 'SGN',
            'Treichville'    => 'TVIL',
            'Yopougon'       => 'YOP',
            'Abobo'          => 'ABO',
            'Anyama'         => 'ANY',
            'Abengourou'     => 'ABG',
            'Adiaké'         => 'ADK',
            'Bondoukou'      => 'BDK',
            'Bonoua'         => 'BNA',
            'Bouaflé'        => 'BFL',
            'Bouaké'         => 'BKE',
            'Daloa'          => 'DLA',
            'Ferkessédougou' => 'FERK',
            'Gagnoa'         => 'GNA',
            'Korhogo'        => 'KHG',
            'Man'            => 'MAN',
            'Odienné'        => 'ODN',
            'Samo'           => 'SM',
            'San-Pédro'      => 'SP',
            'San Pedro'      => 'SP',
            'San-Pedro'      => 'SP',
            'Soubré'         => 'SBR',
            'Yamoussoukro'   => 'YKR',
            'Daoukro'        => 'DAO',
            'Divo'           => 'DIV',
            'Sinfra'         => 'SNF',
            'Issia'          => 'ISS',
            'Tiébissou'      => 'TBS',
            'Bouna'          => 'BNA2',
            'Sikensi'        => 'SKS',
        ];

        foreach ($communeCodes as $name => $code) {
            DB::table('communes')
                ->where('name', $name)
                ->whereNull('code')
                ->update(['code' => $code]);
        }

        // ── Backfill codes catégorie ───────────────────────────────
        // Classique = pas de code (suffix vide → PBT-002 plutôt que PBTCLA-002)
        $categoryCodes = [
            'Classique'          => '',
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
        ];

        foreach ($categoryCodes as $name => $code) {
            DB::table('panel_categories')
                ->where('name', $name)
                ->whereNull('code')
                ->update(['code' => $code]);
        }

        // ── Cleanup : supprimer la catégorie "VIP" si présente ─────
        // VIP n'est pas une catégorie : toute catégorie peut être VIP
        // via le booléen panels.is_vip. On bascule les panneaux liés
        // sur is_vip=true + category_id=NULL avant suppression.
        $vipCat = DB::table('panel_categories')->where('name', 'VIP')->first();
        if ($vipCat) {
            DB::table('panels')
                ->where('category_id', $vipCat->id)
                ->update([
                    'is_vip'      => true,
                    'category_id' => null,
                    'updated_at'  => now(),
                ]);
            DB::table('panel_categories')->where('id', $vipCat->id)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('communes', function (Blueprint $table) {
            if (Schema::hasColumn('communes', 'code')) {
                $table->dropIndex(['code']);
                $table->dropColumn('code');
            }
        });

        Schema::table('panel_categories', function (Blueprint $table) {
            if (Schema::hasColumn('panel_categories', 'code')) {
                $table->dropIndex(['code']);
                $table->dropColumn('code');
            }
        });
    }
};
