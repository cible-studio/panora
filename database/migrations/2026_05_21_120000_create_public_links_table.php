<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table public_links — registre centralisé de tous les liens publics
 * envoyés par email aux clients (factures, piges, décap, etc.).
 *
 * Sécurité multi-couches :
 *   1. Token cryptographique 64 hex = 256 bits d'entropie
 *   2. Expiration obligatoire (`expires_at`)
 *   3. Révocation manuelle (`revoked_at`)
 *   4. Usage unique configurable (`max_uses` + `use_count`)
 *   5. Audit d'accès (`access_count`, `last_accessed_ip`)
 *   6. Throttle géré côté route (middleware Laravel)
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::create('public_links', function (Blueprint $table) {
            $table->id();

            // Token unique cryptographique (64 hex chars = 256 bits)
            $table->string('token', 64)->unique();

            // Type de lien : 'invoice', 'pige', 'reservation', 'decap', 'pige-bundle'...
            $table->string('type', 40)->index();

            // Cible polymorphique
            $table->string('target_type', 100);
            $table->unsignedBigInteger('target_id');
            $table->index(['target_type', 'target_id']);

            // Client destinataire (audit + permissions)
            $table->foreignId('client_id')->nullable()
                  ->constrained('clients')->nullOnDelete();

            // Expiration (par défaut +30 jours côté service)
            $table->timestamp('expires_at')->nullable()->index();

            // Révocation manuelle
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('revoked_reason', 255)->nullable();

            // Usage unique / limite d'usages (pour paiements one-time)
            $table->unsignedInteger('max_uses')->nullable(); // null = illimité
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('used_at')->nullable(); // 1er usage validant

            // Audit consultation (différent de "usage")
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('last_accessed_ip', 45)->nullable();
            $table->string('last_accessed_ua', 255)->nullable();

            // Créateur (qui a généré le lien)
            $table->foreignId('created_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();

            // Metadata flexible (JSON) — utile pour stocker p.ex. l'id de la
            // facture, le canal d'envoi, le numéro de relance, etc.
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_links');
    }
};
