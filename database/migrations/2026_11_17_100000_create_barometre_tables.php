<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baromètre clients (2026-11-17) — envoi du questionnaire par Panora.
 *
 *   barometre_editions : une ligne par édition (2026, 2027…) et sa date
 *                        limite, modifiable à l'écran.
 *   barometre_envois   : une ligne par client et par édition, créée à
 *                        l'exclusion ou à la demande d'envoi. Elle fige à
 *                        qui le mail part (adresse, interlocuteur, règle).
 *   barometre_reponses : chaque réponse signalée par le script Google.
 *
 * Pas de clé étrangère vers clients : sa structure réelle a divergé des
 * migrations (CLAUDE.md, règle n° 2). Portable MySQL et SQLite.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('barometre_editions')) {
            Schema::create('barometre_editions', function (Blueprint $table) {
                $table->id();
                $table->string('edition', 20)->unique();
                $table->date('date_limite');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('barometre_envois')) {
            Schema::create('barometre_envois', function (Blueprint $table) {
                $table->id();
                $table->string('edition', 20);
                $table->unsignedBigInteger('client_id');

                // À qui le mail part, figé à la demande d'envoi.
                $table->string('email')->nullable();
                $table->string('contact_nom')->nullable();
                $table->unsignedBigInteger('client_contact_id')->nullable();
                // principal | dossier | echange | seul | fiche
                $table->string('source', 20)->nullable();

                $table->unsignedBigInteger('public_link_id')->nullable();

                // Invitation, puis relance : null | demande | envoye | echec.
                $table->string('invite_statut', 20)->nullable();
                $table->timestamp('invite_le')->nullable();
                $table->string('invite_brevo_id')->nullable();
                $table->unsignedBigInteger('invite_par')->nullable();
                $table->string('relance_statut', 20)->nullable();
                $table->timestamp('relance_le')->nullable();
                $table->string('relance_brevo_id')->nullable();
                $table->unsignedBigInteger('relance_par')->nullable();
                $table->text('erreur')->nullable();

                // Premier clic sur le lien personnel, puis nombre de clics.
                $table->timestamp('ouvert_le')->nullable();
                $table->unsignedInteger('ouvertures')->default(0);

                // Retiré de l'envoi (régie, nouveau client…), réversible.
                $table->string('exclu_motif', 255)->nullable();
                $table->timestamp('exclu_le')->nullable();
                $table->unsignedBigInteger('exclu_par')->nullable();

                $table->timestamps();

                $table->unique(['edition', 'client_id']);
                $table->index(['edition', 'invite_statut']);
                $table->index(['edition', 'relance_statut']);
            });
        }

        if (!Schema::hasTable('barometre_reponses')) {
            Schema::create('barometre_reponses', function (Blueprint $table) {
                $table->id();
                $table->string('edition', 20);
                // Identifiant envoyé par Google : rejouer le même envoi ne
                // crée pas de doublon.
                $table->string('reference', 191);
                $table->string('structure', 255);
                $table->timestamp('repondu_le')->nullable();
                // Client reconnu (par son nom), ou rattaché à la main.
                $table->unsignedBigInteger('client_id')->nullable();
                $table->boolean('test')->default(false);
                $table->unsignedBigInteger('rattache_par')->nullable();
                $table->timestamps();

                $table->unique(['edition', 'reference']);
                $table->index(['edition', 'client_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('barometre_reponses');
        Schema::dropIfExists('barometre_envois');
        Schema::dropIfExists('barometre_editions');
    }
};
