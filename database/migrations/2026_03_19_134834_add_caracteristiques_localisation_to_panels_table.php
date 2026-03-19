<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            // Caractéristiques
            $table->integer('nombre_faces')->default(1)->after('is_lit');
            $table->string('type_support')->nullable()->after('nombre_faces');
            $table->enum('orientation', [
                'nord', 'sud', 'est', 'ouest',
                'nord-est', 'nord-ouest',
                'sud-est', 'sud-ouest'
            ])->nullable()->after('type_support');

            // Localisation
            $table->string('adresse')->nullable()->after('zone_description');
            $table->string('quartier')->nullable()->after('adresse');
            $table->string('axe_routier')->nullable()->after('quartier');
        });
    }

    public function down(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_faces',
                'type_support',
                'orientation',
                'adresse',
                'quartier',
                'axe_routier'
            ]);
        });
    }
};
