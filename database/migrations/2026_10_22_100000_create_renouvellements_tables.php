<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renouvellement des campagnes annuelles (2026-10-22).
 *
 * Pour les campagnes de 12 mois ou plus, Panora envoie une séquence de
 * mails (J-60, J-45, J-30, J-15, J-7) avec des boutons de réponse. Cf.
 * RenouvellementService et docs/RENOUVELLEMENTS.md.
 *
 * Migration ADDITIVE :
 *   · renouvellements : un dossier par campagne et par date de fin
 *     (statut, réponse du client, confirmation du MP, historique) ;
 *   · modeles_mail : les textes des mails clients, modifiables dans
 *     Panora (sans ligne → texte d'origine, codé dans ModelesMail) ;
 *   · relances_fin_campagne : colonne « etape » (fin = mail J-7 simple,
 *     j60…j7 = séquence, accuse = accusé de réception). L'unicité passe
 *     de (campagne, fin, mode) à (campagne, fin, mode, étape).
 *
 * Pas de clé étrangère vers campaigns / clients / users : base de dev en
 * MyISAM (cf. TECHNICAL_DEBT.md), et l'historique doit survivre à une
 * suppression.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('renouvellements')) {
            Schema::create('renouvellements', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('campaign_id')->index();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->date('fin_campagne');
                // en_attente | renouveler | ajuster | refus | rappel | confirme
                $table->string('statut', 12)->default('en_attente')->index();
                // Jeton des liens personnels envoyés au client (boutons).
                $table->string('token', 64)->unique();
                $table->string('reponse_choix', 12)->nullable();
                $table->text('reponse_message')->nullable();
                $table->timestamp('reponse_at')->nullable();
                // client | equipe
                $table->string('reponse_par', 10)->nullable();
                $table->unsignedBigInteger('confirme_par')->nullable();
                $table->timestamp('confirme_at')->nullable();
                // [{date, auteur, action, texte}]
                $table->json('historique')->nullable();
                $table->timestamps();

                $table->unique(['campaign_id', 'fin_campagne'], 'renouvellements_unique');
            });
        }

        if (!Schema::hasTable('modeles_mail')) {
            Schema::create('modeles_mail', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('cle', 30)->unique();
                $table->string('objet');
                $table->text('corps');
                $table->unsignedBigInteger('modifie_par')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('relances_fin_campagne') && !Schema::hasColumn('relances_fin_campagne', 'etape')) {
            Schema::table('relances_fin_campagne', function (Blueprint $table) {
                $table->string('etape', 12)->default('fin')->after('mode');
            });
            Schema::table('relances_fin_campagne', function (Blueprint $table) {
                $table->dropUnique('relances_fin_unique');
                $table->unique(['campaign_id', 'fin_campagne', 'mode', 'etape'], 'relances_fin_etape_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('relances_fin_campagne', 'etape')) {
            Schema::table('relances_fin_campagne', function (Blueprint $table) {
                $table->dropUnique('relances_fin_etape_unique');
            });
            // Les lignes de la séquence n'ont pas d'équivalent dans l'ancien schéma.
            \Illuminate\Support\Facades\DB::table('relances_fin_campagne')->where('etape', '!=', 'fin')->delete();
            Schema::table('relances_fin_campagne', function (Blueprint $table) {
                $table->dropColumn('etape');
                $table->unique(['campaign_id', 'fin_campagne', 'mode'], 'relances_fin_unique');
            });
        }
        Schema::dropIfExists('modeles_mail');
        Schema::dropIfExists('renouvellements');
    }
};
