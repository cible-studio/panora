<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les contacts détaillés aux régies externes :
 *   - manager_name      : responsable général
 *   - commercial_name   : interlocuteur commercial dédié
 *   - commercial_email  : email du commercial (cliquable)
 *   - commercial_phone  : téléphone du commercial (cliquable)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('external_agencies', function (Blueprint $table) {
            $table->string('manager_name', 200)->nullable()->after('name');
            $table->string('commercial_name', 200)->nullable()->after('manager_name');
            $table->string('commercial_email', 200)->nullable()->after('commercial_name');
            $table->string('commercial_phone', 25)->nullable()->after('commercial_email');
        });
    }

    public function down(): void
    {
        Schema::table('external_agencies', function (Blueprint $table) {
            $table->dropColumn([
                'manager_name',
                'commercial_name',
                'commercial_email',
                'commercial_phone',
            ]);
        });
    }
};
