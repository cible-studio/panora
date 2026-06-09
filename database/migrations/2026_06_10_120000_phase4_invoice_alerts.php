<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Alertes automatiques sur échéances (§7 cahier).
 *
 * Crée invoice_alerts qui matérialise chaque alerte planifiée pour une
 * échéance donnée :
 *   - kind  : 'before' | 'after'
 *   - offset_days : -15, -7, -3, -1, +1, +7, +15, +30 (selon kind)
 *   - due_date : date de déclenchement calculée = schedule.due_date + offset
 *   - channel : 'in_app' | 'email' | 'whatsapp' (canal à utiliser)
 *   - status  : 'pending' | 'sent' | 'failed' | 'skipped'
 *   - sent_at : timestamp d'envoi effectif
 *
 * Le job InvoiceAlertsJob (planifié quotidien) :
 *   - balaye les échéances actives (paid_at NULL)
 *   - matérialise les alertes manquantes pour les 8 offsets (4 avant + 4 après)
 *     par canal configuré (config('billing.alerts.channels')) → in_app + email
 *   - envoie les alertes 'pending' dont due_date <= today
 *   - met à jour status = sent (ou failed)
 *   - déclenche la transition auto invoice.status → 'en_retard' quand
 *     une échéance dépassée + reste à payer
 *
 * Les délais sont CONFIGURABLES dans config/billing.php → 'alerts.offsets'
 * (tableau ['before' => [15,7,3,1], 'after' => [1,7,15,30]]).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()
                ->constrained('invoice_schedules')->cascadeOnDelete()
                ->comment('Échéance prévisionnelle concernée. Null si l\'alerte est globale facture.');
            $table->enum('kind', ['before', 'after'])
                ->comment('before = avant l\'échéance (J-15…J-1), after = après (J+1…J+30).');
            $table->smallInteger('offset_days')
                ->comment('Décalage en jours par rapport à la due_date du schedule (-15, -7…+30).');
            $table->date('due_date')
                ->comment('Date effective de déclenchement = schedule.due_date + offset_days.');
            $table->enum('channel', ['in_app', 'email', 'whatsapp'])->default('in_app');
            $table->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->dateTime('sent_at')->nullable();
            $table->string('error_message', 500)->nullable()
                ->comment('Si status=failed, raison de l\'échec (ex: "SMTP timeout", "user has no email").');
            $table->timestamps();

            $table->index(['status', 'due_date'], 'idx_alerts_pending_due');
            $table->unique(['schedule_id', 'kind', 'offset_days', 'channel'], 'uq_alerts_schedule_kind_offset_channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_alerts');
    }
};
