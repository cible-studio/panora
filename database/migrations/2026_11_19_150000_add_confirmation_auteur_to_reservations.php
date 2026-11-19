<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réservation : qui l'a confirmée, et pourquoi sans BC (2026-11-19).
 *
 * Règles validées par écrit le 19/11 :
 *   · le BC reçu confirme la réservation et bloque fermement les panneaux ;
 *   · l'admin garde « Confirmer sans BC » pour les exceptions, motif
 *     obligatoire, le suivi du BC continue ;
 *   · une réservation confirmée sans BC dont l'échéance passe n'est pas
 *     annulée : l'admin et le commercial sont prévenus, une seule fois.
 *
 * Additive uniquement, colonnes nullables : rien d'existant ne change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'confirme_par')) {
                $table->unsignedBigInteger('confirme_par')->nullable()->after('confirmed_at');
            }
            if (!Schema::hasColumn('reservations', 'confirme_sans_bc_motif')) {
                $table->string('confirme_sans_bc_motif', 500)->nullable()->after('confirme_par');
            }
            if (!Schema::hasColumn('reservations', 'bc_echeance_signalee_at')) {
                $table->timestamp('bc_echeance_signalee_at')->nullable()->after('confirme_sans_bc_motif');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            foreach (['bc_echeance_signalee_at', 'confirme_sans_bc_motif', 'confirme_par'] as $col) {
                if (Schema::hasColumn('reservations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
