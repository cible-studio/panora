<?php

namespace Tests\Unit;

use App\Models\Quote;
use App\Models\QuoteService;
use App\Services\InvoiceCalculator;
use App\Services\QuoteBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lot A des devis (2026-11-19) — le TTC du devis inclut la TSP.
 *
 * Constat : sur le PDF DEV-2026-001 (ILUX), le TTC affiché valait
 * 5 664 000 (HT + TVA) alors que le net à payer, 6 236 400, comptait bien
 * la TSP. Les lignes HT + TVA + TSP du PDF ne redonnaient pas le TTC.
 *
 * Règle validée par écrit le 2026-11-19, pour le DEVIS SEULEMENT :
 *   TTC du devis = net HT + TVA + TSP
 *   net à payer  = TTC + TM + ODP + services TTC (la TSP n'est pas recomptée)
 *
 * La facture FNE garde TTC = net HT + TVA (InvoiceCalculator inchangé).
 * Le net à payer est le même au franc près entre le devis et la facture.
 *
 * Source unique : QuoteBuilder::totaux(), appelée à l'enregistrement et
 * par le récapitulatif en direct du formulaire.
 */
class DevisTtcTspTest extends TestCase
{
    // ── Les deux cas de référence du cahier des charges ────────────

    /**
     * ILUX : 4 800 000 HT, taxes publicitaires 216 000 (TM seule),
     * un service de 180 000 HT soumis à TVA (212 400 TTC).
     */
    private function ilux(): array
    {
        return [
            [[
                'pu_ht_mensuel' => 400000, 'quantite' => 1, 'duree_mois' => 12,
                'dimension_m2' => 18, 'odp_rate_applique' => 0, 'tm_rate_applique' => 1000,
            ]],
            ['remise_pct' => 0, 'services' => [
                ['label' => 'Impression', 'quantite' => 1, 'prix_ht' => 180000, 'tva_applicable' => true],
            ]],
        ];
    }

    /**
     * Havas : 5 panneaux de 12 m², 468 000 HT ; TM 60 000 + ODP 300 000 ;
     * service 30 × 20 000 = 600 000 HT soumis à TVA (708 000 TTC).
     */
    private function havas(): array
    {
        return [
            [[
                'pu_ht_mensuel' => 93600, 'quantite' => 5, 'duree_mois' => 1,
                'dimension_m2' => 12, 'odp_rate_applique' => 5000, 'tm_rate_applique' => 1000,
            ]],
            ['remise_pct' => 0, 'services' => [
                ['label' => 'Impression', 'quantite' => 30, 'prix_ht' => 20000, 'tva_applicable' => true],
            ]],
        ];
    }

    public function test_ilux_le_ttc_inclut_la_tsp_et_le_net_ne_bouge_pas(): void
    {
        [$lignes, $opts] = $this->ilux();
        $t = app(QuoteBuilder::class)->totaux($lignes, $opts);

        $this->assertSame(4800000, $t['net_ht'], 'HT');
        $this->assertSame(864000,  $t['tva_amount'], 'TVA 18 %');
        $this->assertSame(144000,  $t['tsp_amount'], 'TSP 3 %');
        $this->assertSame(5808000, $t['amount_ttc'], 'TTC = HT + TVA + TSP');
        $this->assertSame(216000,  $t['tm_total'] + $t['odp_total'], 'Taxes publicitaires');
        $this->assertSame(212400,  $t['services_ttc_total'], 'Services TTC');
        $this->assertSame(6236400, $t['total_a_payer'], 'NET');
    }

    public function test_havas_le_ttc_inclut_la_tsp_et_le_net_ne_bouge_pas(): void
    {
        [$lignes, $opts] = $this->havas();
        $t = app(QuoteBuilder::class)->totaux($lignes, $opts);

        $this->assertSame(468000,  $t['net_ht'], 'HT');
        $this->assertSame(566280,  $t['amount_ttc'], 'TTC = HT + TVA + TSP');
        $this->assertSame(360000,  $t['tm_total'] + $t['odp_total'], 'Taxes publicitaires');
        $this->assertSame(708000,  $t['services_ttc_total'], 'Services TTC');
        $this->assertSame(1634280, $t['total_a_payer'], 'NET');
    }

    /**
     * Pas de double comptage : la TSP est dans le TTC, le net s'obtient
     * en ajoutant les seules taxes publicitaires et les services.
     */
    public function test_le_net_se_lit_a_partir_du_ttc_sans_recompter_la_tsp(): void
    {
        foreach (['ILUX' => $this->ilux(), 'Havas' => $this->havas()] as $cas => [$lignes, $opts]) {
            $t = app(QuoteBuilder::class)->totaux($lignes, $opts);

            $this->assertSame(
                $t['total_a_payer'],
                $t['amount_ttc'] + $t['tm_total'] + $t['odp_total'] + $t['services_ttc_total'],
                "{$cas} : net = TTC + TM + ODP + services TTC."
            );
        }
    }

    // ── La facture garde sa règle, le net reste commun ─────────────

    public function test_la_facture_garde_son_ttc_et_le_meme_net(): void
    {
        foreach ([
            'ILUX'  => [$this->ilux(),  5664000],
            'Havas' => [$this->havas(), 552240],
        ] as $cas => [[$lignes, $opts], $ttcFacture]) {
            $devis   = app(QuoteBuilder::class)->totaux($lignes, $opts);
            $facture = app(InvoiceCalculator::class)->calculateInvoice($lignes, $opts);

            $this->assertSame($ttcFacture, $facture['amount_ttc'],
                "{$cas} : la facture FNE garde TTC = HT + TVA (TSP dans les autres taxes).");
            $this->assertSame($facture['total_a_payer'], $devis['total_a_payer'],
                "{$cas} : le devis et la facture qui en découle annoncent le même net.");
            $this->assertSame($facture['amount_ttc'] + $facture['tsp_amount'], $devis['amount_ttc'],
                "{$cas} : le TTC du devis ne diffère de celui de la facture que par la TSP.");
        }
    }

    // ── L'enregistrement écrit le nouveau TTC ──────────────────────

    public function test_l_enregistrement_ecrit_le_ttc_avec_tsp(): void
    {
        foreach (['clients', 'campaigns', 'users', 'communes', 'panels', 'external_panels', 'invoice_lines', 'reservations'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->timestamps(); });
        }
        foreach ([
            '2026_07_21_150448_create_quotes_table.php',
            '2026_07_21_150449_create_quote_lines_table.php',
            '2026_07_21_150450_create_quote_services_table.php',
            '2026_07_29_100000_add_campaign_dates_to_invoice_and_quote_lines.php',
            '2026_10_29_100000_add_interlocuteur_to_campaigns_reservations_quotes.php',
            '2026_10_30_100000_add_tva_applicable_to_quote_services.php',
            '2026_11_16_160000_add_quantite_to_services.php',
        ] as $m) {
            (require base_path("database/migrations/$m"))->up();
        }

        DB::table('clients')->insert(['id' => 10, 'name' => 'HAVAS']);
        DB::table('users')->insert(['id' => 1, 'name' => 'K. Yao']);
        $quote = Quote::create([
            'reference' => 'DEV-TEST-HAVAS', 'client_id' => 10, 'commercial_user_id' => 1, 'created_by' => 1,
            'title' => 'Havas', 'status' => 'brouillon', 'version' => 1, 'valid_days' => 30,
            'remise_pct' => 0, 'tva' => 18, 'public_token' => str_repeat('b', 64),
        ]);
        $quote->lines()->create([
            'designation' => 'Panneau 12 m²', 'dimension_m2' => 12, 'pu_ht_mensuel' => 93600,
            'quantite' => 5, 'duree_mois' => 1, 'odp_rate_applique' => 5000, 'tm_rate_applique' => 1000,
        ]);
        $quote->services()->create([
            'label' => 'Impression', 'quantite' => 30, 'prix_ht' => 20000, 'tva_applicable' => true,
        ]);

        $quote = app(QuoteBuilder::class)->recalculateAndPersist($quote->fresh());

        $this->assertSame(566280,  $quote->amount_ttc);
        $this->assertSame(1634280, $quote->total_a_payer);
        // La TVA des services, lue par la fiche admin, l'espace client et
        // le PDF, redonne le total des services du calculateur.
        $this->assertSame(108000, $quote->tvaServices());
        $this->assertSame($quote->services_ttc_total, $quote->services_ht_total + $quote->tvaServices());
    }

    // ── La TVA des services : une seule source ─────────────────────

    public function test_la_tva_des_services_ne_frappe_que_ceux_qui_y_sont_soumis(): void
    {
        $quote = new Quote(['tva' => 18]);
        $quote->setRelation('services', collect([
            new QuoteService(['label' => 'Impression', 'quantite' => 30, 'prix_ht' => 20000, 'tva_applicable' => true]),
            new QuoteService(['label' => 'Électricité', 'quantite' => 1, 'prix_ht' => 50000, 'tva_applicable' => false]),
        ]));

        // 600 000 × 18 % ; le service refacturé hors TVA n'en porte pas.
        // L'ancien calcul de l'espace client taxait les 650 000 en bloc :
        // il annonçait 117 000.
        $this->assertSame(108000, $quote->tvaServices());
    }

    public function test_le_ttc_d_un_service_a_une_seule_regle(): void
    {
        $this->assertSame(708000, QuoteService::ttcPour(600000, true, 18));
        $this->assertSame(50000,  QuoteService::ttcPour(50000, false, 18));

        // montantTtc() d'un service enregistré passe par la même règle.
        $svc = new QuoteService(['quantite' => 30, 'prix_ht' => 20000, 'tva_applicable' => true]);
        $this->assertSame(QuoteService::ttcPour(600000, true, 18), $svc->montantTtc(18));
    }

    // ── Tous les appelants passent par les sources uniques ─────────

    public function test_l_enregistrement_et_le_recapitulatif_passent_par_la_meme_methode(): void
    {
        $builder = (string) file_get_contents(base_path('app/Services/QuoteBuilder.php'));
        $this->assertStringContainsString('$totals = $this->totaux($linesArr', $builder,
            "L'enregistrement doit passer par QuoteBuilder::totaux().");

        $source = (string) file_get_contents(base_path('app/Http/Controllers/Admin/QuoteController.php'));
        $apercu = substr($source, strpos($source, 'public function totaux'));
        $apercu = substr($apercu, 0, strpos($apercu, 'protected function syncServices'));

        $this->assertStringContainsString('$builder->totaux(', $apercu,
            'Le récapitulatif en direct doit passer par QuoteBuilder::totaux().');
        $this->assertStringNotContainsString('calculateInvoice(', $apercu,
            "Le récapitulatif ne doit plus appeler le calculateur de facture en direct : "
            . "il afficherait le TTC de la facture (sans TSP).");
        $this->assertStringContainsString('QuoteService::ttcPour(', $apercu,
            'Le TTC de chaque service doit venir de QuoteService::ttcPour().');
    }

    public function test_la_facture_fne_n_est_pas_touchee(): void
    {
        $this->assertStringContainsString(
            '$amountTtc  = $netHt + $tvaAmount;',
            (string) file_get_contents(base_path('app/Services/InvoiceCalculator.php')),
            'La facture FNE garde TTC = net HT + TVA : la règle du devis ne doit pas y entrer.'
        );
    }

    public function test_les_ecrans_et_le_pdf_lisent_la_meme_tva_des_services(): void
    {
        foreach ([
            'resources/views/pdf/quote.blade.php',
            'resources/views/admin/quotes/show.blade.php',
            'resources/views/quotes/_show_content.blade.php',
        ] as $vue) {
            $src = (string) file_get_contents(base_path($vue));

            $this->assertStringContainsString('$quote->tvaServices()', $src,
                "{$vue} doit lire la TVA des services dans Quote::tvaServices().");
            $this->assertStringNotContainsString(
                'round($quote->services_ht_total * ((float) $quote->tva / 100))',
                $src,
                "{$vue} : la TVA ne doit pas frapper tous les services en bloc."
            );
            $this->assertStringContainsString('$quote->amount_ttc', $src,
                "{$vue} doit afficher le TTC enregistré, sans le recalculer.");
        }
    }

    public function test_le_formulaire_affiche_le_ttc_du_serveur(): void
    {
        $src = (string) file_get_contents(base_path('resources/views/admin/quotes/partials/_form.blade.php'));

        $this->assertStringContainsString("txt('qf-ttc',   fmt(t.amount_ttc));", $src,
            'Le récapitulatif doit afficher le TTC renvoyé par le serveur.');
        $this->assertStringContainsString('afficherMontantsServices(services, t.services_ttc_total);', $src,
            'Les montants des lignes de service doivent venir du serveur.');
        $this->assertStringNotContainsString('(1 + taux / 100)', $src,
            'Le TTC des services ne doit plus être recalculé en JavaScript.');
    }
}
