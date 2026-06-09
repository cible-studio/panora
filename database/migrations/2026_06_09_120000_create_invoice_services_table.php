<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Services annexes libres sur les factures — prompt v2 § 1 et § 5.
 *
 * Avant : 2 champs fixes invoices.services_impression et invoices.
 * services_pose_depose. Limitant — le prompt v2 demande N services
 * saisis librement par l'utilisateur (libellé + prix HT), chacun
 * soumis à TVA 18 %.
 *
 * Cette migration crée la table puis BACKFILL les 2 champs legacy
 * pour ne pas perdre d'historique. Les 2 anciens champs RESTENT en
 * base (lecture seule pour les factures verrouillées) mais ne sont
 * plus lus par le calculator : invoice_services devient la source
 * unique des services annexes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('label', 200);
            $table->decimal('prix_ht', 15, 2)->default(0);
            $table->unsignedTinyInteger('order_index')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'order_index']);
        });

        // ── Backfill des 2 champs legacy ───────────────────────────
        // Pour chaque facture existante :
        //   services_impression  > 0 → ligne "Frais d'impression"
        //   services_pose_depose > 0 → ligne "Frais de pose et dépose"
        $invoices = DB::table('invoices')
            ->where(function ($q) {
                $q->where('services_impression', '>', 0)
                  ->orWhere('services_pose_depose', '>', 0);
            })
            ->get(['id', 'services_impression', 'services_pose_depose']);

        $now = now();
        foreach ($invoices as $inv) {
            $idx = 0;
            if ($inv->services_impression > 0) {
                DB::table('invoice_services')->insert([
                    'invoice_id'  => $inv->id,
                    'label'       => "Frais d'impression",
                    'prix_ht'     => $inv->services_impression,
                    'order_index' => $idx++,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
            if ($inv->services_pose_depose > 0) {
                DB::table('invoice_services')->insert([
                    'invoice_id'  => $inv->id,
                    'label'       => 'Frais de pose et dépose',
                    'prix_ht'     => $inv->services_pose_depose,
                    'order_index' => $idx++,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_services');
    }
};
