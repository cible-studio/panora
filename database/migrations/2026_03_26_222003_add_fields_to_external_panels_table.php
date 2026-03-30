<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('external_panels', function (Blueprint $table) {
            // Localisation
            $table->string('quartier')->nullable()->after('type');
            $table->string('adresse')->nullable()->after('quartier');
            $table->string('axe_routier')->nullable()->after('adresse');
            $table->text('zone_description')->nullable()->after('axe_routier');

            // Technique
            $table->foreignId('format_id')->nullable()->constrained('formats')->nullOnDelete()->after('zone_description');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()->after('format_id');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete()->after('category_id');
            $table->integer('nombre_faces')->default(1)->after('zone_id');
            $table->string('type_support')->nullable()->after('nombre_faces');
            $table->string('orientation')->nullable()->after('type_support');
            $table->boolean('is_lit')->default(false)->after('orientation');

            // Tarification
            $table->decimal('monthly_rate', 12, 2)->default(0)->after('is_lit');
            $table->integer('daily_traffic')->nullable()->after('monthly_rate');

            // GPS
            $table->decimal('latitude', 10, 7)->nullable()->after('daily_traffic');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('external_panels', function (Blueprint $table) {
            $table->dropColumn([
                'quartier','adresse','axe_routier','zone_description',
                'format_id','category_id','zone_id','nombre_faces',
                'type_support','orientation','is_lit',
                'monthly_rate','daily_traffic','latitude','longitude',
            ]);
        });
    }
};
