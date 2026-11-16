<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Devis généré depuis un renouvellement, et services proposés en un clic
 * (2026-11-16).
 *
 * ═══ CE QUI EST EN JEU ═══
 *
 * 1) DEUX DOSSIERS SUR LE MÊME PANNEAU.
 *    Depuis le flux « bon de commande », un client qui répond « je
 *    renouvelle » déclenche une RÉSERVATION EN OPTION sur ses panneaux,
 *    en attente de BC. On peut maintenant générer un devis depuis la
 *    fiche de renouvellement : sans précaution, l'acceptation de ce
 *    devis créerait une SECONDE réservation sur les mêmes panneaux.
 *
 *    Règle retenue : le devis remplace l'option. Mais l'option n'est
 *    annulée qu'à l'ACCEPTATION du devis — pas à sa création. Un devis
 *    ne bloque aucun panneau (billing.quote_legal_mention) : fermer
 *    l'option plus tôt rouvrirait le réseau à un autre client pendant
 *    que celui-ci décide.
 *
 * 2) DEUX FORMULES DE PÉRIODE.
 *    L'option automatique et le devis doivent proposer exactement les
 *    mêmes dates. La règle vit dans RenouvellementService::periodeSuivante().
 *
 * 3) UNE DURÉE FIGÉE À 1 MOIS.
 *    Toute ligne pré-remplie partait à `duree_mois => 1`, même pour une
 *    période d'un an : le total du devis était faux dès l'ouverture.
 *
 * Tests sur le code source et la configuration, sans base de données :
 * ils tournent sur le poste à base vide. Le comportement réel se vérifie
 * en production (docs/A_TESTER.md).
 */
class DevisRenouvellementEtServicesTest extends TestCase
{
    private function src(string $chemin): string
    {
        $absolu = __DIR__ . '/../../' . $chemin;
        $this->assertFileExists($absolu, "Fichier attendu absent : {$chemin}");

        return (string) file_get_contents($absolu);
    }

    // ══════════════════════════════════════════════════════════════
    // SERVICES PROPOSÉS EN UN CLIC
    // ══════════════════════════════════════════════════════════════

    public function test_les_deux_prestations_demandees_sont_dans_la_config(): void
    {
        $config = require __DIR__ . '/../../config/billing.php';

        $this->assertArrayHasKey('services_preselection', $config);

        $libelles = array_column($config['services_preselection'], 'label');

        $this->assertContains('FRAIS DE POSE', $libelles);
        $this->assertContains('IMPRESSION', $libelles);
    }

    /**
     * Le devis ET la facture FNE lisent la MÊME liste. Deux listes
     * recopiées, c'est la garantie qu'un jour elles divergeront.
     */
    public function test_le_devis_et_la_facture_lisent_la_meme_liste(): void
    {
        foreach ([
            'devis'        => 'resources/views/admin/quotes/partials/_form.blade.php',
            'facture FNE'  => 'resources/views/admin/invoices/partials/_form-fne.blade.php',
        ] as $ecran => $vue) {
            $this->assertStringContainsString(
                "config('billing.services_preselection', [])",
                $this->src($vue),
                "Le formulaire {$ecran} doit lire la liste depuis "
                . "config/billing.php, et non porter sa propre copie."
            );
        }
    }

    /**
     * Les prestations sont PROPOSÉES, pas imposées : aucun des deux
     * formulaires ne doit les ajouter d'office à l'ouverture.
     */
    public function test_les_prestations_ne_sont_pas_ajoutees_d_office(): void
    {
        foreach ([
            'resources/views/admin/quotes/partials/_form.blade.php',
            'resources/views/admin/invoices/partials/_form-fne.blade.php',
        ] as $vue) {
            $src = $this->src($vue);

            // Un appel automatique au chargement trahirait l'ajout d'office.
            $this->assertStringNotContainsString(
                "addService('FRAIS DE POSE')",
                $src,
                "{$vue} : la prestation ne doit être ajoutée que par un clic."
            );
        }
    }

