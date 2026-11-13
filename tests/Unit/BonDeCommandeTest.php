<?php

namespace Tests\Unit;

use App\Services\BonDeCommandeService;
use PHPUnit\Framework\TestCase;

/**
 * « Sans bon de commande, pas de campagne » — flux validé le 2026-11-13.
 *
 * ═══ LE DÉFAUT CORRIGÉ ═══
 * Quand le client cliquait « J'accepte » sur sa proposition,
 * PropositionService::confirmer() faisait TROIS choses dans la même
 * transaction : bloquer fermement les panneaux, annuler les options
 * concurrentes, et CRÉER LA CAMPAGNE. Or le client n'avait pas forcément
 * envoyé son bon de commande — et sans BC, il ne doit pas y avoir de
 * campagne.
 *
 * ═══ LA RÈGLE ═══
 *   1. Le client s'engage → la résa RESTE en option, fenêtre BC de 30 j.
 *      Panneaux non bloqués fermement, options concurrentes conservées :
 *      plusieurs dossiers peuvent attendre un BC, le premier BC gagne.
 *   2. Relances au commercial (MP en copie) à J-15, J-7, J-2.
 *   3. Le commercial enregistre la réception — verbalement suffit,
 *      référence facultative.
 *   4. Le MP crée la campagne dédiée.
 *   5. Sans BC à l'échéance, l'option expire et les panneaux repartent
 *      au catalogue.
 *
 * Tests sur le code source et la configuration, sans base de données :
 * ils tournent sur le poste à base vide. Le comportement réel se vérifie
 * en production (docs/A_TESTER.md § 24).
 */
class BonDeCommandeTest extends TestCase
{
    private function src(string $chemin): string
    {
        return file_get_contents(__DIR__ . '/../../' . $chemin);
    }

    // ══════════════════════════════════════════════════════════════
    // LE VERROU CENTRAL
    // ══════════════════════════════════════════════════════════════

    /**
     * Le verrou est posé DANS confirmer(), pas chez ses appelants :
     * aucun chemin ne doit pouvoir créer une campagne sans BC.
     */
    public function test_confirmer_refuse_de_creer_une_campagne_sans_bc(): void
    {
        $src = $this->src('app/Services/PropositionService.php');

        $this->assertStringContainsString('$bc->peutCreerCampagne($reservation)', $src);
        $this->assertStringContainsString("throw new \\RuntimeException('BC_MANQUANT:'", $src);

        // Le verrou doit précéder la transaction, sinon il ne protège rien.
        $posVerrou = strpos($src, 'BC_MANQUANT');
        $posTrans  = strpos($src, 'DB::transaction(function () use ($reservation, &$campaign)');
        $this->assertIsInt($posVerrou);
        $this->assertIsInt($posTrans);
        $this->assertLessThan($posTrans, $posVerrou,
            'Le contrôle du BC doit être évalué AVANT la transaction de confirmation.');
    }

    public function test_le_clic_du_client_n_appelle_plus_confirmer(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/PropositionController.php');

        $this->assertStringContainsString('$this->propositionService->accepterClient($reservation);', $src,
            "L'acceptation du client doit ouvrir l'attente de BC, pas créer la campagne.");
    }

    /**
     * Le mail « votre campagne est lancée » ne doit plus partir à
     * l'acceptation : il annoncerait une campagne inexistante.
     */
    public function test_le_mail_campagne_lancee_ne_part_plus_a_l_acceptation(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/PropositionController.php');

        $this->assertStringContainsString('$adressesSuivi = $campaign && $reservation->client', $src,
            'Sans campagne, aucune adresse : le mail ne part pas.');
    }

    // ══════════════════════════════════════════════════════════════
    // LES QUATRE PORTES SONT FERMÉES
    // ══════════════════════════════════════════════════════════════

    public function test_la_resa_ferme_ouvre_une_attente_au_lieu_de_creer_la_campagne(): void
    {
        foreach ([
            'app/Services/ReservationService.php',
            'app/Http/Controllers/Admin/ReservationController.php',
        ] as $fichier) {
            $src = $this->src($fichier);
            $this->assertStringContainsString('ouvrirAttente($reservation)', $src,
                "{$fichier} : une résa ferme doit ouvrir l'attente de BC.");
            $this->assertStringContainsString('campagne_differee_bc', $src,
                "{$fichier} : le report de la campagne doit être tracé.");
        }
    }

