<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotence des uploads de piges depuis le terrain (anti double-envoi /
 * reprise réseau / futur mode hors-ligne) : identifiant unique généré côté
 * client par tentative d'upload.
 *
 * Boot-safe (cf. migrations gps) : ADD COLUMN nullable idempotent uniquement,
 * AUCUN index/contrainte au boot (la table piges est chaude). L'unicité est
 * garantie applicativement (dédup serveur dans uploadPhoto).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            if (!Schema::hasColumn('piges', 'client_uuid')) {
                $table->string('client_uuid', 64)->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            if (Schema::hasColumn('piges', 'client_uuid')) {
                $table->dropColumn('client_uuid');
            }
        });
    }
};
