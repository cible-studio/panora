<?php

namespace Tests\Unit;

use App\Services\TaxCalculationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * TX-10 (2026-09-23) — Panneaux double-face et ODP.
 *
 * Règle métier validée par écrit (patronne, 2026-09-23) :
 * « Les panneaux à double face, dans Panora on considère chaque face
 *   comme un panneau, mais en réalité c'est un seul panneau : l'ODP se
 *   paye pour un, pas pour les faces. »
 *
 * L'ODP taxe l'emprise au sol → un mât double-face = une seule ODP.
 * La TM taxe l'affichage → 2 faces = 2 publicités = 2 TM. Les tests
 * ci-dessous verrouillent les DEUX moitiés de la règle.
 *
 * Tests purs (pas de BDD) → tournent aussi sur SQLite en CI.
 */
class TaxOdpFaceMergeTest extends TestCase
{
    // ── referencePhysique() ────────────────────────────────────────

    public static function referenceProvider(): array
    {
        return [
            'face A'                 => ['ABG-001A',    'ABG-001'],
            'face B'                 => ['ABG-001B',    'ABG-001'],
            'face minuscule'         => ['ABG-001b',    'ABG-001'],
            'trivision face A'       => ['CDYT2-001A',  'CDYT2-001'],
            'mono-face inchange'     => ['SPBS-01',     'SPBS-01'],
            'segment alpha inchange' => ['ABG-PAN-01',  'ABG-PAN-01'],
            'lettre sans chiffre'    => ['ZONEA',       'ZONEA'],
            'lettre C non traitee'   => ['ABG-001C',    'ABG-001C'],
            'espaces parasites'      => ['  ABG-001A ', 'ABG-001'],
        ];
    }

    #[DataProvider('referenceProvider')]
    public function test_reference_physique(string $entree, string $attendu): void
    {
        $this->assertSame($attendu, TaxCalculationService::referencePhysique($entree));
    }

    // ── fusionnerFacesODP() ────────────────────────────────────────

    private function fusionner(array $lignes): array
    {
        $service = new TaxCalculationService();
        $methode = new \ReflectionMethod($service, 'fusionnerFacesODP');
        $methode->setAccessible(true);

        return $methode->invoke($service, collect($lignes))->all();
    }

    private function ligne(array $override = []): array
    {
        return array_merge([
            'commune'        => 'Adjamé',
            'commune_id'     => 1,
            'panel_id'       => 1,
            'reference'      => 'ADJ-004A',
            'name'           => 'Carrefour Liberté',
            'dimensions'     => '4x3',
            'surface'        => 12.0,
            'type'           => TaxCalculationService::TYPE_ODP,
            'statut'         => 'libre',
            'client_name'    => null,
            'client_id'      => null,
            'campaign_name'  => null,
            'campaign_id'    => null,
            'campaign_start' => null,
            'campaign_end'   => null,
            'period_start'   => null,
            'period_end'     => null,
            'months'         => 4,
            'unit'           => 'trimestre',
            'rate'           => 3000,
            'rate_applied'   => 9000,
            'amount'         => 432000.0,
        ], $override);
    }

    public function test_les_deux_faces_ne_font_qu_une_seule_ligne_odp(): void
    {
        $res = $this->fusionner([
            $this->ligne(['panel_id' => 1, 'reference' => 'ADJ-004A']),
            $this->ligne(['panel_id' => 2, 'reference' => 'ADJ-004B']),
        ]);

        $this->assertCount(1, $res, 'Un mât double-face = 1 seule ligne ODP');
        $this->assertSame('ADJ-004', $res[0]['reference']);
        $this->assertSame(2, $res[0]['faces_count']);
        $this->assertSame(['ADJ-004A', 'ADJ-004B'], $res[0]['faces_refs']);
    }

    public function test_le_montant_odp_est_celui_d_une_seule_face(): void
    {
        $res = $this->fusionner([
            $this->ligne(['panel_id' => 1, 'reference' => 'ADJ-004A']),
            $this->ligne(['panel_id' => 2, 'reference' => 'ADJ-004B']),
        ]);

        // 9 000 (forfait trimestriel) × 12 m² (UNE face) × 4 trimestres
        $this->assertSame(12.0, $res[0]['surface']);
        $this->assertSame(432000.0, $res[0]['amount']);
    }