    public function test_le_renouvellement_cree_une_option_en_attente_de_bc(): void
    {
        $src = $this->src('app/Services/Diffusion/RenouvellementService.php');

        $this->assertStringContainsString('ouvrirOptionRenouvellement', $src);
        $this->assertStringContainsString("\$choix === 'renouveler'", $src,
            "Seul « renouveler » bloque les panneaux : « ajuster » change le réseau.");
        $this->assertStringContainsString('ouvrirAttente($resa)', $src);
        $this->assertStringContainsString('!$essai', $src,
            'Un envoi de test ne doit jamais créer de vraie réservation.');
    }

    // ══════════════════════════════════════════════════════════════
    // LE PIÈGE DES 7 JOURS
    // ══════════════════════════════════════════════════════════════

    /**
     * `reservations:expire-old-options --days 7` tourne tous les jours à
     * 01h20 et annule les options de plus de 7 jours. Sans garde-fou,
     * elle tuait les dossiers en attente de BC au 7e jour — avant même
     * la première relance (J-15 sur une fenêtre de 30 jours).
     */
    public function test_l_ancienne_expiration_7j_ignore_les_dossiers_en_attente_de_bc(): void
    {
        $src = $this->src('app/Console/Commands/ExpireReservationOptions.php');

        $this->assertStringContainsString("->whereNull('bc_attendu_jusqu_au')", $src,
            'Sans ce filtre, une attente de BC de 30 jours meurt au 7e jour.');
    }

    public function test_les_deux_commandes_bc_sont_planifiees(): void
    {
        $src = $this->src('routes/console.php');

        $this->assertStringContainsString("Schedule::command('reservations:expire-bc')", $src);
        $this->assertStringContainsString("Schedule::command('reservations:relancer-bc')", $src);

        // L'expiration doit passer AVANT les relances du jour : inutile de
        // relancer le matin un dossier annulé à l'aube.
        $this->assertLessThan(
            strpos($src, "Schedule::command('reservations:relancer-bc')"),
            strpos($src, "Schedule::command('reservations:expire-bc')"),
        );
    }

    // ══════════════════════════════════════════════════════════════
    // CONFIGURATION ET PALIERS
    // ══════════════════════════════════════════════════════════════

    public function test_le_delai_et_les_paliers_sont_ceux_valides(): void
    {
        $config = require __DIR__ . '/../../config/bon_de_commande.php';

        $this->assertSame(30, $config['delai_jours'],
            'Délai validé par écrit : 30 jours.');
        $this->assertSame([15, 7, 2], $config['relances_jours_restants'],
            'Relances validées : J-15, J-7, J-2.');
    }

    public function test_chaque_palier_a_sa_colonne_de_memoire(): void
    {
        $config = require __DIR__ . '/../../config/bon_de_commande.php';

        foreach ($config['relances_jours_restants'] as $jours) {
            $this->assertArrayHasKey($jours, $config['colonnes_relance'],
                "Sans colonne dédiée, la relance J-{$jours} repartirait chaque jour.");
        }
    }

    public function test_les_colonnes_de_relance_existent_dans_la_migration(): void
    {
        $config    = require __DIR__ . '/../../config/bon_de_commande.php';
        $migration = $this->src('database/migrations/2026_11_13_150000_add_bon_de_commande_to_reservations.php');

        foreach ($config['colonnes_relance'] as $colonne) {
            $this->assertStringContainsString("'{$colonne}'", $migration,
                "La colonne {$colonne} doit être créée par la migration.");
        }
        foreach (['bc_attendu_jusqu_au', 'bc_recu_at', 'bc_recu_par', 'bc_reference'] as $colonne) {
            $this->assertStringContainsString("'{$colonne}'", $migration);
        }
    }

