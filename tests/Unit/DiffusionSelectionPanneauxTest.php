<?php

namespace Tests\Unit;

use App\Services\Diffusion\DiffusionDisponibilitesService;
use PHPUnit\Framework\TestCase;

/**
 * Sélection des panneaux présentés aux clients (2026-09-29).
 *
 * Règle validée : les panneaux libres sur toute la période, plus ceux qui
 * se libèrent AVANT la fin de la période. Exclus : maintenance, options,
 * occupés jusqu'à la fin de la période.
 */
class DiffusionSelectionPanneauxTest extends TestCase
{
    private function selection(array $lignes, string $fin = '2026-11-30'): array
    {
        return DiffusionDisponibilitesService::filtrerDiffusables(collect($lignes), $fin)
            ->pluck('reference')
            ->all();
    }

    public function test_un_panneau_libre_est_presente(): void
    {
        $this->assertSame(['A'], $this->selection([
            ['reference' => 'A', 'display_status' => 'libre', 'release_date' => null],
        ]));
    }

    public function test_un_panneau_qui_se_libere_en_cours_de_periode_est_presente(): void
    {
        // Occupé jusqu'au 19 → libre le 20 : présenté avec sa date.
        $this->assertSame(['A'], $this->selection([
            ['reference' => 'A', 'display_status' => 'occupe', 'release_date' => '2026-11-19'],
        ]));
    }

    public function test_un_panneau_libere_la_veille_du_dernier_jour_est_presente(): void
    {
        // Occupé jusqu'au 29 → libre le 30, dernier jour de la période.
        $this->assertSame(['A'], $this->selection([
            ['reference' => 'A', 'display_status' => 'occupe', 'release_date' => '2026-11-29'],
        ]));
    }

    public function test_un_panneau_occupe_jusqu_a_la_fin_de_la_periode_est_exclu(): void
    {
        $this->assertSame([], $this->selection([
            ['reference' => 'A', 'display_status' => 'occupe', 'release_date' => '2026-11-30'],
            ['reference' => 'B', 'display_status' => 'occupe', 'release_date' => '2027-02-28'],
        ]));
    }

    public function test_un_panneau_occupe_sans_date_de_liberation_est_exclu(): void
    {
        // Sans date connue, on ne peut pas promettre de disponibilité.
        $this->assertSame([], $this->selection([
            ['reference' => 'A', 'display_status' => 'occupe', 'release_date' => null],
        ]));
    }

    public function test_maintenance_et_options_sont_exclus(): void
    {
        $this->assertSame([], $this->selection([
            ['reference' => 'A', 'display_status' => 'maintenance', 'release_date' => null],
            ['reference' => 'B', 'display_status' => 'option_periode', 'release_date' => null],
        ]));
    }

    public function test_melange_realiste(): void
    {
        $this->assertSame(['LIBRE', 'SE-LIBERE'], $this->selection([
            ['reference' => 'LIBRE',     'display_status' => 'libre',          'release_date' => null],
            ['reference' => 'SE-LIBERE', 'display_status' => 'occupe',         'release_date' => '2026-11-10'],
            ['reference' => 'PRIS',      'display_status' => 'occupe',         'release_date' => '2026-12-31'],
            ['reference' => 'OPTION',    'display_status' => 'option_periode', 'release_date' => null],
            ['reference' => 'HS',        'display_status' => 'maintenance',    'release_date' => null],
        ]));
    }

    public function test_seconde_quinzaine_meme_regle(): void
    {
        // Période du 15 au 30 : un panneau occupé jusqu'au 25 est présenté.
        $this->assertSame(['A'], $this->selection([
            ['reference' => 'A', 'display_status' => 'occupe', 'release_date' => '2026-11-25'],
        ], '2026-11-30'));
    }

    // ── Répartition Abidjan / intérieur du pays (2026-10-29) ───────

    /** @return array{abidjan: array, interieur: array} références par zone */
    private function zones(array $lignes): array
    {
        $parZone = DiffusionDisponibilitesService::repartirParZone(collect($lignes));

        return [
            'abidjan'   => $parZone['abidjan']->pluck('reference')->all(),
            'interieur' => $parZone['interieur']->pluck('reference')->all(),
        ];
    }

    public function test_les_communes_du_district_vont_dans_abidjan(): void
    {
        $zones = $this->zones([
            ['reference' => 'PLA-01', 'commune' => 'PLATEAU'],
            ['reference' => 'COC-01', 'commune' => 'Cocody'],
            ['reference' => 'YOP-01', 'commune' => 'YOPOUGON'],
        ]);

        $this->assertSame(['PLA-01', 'COC-01', 'YOP-01'], $zones['abidjan']);
        $this->assertSame([], $zones['interieur']);
    }

