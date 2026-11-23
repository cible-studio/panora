<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Quote;
use App\Services\CampagneDevis;
use App\Services\CampaignAmountConsistency;
use App\Services\InvoiceFromCampaignBuilder;
use App\Services\QuoteBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Montants d'une campagne issue d'un devis accepté (règles validées le
 * 2026-11-23) :
 *   1. réservation et campagne portent le HT NET des panneaux (après remise) ;
 *   2. la facture FNE copie le devis (lignes, remise, services et leur TVA) ;
 *   3. avec les taux ODP / TM figés sur le devis ;
 *   4. campagne modifiée (dates, panneaux) : facture sur la campagne réelle,
 *      aux prix nets du devis, et alerte d'écart.
 *
 * Données fictives : SP-TEST à 85 000 / mois, remise 10 %, pose 50 000 et
 * impression 100 000 (TVA), décembre 2026. Les taux ODP / TM du devis
 * (5 000 / 1 000) diffèrent volontairement de ceux de la commune
 * (9 999 / 9 999) : la facture doit garder ceux du devis.
 */
class DevisMontantsCampagneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->nullable();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedInteger('odp_rate')->default(9999); $t->unsignedInteger('tm_rate')->default(9999); $t->timestamps();
        });
        Schema::create('panel_formats', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->decimal('width', 8, 2)->nullable(); $t->decimal('height', 8, 2)->nullable(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('name')->nullable(); $t->string('status')->nullable();
            $t->unsignedBigInteger('monthly_rate')->default(0); $t->unsignedBigInteger('commune_id')->nullable();
            $t->unsignedBigInteger('format_id')->nullable(); $t->boolean('is_lit')->default(false);
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('external_panels', function (Blueprint $t) {
            $t->id(); $t->string('reference')->nullable(); $t->unsignedBigInteger('commune_id')->nullable();
            $t->unsignedBigInteger('format_id')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->string('status');
            $t->unsignedBigInteger('total_amount')->nullable(); $t->date('start_date'); $t->date('end_date');
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservation_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reservation_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('source')->default('interne');
            $t->unsignedBigInteger('unit_price')->default(0); $t->unsignedBigInteger('total_price')->default(0); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('reservation_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->unsignedBigInteger('commercial_user_id')->nullable();
            $t->string('status'); $t->date('start_date'); $t->date('end_date');
            $t->unsignedBigInteger('total_amount')->nullable(); $t->timestamp('total_amount_overridden_at')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('type')->default('interne'); $t->timestamps();
        });
        // GREATEST() manque à SQLite (migration FNE, cf. TECHNICAL_DEBT).
        $pdo = DB::connection()->getPdo();
        method_exists($pdo, 'createFunction')
            ? $pdo->createFunction('GREATEST', fn (...$v) => max($v), -1)
            : $pdo->sqliteCreateFunction('GREATEST', fn (...$v) => max($v), -1);

        foreach ([
            '2026_07_21_150448_create_quotes_table.php',
            '2026_07_21_150449_create_quote_lines_table.php',
            '2026_07_21_150450_create_quote_services_table.php',
            '2026_07_29_100000_add_campaign_dates_to_invoice_and_quote_lines.php',
            '2026_10_29_100000_add_interlocuteur_to_campaigns_reservations_quotes.php',
            '2026_10_30_100000_add_tva_applicable_to_quote_services.php',
            '2026_11_12_100000_add_axes_to_quote_lines.php',
            '2026_03_11_153340_create_invoices_table.php',
            '2026_06_08_162020_create_invoice_lines_table.php',
            '2026_06_08_162040_add_fne_columns_to_invoices.php',
            '2026_06_09_120000_create_invoice_services_table.php',
            '2026_07_16_140222_add_external_panel_id_to_invoice_lines.php',
            '2026_07_29_100000_add_campaign_dates_to_invoice_lines.php',
            '2026_08_03_120000_add_tax_overrides_to_invoices.php',
            '2026_10_30_110000_add_identite_client_to_invoices.php',
            '2026_11_16_160000_add_quantite_to_services.php',
        ] as $m) {
            (require base_path("database/migrations/$m"))->up();
        }

        // Colonnes ajoutées en base par des migrations non portables sous
        // SQLite (phase1_fne_foundations) : posées à la main si absentes.
        Schema::table('invoices', function (Blueprint $t) {
            foreach (['commercial_user_id', 'created_by', 'campaign_year'] as $col) {
                if (!Schema::hasColumn('invoices', $col)) $t->unsignedBigInteger($col)->nullable();
            }
        });

        Schema::create('invoice_status_history', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('invoice_id'); $t->string('from_status')->nullable(); $t->string('to_status');
            $t->unsignedBigInteger('user_id')->nullable(); $t->text('reason')->nullable(); $t->boolean('auto')->default(false); $t->timestamps();
        });

        DB::table('users')->insert(['id' => 2, 'name' => 'Commercial Essai', 'email' => 'commercial@regie.test', 'role' => 'commercial']);
        DB::table('clients')->insert(['id' => 1, 'name' => 'CLIENT ESSAI']);
        DB::table('communes')->insert(['id' => 1, 'name' => 'COMMUNE ESSAI']);
        DB::table('panel_formats')->insert(['id' => 1, 'name' => '4x3', 'width' => 4, 'height' => 3]);
        DB::table('panels')->insert(['id' => 10, 'reference' => 'SP-TEST', 'name' => 'Carrefour essai', 'monthly_rate' => 85000, 'commune_id' => 1, 'format_id' => 1]);

        // Devis accepté, déjà converti en réservation (RES-ESSAI).
        DB::table('reservations')->insert(['id' => 1, 'reference' => 'RES-ESSAI', 'client_id' => 1, 'status' => 'confirme',
            'total_amount' => 76500, 'start_date' => '2026-12-01', 'end_date' => '2026-12-31']);
        DB::table('reservation_panels')->insert(['reservation_id' => 1, 'panel_id' => 10, 'unit_price' => 76500, 'total_price' => 76500]);

        $quote = Quote::create([
            'reference' => 'DEV-TEST-MONT', 'client_id' => 1, 'commercial_user_id' => 2, 'created_by' => 2,
            'title' => 'Campagne essai', 'status' => 'accepte', 'version' => 1, 'valid_days' => 30,
            'remise_pct' => 10, 'tva' => 18, 'public_token' => str_repeat('d', 64),
            'period_start' => '2026-12-01', 'period_end' => '2026-12-31', 'converted_reservation_id' => 1,
        ]);
        $quote->lines()->create([
            'designation' => 'SP-TEST — Carrefour essai', 'panel_id' => 10, 'commune_id' => 1, 'snapshot_commune_name' => 'COMMUNE ESSAI',
            'dimension_m2' => 12, 'pu_ht_mensuel' => 85000, 'quantite' => 1, 'duree_mois' => 1,
            'odp_rate_applique' => 5000, 'tm_rate_applique' => 1000, 'order_index' => 0,
        ]);
        $quote->services()->create(['label' => 'FRAIS DE POSE', 'quantite' => 1, 'prix_ht' => 50000, 'tva_applicable' => true, 'order_index' => 0]);
        $quote->services()->create(['label' => 'IMPRESSION', 'quantite' => 1, 'prix_ht' => 100000, 'tva_applicable' => true, 'order_index' => 1]);
        app(QuoteBuilder::class)->recalculateAndPersist($quote->fresh(['lines', 'services']));

        DB::table('campaigns')->insert(['id' => 5, 'name' => 'Campagne essai', 'client_id' => 1, 'reservation_id' => 1,
            'commercial_user_id' => 2, 'user_id' => 2, 'status' => 'planifie', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31',
            'total_amount' => 76500]);
        DB::table('campaign_panels')->insert(['campaign_id' => 5, 'panel_id' => 10]);
    }

    private function devis(): Quote
    {
        return Quote::where('reference', 'DEV-TEST-MONT')->firstOrFail();
    }

    private function campagne(): Campaign
    {
        return Campaign::findOrFail(5);
    }

    public function test_le_devis_de_reference_est_coherent(): void
    {
        $q = $this->devis();
        $this->assertSame(85000, (int) $q->amount);
        $this->assertSame(76500, (int) $q->net_ht, 'HT net après 10 % de remise');
        $this->assertGreaterThan(76500 + 150000, (int) $q->total_a_payer, 'TTC + taxes + services');
    }

    public function test_campagne_identique_au_devis(): void
    {
        $cd = app(CampagneDevis::class);
        $this->assertSame('DEV-TEST-MONT', $cd->devis($this->campagne())?->reference);
        $this->assertTrue($cd->correspond($this->campagne(), $this->devis()));

        DB::table('campaigns')->where('id', 5)->update(['end_date' => '2027-01-31']);
        $this->assertFalse($cd->correspond($this->campagne(), $this->devis()), 'dates changées');

        DB::table('campaigns')->where('id', 5)->update(['end_date' => '2026-12-31']);
        DB::table('panels')->insert(['id' => 11, 'reference' => 'SP-AUTRE', 'monthly_rate' => 50000, 'commune_id' => 1, 'format_id' => 1]);
        DB::table('campaign_panels')->insert(['campaign_id' => 5, 'panel_id' => 11]);
        $this->assertFalse($cd->correspond($this->campagne(), $this->devis()), 'panneau ajouté');
    }

    public function test_pas_de_faux_ecart_pour_une_campagne_issue_du_devis(): void
    {
        $check = app(CampaignAmountConsistency::class)->check($this->campagne());

        $this->assertSame('devis', $check['source']);
        $this->assertSame(76500.0, $check['expected'], 'HT net du devis');
        $this->assertTrue($check['matches'], 'plus de faux écart : HT comparé à HT');
    }

    public function test_campagne_prolongee_ecart_sur_les_prix_nets_du_devis(): void
    {
        DB::table('campaigns')->where('id', 5)->update(['end_date' => '2027-01-31']);

        $check = app(CampaignAmountConsistency::class)->check($this->campagne());
        $this->assertSame('reservation', $check['source']);
        $this->assertSame(153000.0, $check['expected'], '76 500 net × 2 mois');
        $this->assertFalse($check['matches']);
        $this->assertStringContainsString('FCFA HT', app(CampaignAmountConsistency::class)->humanMessage($check));
    }

    public function test_la_facture_fne_copie_le_devis(): void
    {
        $builder = app(InvoiceFromCampaignBuilder::class);
        $facture = $builder->build($this->campagne(), ['issued_at' => '2026-12-01']);
        $q = $this->devis();

        $this->assertSame('DEV-TEST-MONT', $builder->devisCopie?->reference);
        $this->assertSame((int) $q->total_a_payer, (int) $facture->total_a_payer, 'la facture retombe sur le devis');
        $this->assertSame(76500, (int) $facture->net_ht);
        $this->assertSame(10.0, (float) $facture->remise_pct);

        $ligne = $facture->lines()->first();
        $this->assertSame(85000, (int) $ligne->pu_ht_mensuel, 'PU du devis, la remise est portée par la facture');
        $this->assertSame([5000, 1000], [(int) $ligne->odp_rate_applique, (int) $ligne->tm_rate_applique], 'taux figés du devis');
        $this->assertSame(['FRAIS DE POSE', 'IMPRESSION'], $facture->services()->pluck('label')->all());
    }

    public function test_campagne_prolongee_la_facture_suit_la_campagne(): void
    {
        DB::table('campaigns')->where('id', 5)->update(['end_date' => '2027-01-31']);

        $builder = app(InvoiceFromCampaignBuilder::class);
        $facture = $builder->build($this->campagne(), ['issued_at' => '2026-12-01']);

        $this->assertNull($builder->devisCopie, 'plus une copie : la campagne a changé');
        $ligne = $facture->lines()->first();
        $this->assertSame(76500, (int) $ligne->pu_ht_mensuel, 'prix net du devis (pivot)');
        $this->assertSame(2.0, (float) $ligne->duree_mois);
        $this->assertSame(0.0, (float) $facture->remise_pct, 'la remise est déjà dans le prix net');
        $this->assertSame([5000, 1000], [(int) $ligne->odp_rate_applique, (int) $ligne->tm_rate_applique], 'taux figés du devis');
        $this->assertSame(['FRAIS DE POSE', 'IMPRESSION'], $facture->services()->pluck('label')->all(), 'les frais ponctuels restent dus');
    }

    /** Étiquettes : tout « montant total » de réservation / campagne dit HT. */
    public function test_les_montants_disent_ht_ou_ttc(): void
    {
        foreach ([
            'resources/views/emails/campaign-started.blade.php',
            'resources/views/emails/reservation-confirmed.blade.php',
            'resources/views/emails/client-campaign-started.blade.php',
            'resources/views/public/reservation.blade.php',
            'resources/views/client/campagne-detail.blade.php',
        ] as $f) {
            $this->assertStringContainsString('FCFA HT', File::get(base_path($f)), $f);
        }
        $this->assertStringContainsString('Montant total HT', File::get(resource_path('views/admin/reservations/show.blade.php')));
        $this->assertStringContainsString('FCFA TTC', File::get(resource_path('views/emails/quotes/sent.blade.php')));
        $this->assertStringContainsString("'total_amount'       => (int) \$quote->net_ht", File::get(app_path('Services/QuoteConverter.php')));
    }
}
