<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archivage des piges quand leur campagne parent est supprimée.
 *
 * Les piges ont une valeur légale (preuve photo de prestation) et
 * comptable (rattachées à une facture). On ne les supprime JAMAIS,
 * même quand la campagne associée est soft-deleted. On les marque
 * `archived_at` pour les sortir de la vue active mais conserver
 * l'historique accessible via une vue "Archives".
 *
 * - `archived_at` NULL → pige active (campagne vivante)
 * - `archived_at` SET  → pige archivée (campagne supprimée / cleanup)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            $table->timestamp('archived_at')
                ->nullable()
                ->after('rejection_reason')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
