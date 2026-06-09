<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Recouvrement (§8 et §9 cahier).
 *
 * Le dev A a déjà créé la table relances avec 5 canaux :
 *   telephone, email, courrier, visite, autre
 * Le cahier §9 en demande 5 :
 *   téléphone, e-mail, WhatsApp, visite, courrier
 *
 * Cette migration ajoute 'whatsapp' à l'enum existant (manquant) sans
 * casser la donnée existante.
 *
 * Ajout aussi de outcome (résultat de la relance) — explicitement listé
 * dans §14 du cahier (« reminders : ... outcome, user_id »).
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE relances MODIFY COLUMN canal ENUM(
                    'telephone',
                    'email',
                    'whatsapp',
                    'visite',
                    'courrier',
                    'autre'
                ) NOT NULL DEFAULT 'telephone'
            ");
        }

        Schema::table('relances', function (\Illuminate\Database\Schema\Blueprint $table) {
            if (!Schema::hasColumn('relances', 'outcome')) {
                $table->enum('outcome', [
                    'promesse_paiement',
                    'paiement_recu',
                    'desaccord',
                    'sans_reponse',
                    'a_relancer',
                    'autre',
                ])->nullable()->after('note')
                  ->comment('Résultat de la relance pour le suivi. Optionnel.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('relances', function (\Illuminate\Database\Schema\Blueprint $table) {
            if (Schema::hasColumn('relances', 'outcome')) {
                $table->dropColumn('outcome');
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE relances MODIFY COLUMN canal ENUM(
                    'telephone','email','courrier','visite','autre'
                ) NOT NULL DEFAULT 'telephone'
            ");
        }
    }
};
