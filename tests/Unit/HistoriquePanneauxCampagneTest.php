<?php

namespace Tests\Unit;

use App\Models\CampaignPanelMouvement;
use PHPUnit\Framework\TestCase;

/**
 * Historique des panneaux d'une campagne (2026-11-16).
 *
 * ═══ LE BESOIN ═══
 * « Une campagne commence avec 10 panneaux et se termine avec 6. Plus
 *   tard on veut savoir quel panneau a été retiré, à quelle période, par
 *   qui et pour quelle raison. »
 *
 * Avant : rien de requêtable. Un `Log::info` dans des fichiers qui
 * tournent, une alerte, et aucun motif demandé.
 *
 * ═══ CE QUI EST TENU ICI ═══
 *  · TOUS les chemins écrivent l'historique — y compris ceux qui
 *    touchaient la table directement. Un seul chemin oublié, et un
 *    panneau disparaît sans explication : c'est précisément ce qu'on
 *    cherche à éviter.
 *  · Le motif est obligatoire au retrait manuel, et choisi dans une
 *    liste fermée (un motif tapé au clavier ne se compte jamais).
 *  · Les ajouts sont tracés aussi : sans eux, impossible de dire de quoi
 *    la campagne était faite à une date donnée.
 *  · Une trace qui échoue ne bloque jamais le retrait.
 *
 * Tests sur le code source, sans base de données : ils tournent sur le
 * poste à base vide. Le comportement réel se vérifie en production
 * (docs/A_TESTER.md § 33).
 */
class HistoriquePanneauxCampagneTest extends TestCase
{
    private function src(string $chemin): string
    {
        $absolu = __DIR__ . '/../../' . $chemin;
        $this->assertFileExists($absolu, "Fichier attendu absent : {$chemin}");

        return (string) file_get_contents($absolu);
    }

    // ══════════════════════════════════════════════════════════════
    // AUCUN CHEMIN N'ÉCHAPPE À L'HISTORIQUE
    // ══════════════════════════════════════════════════════════════

    /**
     * Les six endroits où un panneau entre ou sort d'une campagne.
     *
     * @return array<string, array{0:string}>
     */
    public static function cheminsDeMouvement(): array
    {
        return [
            'ajout / retrait manuel, annulation' => ['app/Services/CampaignService.php'],
            'résolution de conflit'              => ['app/Http/Controllers/Admin/ConflictsController.php'],
            'création depuis une réservation'    => ['app/Http/Controllers/Admin/CampaignController.php'],
            'import de campagne'                 => ['app/Http/Controllers/Admin/CampaignImportController.php'],
        ];
    }

    /**
     * @dataProvider cheminsDeMouvement
     */
    public function test_chaque_chemin_ecrit_l_historique(string $fichier): void
    {
        $this->assertStringContainsString(
            'MouvementsPanneaux',
            $this->src($fichier),
            "{$fichier} fait entrer ou sortir un panneau d'une campagne : "
            . "il doit écrire l'historique. Un chemin qui l'oublie laisse "
            . "un panneau disparaître sans explication."
        );
    }

    /**
     * Le piège d'origine : cette page retirait le panneau en touchant
     * `campaign_panels` directement, sans passer par CampaignService.
     */
    public function test_la_resolution_de_conflit_trace_son_retrait(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/ConflictsController.php');

        $this->assertStringContainsString(
            'CampaignPanelMouvement::MOTIF_CONFLIT',
            $src,
            "Un panneau retiré par arbitrage de conflit doit porter le "
            . "motif « conflit de réservation résolu »."
        );
    }

    /**
     * Une campagne annulée libère tous ses panneaux d'un coup : sans
     * trace, ils disparaissent en silence.
     */
    public function test_l_annulation_trace_chaque_panneau_libere(): void
    {
        $src = $this->src('app/Services/CampaignService.php');

        $this->assertStringContainsString(
            'retraitDeTous(',
            $src,
            "L'annulation d'une campagne doit inscrire chaque panneau "
            . "libéré dans l'historique."
        );

        $this->assertStringContainsString(
            'CampaignPanelMouvement::MOTIF_ANNULATION',
            $src,
            "Le motif « campagne annulée » doit être posé automatiquement."
        );
    }

    /**
     * Sans les ajouts, un panneau entré en cours de route puis retiré
     * passerait pour un panneau perdu depuis le départ.
     */
    public function test_les_ajouts_sont_traces_aussi(): void
    {
        $src = $this->src('app/Services/CampaignService.php');

        $this->assertStringContainsString(
            'ajoutInternes(',
            $src,
            "Les ajouts de panneaux doivent être tracés : il en faut deux "
            . "sens pour reconstituer la composition à une date donnée."
        );

        $this->assertStringContainsString(
            'ajoutExternes(',
            $src,
            "Les panneaux de régie partenaire comptent aussi."
        );
    }

    // ══════════════════════════════════════════════════════════════
    // LE MOTIF
    // ══════════════════════════════════════════════════════════════

