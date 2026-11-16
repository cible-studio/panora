<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des panneaux d'une campagne (2026-11-16).
 *
 * ═══ LE BESOIN ═══
 * « Une campagne commence avec 10 panneaux et se termine avec 6. Plus
 *   tard on veut savoir quel panneau a été retiré, à quelle période, par
 *   qui et pour quelle raison. »
 *
 * Avant : rien. Le retrait laissait un `Log::info` dans les fichiers de
 * log (qui tournent) et une alerte, sans motif. Impossible de répondre à
 * la question six mois après.
 *
 * ═══ CE QUI EST ENREGISTRÉ ═══
 * Un mouvement par entrée ou sortie d'un panneau, interne ou externe :
 * date et heure, auteur, sens, motif, note, et d'où vient le mouvement
 * (retrait manuel, résolution de conflit, annulation de campagne…).
 *
 * ⚠ `reference` est un INSTANTANÉ volontaire. Un panneau peut être
 * démantelé ou renommé après coup : l'historique doit rester lisible
 * sans dépendre de l'état actuel du parc. Les clés étrangères sont en
 * `nullOnDelete` pour la même raison — on perd le lien, jamais la trace.
 *
 * L'historique commence le jour de la mise en production : les retraits
 * antérieurs ne sont reconstituables nulle part.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_panel_mouvements')) {
            return;
        }

        Schema::create('campaign_panel_mouvements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            // Un mouvement porte SOIT un panneau du parc, SOIT un panneau
            // de régie partenaire. nullOnDelete : si la fiche disparaît,
            // la ligne d'historique reste, avec sa référence figée.
            $table->foreignId('panel_id')->nullable()
                ->constrained('panels')->nullOnDelete();
            $table->foreignId('external_panel_id')->nullable()
                ->constrained('external_panels')->nullOnDelete();

            // Instantané de la référence au moment du mouvement.
            $table->string('reference', 100)->nullable();

            // 'ajout' | 'retrait'
            $table->string('sens', 10);

            // Code de motif (cf. CampaignPanelMouvement::MOTIFS) + précision.
            $table->string('motif', 40)->nullable();
            $table->text('note')->nullable();

            // D'où vient le mouvement : manuel, conflit, annulation, import…
            $table->string('source', 20)->default('manuel');

            // Qui. Null = mouvement automatique (commande planifiée).
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // created_at porte la DATE ET L'HEURE demandées.
            $table->timestamps();

            // L'écran de la campagne lit toujours par campagne, du plus
            // récent au plus ancien.
            $table->index(['campaign_id', 'created_at'], 'idx_cpm_campagne_date');
            // « Dans quelles campagnes ce panneau est-il passé ? »
            $table->index('panel_id', 'idx_cpm_panneau');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_panel_mouvements');
    }
};
