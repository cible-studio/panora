<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des mairies (2026-11-16) — les règlements de taxes ODP/TM.
 *
 * ═══ RÈGLE VALIDÉE PAR ÉCRIT — 2026-11-16 ═══
 * « Un règlement : un montant global, l'exercice qu'il règle et un objet
 *   indicatif. La ligne ne s'efface jamais : une erreur se corrige par une
 *   annulation avec motif. »
 *
 * Pourquoi une nouvelle table plutôt que commune_tax_payments : celle-ci
 * porte une clé unique (commune × période) et TaxController::recordPayment
 * fait un updateOrCreate — un 2e versement sur la même période ÉCRASAIT le
 * premier. C'est la cause n° 1 du « on pensait avoir tout réglé ».
 * commune_tax_payments reste en base, intacte (règle n° 3) ; ses lignes
 * sont recopiées ici par `taxes:reprendre-reglements`.
 *
 * Montants FCFA entiers. Portable MySQL et SQLite.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('taxe_reglements')) return;

        Schema::create('taxe_reglements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->restrictOnDelete();

            // L'année que le versement RÈGLE — pas forcément celle où il est
            // fait (un paiement de janvier 2027 peut solder 2026).
            $table->unsignedSmallInteger('exercice');
            $table->unsignedBigInteger('montant');
            $table->date('paid_at');

            // Indicatif : arrieres | odp | tm | global. Le solde reste global,
            // comme dans la fiche de la direction.
            $table->string('objet', 20)->default('global');
            $table->string('mode', 20)->nullable();
            $table->string('reference', 100)->nullable();

            // Quittance de la mairie (scan ou photo), disque privé.
            $table->string('quittance_path')->nullable();
            $table->string('quittance_nom')->nullable();

            $table->text('commentaire')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();

            // Annulation : la ligne reste visible, barrée, et sort des totaux.
            $table->timestamp('annule_le')->nullable();
            $table->unsignedBigInteger('annule_par')->nullable();
            $table->string('motif_annulation', 500)->nullable();

            // D'où vient la ligne : saisie | commune_tax_payment | taxe.
            // (origine, origine_id) rend la reprise rejouable sans doublon.
            $table->string('origine', 40)->default('saisie');
            $table->unsignedBigInteger('origine_id')->nullable();

            $table->timestamps();

            $table->index(['commune_id', 'exercice']);
            $table->index('paid_at');
            $table->unique(['origine', 'origine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxe_reglements');
    }
};
