<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration {
    public function up(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            // Remplacer is_verified boolean par status string (plus flexible)
            if (Schema::hasColumn('piges', 'is_verified')) {
                $table->dropColumn('is_verified');
            }
            $table->string('status')->default('en_attente')->after('notes'); // en_attente|verifie|rejete
            $table->text('rejection_reason')->nullable()->after('status');
        });
 
        // Migration des données existantes si needed
        // DB::statement("UPDATE piges SET status = 'verifie' WHERE is_verified = 1");
    }
 
    public function down(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false);
            $table->dropColumn(['status', 'rejection_reason']);
        });
    }
};