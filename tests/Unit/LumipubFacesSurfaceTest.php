<?php

namespace Tests\Unit;

use App\Services\Axes\AxeService;
use PHPUnit\Framework\TestCase;

/**
 * Surface taxable d'un pied d'axe — mesure et correctif du 2026-11-13.
 *
 * ═══ CE QUI A ÉTÉ MESURÉ EN PRODUCTION ═══
 * Le format d'un axe porte la surface d'UNE SEULE FACE, pas celle du pied
 * entier. Les 12 axes Lumipub ont tous un format « 3m² » alors que leur
 * nom annonce 3 faces par pied : un pied Lumipub, c'est 3 × 3 = 9 m².
 * Panora n'en comptait que 3 → ODP et TM divisées par 3 sur tout le parc
 * Lumipub.
 *
 * ═══ LA RÈGLE ═══
 *   surface taxable = surface d'une face × nombre de faces du pied
 *
 * Le nombre de faces vient du NOM de l'axe — « (11P x 3F) »,
 * « (12Panneaux double face) » — et non de `nombre_faces`, que la dette
 * technique du 2026-11-11 qualifie d'inexploitable (deux significations
 * dans la même colonne). Sur les 101 pieds créés, il vaut 1 partout.
 *
 * Tests sans base de données : ils tournent sur le poste à base vide.
 */
class LumipubFacesSurfaceTest extends TestCase
{
    private function axes(): AxeService
    {
        return new AxeService();
    }

    private function src(string $chemin): string
    {
        return file_get_contents(__DIR__ . '/../../' . $chemin);
    }

    // ══════════════════════════════════════════════════════════════
    // LECTURE DU NOMBRE DE FACES — les 13 axes réels de production
    // ══════════════════════════════════════════════════════════════

    /**
     * Noms relevés en production le 2026-11-13. Si l'un cesse d'être
     * analysé, la surface taxable de tout son axe retombe à une face.
     */
    public function test_les_noms_d_axes_de_production_sont_tous_analyses(): void
    {
        $reels = [
            'Cocody Lumipub Axe Hôtel Ivoire - St Jean  (10P x 3F)'      => [10, 3],
            'Cocody Lumipub Axe St Jean - Maison du Parti (6P x 3F)'     => [6, 3],
            'Cocody Lumipub Axe St Jean - Corniche (3P x 3F)'            => [3, 3],
            'Cocody Lumipub Axe St Jean - Carrefour Laviers (11P x 3F)'  => [11, 3],
            'Cocody Lumipub Axe RTI - Pont Lycée Technique (4P x 3F)'    => [4, 3],
            'Cocody Lumipub Axe RTI - CHU (10P x 3F)'                    => [10, 3],
            'Lumipub Axe opéra-Pharmacie st gabriel (2Px3F)'             => [2, 3],
            'Lumipub axe Biafra - solibra  (10P x 3F)'                   => [10, 3],
            'Lumipub axe solibra - Drocolor- (5P x 3F)'                  => [5, 3],
            'Axe Carrefour Adiaké Assinie (12Panneaux double face)'      => [12, 2],
        ];

        foreach ($reels as $nom => [$pieds, $faces]) {
            $s = $this->axes()->lireStructure($nom);
            $this->assertIsArray($s, "Nom non analysé : {$nom}");
            $this->assertSame($pieds, $s['pieds'], "Pieds erronés pour : {$nom}");
            $this->assertSame($faces, $s['faces'], "Faces erronées pour : {$nom}");
        }
    }

    public function test_un_axe_illisible_ne_gonfle_jamais_la_surface(): void
    {
        // Aucune invention : sans structure lisible, on reste à 1 face.
        $this->assertSame(1, $this->axes()->facesDeLAxe(null));

        foreach (['Lumipub simple', '(1 Lumipub)', 'Panneau classique'] as $nom) {
            $axe = new \App\Models\Panel(['name' => $nom]);
            $this->assertSame(1, $this->axes()->facesDeLAxe($axe),
                "« {$nom} » ne doit pas produire plus d'une face.");
        }
    }

