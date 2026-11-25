<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clics des mails de promotion (2026-11-25) — une ligne par ouverture de la
 * page /offre/{code}[/{format}] (config/promos.php).
 *
 *   visiteur : empreinte courte (HMAC de l'adresse IP et du navigateur) pour
 *              compter les visiteurs distincts ; ni l'IP ni le navigateur ne
 *              sont gardés en clair.
 *   robot    : navigateur d'un robot ou d'un filtre de sécurité de messagerie
 *              (ils ouvrent les liens avant le client) : compté à part.
 *
 * Portable MySQL et SQLite, rejouable sans erreur.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('promo_clics')) {
            return;
        }

        Schema::create('promo_clics', function (Blueprint $table) {
            $table->id();
            $table->string('promo', 60);
            $table->string('format', 20)->nullable();
            $table->string('visiteur', 16)->nullable();
            $table->boolean('robot')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['promo', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_clics');
    }
};
