<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistance des messages envoyés par les clients via le formulaire
 * « Contacter la régie ». Avant cette table, les messages étaient
 * seulement transmis par email — aucune trace en BD si l'email était
 * raté/spam, et aucune interface admin pour répondre/suivre.
 *
 * Maintenant :
 *   - chaque envoi crée une ligne ici (en plus de l'email)
 *   - une alerte AlertService est levée → cliquable vers admin.messages.show
 *   - l'admin peut répondre depuis la fiche message ; la réponse est
 *     stockée et envoyée par email au client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // Snapshot des coordonnées au moment de l'envoi — utile si le
            // client modifie ses infos ultérieurement, on garde le contexte.
            $table->string('from_name', 150);
            $table->string('from_email', 150);
            $table->string('subject', 200);
            $table->text('body');
            // Statut métier : new = à traiter ; in_progress = lu ; replied = répondu
            $table->enum('status', ['new', 'in_progress', 'replied', 'archived'])
                  ->default('new');
            $table->timestamp('read_at')->nullable();
            $table->foreignId('read_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reply_body')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index pour le tri courant (date desc) et les filtres par statut
            $table->index(['status', 'created_at']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_messages');
    }
};
