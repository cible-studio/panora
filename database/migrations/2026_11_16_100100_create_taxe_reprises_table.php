<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des mairies (2026-11-16) — l'exercice de reprise.
 *
 * ═══ DÉCISION ÉCRITE — 2026-11-16 ═══
 * « 2026 sera l'année des arriérés ; à partir de 2027 on a des données sûres
 *   et vraies. Les comptables rempliront les paiements de 2026 avec leurs
 *   chiffres. » Option B retenue : pour 2026, le dû d'une mairie est ce
 *   qu'ELLE a réclamé (arriérés compris), saisi par les comptables ; le
 *   calcul de Panora reste affiché à côté pour contrôle. Le solde au
 *   31/12/2026 devient l'arriéré d'ouverture 2027. À partir de 2027, le dû
 *   est le calcul de Panora (cf. config/taxes.php, TaxeExerciceService).
 *
 * Une ligne par (mairie × exercice), mêmes colonnes que la fiche de la
 * direction : arriérés, ODP, TM des quatre trimestres. FCFA entiers.
 * Portable MySQL et SQLite.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('taxe_reprises')) return;

        Schema::create('taxe_reprises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->restrictOnDelete();
            $table->unsignedSmallInteger('exercice');

            $table->unsignedBigInteger('arrieres')->default(0);
            $table->unsignedBigInteger('odp')->default(0);
            $table->unsignedBigInteger('tm_t1')->default(0);
            $table->unsignedBigInteger('tm_t2')->default(0);
            $table->unsignedBigInteger('tm_t3')->default(0);
            $table->unsignedBigInteger('tm_t4')->default(0);

            $table->text('commentaire')->nullable();
            $table->unsignedBigInteger('saisi_par')->nullable();
            $table->timestamps();

            $table->unique(['commune_id', 'exercice']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxe_reprises');
    }
};
