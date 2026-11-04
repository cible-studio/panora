<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vagues d'une campagne (2026-11-04).
 *
 * Un client prend des panneaux pour l'année et change plusieurs fois de
 * message (ex. « Millions à se partager — Phase 2 »). La campagne reste le
 * chapeau (contrat, facture, nom) ; chaque changement de message est une
 * VAGUE, avec son nom. Une pose appartient à une vague ; ses piges suivent.
 *
 * Additive et rejouable : une table, une colonne facultative. Pas de clé
 * étrangère SQL : la structure réelle de `campaigns` / `pose_tasks` a déjà
 * divergé des migrations (cf. CLAUDE.md règle 2) ; index seulement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vagues')) {
            Schema::create('vagues', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedSmallInteger('numero');          // 1, 2, 3… dans la campagne
                $table->string('nom', 150);                      // obligatoire (règle du 2026-11-04)
                $table->date('debut_le')->nullable();            // date prévue du premier rechange
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['campaign_id', 'numero']);
            });
        }

        if (Schema::hasTable('pose_tasks') && !Schema::hasColumn('pose_tasks', 'vague_id')) {
            Schema::table('pose_tasks', function (Blueprint $table) {
                // NULL = campagne sans vagues (jamais de changement de message).
                $table->unsignedBigInteger('vague_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pose_tasks', 'vague_id')) {
            Schema::table('pose_tasks', function (Blueprint $table) {
                $table->dropIndex(['vague_id']);
                $table->dropColumn('vague_id');
            });
        }
        Schema::dropIfExists('vagues');
    }
};