    public function test_surface_retenue_est_la_plus_grande_si_les_faces_different(): void
    {
        // Prudence fiscale : on ne sous-déclare jamais l'emprise.
        $res = $this->fusionner([
            $this->ligne(['panel_id' => 1, 'reference' => 'ADJ-004A', 'surface' => 12.0]),
            $this->ligne(['panel_id' => 2, 'reference' => 'ADJ-004B', 'surface' => 16.0]),
        ]);

        $this->assertSame(16.0, $res[0]['surface']);
        $this->assertSame(576000.0, $res[0]['amount']); // 9000 × 16 × 4
    }

    public function test_trimestres_retenus_sont_le_max_du_groupe(): void
    {
        // Le mât existe dès que sa 1re face existe.
        $res = $this->fusionner([
            $this->ligne(['panel_id' => 1, 'reference' => 'ADJ-004A', 'months' => 4]),
            $this->ligne(['panel_id' => 2, 'reference' => 'ADJ-004B', 'months' => 2]),
        ]);

        $this->assertSame(4, $res[0]['months']);
    }

    public function test_la_tm_reste_facturee_par_face(): void
    {
        $res = $this->fusionner([
            $this->ligne(['panel_id' => 1, 'reference' => 'ADJ-004A', 'type' => TaxCalculationService::TYPE_TM]),
            $this->ligne(['panel_id' => 2, 'reference' => 'ADJ-004B', 'type' => TaxCalculationService::TYPE_TM]),
        ]);

        $this->assertCount(2, $res, "La TM taxe l'affichage : 2 faces = 2 TM");
        $this->assertSame('ADJ-004A', $res[0]['reference']);
        $this->assertSame('ADJ-004B', $res[1]['reference']);
    }

    public function test_un_orphelin_garde_sa_reference_d_origine(): void
    {
        // CDY-037B existe sans CDY-037A dans le parc réel : on ne le
        // renomme pas, le comptable doit retrouver sa référence.
        $res = $this->fusionner([
            $this->ligne(['panel_id' => 1, 'reference' => 'CDY-037B']),
            $this->ligne(['panel_id' => 2, 'reference' => 'SPBS-01']),
        ]);

        $this->assertCount(2, $res);
        $refs = array_column($res, 'reference');
        sort($refs);
        $this->assertSame(['CDY-037B', 'SPBS-01'], $refs);
        $this->assertArrayNotHasKey('faces_count', $res[0]);
    }

    public function test_deux_communes_ne_fusionnent_jamais(): void
    {
        $res = $this->fusionner([
            $this->ligne(['commune_id' => 1, 'panel_id' => 1, 'reference' => 'ADJ-004A']),
            $this->ligne(['commune_id' => 2, 'panel_id' => 2, 'reference' => 'ADJ-004B']),
        ]);

        $this->assertCount(2, $res, 'Le regroupement est borné à la commune');
    }

    public function test_les_infos_campagne_de_la_seconde_face_remontent(): void
    {
        $res = $this->fusionner([
            $this->ligne(['panel_id' => 1, 'reference' => 'ADJ-004A']),
            $this->ligne([
                'panel_id'      => 2,
                'reference'     => 'ADJ-004B',
                'client_name'   => 'Orange CI',
                'client_id'     => 7,
                'campaign_name' => 'Promo rentrée',
                'campaign_id'   => 42,
            ]),
        ]);

        $this->assertSame('Orange CI', $res[0]['client_name']);
        $this->assertSame('Promo rentrée', $res[0]['campaign_name']);
        $this->assertSame(42, $res[0]['campaign_id']);
    }

    public function test_une_seule_ligne_odp_passe_sans_modification(): void
    {
        $res = $this->fusionner([$this->ligne(['reference' => 'ADJ-004A'])]);

        $this->assertCount(1, $res);
        $this->assertSame('ADJ-004A', $res[0]['reference'], 'Pas de renommage sans fusion');
    }
}
