<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Axes Lumipub dans les devis (2026-11-12) — migration ADDITIVE.
 *
 * Règles validées par l'utilisateur le 2026-11-12 :
 *   · une ligne de devis par axe, qui retient QUELS pieds sont proposés
 *     (sinon la conversion en réservation reprendrait l'axe entier) ;
 *   · ODP et TM d'un axe jamais calculées : 0 par défaut, saisies à la main.
 *
 *   pieds               JSON  ids des pieds proposés (NULL = ligne ordinaire)
 *   odp_amount_override BIGINT UNSIGNED  montant ODP saisi (NULL = calcul auto)
 *   tm_amount_override  BIGINT UNSIGNED  montant TM saisi  (NULL = calcul auto)
 *
 * Mêmes noms que sur invoice_lines (2026-08-03). Gardée par hasColumn :
 * rejouable, et sans effet si une colonne existe déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quote_lines')) {
            return;
        }

        $pieds = !Schema::hasColumn('quote_lines', 'pieds');
        $odp   = !Schema::hasColumn('quote_lines', 'odp_amount_override');
        $tm    = !Schema::hasColumn('quote_lines', 'tm_amount_override');
        if (!$pieds && !$odp && !$tm) {
            return;
        }

        Schema::table('quote_lines', function (Blueprint $table) use ($pieds, $odp, $tm) {
            if ($pieds) $table->json('pieds')->nullable();
            if ($odp)   $table->unsignedBigInteger('odp_amount_override')->nullable();
            if ($tm)    $table->unsignedBigInteger('tm_amount_override')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('quote_lines')) {
            return;
        }
        foreach (['pieds', 'odp_amount_override', 'tm_amount_override'] as $col) {
            if (Schema::hasColumn('quote_lines', $col)) {
                Schema::table('quote_lines', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
