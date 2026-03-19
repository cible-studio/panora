<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // NCC — Numéro de Compte Client unique
            $table->string('ncc', 20)->nullable()->unique()->after('name');

            // Secteur limité à une liste fixe — on garde string mais validé côté app
            // Index pour les filtres fréquents
            $table->index('sector',     'idx_clients_sector');
            $table->index('name',       'idx_clients_name');
            $table->index('email',      'idx_clients_email');
            $table->index('created_at', 'idx_clients_created');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('ncc');
            $table->dropIndex('idx_clients_sector');
            $table->dropIndex('idx_clients_name');
            $table->dropIndex('idx_clients_email');
            $table->dropIndex('idx_clients_created');
        });
    }
};