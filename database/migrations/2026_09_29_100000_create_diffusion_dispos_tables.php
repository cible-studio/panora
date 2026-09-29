<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diffusion automatique des disponibilités aux clients (2026-09-29).
 *
 * Deux envois par mois (le 1er et le 15, 10h, décalés au premier jour
 * ouvrable suivant), via une campagne Brevo. Cf.
 * docs/DIFFUSION_DISPONIBILITES.md pour la spécification complète.
 *
 * Migration purement ADDITIVE : trois tables nouvelles, aucune table
 * existante modifiée. Les clients sont inscrits d'office — on ne stocke
 * donc que les EXCEPTIONS (désinscrits, adresses mortes), pas un
 * consentement par contact.
 *
 * Tables forcées en InnoDB : la base de dev locale crée du MyISAM par
 * défaut (cf. TECHNICAL_DEBT.md), or le journal des envois s'appuie sur
 * une contrainte d'unicité pour garantir qu'un créneau ne part jamais
 * deux fois en automatique.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Calendrier des jours fériés. Les fêtes fixes et chrétiennes sont
        // pré-remplies automatiquement ; les fêtes musulmanes (Tabaski,
        // Ramadan, Maouloud…) sont fixées chaque année par décret et
        // doivent être saisies par l'admin.
        if (!Schema::hasTable('jours_feries')) {
            Schema::create('jours_feries', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->date('date')->unique();
                $table->string('libelle', 120);
                // fixe | chretien | musulman | manuel
                $table->string('source', 20)->default('manuel');
                $table->timestamps();
            });
        }

        // Journal des envois — un enregistrement par tentative.
        if (!Schema::hasTable('diffusion_envois')) {
            Schema::create('diffusion_envois', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                // Date nominale du créneau (le 1er ou le 15), même si
                // l'envoi réel a été décalé au jour ouvrable suivant.
                $table->date('creneau');
                $table->date('periode_debut');
                $table->date('periode_fin');
                // auto | manuel | test
                $table->string('mode', 10);
                $table->foreignId('declenche_par')->nullable()
                    ->constrained('users')->nullOnDelete();
                // prepare | en_cours | envoye | echec
                $table->string('statut', 12)->default('prepare');
                $table->unsignedInteger('nb_destinataires')->default(0);
                $table->unsignedInteger('nb_panneaux')->default(0);
                // PDF généré pour cet envoi, servi par un lien non devinable.
                $table->string('pdf_path')->nullable();
                $table->string('pdf_token', 64)->nullable()->unique();
                $table->unsignedBigInteger('brevo_campaign_id')->nullable();
                $table->text('erreur')->nullable();
                $table->timestamp('envoye_at')->nullable();
                $table->timestamps();

                $table->index(['creneau', 'mode', 'statut']);
            });
        }

        // Adresses exclues des envois : désinscriptions (lien du mail),
        // rebonds définitifs et signalements de spam remontés par Brevo.
        if (!Schema::hasTable('diffusion_desinscriptions')) {
            Schema::create('diffusion_desinscriptions', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('email', 191)->unique();
                // desinscription | rebond | spam | manuel
                $table->string('motif', 20);
                $table->string('source', 20)->default('brevo');
                $table->timestamp('survenu_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('diffusion_desinscriptions');
        Schema::dropIfExists('diffusion_envois');
        Schema::dropIfExists('jours_feries');
    }
};
