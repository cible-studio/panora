<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute pose_tasks.tech_name_self — nom du technicien saisi DEPUIS LE LIEN
 * PUBLIC (cas où la tâche n'a pas d'assigned_user_id formel).
 *
 * Cas typique : l'admin envoie le lien WhatsApp à un tech sans le créer
 * en tant qu'utilisateur Panora. Le tech saisit son nom sur la page
 * publique → on persiste ce nom au niveau de la tâche pour traçabilité
 * historique (au-delà des seuls PoseTaskAction.actor).
 *
 * Recherche : on a maintenant 2 sources de vérité pour "qui a fait la pose" :
 *   1. assigned_user_id (lien formel User Panora) → relation technicien()
 *   2. tech_name_self (nom libre saisi via le lien public)
 * Affichage : si assigned_user_id présent → préférer la relation User.
 *             Sinon fallback sur tech_name_self.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            $table->string('tech_name_self', 100)->nullable()->after('team_name');
            $table->timestamp('tech_name_self_at')->nullable()->after('tech_name_self');
            $table->string('tech_name_self_ip', 45)->nullable()->after('tech_name_self_at');
        });
    }

    public function down(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            $table->dropColumn(['tech_name_self', 'tech_name_self_at', 'tech_name_self_ip']);
        });
    }
};
