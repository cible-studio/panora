<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\ReservationController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Statut de disponibilité sur la PÉRIODE demandée (2026-09-24).
 *
 * Bug remonté par la patronne : une recherche de disponibilités sur
 * novembre sortait une fiche PDF « ACTUELLEMENT OCCUPÉ » pour un panneau
 * libre en novembre mais occupé en septembre. Les trois exports (PDF
 * images, PDF liste, Excel) héritaient de `panels.status`, c'est-à-dire
 * l'état du jour, au lieu de le recalculer sur la fenêtre demandée.
 *
 * L'écran web, lui, était juste : sa règle est devenue la source unique
 * (ReservationController::displayStatusForPeriod).
 */
class PeriodDisplayStatusTest extends TestCase
{
    /**
     * @return array<string, array{0:?string, 1:bool, 2:bool, 3:bool, 4:string}>
     */
    public static function statutProvider(): array
    {
        return [
            // rawStatus, hasPeriod, isOccupied, isOption, attendu

            // ── Le cas du bug ────────────────────────────────────────
            'occupe aujourd hui mais libre sur la periode' => ['occupe', true, false, false, 'libre'],
            'confirme aujourd hui mais libre sur la periode' => ['confirme', true, false, false, 'libre'],

            // ── Réellement bloqué sur la période ─────────────────────
            'reservation confirmee sur la periode' => ['libre', true, true, false, 'occupe'],
            'option en attente sur la periode'     => ['libre', true, false, true, 'option_periode'],
            'confirme prime sur option'            => ['libre', true, true, true, 'occupe'],

            // ── Maintenance : indisponible quelle que soit la période ─
            'maintenance avec periode'     => ['maintenance', true, false, false, 'maintenance'],
            'maintenance sans periode'     => ['maintenance', false, false, false, 'maintenance'],
            'maintenance meme si occupee'  => ['maintenance', true, true, false, 'maintenance'],

            // ── Sans période : le statut courant fait foi ────────────
            'sans periode occupe reste occupe' => ['occupe', false, false, false, 'occupe'],
            'sans periode libre reste libre'   => ['libre', false, false, false, 'libre'],
            'sans periode disponible normalise' => ['disponible', false, false, false, 'libre'],

            // ── Robustesse ───────────────────────────────────────────
            'statut null vaut libre'  => [null, false, false, false, 'libre'],
            'statut vide vaut libre'  => ['', true, false, false, 'libre'],
        ];
    }

    #[DataProvider('statutProvider')]
    public function test_statut_sur_la_periode(
        ?string $rawStatus,
        bool $hasPeriod,
        bool $isOccupied,
        bool $isOption,
        string $attendu
    ): void {
        $this->assertSame(
            $attendu,
            ReservationController::displayStatusForPeriod($rawStatus, $hasPeriod, $isOccupied, $isOption)
        );
    }

    // ── Canaris : plus aucune version parallèle de la règle ────────

    public function test_les_trois_exports_passent_par_la_regle_unique(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../app/Http/Controllers/Admin/ReservationController.php'
        );

        // 1 définition + 4 appels : formatInternalPanel (écran web),
        // pdfImages, pdfListe, exportExcel.
        $this->assertSame(
            4,
            substr_count($source, 'self::displayStatusForPeriod('),
            'Les 4 chemins (écran, PDF images, PDF liste, Excel) doivent ' .
            'appeler la même règle. Un export qui recalcule dans son coin ' .
            'finit toujours par diverger.'
        );
    }

    public function test_pdf_images_ne_laisse_plus_le_statut_courant(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../app/Http/Controllers/Admin/ReservationController.php'
        );

        // La branche fautive : on n'enrichissait release_date que dans le
        // else, sans jamais corriger display_status.
        $this->assertStringNotContainsString(
            "} else {\n                \$row['release_date'] = null;\n            }",
            $source,
            'RÉGRESSION : pdfImages ne doit plus laisser display_status à ' .
            'sa valeur d\'enrichPanel() quand aucun booking ne couvre la période.'
        );
    }

    public function test_le_libelle_actuellement_occupe_a_disparu_des_pdf(): void
    {
        // « Actuellement » parle du présent : c'est précisément ce qui
        // induisait le client en erreur sur une recherche de novembre.
        foreach ([
            'disponibilites-images.blade.php',
            'disponibilites-list.blade.php',
        ] as $vue) {
            $source = file_get_contents(
                __DIR__ . '/../../resources/views/admin/reservations/pdf/' . $vue
            );
            $this->assertStringNotContainsString(
                'Actuellement occupé',
                $source,
                "RÉGRESSION : {$vue} ne doit plus parler de l'état du jour."
            );
        }
    }
}
