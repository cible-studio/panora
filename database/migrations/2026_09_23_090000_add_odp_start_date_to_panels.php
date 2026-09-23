<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TX-10 (2026-09-23) — Date de départ ODP par panneau.
 *
 * Problème résolu :
 * Le parc CIBLE existait physiquement bien avant Panora. `created_at`
 * reflète la date de SAISIE dans l'app, pas la date d'implantation
 * réelle du panneau. Or l'ODP se calcule depuis l'existence du panneau
 * sur le domaine public → utiliser created_at sous-facturait tous les
 * panneaux historiques (ex : parc saisi en juin 2026 → 0 ODP sur T1/T2).
 *
 * Règle validée par la patronne :
 *   - odp_start_date NULL  → panneau préexistant : ODP due sur TOUTE la
 *                            période demandée (début d'année compris).
 *   - odp_start_date remplie → ODP due à partir de cette date seulement.
 *
 * Les panneaux créés à partir de maintenant sont auto-remplis par
 * App\Models\Panel::booted() avec leur date de création.
 * Les 364 lignes existantes restent à NULL → comportement "préexistant".
 *
 * Migration purement ADDITIVE : colonne nullable, aucun backfill,
 * aucune donnée touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('panels', 'odp_start_date')) {
            return; // idempotent (base prod déjà divergente des migrations)
        }

        Schema::table('panels', function (Blueprint $table) {
            $table->date('odp_start_date')
                ->nullable()
                ->after('nombre_faces')
                ->comment('Depart du calcul ODP. NULL = panneau preexistant (facture sur toute la periode).');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('panels', 'odp_start_date')) {
            return;
        }

        Schema::table('panels', function (Blueprint $table) {
            $table->dropColumn('odp_start_date');
        });
    }
};
