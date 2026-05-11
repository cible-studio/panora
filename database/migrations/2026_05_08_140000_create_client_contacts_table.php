<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-interlocuteurs par client : un même compte client peut avoir
 * plusieurs personnes à contacter selon le besoin (décideur, comptable,
 * responsable créa, suivi technique…).
 *
 * Le champ `contact_name` historique sur clients reste pour rétro-compat
 * (utilisé par certaines vues). Il sera progressivement remplacé par le
 * contact "is_primary" de cette table.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('client_contacts')) return;

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();

            // Rôle métier — liste blanche côté modèle (Client::CONTACT_ROLES)
            // pour permettre stats fiables sans parser le texte.
            $table->string('role', 30)->default('autre');
            $table->string('position', 100)->nullable(); // intitulé libre (ex: "Directeur marketing")

            $table->boolean('is_primary')->default(false);
            $table->boolean('receives_notifications')->default(true);
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['client_id', 'is_primary']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