    /**
     * Un motif facultatif n'est jamais rempli, et dans six mois
     * l'historique dirait « retiré — motif : (vide) ».
     */
    public function test_le_motif_est_obligatoire_au_retrait_manuel(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/CampaignController.php');

        $this->assertStringContainsString(
            "'motif' => ['required'",
            $src,
            "Le motif doit être obligatoire pour retirer un panneau."
        );

        $this->assertStringContainsString(
            'MOTIFS_RETRAIT_MANUEL',
            $src,
            "Le motif doit être choisi dans la liste fermée : un motif "
            . "libre s'écrit de dix façons et ne se compte jamais."
        );
    }

    /**
     * Les motifs automatiques ne doivent pas être proposés à la main :
     * les choisir donnerait une trace fausse.
     */
    public function test_les_motifs_automatiques_ne_sont_pas_proposes(): void
    {
        foreach ([
            CampaignPanelMouvement::MOTIF_CONFLIT,
            CampaignPanelMouvement::MOTIF_ANNULATION,
            CampaignPanelMouvement::MOTIF_CREATION,
            CampaignPanelMouvement::MOTIF_AJOUT_MANUEL,
        ] as $automatique) {
            $this->assertNotContains(
                $automatique,
                CampaignPanelMouvement::MOTIFS_RETRAIT_MANUEL,
                "Le motif « {$automatique} » est posé par le code : le "
                . "proposer dans la liste donnerait une trace fausse."
            );
        }

        // Et tous les motifs proposés doivent exister dans le catalogue.
        foreach (CampaignPanelMouvement::MOTIFS_RETRAIT_MANUEL as $code) {
            $this->assertArrayHasKey(
                $code,
                CampaignPanelMouvement::MOTIFS,
                "Le motif « {$code} » est proposé mais n'a pas de libellé."
            );
        }
    }

    public function test_le_formulaire_de_retrait_demande_le_motif(): void
    {
        $vue = $this->src('resources/views/admin/campaigns/show.blade.php');

        $this->assertStringContainsString(
            'name="motif"',
            $vue,
            "La fenêtre « Retirer le panneau » doit porter le champ motif."
        );

        $this->assertStringContainsString(
            'MOTIFS_RETRAIT_MANUEL',
            $vue,
            "La liste déroulante doit être alimentée par le catalogue, "
            . "pas recopiée dans la vue."
        );

        // Le motif du retrait précédent ne doit pas rester sélectionné.
        $this->assertStringContainsString(
            '_resetRetireModal()',
            $vue,
            "Le motif doit être remis à zéro à chaque ouverture : sinon "
            . "celui du panneau précédent serait enregistré par mégarde."
        );
    }

    // ══════════════════════════════════════════════════════════════
    // ROBUSTESSE DE LA TRACE
    // ══════════════════════════════════════════════════════════════

    /**
     * Mieux vaut un retrait non tracé qu'un retrait impossible.
     */
    public function test_une_trace_en_echec_ne_bloque_pas_le_retrait(): void
    {
        $src = $this->src('app/Services/Campagnes/MouvementsPanneaux.php');

        $this->assertStringContainsString(
            'catch (\Throwable $e)',
            $src,
            "L'écriture de l'historique ne doit jamais faire échouer "
            . "l'opération métier."
        );
    }

    /**
     * Un panneau démantelé ou renommé après coup ne doit pas rendre
     * l'historique illisible.
     */
    public function test_la_reference_du_panneau_est_figee(): void
    {
        $src = $this->src('app/Services/Campagnes/MouvementsPanneaux.php');

        $this->assertStringContainsString(
            "pluck('reference', 'id')",
            $src,
            "La référence doit être copiée dans la ligne d'historique au "
            . "moment du mouvement."
        );

        $this->assertStringContainsString(
            'Panel::withTrashed()',
            $src,
            "Le retrait d'un panneau qui vient d'être démantelé est le cas "
            . "le plus utile à tracer : il doit rester lisible."
        );
    }

    /**
     * Supprimer une ligne d'historique viderait la trace de son sens.
     */
    public function test_l_historique_ne_se_modifie_ni_ne_se_supprime(): void
    {
        $src = $this->src('app/Services/Campagnes/MouvementsPanneaux.php');

        foreach (['->update(', '->delete(', 'forceDelete'] as $interdit) {
            $this->assertStringNotContainsString(
                $interdit,
                $src,
                "L'historique est un journal : aucune écriture ne doit le "
                . "modifier ni le supprimer ({$interdit})."
            );
        }
    }

    public function test_la_table_conserve_la_trace_meme_sans_le_panneau(): void
    {
        $src = $this->src(
            'database/migrations/2026_11_16_180000_create_campaign_panel_mouvements_table.php'
        );

        $this->assertStringContainsString(
            'nullOnDelete()',
            $src,
            "Si la fiche du panneau disparaît, la ligne d'historique doit "
            . "rester — on perd le lien, jamais la trace."
        );

        $this->assertStringContainsString(
            "index(['campaign_id', 'created_at']",
            $src,
            "L'écran lit toujours par campagne et par date."
        );
    }
}
