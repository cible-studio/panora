<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Reclasser les panneaux par surface (2026-11-17).
 *
 * Demande : « classe tous les 6 m² dans la catégorie petit format ».
 *
 * ═══ TROIS PIÈGES, VÉRIFIÉS AVANT D'ÉCRIRE LA COMMANDE ═══
 *
 * 1. La colonne `surface` des formats a divergé en production —
 *    PanelFormat::getSurfaceM2Attribute() le documente : « vu en prod :
 *    4x3m avec surface=32 ». Se fier à cette colonne reclasserait les
 *    mauvais panneaux.
 *
 * 2. La catégorie pilote le calcul des taxes des Lumipub.
 *    PanelController::update() ne synchronise `faces_par_pied` — qui
 *    alimente l'ODP et la TM — que si le panneau est un axe, un pied, ou
 *    si le NOM de sa catégorie contient « lumipub ». Sortir un Lumipub de
 *    sa catégorie couperait ce lien et ferait réapparaître le défaut
 *    corrigé le 2026-11-13 sur MRYLUP-001.
 *
 * 3. La catégorie sert à générer les références (PanelReferenceGenerator
 *    lit `category->code`). Une catégorie sans code fait retomber les
 *    futurs panneaux sur un repli.
 *
 * Tests sur le code source, sans base de données : ils tournent sur le
 * poste à base vide.
 */
class ClasserPanneauxParSurfaceTest extends TestCase
{
    private function src(string $chemin): string
    {
        $absolu = __DIR__ . '/../../' . $chemin;
        $this->assertFileExists($absolu, "Fichier attendu absent : {$chemin}");

        return (string) file_get_contents($absolu);
    }

    private function commande(): string
    {
        return $this->src('app/Console/Commands/ClasserPanneauxParSurface.php');
    }

    /**
     * Le garde-fou le plus important : rien ne s'écrit sans --appliquer.
     */
    public function test_rien_ne_s_ecrit_sans_appliquer(): void
    {
        $src = $this->commande();

        $this->assertStringContainsString(
            '--appliquer',
            $src,
            'La commande doit simuler par défaut.'
        );

        // Une seule écriture dans tout le fichier…
        $this->assertSame(
            1,
            substr_count($src, "Panel::whereIn('id', \$ids)->update("),
            "Il ne doit y avoir qu'un seul point d'écriture."
        );

        // …et elle est bien à l'intérieur du bloc --appliquer.
        $this->assertMatchesRegularExpression(
            '/if \(\$appliquer\) \{.*?Panel::whereIn\(\x27id\x27, \$ids\)->update\(/s',
            $src,
            "L'écriture doit être enfermée dans le bloc --appliquer."
        );
    }

    /**
     * La surface se calcule, elle ne se lit pas : la colonne a divergé.
     */
    public function test_la_surface_vient_des_dimensions_pas_de_la_colonne(): void
    {
        $src = $this->commande();

        $this->assertStringContainsString(
            '(float) $f->width * (float) $f->height',
            $src,
            'La surface doit être calculée à partir des dimensions.'
        );

        // La colonne `surface` ne doit jamais servir à SÉLECTIONNER.
        $this->assertStringNotContainsString(
            "where('surface'",
            $src,
            "La colonne `surface` a divergé en production : s'en servir "
            . 'pour filtrer reclasserait les mauvais panneaux.'
        );
    }

    /**
     * Sortir un Lumipub de sa catégorie coupe la synchro des faces, donc
     * le calcul des taxes. Ces panneaux sortent du lot par défaut.
     */
    public function test_les_lumipub_axes_et_pieds_sont_ecartes_par_defaut(): void
    {
        $src = $this->commande();

        $this->assertStringContainsString(
            'private function motifEcart(Panel $panneau): ?string',
            $src,
            'La commande doit savoir dire pourquoi elle écarte un panneau.'
        );

        $this->assertStringContainsString('est_axe', $src, "Un axe doit être écarté.");
        $this->assertStringContainsString('axe_id', $src, "Un pied d'axe doit être écarté.");

        // Les trois catégories dont le NOM pilote une règle ailleurs :
        //   · Lumipub  → synchro des faces, donc calcul ODP/TM ;
        //   · Chevalet et Murale → exclues de la classification par
        //     occupation (règle validée par l'utilisateur en 09/2026).
        // Les reclasser les ferait rentrer en douce dans un calcul ou un
        // rapport dont on les avait sorties exprès.
        foreach (['lumipub', 'chevalet', 'murale'] as $cat) {
            $this->assertStringContainsString(
                "'{$cat}'",
                $src,
                "La catégorie « {$cat} » pilote une règle par son nom : "
                . 'elle doit être écartée par défaut.'
            );
        }

        $this->assertStringContainsString(
            '--forcer',
            $src,
            'Il faut une option explicite pour passer outre.'
        );
    }

    /**
     * Chaque mise à l'écart doit dire CE QUI casserait — sinon personne
     * ne peut décider de forcer en connaissance de cause.
     */
    public function test_chaque_mise_a_l_ecart_explique_sa_consequence(): void
    {
        $src = $this->commande();

        $this->assertStringContainsString(
            'CATEGORIES_SENSIBLES',
            $src,
            'Les catégories sensibles doivent être listées à un seul endroit.'
        );

        foreach (['calcul ODP/TM', 'classification par occupation'] as $consequence) {
            $this->assertStringContainsString(
                $consequence,
                $src,
                "Le motif « {$consequence} » doit être affiché à l'utilisateur."
            );
        }
    }

    /**
     * On ne crée pas une catégorie au passage : si elle n'existe pas, on
     * le dit et on liste ce qui existe.
     */
    public function test_la_commande_n_invente_pas_la_categorie(): void
    {
        $src = $this->commande();

        $this->assertStringNotContainsString(
            'PanelCategory::create(',
            $src,
            "La commande ne doit pas créer la catégorie : c'est une "
            . 'décision, pas un effet de bord.'
        );

        $this->assertStringContainsString(
            'Catégories existantes',
            $src,
            "Quand la catégorie manque, la commande doit lister celles qui "
            . 'existent plutôt que de laisser chercher.'
        );
    }

    /**
     * Une catégorie sans code fait retomber les futures références sur un
     * repli : l'utilisateur doit le savoir avant d'écrire.
     */
    public function test_l_absence_de_code_de_categorie_est_signalee(): void
    {
        $this->assertStringContainsString(
            'PanelReferenceGenerator',
            $this->commande(),
            "L'effet d'une catégorie sans code sur les références doit être "
            . 'annoncé.'
        );
    }

    /**
     * Le reclassement touche le parc : il doit rester dans le journal.
     */
    public function test_le_reclassement_est_journalise(): void
    {
        $this->assertStringContainsString(
            "Log::warning('panneaux.reclassement_par_surface'",
            $this->commande(),
            'Un reclassement de masse doit laisser une trace en warning.'
        );
    }
}
