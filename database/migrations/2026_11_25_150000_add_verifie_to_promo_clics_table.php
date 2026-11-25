<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clics des promotions (2026-11-25, après le premier envoi) : la page
 * confirme qu'elle s'est vraiment affichée dans un navigateur.
 *   verifie = null  : clic d'avant ce contrôle (gardé dans le bilan) ;
 *   verifie = false : page jamais affichée (filtre qui vérifie le lien) ;
 *   verifie = true  : un client a vu la page.
 * Portable MySQL et SQLite, rejouable sans erreur.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('promo_clics') || Schema::hasColumn('promo_clics', 'verifie')) {
            return;
        }

        Schema::table('promo_clics', function (Blueprint $table) {
            $table->boolean('verifie')->nullable()->after('robot');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('promo_clics', 'verifie')) {
            Schema::table('promo_clics', function (Blueprint $table) {
                $table->dropColumn('verifie');
            });
        }
    }
};
