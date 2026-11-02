<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date du dernier échange de mail avec une adresse (2026-11-02), reçue du
 * Google Sheets des contacts. Sert à la diffusion des disponibilités : une
 * adresse dont le dernier échange est trop ancien n'est plus destinataire
 * (config diffusion.dernier_echange_min). Vide = inconnue = destinataire.
 *
 * Rejouable : colonnes ajoutées seulement si absentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_contacts') && !Schema::hasColumn('client_contacts', 'dernier_echange')) {
            Schema::table('client_contacts', function (Blueprint $table) {
                $table->date('dernier_echange')->nullable();
            });
        }
        // Adresse générale de la fiche entreprise (clients.email).
        if (Schema::hasTable('clients') && !Schema::hasColumn('clients', 'email_dernier_echange')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->date('email_dernier_echange')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_contacts', 'dernier_echange')) {
            Schema::table('client_contacts', fn (Blueprint $t) => $t->dropColumn('dernier_echange'));
        }
        if (Schema::hasColumn('clients', 'email_dernier_echange')) {
            Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('email_dernier_echange'));
        }
    }
};
