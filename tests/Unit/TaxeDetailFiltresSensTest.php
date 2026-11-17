<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les filtres du détail des taxes se lisent tous dans le même sens
 * (2026-11-17).
 *
 * ═══ LE DÉFAUT ═══
 * Trois cases côte à côte sur la même barre, deux sens opposés :
 *
 *   Catégories de panneau  coché = inclus    décoché = exclu
 *   Maintenance            coché = EXCLU     décoché = inclus   ← l'intruse
 *   Lumipub                coché = inclus    décoché = exclu
 *
 * L'utilisateur décochait « Exclure » et voyait les panneaux en
 * maintenance apparaître — comportement juste, lecture à contresens.
 *
 * ═══ LA CORRECTION ═══
 * La case devient « Inclure », cochée par défaut. Le SERVEUR n'a pas
 * changé : il lit toujours `exclure_maintenance`. Comme pour les
 * catégories, la case ne porte pas de `name` — un champ caché part quand
 * elle est décochée. L'URL par défaut reste donc vide, et les liens et
 * exports déjà enregistrés continuent de fonctionner.
 *
 * Tests sur le code source, sans base de données.
 */
class TaxeDetailFiltresSensTest extends TestCase
{
    private function src(string $chemin): string
    {
        $absolu = __DIR__ . '/../../' . $chemin;
        $this->assertFileExists($absolu, "Fichier attendu absent : {$chemin}");

        return (string) file_get_contents($absolu);
    }

    private function vue(): string
    {
        return $this->src('resources/views/admin/taxes/details.blade.php');
    }

    /**
     * Les trois cases doivent dire « coché = je le prends ».
     */
    public function test_les_trois_filtres_se_lisent_dans_le_meme_sens(): void
    {
        $vue = $this->vue();

        $this->assertStringContainsString(
            '<span>Inclure</span>',
            $vue,
            "La case Maintenance doit dire « Inclure » : elle était la seule "
            . "de la barre où cocher RETIRAIT."
        );

        $this->assertStringNotContainsString(
            '<span>Exclure</span>',
            $vue,
            "Plus aucune case ne doit dire « Exclure » : les trois filtres "
            . "se lisent désormais pareil."
        );

        // Les deux autres n'ont pas bougé.
        $this->assertStringContainsString('Inclure les pieds', $vue, 'Filtre Lumipub.');
        $this->assertStringContainsString('Décocher pour exclure', $vue, 'Filtre Catégories.');
    }

    /**
     * Le contrat serveur ne change pas : les URL et exports déjà
     * enregistrés doivent continuer de marcher.
     */
    public function test_le_serveur_attend_toujours_exclure_maintenance(): void
    {
        $this->assertStringContainsString(
            "!empty(\$filters['exclure_maintenance'])",
            $this->src('app/Services/TaxCalculationService.php'),
            "Le service doit continuer de lire `exclure_maintenance` : "
            . "retourner la case est une affaire d'écran, pas de contrat."
        );

        $this->assertStringContainsString(
            "name=\"exclure_maintenance\" value=\"1\"",
            $this->vue(),
            "Le champ caché doit porter le nom attendu par le serveur."
        );
    }

    /**
     * La case ne porte pas de `name` : seule la DÉCOCHE envoie quelque
     * chose, pour que l'URL par défaut reste propre.
     */
    public function test_seule_la_decoche_envoie_quelque_chose(): void
    {
        $vue = $this->vue();

        $this->assertStringContainsString(
            '@unless($maintenanceIncluse)',
            $vue,
            "Le champ caché ne doit partir que lorsque la case est décochée."
        );

        $this->assertStringContainsString(
            'TAX_MAINTENANCE',
            $vue,
            "Le même procédé que les catégories doit piloter la case."
        );

        // La case elle-même ne doit pas porter name="exclure_maintenance",
        // sinon cocher « Inclure » enverrait « exclure » — l'inverse exact.
        $this->assertStringNotContainsString(
            'type="checkbox" name="exclure_maintenance"',
            $vue,
            "La case ne doit pas porter le name directement : cocher "
            . "« Inclure » enverrait alors « exclure »."
        );
    }

    /**
     * Deux noms opposés pour une seule case trompaient la lecture.
     */
    public function test_le_drapeau_mort_a_disparu(): void
    {
        foreach ([
            'app/Http/Controllers/Admin/TaxController.php',
            'app/Services/TaxCalculationService.php',
            'resources/views/admin/taxes/details.blade.php',
        ] as $fichier) {
            $src = $this->src($fichier);

            // On tolère la mention dans un commentaire qui explique le
            // retrait ; on refuse toute utilisation réelle.
            $this->assertStringNotContainsString(
                "\$filters['include_maintenance']",
                $src,
                "{$fichier} : `include_maintenance` n'a plus d'effet depuis "
                . "le 13/11, il ne doit plus être lu ni écrit."
            );
            $this->assertStringNotContainsString(
                "boolean('include_maintenance')",
                $src,
                "{$fichier} : le drapeau mort ne doit plus être collecté."
            );
        }
    }
}