    public function test_les_villes_du_reste_du_pays_vont_dans_l_interieur(): void
    {
        $zones = $this->zones([
            ['reference' => 'SPB-01', 'commune' => 'SAN PEDRO'],
            ['reference' => 'BKE-01', 'commune' => 'BOUAKE'],
            ['reference' => 'YAM-01', 'commune' => 'YAMOUSSOUKRO'],
        ]);

        $this->assertSame([], $zones['abidjan']);
        $this->assertSame(['SPB-01', 'BKE-01', 'YAM-01'], $zones['interieur']);
    }

    public function test_anyama_bingerville_songon_et_l_autoroute_du_nord_comptent_pour_abidjan(): void
    {
        // Règle de la liste blanche Commune::ABIDJAN_COMMUNES, la même que
        // le filtre « Abidjan / Intérieur » des rapports et des taxes.
        $zones = $this->zones([
            ['reference' => 'ANY-01', 'commune' => 'ANYAMA'],
            ['reference' => 'BGV-01', 'commune' => 'BINGERVILLE'],
            ['reference' => 'SON-01', 'commune' => 'SONGON'],
            ['reference' => 'ADN-01', 'commune' => 'AUTOROUTE-DU-NORD'],
        ]);

        $this->assertCount(4, $zones['abidjan']);
        $this->assertSame([], $zones['interieur']);
    }

    public function test_les_variantes_de_nom_ne_basculent_pas_a_tort_dans_l_interieur(): void
    {
        // Tirets, accents et sous-quartiers : le bug de 2026-07-16 avait
        // fait passer 14 panneaux d'Abidjan en « Intérieur » dans les KPI.
        $zones = $this->zones([
            ['reference' => 'TRE-01', 'commune' => 'TREICH-VILLE'],
            ['reference' => 'ADJ-01', 'commune' => 'ADJAMÉ'],
            ['reference' => 'COC-99', 'commune' => 'COCODY ANGRÉ'],
            ['reference' => 'PBT-01', 'commune' => 'Port-Bouët'],
        ]);

        $this->assertCount(4, $zones['abidjan']);
        $this->assertSame([], $zones['interieur']);
    }

    public function test_une_commune_absente_ou_vide_part_a_l_interieur(): void
    {
        // Sans commune, on ne peut pas affirmer que le panneau est à
        // Abidjan : il rejoint l'intérieur plutôt que de gonfler Abidjan.
        $zones = $this->zones([
            ['reference' => 'X-01', 'commune' => '—'],
            ['reference' => 'X-02'],
        ]);

        $this->assertSame([], $zones['abidjan']);
        $this->assertSame(['X-01', 'X-02'], $zones['interieur']);
    }

    public function test_la_somme_des_deux_zones_retombe_sur_le_total(): void
    {
        $lignes = [
            ['reference' => 'PLA-01', 'commune' => 'PLATEAU'],
            ['reference' => 'SPB-01', 'commune' => 'SAN PEDRO'],
            ['reference' => 'COC-01', 'commune' => 'COCODY'],
            ['reference' => 'BKE-01', 'commune' => 'BOUAKE'],
            ['reference' => 'ANY-01', 'commune' => 'ANYAMA'],
        ];
        $zones = $this->zones($lignes);

        $this->assertCount(
            count($lignes),
            array_merge($zones['abidjan'], $zones['interieur']),
            'Aucun panneau ne doit être perdu ni compté deux fois entre les deux catalogues.'
        );
    }

    // ── Garde-fous de configuration ────────────────────────────────

    public function test_le_mode_test_est_le_defaut(): void
    {
        // Le staging (develop) fait tourner le même planificateur que la
        // prod : sans ce défaut, un staging branché sur la vraie clé
        // Brevo écrirait aux clients.
        $this->assertStringContainsString(
            "env('DIFFUSION_MODE', 'test')",
            file_get_contents(__DIR__ . '/../../config/diffusion.php')
        );
        $this->assertStringContainsString(
            "env('DIFFUSION_AUTO', false)",
            file_get_contents(__DIR__ . '/../../config/diffusion.php')
        );
    }

    public function test_l_envoi_de_campagne_n_est_jamais_rejoue(): void
    {
        // Un sendNow rejoué après un délai dépassé peut partir DEUX fois.
        $source = file_get_contents(__DIR__ . '/../../app/Services/Brevo/BrevoClient.php');

        $this->assertStringNotContainsString('->retry(', $source,
            'Aucune nouvelle tentative automatique vers Brevo : risque de double envoi.');
    }
}
