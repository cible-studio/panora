<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace de la première consultation d'une campagne par les utilisateurs
 * du client (espace client). Permet d'afficher un badge « N nouvelles
 * campagnes » dans le menu qui décrémente quand le client visite
 * /client/campagnes — pattern style Gmail/notifications.
 *
 * Un seul timestamp par campagne (partagé entre tous les ClientUser du
 * même client) : si l'owner consulte, les members ne verront plus le
 * badge non plus. Cohérent avec la vision « équipe partagée » d'un
 * compte client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->timestamp('client_first_viewed_at')->nullable()->after('notes');
            // Index pour le count badge (WHERE client_first_viewed_at IS NULL).
            $table->index('client_first_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['client_first_viewed_at']);
            $table->dropColumn('client_first_viewed_at');
        });
    }
};
