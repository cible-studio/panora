<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enquêtes de satisfaction client envoyées 3 jours après la fin de campagne.
 *
 * Cycle de vie d'une survey :
 *   1. CampaignObserver détecte status → 'termine' → dispatche SendSatisfactionSurvey
 *   2. Job (delay 3 jours) crée la survey + envoie le mail
 *   3. Client clique le lien → /satisfaction/{token} → remplit
 *   4. completed_at rempli → utilisé pour la moyenne dans la fiche client
 *
 * Sécurité : token 64 chars unique, accès public sans auth (style proposition).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Notes 1-5 — null si pas encore complété
            $table->unsignedTinyInteger('score_global')->nullable();
            $table->unsignedTinyInteger('score_qualite')->nullable();
            $table->unsignedTinyInteger('score_delais')->nullable();
            $table->unsignedTinyInteger('score_communication')->nullable();
            $table->unsignedTinyInteger('score_rapport_qualite_prix')->nullable();
            $table->boolean('would_renew')->nullable();
            $table->text('commentaire')->nullable();
            $table->ipAddress('completed_ip')->nullable(); // anti-spam léger
            $table->timestamps();

            // Une seule survey par campagne
            $table->unique('campaign_id', 'uniq_satisfaction_per_campaign');
            $table->index(['client_id', 'completed_at'], 'idx_satisfaction_client_completed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_surveys');
    }
};
