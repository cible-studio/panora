<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date RÉELLE d'un mouvement de panneau (2026-11-24, règle validée).
 *
 * Un panneau peut sortir d'une campagne bien avant qu'on l'enregistre :
 * YOPCAIS-01A, démantelé le 27/07/2026, a été retiré de sa campagne le
 * 24/11. La TM se compte jusqu'à la date réelle de sortie, pas jusqu'au
 * clic. NULL = la date d'enregistrement (created_at) fait foi.
 *
 * Rejouable : ne touche à rien si la colonne existe déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campaign_panel_mouvements') || Schema::hasColumn('campaign_panel_mouvements', 'effet_le')) {
            return;
        }

        Schema::table('campaign_panel_mouvements', function (Blueprint $table) {
            $table->date('effet_le')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('campaign_panel_mouvements') && Schema::hasColumn('campaign_panel_mouvements', 'effet_le')) {
            Schema::table('campaign_panel_mouvements', function (Blueprint $table) {
                $table->dropColumn('effet_le');
            });
        }
    }
};
