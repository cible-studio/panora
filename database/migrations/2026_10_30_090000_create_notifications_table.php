<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table standard des notifications Laravel (2026-10-30).
 *
 * InvoiceScheduleAlertNotification passe par le canal 'database' pour les
 * alertes d'échéance in-app (commercial + admins), mais aucune migration ne
 * créait la table `notifications` : chaque envoi in-app échouait (alerte
 * marquée « failed » en file synchrone, job en échec avec une file
 * asynchrone). Structure identique au gabarit `notifications:table`.
 * Additive : ne fait rien si la table existe déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
