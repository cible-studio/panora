<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token public permanent pour l'espace technicien.
 *
 * Permet à un technicien d'ouvrir une URL personnelle stable
 * (/tech/{token}/poses) qui liste UNIQUEMENT ses propres tâches de
 * pose, regroupées par date, toutes campagnes confondues.
 *
 * Remplace l'usage du `campaigns.pige_token` côté technicien — qui
 * affichait tous les panneaux de la campagne (y compris ceux assignés
 * à d'autres techs). Le pige_token reste pour les pages publiques de
 * pige sans authentification tech (cas legacy).
 *
 * Idempotent : `users.tech_public_token` est généré à la demande via
 * User::ensureTechPublicToken() — pas de génération automatique en
 * masse, on attend que le tech soit effectivement assigné à au moins
 * une pose pour économiser des slots de token.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tech_public_token', 32)
                ->nullable()
                ->unique()
                ->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tech_public_token']);
            $table->dropColumn('tech_public_token');
        });
    }
};
