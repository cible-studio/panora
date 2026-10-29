<?php

namespace Tests\Unit;

use App\Models\Quote;
use App\Services\QuoteBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Totaux d'un devis (2026-10-29) : le calcul partagé avec les factures
 * renvoie aussi des clés propres aux factures (tsp_rate_override, ajoutée
 * le 2026-08-03). Les écrire sur le devis faisait planter toute création
 * de devis (« Unknown column 'tsp_rate_override' »).
 *
 * Vraies migrations des devis ; tables voisines réduites.
 */
class QuoteBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['clients', 'campaigns', 'users', 'communes', 'panels', 'external_panels', 'invoice_lines', 'reservations'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->timestamps(); });
        }
        foreach ([
            '2026_07_21_150448_create_quotes_table.php',
            '2026_07_21_150449_create_quote_lines_table.php',
            '2026_07_21_150450_create_quote_services_table.php',
            '2026_07_29_100000_add_campaign_dates_to_invoice_and_quote_lines.php',
            '2026_10_29_100000_add_interlocuteur_to_campaigns_reservations_quotes.php',
        ] as $m) {
            (require base_path("database/migrations/$m"))->up();
        }
    }

    public function test_les_totaux_d_un_devis_s_enregistrent(): void
    {
        DB::table('clients')->insert(['id' => 10, 'name' => 'ORANGE CI']);
        DB::table('users')->insert(['id' => 1, 'name' => 'K. Yao']);
        $quote = Quote::create([
            'reference' => 'DEV-TEST-1', 'client_id' => 10, 'commercial_user_id' => 1, 'created_by' => 1, 'title' => 'Essai', 'status' => 'brouillon',
            'version' => 1, 'valid_days' => 30, 'remise_pct' => 0, 'tva' => 18, 'public_token' => str_repeat('a', 64),
        ]);
        $quote->lines()->create([
            'designation' => 'Panneau 12 m²', 'dimension_m2' => 12, 'pu_ht_mensuel' => 120000,
            'quantite' => 1, 'duree_mois' => 1,
        ]);

        $quote = app(QuoteBuilder::class)->recalculateAndPersist($quote->fresh());

        $this->assertSame(120000, (int) $quote->amount);
        $this->assertGreaterThan(0, (int) $quote->amount_ttc);
    }
}