    /**
     * Les options déjà en cours basculent dans le nouveau flux avec 30
     * jours pleins — sinon l'expiration des 7 jours les tuerait le
     * lendemain du déploiement.
     */
    public function test_la_migration_bascule_les_options_existantes(): void
    {
        $src = $this->src('database/migrations/2026_11_13_150000_add_bon_de_commande_to_reservations.php');

        $this->assertStringContainsString("->where('type', 'option')", $src);
        $this->assertStringContainsString("->where('status', 'en_attente')", $src);
        $this->assertStringContainsString('now()->addDays($delai)', $src);
    }

    // ══════════════════════════════════════════════════════════════
    // DOSSIERS CONCURRENTS
    // ══════════════════════════════════════════════════════════════

    /**
     * Conséquence assumée du flux : l'acceptation ne bloquant plus
     * fermement, deux clients peuvent attendre un BC sur le même
     * panneau. L'écran DOIT le dire, sinon on vend deux fois sans le
     * savoir.
     */
    public function test_la_concurrence_est_calculee_et_affichee(): void
    {
        $service = $this->src('app/Services/BonDeCommandeService.php');
        $vue     = $this->src('resources/views/admin/reservations/show.blade.php');

        $this->assertStringContainsString('public function dossiersConcurrents', $service);
        $this->assertStringContainsString('public function messageConcurrence', $service);
        $this->assertStringContainsString('dossiers attendent un bon de commande', $service);
        $this->assertStringContainsString('messageConcurrence($reservation)', $vue,
            "La fiche réservation doit afficher les dossiers concurrents.");
    }

    // ══════════════════════════════════════════════════════════════
    // RÔLES
    // ══════════════════════════════════════════════════════════════

    public function test_le_commercial_saisit_le_bc_et_le_mp_cree_la_campagne(): void
    {
        $routes = $this->src('routes/admin.php');

        $this->assertMatchesRegularExpression(
            "/role:admin,commercial'\)->group\(function \(\) \{\s*Route::post\('reservations\/\{reservation\}\/bc\/recu'/",
            $routes,
            'La saisie du BC revient au commercial (et à l\'admin).'
        );
        $this->assertMatchesRegularExpression(
            "/role:admin,mediaplanner'\)->group\(function \(\) \{\s*Route::post\('reservations\/\{reservation\}\/creer-campagne'/",
            $routes,
            'La création de la campagne revient au MP (et à l\'admin).'
        );
    }

    public function test_la_reference_du_bc_reste_facultative(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/ReservationController.php');

        $this->assertStringContainsString("'bc_reference' => 'nullable|string|max:190'", $src,
            'Le BC peut être confirmé verbalement : la référence ne doit jamais être obligatoire.');
    }

    // ══════════════════════════════════════════════════════════════
    // API DU SERVICE
    // ══════════════════════════════════════════════════════════════

    public function test_le_service_expose_la_regle_en_un_seul_endroit(): void
    {
        foreach ([
            'ouvrirAttente', 'estEnAttente', 'estRecu', 'joursRestants',
            'peutCreerCampagne', 'motifBlocage', 'enregistrerReception',
            'annulerReception', 'dossiersConcurrents', 'messageConcurrence',
            'aRelancer', 'aExpirer', 'noterRelance',
        ] as $methode) {
            $this->assertTrue(
                method_exists(BonDeCommandeService::class, $methode),
                "BonDeCommandeService::{$methode}() manque."
            );
        }
    }

    public function test_ouvrir_attente_est_idempotent(): void
    {
        $src = $this->src('app/Services/BonDeCommandeService.php');

        $this->assertStringContainsString('if ($reservation->bc_attendu_jusqu_au) {', $src,
            "Un second clic du client ne doit pas repousser l'échéance.");
    }

    /**
     * Un dossier antérieur à cette règle (sans échéance BC) ne doit pas
     * se retrouver bloqué : l'historique deviendrait ingérable.
     */
    public function test_les_dossiers_anterieurs_ne_sont_pas_bloques(): void
    {
        $src = $this->src('app/Services/BonDeCommandeService.php');

        $this->assertStringContainsString(
            'if (!$reservation->bc_attendu_jusqu_au) return true;',
            $src,
            'Sans fenêtre BC ouverte, peutCreerCampagne() doit répondre vrai.'
        );
    }
}
