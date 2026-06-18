<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\CaRealService;
use App\Services\FinancialDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test de COHÉRENCE Finance ↔ Rapports — Bloc 4 Garde-fou 3 (patronne).
 *
 * Règle absolue : pour la même période, le CA encaissé TTC et le CA HT
 * facturé renvoyés par CaRealService doivent être au franc près égaux à
 * ceux renvoyés par FinancialDashboardService. Si ce test échoue, la
 * mission n'est PAS livrable — il y a divergence d'interprétation des
 * définitions métier entre Finance et Rapports.
 *
 * Skip auto sur sqlite (les migrations Panora utilisent ALTER MODIFY non
 * supporté — cf. RapportsFilterTest pour le même skip).
 */
class CaRealServiceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Skip AVANT parent::setUp() (cf. RapportsFilterTest) — sinon
        // RefreshDatabase essaie de migrer sur sqlite et crash sur les
        // ALTER MODIFY MySQL-spécifiques avant qu'on ait pu skipper.
        if (env('DB_CONNECTION', 'sqlite') !== 'mysql') {
            $this->markTestSkipped('Tests nécessitent MySQL — migrations utilisent ALTER MODIFY/SHOW INDEX non supporté par sqlite.');
        }
        parent::setUp();
    }

    public function test_ttc_encaisse_est_identique_a_finance_encaisse(): void
    {
        $this->seedFixture();

        $from = Carbon::create(2026, 1, 1)->startOfDay();
        $to   = Carbon::create(2026, 12, 31)->endOfDay();

        $finance = app(FinancialDashboardService::class)->kpis($from, $to);
        $caReal  = app(CaRealService::class)->kpis($from, $to);

        $this->assertEqualsWithDelta(
            $finance['encaisse'],
            $caReal['ttc_encaisse'],
            0.01,
            'CaRealService::ttc_encaisse doit être identique à FinancialDashboardService::encaisse au franc près.'
        );
    }

    public function test_ht_facture_est_identique_a_finance_facture_periode_ht(): void
    {
        $this->seedFixture();

        $from = Carbon::create(2026, 1, 1)->startOfDay();
        $to   = Carbon::create(2026, 12, 31)->endOfDay();

        $finance = app(FinancialDashboardService::class)->kpis($from, $to);
        $caReal  = app(CaRealService::class)->kpis($from, $to);

        $this->assertArrayHasKey('facture_periode_ht', $finance, 'FinancialDashboardService doit exposer facture_periode_ht (ajouté Bloc 4).');
        $this->assertEqualsWithDelta(
            $finance['facture_periode_ht'],
            $caReal['ht_facture'],
            0.01,
            'CaRealService::ht_facture doit être identique à FinancialDashboardService::facture_periode_ht au franc près.'
        );
    }

    public function test_filtres_incompatibles_sont_rapportes_dans_ignored_filters(): void
    {
        $this->seedFixture();

        $from = Carbon::create(2026, 1, 1)->startOfDay();
        $to   = Carbon::create(2026, 12, 31)->endOfDay();

        $caReal = app(CaRealService::class)->kpis($from, $to, null, null, [
            'commune_id'  => 99,
            'zone'        => 'abidjan',
            'client_id'   => 7,
        ]);

        $this->assertContains('commune_id', $caReal['ignored_filters']);
        $this->assertContains('zone',       $caReal['ignored_filters']);
        $this->assertNotContains('client_id', $caReal['ignored_filters'], 'client_id est un filtre supporté, pas ignoré.');
    }

    public function test_pas_de_filtres_incompatibles_donne_array_vide(): void
    {
        $this->seedFixture();

        $from = Carbon::create(2026, 1, 1)->startOfDay();
        $to   = Carbon::create(2026, 12, 31)->endOfDay();

        $caReal = app(CaRealService::class)->kpis($from, $to);
        $this->assertSame([], $caReal['ignored_filters']);
    }

    // ── Fixture minimale ────────────────────────────────────────────

    protected function seedFixture(): void
    {
        $user   = User::factory()->create();
        $client = Client::factory()->create();

        // 2 factures émises en 2026
        $inv1 = Invoice::factory()->create([
            'client_id'     => $client->id,
            'created_by'    => $user->id,
            'amount'        => 1_000_000,
            'net_ht'        => 1_000_000,
            'tva'           => 18.0,
            'amount_ttc'    => 1_180_000,
            'total_a_payer' => 1_180_000,
            'issued_at'     => Carbon::create(2026, 3, 15),
            'status'        => 'envoyee',
        ]);
        $inv2 = Invoice::factory()->create([
            'client_id'     => $client->id,
            'created_by'    => $user->id,
            'amount'        => 2_500_000,
            'net_ht'        => 2_500_000,
            'tva'           => 18.0,
            'amount_ttc'    => 2_950_000,
            'total_a_payer' => 2_950_000,
            'issued_at'     => Carbon::create(2026, 6, 20),
            'status'        => 'envoyee',
        ]);

        // 1 facture annulée — ne doit PAS apparaître
        Invoice::factory()->create([
            'client_id'     => $client->id,
            'created_by'    => $user->id,
            'amount'        => 9_999_999,
            'net_ht'        => 9_999_999,
            'amount_ttc'    => 11_799_999,
            'total_a_payer' => 11_799_999,
            'issued_at'     => Carbon::create(2026, 4, 10),
            'status'        => 'annulee',
        ]);

        // Paiements partiels
        InvoicePayment::create([
            'invoice_id' => $inv1->id,
            'montant'    => 700_000,
            'paid_at'    => Carbon::create(2026, 4, 1),
            'mode'       => 'virement',
        ]);
        InvoicePayment::create([
            'invoice_id' => $inv2->id,
            'montant'    => 1_500_000,
            'paid_at'    => Carbon::create(2026, 7, 5),
            'mode'       => 'virement',
        ]);
    }
}
