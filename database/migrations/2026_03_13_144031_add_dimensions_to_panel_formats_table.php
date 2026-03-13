<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('panel_formats', function (Blueprint $table) {
            // Ajouter si inexistants
            if (! Schema::hasColumn('panel_formats', 'width')) {
                $table->decimal('width', 6, 2)->nullable()->after('name')
                    ->comment('Largeur en mètres');
            }
            if (! Schema::hasColumn('panel_formats', 'height')) {
                $table->decimal('height', 6, 2)->nullable()->after('width')
                    ->comment('Hauteur en mètres');
            }
        });
    }
    
    public function down(): void
    {
        Schema::table('panel_formats', function (Blueprint $table) {
            $table->dropColumnIfExists('width');
            $table->dropColumnIfExists('height');
        });
    }
};
