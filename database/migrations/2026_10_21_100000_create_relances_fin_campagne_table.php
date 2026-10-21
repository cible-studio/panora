<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal du mail « Votre campagne se termine » (J-7) — 2026-10-21.
 *
 * Remplace le simple drapeau en cache de campaigns:notify-ending : c'est
 * ce journal qui garantit UN seul mail par campagne (et par date de fin),
 * et qui montre à l'admin qui a été prévenu, quand et pourquoi pas.
 *
 * Migration purement ADDITIVE : une table nouvelle. Pas de clé étrangère
 * vers campaigns / clients : la base de dev locale est en MyISAM
 * (cf. TECHNICAL_DEBT.md), et le journal doit survivre à la suppression
 * d'une campagne.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('relances_fin_campagne')) {
            return;
        }

        Schema::create('relances_fin_campagne', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            // Date de fin annoncée dans le mail. Si la campagne est
            // prolongée, la nouvelle date donne droit à un nouveau mail.
            $table->date('fin_campagne');
            // test | production
            $table->string('mode', 12);
            // envoye | echec (retenté le lendemain) | exclu (définitif)
            $table->string('statut', 12);
            // brevo | smtp
            $table->string('canal', 10)->nullable();
            $table->string('destinataire')->nullable();
            $table->string('copie')->nullable();
            $table->string('repondre_a')->nullable();
            // Raison d'une exclusion ou d'un échec.
            $table->text('motif')->nullable();
            $table->string('brevo_message_id')->nullable();
            $table->unsignedSmallInteger('tentatives')->default(0);
            $table->timestamp('envoye_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'fin_campagne', 'mode'], 'relances_fin_unique');
            $table->index(['mode', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relances_fin_campagne');
    }
};