    /**
     * Deux lignes « FRAIS DE POSE » seraient facturées deux fois au
     * client. Les deux formulaires doivent refuser le doublon.
     */
    public function test_une_prestation_deja_presente_n_est_pas_ajoutee_deux_fois(): void
    {
        foreach ([
            'devis'       => 'resources/views/admin/quotes/partials/_form.blade.php',
            'facture FNE' => 'resources/views/admin/invoices/partials/_form-fne.blade.php',
        ] as $ecran => $vue) {
            $this->assertStringContainsString(
                'existante',
                $this->src($vue),
                "Le formulaire {$ecran} doit détecter une prestation déjà "
                . "présente au lieu d'en créer une jumelle."
            );
        }
    }

    // ══════════════════════════════════════════════════════════════
    // DEVIS DEPUIS UN RENOUVELLEMENT
    // ══════════════════════════════════════════════════════════════

    /**
     * La période du renouvellement est calculée à UN SEUL endroit : la
     * création de l'option automatique et le devis doivent proposer les
     * mêmes dates.
     */
    public function test_la_periode_du_renouvellement_est_centralisee(): void
    {
        $src = $this->src('app/Services/Diffusion/RenouvellementService.php');

        $this->assertStringContainsString(
            'public static function periodeSuivante(Campaign $campagne): array',
            $src,
            "La règle « lendemain de la fin + durée de la campagne » doit "
            . "vivre dans une méthode unique."
        );

        $this->assertStringContainsString(
            '[$nDebut, $nFin] = self::periodeSuivante($campagne);',
            $src,
            "La création de l'option automatique doit appeler la règle "
            . "centralisée au lieu de recalculer les dates."
        );

        // La formule ne doit plus exister en double dans le fichier.
        $this->assertSame(
            1,
            substr_count($src, "copy()->addDays(\$duree)"),
            "La formule de durée du renouvellement apparaît plusieurs fois : "
            . "c'est exactement la duplication que la centralisation évite."
        );
    }

    public function test_le_dossier_de_renouvellement_propose_de_generer_le_devis(): void
    {
        $vue = $this->src('resources/views/admin/renouvellements/_dossier.blade.php');

        $this->assertStringContainsString(
            "route('admin.renouvellements.devis', \$c)",
            $vue,
            "La fiche dossier doit porter le bouton « Générer le devis »."
        );

        // Pas proposé tant que rien n'est décidé, ni après un refus.
        $this->assertStringContainsString(
            "in_array(\$l['statut'], ['renouveler', 'ajuster', 'confirme'], true)",
            $vue,
            "Le bouton ne doit apparaître que sur un renouvellement engagé."
        );
    }

    /**
     * Le devis s'ouvre avec ce qu'on sait déjà : client, interlocuteur,
     * campagne, période, panneaux. C'était l'objet de la demande.
     */
    public function test_le_devis_est_prerempli_avec_le_dossier(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/RenouvellementController.php');

        foreach ([
            'client_id',
            'client_contact_id',
            'campaign_id',
            'period_start',
            'period_end',
            'panel_ids',
            'remplace_reservation_id',
        ] as $cle) {
            $this->assertStringContainsString(
                "'{$cle}'",
                $src,
                "Le devis généré depuis un renouvellement doit transmettre « {$cle} »."
            );
        }

        $this->assertStringContainsString(
            'RenouvellementService::optionAutomatique($campaign)',
            $src,
            "Les panneaux et les dates doivent venir de l'option déjà "
            . "ouverte quand elle existe : c'est elle qui fait foi."
        );
    }

    /**
     * Le cœur de la règle : un seul dossier vivant, mais pas avant
     * l'acceptation.
     */
    public function test_l_option_n_est_annulee_qu_a_l_acceptation_du_devis(): void
    {
        $controleur = $this->src('app/Http/Controllers/Admin/QuoteController.php');
        $convertisseur = $this->src('app/Services/QuoteConverter.php');

        // À l'enregistrement : on RATTACHE, on n'annule pas.
        $this->assertStringContainsString(
            'rattacherOptionRemplacee',
            $controleur,
            "L'enregistrement du devis doit rattacher l'option qu'il remplacera."
        );
        $this->assertStringNotContainsString(
            'ReservationStatus::ANNULE',
            $controleur,
            "QuoteController ne doit PAS annuler l'option : tant que le "
            . "client n'a pas accepté, le devis ne bloque rien et les "
            . "panneaux doivent rester tenus."
        );

        // À l'acceptation : on annule.
        $this->assertStringContainsString(
            'libererOptionRemplacee',
            $convertisseur,
            "La conversion du devis accepté doit libérer l'option remplacée."
        );
        $this->assertStringContainsString(
            'ReservationStatus::ANNULE->value',
            $convertisseur,
            "L'option remplacée doit être annulée, pas supprimée : elle "
            . "reste lisible dans l'historique du client."
        );
    }

