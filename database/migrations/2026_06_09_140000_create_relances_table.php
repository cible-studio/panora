<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des relances de recouvrement.
 *
 *   - invoice_id  : relance liée à une facture précise (cas le plus courant)
 *   - schedule_id : optionnel — pointe l'échéance spécifique de la facture
 *                   (acompte / solde / mensualité) qui était impayée
 *   - client_id   : toujours rempli — permet aussi les relances "globales"
 *                   (rappel solde total dû) quand invoice_id est null
 *
 *   - canal       : moyen de contact effectivement utilisé
 *   - note        : ce qui s'est dit / promis
 *   - suite_donnee: action de suivi (à recontacter le 15, dossier transmis
 *                   au juridique, etc.) — optionnel
 *   - user_id     : qui a effectué la relance (audit + responsabilisation)
 *
 * Index ciblés sur les requêtes du tableau de bord : par client + date,
 * par facture, par échéance.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('relances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained('clients')->cascadeOnDelete();

            $table->foreignId('invoice_id')->nullable()
                ->constrained('invoices')->cascadeOnDelete();

            $table->foreignId('schedule_id')->nullable()
                ->constrained('invoice_schedules')->nullOnDelete();

            $table->date('relance_date');

            $table->enum('canal', [
                'telephone',
                'email',
                'courrier',
                'visite',
                'autre',
            ])->default('telephone');

            $table->text('note');
            $table->text('suite_donnee')->nullable();

            $table->foreignId('user_id')
                ->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['client_id', 'relance_date']);
            $table->index(['invoice_id', 'relance_date']);
            $table->index('schedule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relances');
    }
};
