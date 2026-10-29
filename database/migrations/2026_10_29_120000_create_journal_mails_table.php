<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des mails (2026-10-29) : chaque mail parti de Panora (serveur
 * de mail ou API Brevo), ou dont l'envoi a échoué, avec ses destinataires
 * exacts. Répond à « qui a reçu quoi, quand ». Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journal_mails')) {
            return;
        }
        Schema::create('journal_mails', function (Blueprint $table) {
            $table->id();
            $table->timestamp('envoye_le')->index();
            $table->string('statut', 10);               // envoye | echec
            $table->string('canal', 10);                // serveur | brevo
            $table->string('type', 120);                // classe du mail ou étape Brevo
            $table->string('sujet', 255)->nullable();
            $table->text('destinataires');
            $table->text('copie')->nullable();
            $table->string('dossier_type', 40)->nullable(); // campagne, facture, réservation, devis, pige, client
            $table->unsignedBigInteger('dossier_id')->nullable();
            $table->text('erreur')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // qui a déclenché (vide : tâche automatique)
            $table->timestamps();
            $table->index(['statut', 'envoye_le'], 'idx_journal_mails_statut');
            $table->index(['dossier_type', 'dossier_id'], 'idx_journal_mails_dossier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_mails');
    }
};