    /**
     * Un identifiant trafiqué dans le formulaire ne doit pas pouvoir
     * condamner la réservation d'un autre client.
     */
    public function test_le_rattachement_verifie_le_client_et_l_etat_de_l_option(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/QuoteController.php');

        foreach ([
            '(int) $option->client_id === (int) $quote->client_id',
            "\$option->bc_attendu_jusqu_au !== null",
            "\$option->bc_recu_at === null",
        ] as $garde) {
            $this->assertStringContainsString(
                $garde,
                $src,
                "Le rattachement d'une option à un devis doit vérifier : {$garde}"
            );
        }
    }

    /**
     * On ne détourne pas `reservations.quote_id` : cette colonne sert de
     * preuve qu'un devis a été accepté (Quote::aEteAccepte()). La remplir
     * sur une simple option ferait passer un brouillon pour un devis signé
     * et interdirait sa suppression.
     */
    public function test_le_lien_ne_detourne_pas_la_colonne_quote_id(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/QuoteController.php');

        $this->assertStringContainsString(
            "'remplacee_par_quote_id' => \$quote->id",
            $src,
            "Le lien doit passer par sa colonne dédiée."
        );
        $this->assertStringNotContainsString(
            "forceFill(['quote_id' => \$quote->id])",
            $src,
            "Remplir quote_id sur une option ferait passer un brouillon "
            . "pour un devis accepté."
        );
    }

    // ══════════════════════════════════════════════════════════════
    // DURÉE DES LIGNES PRÉ-REMPLIES
    // ══════════════════════════════════════════════════════════════

    /**
     * Une période d'un an doit donner des lignes à 12 mois, pas à 1.
     * Et la règle de durée facturée ne doit jamais être recopiée.
     */
    public function test_la_duree_des_lignes_suit_la_periode_du_devis(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/QuoteController.php');

        $this->assertStringContainsString(
            'Campaign::computeBillableMonths(',
            $src,
            "La durée des lignes pré-remplies doit venir de la règle "
            . "centralisée, jamais d'une formule recopiée."
        );

        $this->assertStringNotContainsString(
            "'duree_mois'       => 1,",
            $src,
            "Les lignes pré-remplies ne doivent plus partir à 1 mois en dur."
        );
    }

    /**
     * Une querystring illisible ne doit pas rendre l'écran inaccessible.
     */
    public function test_des_dates_illisibles_ne_cassent_pas_le_formulaire(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/QuoteController.php');

        $this->assertStringContainsString(
            '$dureeMoisPreselect = 1.0;',
            $src,
            "Des dates illisibles dans l'URL doivent retomber sur 1 mois, "
            . "jamais produire une 500."
        );
    }

    // ══════════════════════════════════════════════════════════════
    // MIGRATION
    // ══════════════════════════════════════════════════════════════

    public function test_la_migration_est_additive_et_rejouable(): void
    {
        $src = $this->src(
            'database/migrations/2026_11_16_140000_add_remplacee_par_quote_id_to_reservations.php'
        );

        $this->assertStringContainsString(
            "Schema::hasColumn('reservations', 'remplacee_par_quote_id')",
            $src,
            "La migration doit pouvoir être rejouée sans échouer : les "
            . "migrations Panora ont divergé de la base réelle (règle n°2)."
        );

        $this->assertStringContainsString(
            "Schema::hasColumn('reservations', 'quote_id')",
            $src,
            "after() ne doit être posé que si la colonne de référence "
            . "existe, sinon MySQL rejette l'ALTER entier."
        );
    }
}
