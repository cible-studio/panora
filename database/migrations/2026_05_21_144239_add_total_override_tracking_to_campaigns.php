<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace de l'override manuel du montant total d'une campagne.
 *
 * Quand un commercial saisit un total différent de la somme calculée
 * depuis les prix unitaires (panel.unit_price × mois), on enregistre :
 *   - `total_amount_overridden_at`     : moment de la négociation
 *   - `total_amount_overridden_by_id`  : qui a négocié
 *
 * Permet d'afficher un badge « 💡 Total négocié par X le DD/MM » sur
 * la fiche campagne. Si une opération ultérieure recalcule le total
 * (ajout/retrait panneau, modification d'un prix unitaire), ces deux
 * champs sont remis à NULL — le total redevient « calculé ».
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->timestamp('total_amount_overridden_at')->nullable()->after('total_amount');
            $table->foreignId('total_amount_overridden_by_id')
                ->nullable()
                ->after('total_amount_overridden_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('total_amount_overridden_by_id');
            $table->dropColumn('total_amount_overridden_at');
        });
    }
};
