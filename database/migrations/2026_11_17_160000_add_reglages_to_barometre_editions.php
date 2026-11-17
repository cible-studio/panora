<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baromètre clients (2026-11-17, après le premier essai en prod) : les
 * réglages de l'édition se font à l'écran, plus seulement dans le .env —
 * le bouton « Envoyer à moi » restait grisé tant que le lien du
 * questionnaire n'était pas posé sur le serveur.
 *
 *   formulaire / champ_structure : tirés du lien prérempli collé à l'écran ;
 *   signature_* : signature du mail (Josiane, décision du 17/11) ;
 *   repondre_a  : adresse qui reçoit les réponses des clients.
 * Vide = valeur du .env (config/barometre.php). Portable MySQL et SQLite.
 */
return new class extends Migration {
    public function up(): void
    {
        $colonnes = [
            'formulaire'         => fn (Blueprint $t) => $t->string('formulaire', 500)->nullable(),
            'champ_structure'    => fn (Blueprint $t) => $t->string('champ_structure', 60)->nullable(),
            'signature_nom'      => fn (Blueprint $t) => $t->string('signature_nom', 120)->nullable(),
            'signature_fonction' => fn (Blueprint $t) => $t->string('signature_fonction', 160)->nullable(),
            'signature_societe'  => fn (Blueprint $t) => $t->string('signature_societe', 120)->nullable(),
            'repondre_a'         => fn (Blueprint $t) => $t->string('repondre_a', 191)->nullable(),
        ];

        foreach ($colonnes as $nom => $definition) {
            if (!Schema::hasColumn('barometre_editions', $nom)) {
                Schema::table('barometre_editions', fn (Blueprint $t) => $definition($t));
            }
        }
    }

    public function down(): void
    {
        foreach (['formulaire', 'champ_structure', 'signature_nom', 'signature_fonction', 'signature_societe', 'repondre_a'] as $nom) {
            if (Schema::hasColumn('barometre_editions', $nom)) {
                Schema::table('barometre_editions', fn (Blueprint $t) => $t->dropColumn($nom));
            }
        }
    }
};
