<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 5 : 1 lien par campagne pour la prise de piges côté terrain.
 *
 * Le commercial / mediaplanner génère un token (random 64 chars), le
 * partage au technicien sur le terrain (WhatsApp, SMS, papier QR) ;
 * le technicien ouvre la page publique sur son téléphone, voit la
 * liste des panneaux de la campagne et peut uploader les photos
 * une à une — sans login ni compte.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'pige_token')) {
                $table->string('pige_token', 80)->nullable()->unique()->after('status');
            }
            if (!Schema::hasColumn('campaigns', 'pige_token_created_at')) {
                $table->timestamp('pige_token_created_at')->nullable()->after('pige_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'pige_token_created_at')) {
                $table->dropColumn('pige_token_created_at');
            }
            if (Schema::hasColumn('campaigns', 'pige_token')) {
                $table->dropUnique(['pige_token']);
                $table->dropColumn('pige_token');
            }
        });
    }
};
