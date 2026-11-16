<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les emplacements des clients annuels ne se proposent pas (2026-11-16).
 *
 * ═══ LE DÉFAUT ═══
 * Une recherche de disponibilités sur 2027 faisait ressortir TOUT le parc :
 * les campagnes 2026 se terminent avant, donc tous les panneaux
 * apparaissaient libres. Les emplacements des clients fidèles partaient
 * ainsi au catalogue — et à la concurrence — avant même qu'on les ait
 * appelés pour leur renouvellement.
 *
 * ═══ LA RÈGLE (validée le 2026-11-16) ═══
 * « Les clients annuels ont un emplacement fixe. On ne peut pas proposer
 *   leur panneau pour l'année suivante, même s'ils n'ont pas encore
 *   décidé de renouveler. »
 *
 *   · Est concerné : un panneau tenu par une campagne de 12 mois ou plus
 *     (Campaign::estAnnuelle) qui tourne aujourd'hui ou se termine plus
 *     tard. Terminée sans renouvellement → le panneau revient aussitôt.
 *   · Écran Disponibilités : masqués PAR DÉFAUT, avec un compteur ; la
 *     case « Inclure leurs emplacements » les réintègre.
 *   · Catalogue envoyé aux clients : toujours écartés, sans arbitrage
 *     possible — l'envoi est automatique.
 *   · Rien n'est BLOQUÉ : c'est une règle de ce qu'on propose, pas de ce
 *     qu'on peut réserver. Distincte de retenues() (renouvellement
 *     confirmé), qui bloque.
 *
 * Tests sur le code source, sans base de données : ils tournent sur le
 * poste à base vide. Le comportement réel se vérifie en production
 * (docs/A_TESTER.md § 30).
 */
class EmplacementsClientsAnnuelsTest extends TestCase
{
    private function src(string $chemin): string
    {
        $absolu = __DIR__ . '/../../' . $chemin;
        $this->assertFileExists($absolu, "Fichier attendu absent : {$chemin}");

        return (string) file_get_contents($absolu);
    }

    private function service(): string
    {
        return $this->src('app/Services/Diffusion/RenouvellementService.php');
    }

    // ══════════════════════════════════════════════════════════════
    // LA RÈGLE, À UN SEUL ENDROIT
    // ══════════════════════════════════════════════════════════════

    public function test_la_regle_vit_dans_une_methode_unique(): void
    {
        $src = $this->service();

        $this->assertStringContainsString(
            'public static function emplacementsAnnuels(): array',
            $src,
            "La règle « emplacement d'un client annuel » doit vivre à un "
            . "seul endroit, à côté de sa sœur retenues()."
        );

        $this->assertStringContainsString(
            'public static function panneauxClientsAnnuels(): array',
            $src,
            "Les appelants qui veulent juste exclure doivent avoir une "
            . "entrée qui ne lève jamais."
        );
    }

    /**
     * Le piège central : estAnnuelle() est une boucle PHP qui compte les
     * mois, jamais traduite en SQL. Une approximation du type
     * « DATEDIFF >= 365 » serait une copie divergente de la règle de durée.
     */
    public function test_la_duree_annuelle_n_est_jamais_recopiee_en_sql(): void
    {
        $src = $this->service();

        $this->assertStringContainsString(
            'estAnnuelle()',
            $src,
            "La détection « annuelle » doit passer par Campaign::estAnnuelle()."
        );

        foreach (['DATEDIFF', 'TIMESTAMPDIFF', 'INTERVAL 1 YEAR', 'INTERVAL 12 MONTH'] as $approximation) {
            $this->assertStringNotContainsString(
                $approximation,
                $src,
                "La durée d'une campagne annuelle ne doit jamais être "
                . "réécrite en SQL ({$approximation}) : la règle vit dans "
                . "Campaign::resolvePeriodBetween()."
            );
        }
    }

    /**
     * Une campagne terminée ou annulée ne tient plus rien : son panneau
     * est réellement libre, le masquer ferait disparaître du stock
     * vendable.
     */
    public function test_seules_les_campagnes_en_cours_ou_a_venir_tiennent_un_emplacement(): void
    {
        $src = $this->service();

        $this->assertStringContainsString(
            "whereDate('end_date', '>=', \$aujourdhui)",
            $src,
            "Une campagne déjà terminée ne doit pas masquer son panneau."
        );

        foreach (['CampaignStatus::ANNULE->value', 'CampaignStatus::TERMINE->value'] as $statut)  {
            $this->assertStringContainsString(
                $statut,
                $src,
                "Une campagne {$statut} a rendu son emplacement : elle ne "
                . "doit plus masquer le panneau."
            );
        }
    }

    /**
     * Les contrats signés avant le découpage tiennent la LIGNE d'un axe.
     * Sans héritage, on proposerait ses pieds un par un.
     */
    public function test_les_pieds_heritent_de_l_axe(): void
    {
        $this->assertStringContainsString(
            "whereIn('axe_id', array_keys(\$emplacements))",
            $this->service(),
            "Un pied doit hériter de l'emplacement annuel de son axe, "
            . "comme il hérite déjà de ses blocages."
        );
    }

    // ══════════════════════════════════════════════════════════════
    // ÉCRAN DISPONIBILITÉS
    // ══════════════════════════════════════════════════════════════

