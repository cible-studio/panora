<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regroupement de deux fiches client (2026-10-30) — journal.
 *
 * Une ligne par regroupement : la fiche retirée (id + nom à ce moment-là)
 * et la fiche gardée. Sert aussi de table de correspondance :
 *   · le nom de la fiche retirée renvoie vers la fiche gardée (règle
 *     « nom unique », App\Support\NomEntreprise) ;
 *   · le Sheet rattache à la fiche gardée au lieu de refuser
 *     « Entreprise supprimée » ;
 *   · un ancien lien vers la fiche retirée ouvre la fiche gardée.
 * `detail` garde ce qui a bougé (nombre de lignes par table, valeurs de
 * l'ancienne fiche non reprises, NCC libéré…).
 *
 * Migration ADDITIVE, sans clé étrangère (la base réelle n'en a aucune
 * vers `clients`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_regroupements')) {
            return;
        }

        Schema::create('client_regroupements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ancien_client_id');
            $table->string('ancien_nom', 150);
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->longText('detail')->nullable(); // JSON
            $table->timestamps();

            $table->index('ancien_client_id', 'idx_client_regroupements_ancien');
            $table->index('client_id', 'idx_client_regroupements_client');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_regroupements');
    }
};