    public function test_un_panneau_sans_axe_vaut_une_face(): void
    {
        $this->assertSame(1, $this->axes()->facesParPied(null));

        $ordinaire = new \App\Models\Panel(['name' => 'ABG-004']);
        $this->assertSame(1, $this->axes()->facesParPied($ordinaire),
            "Un panneau ordinaire ne doit jamais être multiplié.");
    }

    // ══════════════════════════════════════════════════════════════
    // LA FORMULE
    // ══════════════════════════════════════════════════════════════

    public function test_la_surface_taxable_multiplie_bien_les_faces(): void
    {
        $src = $this->src('app/Services/TaxCalculationService.php');

        $this->assertStringContainsString('$surface    = $surfaceUne * $faces;', $src,
            'La surface taxable doit être surface_une_face × faces.');
        $this->assertStringContainsString('facesParLot(', $src,
            'Le comptage doit se faire en un seul appel, pas panneau par panneau.');
    }

    /**
     * Les totaux par commune passent par d'autres méthodes : si elles
     * oublient les faces, la fiche commune contredit l'écran de détail.
     */
    public function test_les_totaux_par_commune_comptent_aussi_les_faces(): void
    {
        $src = $this->src('app/Services/TaxCalculationService.php');

        $this->assertSame(
            2,
            substr_count($src, 'facesParPied($panel)'),
            'calculODPCommune() ET calculTMCommune() doivent multiplier par les faces.'
        );
    }

    public function test_la_ligne_affiche_le_detail_des_faces(): void
    {
        $src = $this->src('app/Services/TaxCalculationService.php');
        $vue = $this->src('resources/views/admin/taxes/details.blade.php');

        $this->assertStringContainsString("'faces_pied'     => \$faces", $src);
        $this->assertStringContainsString("'surface_face'   => \$surfaceUne", $src);
        $this->assertStringContainsString("faces_pied", $vue,
            "Le nombre de faces doit être visible : il vient du nom de l'axe, "
            . "donc une erreur de saisie doit pouvoir se repérer sur la ligne.");
    }

    // ══════════════════════════════════════════════════════════════
    // CE QU'IL NE FAUT PAS FAIRE
    // ══════════════════════════════════════════════════════════════

    /**
     * `nombre_faces` porte deux significations (1 sur 320 panneaux, 4 sur
     * les chevalets, 3 sur un seul axe) : s'en servir pour la taxe
     * multiplierait par 4 la surface de chaque chevalet.
     */
    public function test_le_calcul_n_utilise_pas_nombre_faces(): void
    {
        // On vise les LECTURES réelles de la colonne, pas les mentions en
        // commentaire — ce fichier explique justement pourquoi on l'évite.
        foreach ([
            'app/Services/TaxCalculationService.php',
            'app/Services/Axes/AxeService.php',
        ] as $fichier) {
            $src = $this->src($fichier);
            $this->assertStringNotContainsString('->nombre_faces', $src,
                "{$fichier} : nombre_faces est inexploitable (cf. docs/TECHNICAL_DEBT.md) — "
                . "s'en servir multiplierait par 4 la surface de chaque chevalet.");
            $this->assertStringNotContainsString("'nombre_faces'", $src);
        }
    }

    /**
     * Repère chiffré, sur les vrais tarifs de production : un pied
     * Lumipub à Cocody (5 000 F/m²/mois) sur 12 mois.
     *   avant : 5 000 × 3  × 12 =   180 000
     *   après : 5 000 × 9  × 12 =   540 000
     */
    public function test_repere_chiffre_un_pied_lumipub_a_cocody(): void
    {
        $faces   = $this->axes()->facesDeLAxe(
            new \App\Models\Panel(['name' => 'Cocody Lumipub Axe RTI - CHU (10P x 3F)'])
        );
        $taux    = 5000;   // odp_rate Cocody, mesuré en production
        $uneFace = 3.0;    // format « 3m² », mesuré en production

        $this->assertSame(3, $faces);
        $this->assertSame(540000.0, $taux * ($uneFace * $faces) * 12);
        $this->assertSame(180000.0, $taux * $uneFace * 12,
            'Montant que Panora calculait avant le correctif.');
    }
}
