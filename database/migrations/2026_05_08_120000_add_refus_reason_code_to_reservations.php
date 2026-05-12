<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refus de proposition côté client : on ajoute un champ structuré pour
 * distinguer le motif (budget / zones / période / concurrent / délais /
 * autre) du commentaire libre éventuel.
 *
 * - `refus_reason_code` : valeur d'une liste prédéfinie (snake_case)
 *   permettant des stats fiables. Stocké en string pour rester souple
 *   si le métier ajoute un nouveau code.
 * - On conserve le champ existant `notes` (où s'écrit déjà le texte
 *   libre via PropositionService::refuser()) — pas de doublon.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'refus_reason_code')) {
                $table->string('refus_reason_code', 30)
                      ->nullable()
                      ->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'refus_reason_code')) {
                $table->dropColumn('refus_reason_code');
            }
        });
    }
};