    public function test_la_grille_masque_les_annuels_par_defaut(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/ReservationController.php');

        $this->assertStringContainsString(
            "\$request->boolean('inclure_annuels')",
            $src,
            "Le paramètre absent doit valoir « masqués » : c'est le défaut "
            . "demandé."
        );

        $this->assertStringContainsString(
            'RenouvellementService::panneauxClientsAnnuels()',
            $src,
            "La grille doit lire la règle centralisée, pas sa propre copie."
        );
    }

    /**
     * Masquer du stock sans le dire ferait croire le parc plus petit qu'il
     * n'est — c'est le risque principal d'un filtre actif par défaut.
     */
    public function test_le_nombre_de_panneaux_masques_est_annonce(): void
    {
        $controleur = $this->src('app/Http/Controllers/Admin/ReservationController.php');
        $vue = $this->src('resources/views/admin/reservations/disponibilites.blade.php');

        $this->assertStringContainsString(
            "'masques_annuels' => \$masquesAnnuels",
            $controleur,
            "Le serveur doit dire combien de panneaux il a retirés."
        );

        $this->assertStringContainsString(
            'stats.masques_annuels',
            $vue,
            "L'écran doit afficher ce compteur."
        );

        $this->assertStringContainsString(
            'stat-annuels',
            $vue,
            "Le compteur doit avoir sa pastille, à côté des autres."
        );
    }

    /**
     * L'exclusion doit intervenir AVANT la pagination et les compteurs,
     * sinon une page afficherait moins de panneaux que son total annoncé.
     */
    public function test_l_exclusion_precede_la_pagination(): void
    {
        $src = $this->src('app/Http/Controllers/Admin/ReservationController.php');

        $posExclusion = strpos($src, "\$query->whereNotIn('id', \$annuels)");
        $posGet       = strpos($src, "\$panels = \$query->orderBy('reference')->get()");

        $this->assertNotFalse($posExclusion, "L'exclusion est introuvable.");
        $this->assertNotFalse($posGet, "La récupération des panneaux est introuvable.");
        $this->assertLessThan(
            $posGet,
            $posExclusion,
            "Les emplacements annuels doivent sortir avant que les panneaux "
            . "soient lus, sinon les compteurs et la pagination mentent."
        );
    }

    /**
     * « Tout sélectionner » et les exports passent par _params() : ils
     * doivent viser exactement le même résultat que la grille.
     */
    public function test_les_exports_suivent_le_meme_filtre(): void
    {
        $vue = $this->src('resources/views/admin/reservations/disponibilites.blade.php');

        $this->assertStringContainsString(
            "if (S.f.inclure_annuels) p.set('inclure_annuels', 1);",
            $vue,
            "_params() est partagé par l'affichage, « Tout sélectionner » "
            . "et les exports : le filtre doit y figurer, sans quoi un "
            . "export contiendrait des panneaux absents de l'écran."
        );
    }

    public function test_reinitialiser_revient_au_defaut(): void
    {
        $vue = $this->src('resources/views/admin/reservations/disponibilites.blade.php');

        $this->assertStringContainsString(
            'inclure_annuels: false',
            $vue,
            "Réinitialiser les filtres doit remasquer les emplacements "
            . "annuels : c'est le défaut."
        );

        $this->assertStringContainsString(
            'f.inclure_annuels',
            $vue,
            "Inclure les annuels est un écart au défaut : le bouton "
            . "« Réinitialiser » doit apparaître."
        );
    }

    // ══════════════════════════════════════════════════════════════
    // CATALOGUE ENVOYÉ AUX CLIENTS
    // ══════════════════════════════════════════════════════════════

    /**
     * C'est l'endroit où le défaut coûtait le plus cher : ce PDF part à
     * TOUS les clients. Pas de case à cocher ici — l'envoi est
     * automatique, personne n'arbitre au moment du départ.
     */
    public function test_le_catalogue_client_ecarte_toujours_les_annuels(): void
    {
        $src = $this->src('app/Services/Diffusion/DiffusionDisponibilitesService.php');

        $this->assertStringContainsString(
            "whereNotIn('panels.id', RenouvellementService::panneauxClientsAnnuels())",
            $src,
            "Le catalogue envoyé aux clients doit écarter les emplacements "
            . "des clients annuels."
        );

        // La retenue des renouvellements confirmés reste en place : les
        // deux règles se cumulent, elles ne se remplacent pas.
        $this->assertStringContainsString(
            "whereNotIn('panels.id', RenouvellementService::panneauxRetenus())",
            $src,
            "La retenue des renouvellements confirmés ne doit pas avoir "
            . "été remplacée par la nouvelle règle : elles se cumulent."
        );
    }

    /**
     * La nouvelle règle ne bloque rien : elle retire de ce qu'on PROPOSE.
     * Si elle entrait dans AvailabilityService, elle empêcherait de
     * réserver un panneau que le commercial a délibérément choisi.
     */
    public function test_la_regle_ne_bloque_aucune_reservation(): void
    {
        $this->assertStringNotContainsString(
            'panneauxClientsAnnuels',
            $this->src('app/Services/AvailabilityService.php'),
            "Les emplacements annuels ne doivent pas entrer dans le "
            . "contrôle de disponibilité : on ne propose pas le panneau, "
            . "mais rien n'interdit de le réserver en connaissance de cause."
        );
    }
}
