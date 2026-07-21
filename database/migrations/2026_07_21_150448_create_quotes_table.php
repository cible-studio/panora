<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Devis (Quote) — table principale.
 *
 * Contexte (2026-07-21) : nouveau module commercial pour permettre aux
 * commerciaux de proposer des devis NON BLOQUANTS à des prospects.
 * Contrairement à Reservation type=option (qui bloque les panneaux
 * pendant 7 jours), le devis laisse les panneaux libres — plusieurs
 * commerciaux peuvent proposer les mêmes panneaux à des prospects
 * différents. À la validation, on convertit en Reservation type=ferme
 * après double-check de disponibilité.
 *
 * Structure alignée sur Invoice pour bénéficier du même calculator
 * (InvoiceCalculator réutilisé via QuoteBuilder) — les totaux HT/TVA/
 * TSP/TM/ODP suivent les mêmes règles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();

            // Référence lisible unique (ex: DEV-2026-001)
            $table->string('reference', 40)->unique();

            // Client destinataire
            $table->foreignId('client_id')->constrained('clients');

            // Campagne liée si le devis en concerne une (rare — utile pour
            // extension de campagne existante)
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();

            // Commercial responsable (ownership) — un commercial ne voit
            // que ses devis via QuotePolicy::view
            $table->foreignId('commercial_user_id')->constrained('users');

            // Créateur (audit) — peut être différent du commercial
            // (ex: admin qui prépare pour un commercial absent)
            $table->foreignId('created_by')->constrained('users');

            // Titre humain (ex: « Campagne lancement Duster - Q2 2026 »)
            $table->string('title', 200);

            // Statut du devis dans son cycle de vie
            $table->enum('status', [
                'brouillon',              // créé, pas encore envoyé
                'envoye',                 // envoyé au client, attend décision
                'accepte',                // client a accepté, converti en résa
                'accepte_avec_conflit',   // accepté mais panneaux plus dispos
                'refuse',                 // client a refusé
                'en_negociation',         // client demande modification
                'expire',                 // dépassé la date valid_until
                'archive',                // rangé, plus actionnable
            ])->default('brouillon');

            // Version du devis — incrémentée à chaque modif après envoi
            // (permet de tracer les négociations « v2, v3… »)
            $table->unsignedSmallInteger('version')->default(1);

            // Période visée par la campagne à venir (indicative)
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            // Durée de validité du devis (jours). Config par défaut 30.
            $table->unsignedSmallInteger('valid_days')->default(30);

            // Timestamps de cycle de vie
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->text('decision_reason')->nullable();

            // Notes libres pour le client (apparaît dans le PDF/email)
            $table->text('notes_client')->nullable();

            // Notes internes (visibles admin/commercial seulement)
            $table->text('notes_internes')->nullable();

            // Taux TVA appliqué (snapshot à la création — protège du
            // changement de config futur, comme pour Invoice)
            $table->decimal('tva', 5, 2)->default(18.00);

            // Remise globale en % (sur HT panneaux uniquement — pas services)
            $table->decimal('remise_pct', 5, 2)->default(0);

            // Agrégats de montants (calculés par QuoteBuilder, stockés
            // pour perf et pour figer le devis envoyé).
            // Tous en entiers FCFA (règle d'or Panora — pas de centimes).
            $table->unsignedBigInteger('amount')->default(0);           // Total HT brut lignes
            $table->unsignedBigInteger('net_ht')->default(0);           // HT après remise
            $table->unsignedBigInteger('tva_amount')->default(0);       // TVA sur net_ht
            $table->unsignedBigInteger('tsp_amount')->default(0);       // TSP 3% sur net_ht
            $table->unsignedBigInteger('tm_total')->default(0);         // TM cumul lignes
            $table->unsignedBigInteger('odp_total')->default(0);        // ODP cumul lignes
            $table->unsignedBigInteger('services_ht_total')->default(0);
            $table->unsignedBigInteger('services_ttc_total')->default(0);
            $table->unsignedBigInteger('amount_ttc')->default(0);       // net_ht + tva
            $table->unsignedBigInteger('total_a_payer')->default(0);    // ttc + autres taxes + services

            // Token public pour lien externe (client sans compte).
            // 64 caractères = 256 bits — même sécurité que les PublicLink.
            $table->string('public_token', 64)->unique();

            // Réservation créée à la conversion (si accepté)
            $table->foreignId('converted_reservation_id')->nullable()
                  ->constrained('reservations')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes(); // permet l'archivage sans destruction

            // Index utiles
            $table->index('status');
            $table->index(['commercial_user_id', 'status']);
            $table->index('client_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
