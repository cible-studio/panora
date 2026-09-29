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
