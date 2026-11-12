<?php

namespace Tests\Unit;

use App\Models\Panel;
use App\Services\AvailabilityService;
use App\Services\Axes\AxeService;
use App\Services\InvoiceFromCampaignBuilder;
use App\Services\TaxCalculationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Axes multi-pieds — phase 1 (2026-11-11).
 *
 * Un axe est une ligne `panels` qui regroupe plusieurs supports. Le pied
 * devient l'unité de réservation. Trois promesses sont tenues ici :
 *
 *   1. une **ligne d'axe ne se vend plus** — sinon l'axe et ses pieds
 *      seraient proposés deux fois ;
 *   2. un **pied hérite du blocage de son axe** — les contrats signés
 *      avant le découpage portent sur la ligne de l'axe, et on ne doit
 *      pas revendre un axe déjà vendu en entier ;
 *   3. la **facture ne change pas** — phase 1 fiscalement neutre : l'ODP
 *      et la TM restent au franc près ce qu'elles étaient.
 *
 * Base du scénario : AXE-10 est un axe de 3 pieds, vendu en entier à
 * ORANGE CI jusqu'au 31/12. AXE-20 est un axe de 2 pieds, libre. P-30 est
 * un panneau normal, libre.
 */
class AxesMultiPiedsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes(); $t->timestamps();
        });
        // Tables nécessaires à l'enrichissement des lignes PDF
        // (PdfExportService::enrichPanel lit commune, zone, format,
        // catégorie et photos).
        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('city')->nullable(); $t->timestamps();
        });
        Schema::create('zones', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('panel_formats', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->decimal('width', 8, 2)->nullable();
            $t->decimal('height', 8, 2)->nullable(); $t->timestamps();
        });
        Schema::create('panel_categories', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('panel_photos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->string('path');
            $t->unsignedInteger('ordre')->default(0); $t->timestamps();
        });

        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('name')->nullable();
            $t->string('status')->nullable();
            $t->unsignedBigInteger('monthly_rate')->default(0);
            $t->unsignedBigInteger('commune_id')->nullable();
            $t->unsignedBigInteger('zone_id')->nullable();
            $t->unsignedBigInteger('format_id')->nullable();
            $t->unsignedBigInteger('category_id')->nullable();
            $t->string('adresse')->nullable(); $t->string('quartier')->nullable();
            $t->boolean('is_lit')->default(false);
            $t->unsignedInteger('daily_traffic')->default(0);
            $t->text('zone_description')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            // Les colonnes de la phase 1
            $t->unsignedBigInteger('axe_id')->nullable();
            $t->boolean('est_axe')->default(false);
            $t->unsignedSmallInteger('pied_numero')->nullable();
            $t->unsignedSmallInteger('faces_par_pied')->nullable();
            $t->date('odp_start_date')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id');
            $t->string('status'); $t->date('start_date'); $t->date('end_date');
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservation_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reservation_id');
            $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable();
            $t->string('source')->default('interne');
            $t->unsignedBigInteger('unit_price')->default(0);
            $t->unsignedBigInteger('total_price')->default(0);
            $t->date('panel_start_date')->nullable(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('reservation_id')->nullable(); $t->string('status');
            $t->date('start_date'); $t->date('end_date'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id');
            $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable();
            $t->string('type')->default('interne'); $t->timestamps();
        });

        DB::table('clients')->insert([['id' => 1, 'name' => 'ORANGE CI'], ['id' => 2, 'name' => 'MOOV']]);
        DB::table('communes')->insert(['id' => 1, 'name' => 'COCODY', 'city' => 'Abidjan']);
        DB::table('zones')->insert(['id' => 1, 'name' => 'Zone 1']);
        DB::table('panel_formats')->insert(['id' => 1, 'name' => '3x1', 'width' => 3, 'height' => 1]);
        DB::table('panel_categories')->insert(['id' => 1, 'name' => 'Lumipub']);

        // Un insert groupé exige les MÊMES colonnes sur chaque ligne.
        $panneau = fn (array $valeurs) => $valeurs + [
            // Un panneau réel porte toujours un statut : après découpage,
            // chaque pied hérite de celui de son axe.
            'status' => 'libre', 'monthly_rate' => 130000, 'axe_id' => null,
            'est_axe' => false, 'pied_numero' => null, 'faces_par_pied' => 3,
            'commune_id' => 1, 'zone_id' => 1, 'format_id' => 1, 'category_id' => 1,
            'adresse' => null, 'quartier' => null, 'is_lit' => false,
            'daily_traffic' => 0, 'zone_description' => null,
            'latitude' => null, 'longitude' => null,
        ];

        DB::table('panels')->insert([
            // Axe vendu en entier, et ses 3 pieds
            $panneau(['id' => 10, 'reference' => 'AXE-10',     'name' => 'Axe Vendu (3P x 3F)',   'est_axe' => true]),
            $panneau(['id' => 11, 'reference' => 'AXE-10-P01', 'name' => 'Axe Vendu — pied 1/3',  'axe_id' => 10, 'pied_numero' => 1]),
            $panneau(['id' => 12, 'reference' => 'AXE-10-P02', 'name' => 'Axe Vendu — pied 2/3',  'axe_id' => 10, 'pied_numero' => 2]),
            $panneau(['id' => 13, 'reference' => 'AXE-10-P03', 'name' => 'Axe Vendu — pied 3/3',  'axe_id' => 10, 'pied_numero' => 3]),
            // Axe libre, et ses 2 pieds
            $panneau(['id' => 20, 'reference' => 'AXE-20',     'name' => 'Axe Libre (2P x 3F)',   'est_axe' => true]),
            $panneau(['id' => 21, 'reference' => 'AXE-20-P01', 'name' => 'Axe Libre — pied 1/2',  'axe_id' => 20, 'pied_numero' => 1]),
            $panneau(['id' => 22, 'reference' => 'AXE-20-P02', 'name' => 'Axe Libre — pied 2/2',  'axe_id' => 20, 'pied_numero' => 2]),
            // Panneau normal
            $panneau(['id' => 30, 'reference' => 'P-30',       'name' => 'Panneau simple', 'monthly_rate' => 450000, 'faces_par_pied' => null]),
        ]);

        // Le contrat signé AVANT le découpage porte sur la LIGNE DE L'AXE.
        DB::table('reservations')->insert([
            'id' => 60, 'reference' => 'RES-ORANGE', 'client_id' => 1, 'status' => 'confirme',
            'start_date' => '2026-11-01', 'end_date' => '2026-12-31',
        ]);
        DB::table('reservation_panels')->insert([
            'reservation_id' => 60, 'panel_id' => 10, 'unit_price' => 130000, 'total_price' => 130000,
        ]);
    }

    private function dispo(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    // ══════════════════════════════════════════════════════════════
    // Lecture de la structure dans le nom
    // ══════════════════════════════════════════════════════════════

    public function test_la_structure_se_lit_dans_le_nom(): void
    {
        $axes = new AxeService();

        $this->assertSame(
            ['pieds' => 11, 'faces' => 3],
            $axes->lireStructure('Cocody Lumipub Axe St Jean - Carrefour Lavie (11P x 3F)')
        );
        // Sans espaces, comme CDYLUP-10 en production.
        $this->assertSame(
            ['pieds' => 2, 'faces' => 3],
            $axes->lireStructure('Lumipub Axe opéra-Pharmacie st gabriel (2Px3F)')
        );
        // BK-002 : un Classique double face, 12 pieds de 2 faces.
        $this->assertSame(
            ['pieds' => 12, 'faces' => 2],
            $axes->lireStructure('Axe Carrefour Adiaké Assinie (12Panneaux double face)')
        );
    }

    public function test_un_lumipub_isole_n_est_pas_un_axe(): void
    {
        // MRYLUP-001 porte le mot « Lumipub » et un nombre, mais c'est UN
        // support : le découper créerait un pied fantôme.
        $axes = new AxeService();

        $this->assertNull($axes->lireStructure('Marcory Résidentiel (1 Lumipub)'));
        $this->assertNull($axes->lireStructure('Abengourou route de la gare'));
        $this->assertNull($axes->lireStructure(null));
    }

    public function test_la_reference_d_un_pied_ne_casse_pas_le_calcul_de_l_odp(): void
    {
        $axes = new AxeService();
        $reference = $axes->referencePied('CDYLUP-004', 1);

        $this->assertSame('CDYLUP-004-P01', $reference);

        // TaxCalculationService fusionne les faces d'un même mât en
        // retirant un suffixe A/B collé à un chiffre. Un pied ne doit
        // JAMAIS être fusionné : son ODP resterait celle d'un seul.
        $this->assertSame(
            $reference,
            TaxCalculationService::referencePhysique($reference),
            'Une référence de pied ne doit pas être regroupée comme une face.'
        );
        $this->assertSame(
            'CDYLUP-004-P11',
            TaxCalculationService::referencePhysique($axes->referencePied('CDYLUP-004', 11))
        );
    }

    public function test_le_nom_du_pied_perd_la_mention_de_structure(): void
    {
        $axes = new AxeService();

        $this->assertSame(
            'Cocody Lumipub Axe St Jean - Carrefour Lavie — pied 3/11',
            $axes->nomPied('Cocody Lumipub Axe St Jean - Carrefour Lavie (11P x 3F)', 3, 11)
        );
    }

    // ══════════════════════════════════════════════════════════════
    // RÈGLE 1 — une ligne d'axe ne se vend plus
    // ══════════════════════════════════════════════════════════════

    public function test_une_ligne_d_axe_n_est_jamais_proposee(): void
    {
        $proposes = $this->dispo()
            ->getAvailablePanels('2026-06-01', '2026-06-30')
            ->pluck('reference')
            ->all();

        $this->assertNotContains('AXE-10', $proposes, 'La ligne d\'axe serait comptée deux fois.');
        $this->assertNotContains('AXE-20', $proposes);
        $this->assertContains('AXE-20-P01', $proposes);
        $this->assertContains('P-30', $proposes, 'Les panneaux simples ne changent pas.');
    }

    public function test_la_portee_vendables_ecarte_les_axes(): void
    {
        $refs = Panel::vendables()->pluck('reference')->all();

        $this->assertNotContains('AXE-10', $refs);
        $this->assertNotContains('AXE-20', $refs);
        $this->assertCount(6, $refs, '5 pieds + 1 panneau simple.');
    }

    // ══════════════════════════════════════════════════════════════
    // RÈGLE 2 — un pied hérite du blocage de son axe
    // ══════════════════════════════════════════════════════════════

    public function test_les_pieds_d_un_axe_vendu_en_entier_ne_sont_pas_libres(): void
    {
        // Le contrat porte sur la ligne AXE-10, pas sur ses pieds. Sans le
        // report, on revendrait trois fois un axe déjà vendu.
        $bloques = $this->dispo()->getUnavailablePanelIds([11, 12, 13], '2026-11-10', '2026-11-20');

        sort($bloques);
        $this->assertSame([11, 12, 13], $bloques);
    }

    public function test_les_pieds_redeviennent_libres_apres_la_fin_du_contrat(): void
    {
        $bloques = $this->dispo()->getUnavailablePanelIds([11, 12, 13], '2027-01-01', '2027-01-31');

        $this->assertSame([], $bloques, 'Le contrat finit le 31/12 : janvier est libre.');
    }

    public function test_les_pieds_d_un_axe_libre_restent_libres(): void
    {
        $bloques = $this->dispo()->getUnavailablePanelIds([21, 22], '2026-11-10', '2026-11-20');

        $this->assertSame([], $bloques);
    }

    public function test_l_axe_interroge_au_passage_ne_ressort_pas(): void
    {
        // On n'a demandé que les pieds : l'appelant ne doit pas recevoir
        // l'id de l'axe, qu'il ne connaît pas.
        $bloques = $this->dispo()->getUnavailablePanelIds([11], '2026-11-10', '2026-11-20');

        $this->assertSame([11], $bloques);
        $this->assertNotContains(10, $bloques);
    }

    public function test_le_conflit_ferme_d_un_pied_nomme_l_axe(): void
    {
        $conflits = $this->dispo()->conflitsFermes([11, 21], '2026-11-10', '2026-11-20');

        $this->assertCount(1, $conflits, 'Seul le pied de l\'axe vendu est en conflit.');
        $this->assertSame(11, $conflits[0]['panel_id']);
        $this->assertSame('AXE-10-P01', $conflits[0]['reference']);
        $this->assertStringContainsString('axe vendu en entier', $conflits[0]['blocking_ref'],
            'L\'utilisateur doit comprendre que c\'est l\'axe qui est pris, pas ce pied.');
    }

    public function test_la_date_de_liberation_d_un_pied_suit_son_axe(): void
    {
        $details = $this->dispo()->getOccupationDetails([11, 21], '2026-11-10', '2026-11-20');

        $this->assertArrayHasKey(11, $details);
        $this->assertArrayNotHasKey(21, $details, 'L\'axe libre n\'occupe rien.');
        $this->assertArrayNotHasKey(10, $details, 'L\'axe interrogé au passage ne ressort pas.');
        $this->assertSame('2026-12-31', $details[11]['end_date']->toDateString());
    }

    public function test_le_statut_d_un_pied_reflete_le_contrat_de_son_axe(): void
    {
        // Le statut est le cache lu par les badges d'inventaire : un pied
        // affiché « libre » alors que son axe est vendu, c'est une vente
        // en double annoncée.
        $this->dispo()->syncPanelStatuses([11, 12, 13, 21, 22, 30]);

        $this->assertSame('confirme', Panel::find(11)->status->value);
        $this->assertSame('confirme', Panel::find(13)->status->value);
        $this->assertSame('libre',    Panel::find(21)->status->value);
        $this->assertSame('libre',    Panel::find(30)->status->value);
    }

    public function test_le_controle_rapide_voit_aussi_l_axe(): void
    {
        $this->assertFalse($this->dispo()->quickCheck(11, '2026-11-10', '2026-11-20'));
        $this->assertTrue($this->dispo()->quickCheck(21, '2026-11-10', '2026-11-20'));
    }

    public function test_les_donnees_d_affichage_marquent_le_pied_occupe(): void
    {
        $data = $this->dispo()->getPanelAvailabilityData([11, 21], '2026-11-10', '2026-11-20');

        $this->assertFalse($data[11]['available']);
        $this->assertSame('2026-12-31', $data[11]['release_date']);
        $this->assertTrue($data[21]['available']);
        $this->assertFalse($data->has(10), 'L\'axe interrogé au passage ne ressort pas.');
    }

    // ══════════════════════════════════════════════════════════════
    // RÈGLE 3 — la facture ne change pas (phase 1)
    // ══════════════════════════════════════════════════════════════

    public function test_la_phase_1_ne_facture_pas_encore_au_pied(): void
    {
        // Bascule plus lue depuis le 2026-11-12 (prix = somme des pieds,
        // ODP/TM à la main). Elle doit rester à false : la réactiver
        // n'aurait plus de sens.
        $this->assertFalse(InvoiceFromCampaignBuilder::FACTURER_AU_PIED);
    }

    public function test_les_pieds_d_un_axe_donnent_une_seule_ligne_de_facture(): void
    {
        $builder = new class extends InvoiceFromCampaignBuilder {
            public function __construct() {}
            public function grouper($panels, array $negotiated): array
            {
                return $this->grouperParAxe($panels, $negotiated);
            }
        };

        $panels = Panel::whereIn('id', [11, 12, 13, 30])->get();
        $groupes = $builder->grouper($panels, [
            'int_11' => 130000.0, 'int_12' => 130000.0, 'int_13' => 0.0,
            'int_30' => 450000.0,
        ]);

        $this->assertCount(2, $groupes, 'Trois pieds + un panneau simple = deux lignes.');

        $parPorteur = collect($groupes)->keyBy(fn ($g) => $g['porteur']->reference);

        // Le porteur est la LIGNE DE L'AXE : même panel_id, même surface,
        // même commune qu'avant le découpage.
        $this->assertTrue($parPorteur->has('AXE-10'));
        $this->assertSame(3, $parPorteur['AXE-10']['pieds']);
        // Règle du 2026-11-12 : somme des prix des pieds réservés ; le pied
        // à 0 est un pied offert, il compte 0.
        $this->assertSame(260000.0, $parPorteur['AXE-10']['pu']);
        $this->assertSame('3 pieds', $parPorteur['AXE-10']['complement']);
        $this->assertTrue($parPorteur['AXE-10']['taxes_a_la_main'], 'ODP/TM d\'un axe : à la main.');

        $this->assertTrue($parPorteur->has('P-30'));
        $this->assertSame(1, $parPorteur['P-30']['pieds']);
        $this->assertSame(450000.0, $parPorteur['P-30']['pu']);
        $this->assertNull($parPorteur['P-30']['complement']);
        $this->assertFalse($parPorteur['P-30']['taxes_a_la_main'], 'Panneau ordinaire : ODP/TM calculées.');
    }

    public function test_sans_contrat_chaque_pied_compte_son_tarif(): void
    {
        $builder = new class extends InvoiceFromCampaignBuilder {
            public function __construct() {}
            public function grouper($panels, array $negotiated): array
            {
                return $this->grouperParAxe($panels, $negotiated);
            }
        };

        $groupes = $builder->grouper(Panel::whereIn('id', [11, 12])->get(), []);
        $attendu = (float) Panel::whereIn('id', [11, 12])->sum('monthly_rate');

        $this->assertCount(1, $groupes);
        $this->assertSame($attendu, $groupes[0]['pu']);
        $this->assertSame('2 pieds', $groupes[0]['complement']);
    }

    public function test_la_ligne_d_axe_d_un_ancien_contrat_porte_son_groupe(): void
    {
        // Contrat signé avant le découpage : le pivot pointe l'axe. La
        // facture doit continuer de sortir UNE ligne, portée par l'axe.
        $builder = new class extends InvoiceFromCampaignBuilder {
            public function __construct() {}
            public function grouper($panels, array $negotiated): array
            {
                return $this->grouperParAxe($panels, $negotiated);
            }
        };

        $groupes = $builder->grouper(Panel::whereIn('id', [10])->get(), ['int_10' => 130000.0]);

        $this->assertCount(1, $groupes);
        $this->assertSame('AXE-10', $groupes[0]['porteur']->reference);
        $this->assertSame(1, $groupes[0]['pieds'], 'Aucun pied au contrat : quantité 1.');
        $this->assertSame(130000.0, $groupes[0]['pu'], 'Axe entier : le prix de son contrat.');
        $this->assertNull($groupes[0]['complement']);
        $this->assertTrue($groupes[0]['taxes_a_la_main'], 'Même un ancien contrat : ODP/TM à la main.');
    }

    // ══════════════════════════════════════════════════════════════
    // Affichage — un axe = UNE entrée (PDF et catalogue Brevo)
    // ══════════════════════════════════════════════════════════════

    private function lignesFactices(array $ids): \Illuminate\Support\Collection
    {
        // Des lignes au format de DisponibilitesPdfBuilder::lignes().
        return Panel::whereIn('id', $ids)->get()->map(fn ($p) => [
            'id'             => $p->id,
            'reference'      => $p->reference,
            'name'           => $p->name,
            'commune'        => 'COCODY',
            'display_status' => 'libre',
            'release_date'   => null,
        ]);
    }

    public function test_les_pieds_d_un_axe_donnent_une_seule_entree(): void
    {
        // Deux pieds sur trois disponibles : le client doit lire « 2 sur 3 »,
        // pas voir deux pages identiques.
        $groupes = app(\App\Services\DisponibilitesPdfBuilder::class)
            ->regrouperLesPieds($this->lignesFactices([11, 12, 30]));

        $this->assertCount(2, $groupes, 'Deux pieds d\'un axe + un panneau simple = deux entrées.');

        $axe = $groupes->firstWhere('reference', 'AXE-10');
        $this->assertNotNull($axe, 'L\'entrée porte la référence de l\'AXE — donc sa photo.');
        $this->assertTrue($axe['est_axe']);
        $this->assertSame(2, $axe['pieds_disponibles']);
        $this->assertSame(3, $axe['pieds_total']);
        $this->assertSame(3, $axe['faces_par_pied']);
        $this->assertSame('libre', $axe['display_status'],
            'Le statut vient du pied : l\'axe peut porter un vieux contrat.');
    }

    public function test_un_panneau_simple_traverse_sans_changement(): void
    {
        $groupes = app(\App\Services\DisponibilitesPdfBuilder::class)
            ->regrouperLesPieds($this->lignesFactices([30]));

        $this->assertCount(1, $groupes);
        $this->assertSame('P-30', $groupes[0]['reference']);
        $this->assertArrayNotHasKey('est_axe', $groupes[0]);
    }

    public function test_deux_axes_restent_deux_entrees(): void
    {
        $groupes = app(\App\Services\DisponibilitesPdfBuilder::class)
            ->regrouperLesPieds($this->lignesFactices([11, 12, 13, 21, 22]));

        $this->assertCount(2, $groupes);
        $this->assertSame(
            [3, 2],
            $groupes->pluck('pieds_disponibles')->all(),
            'AXE-10 propose ses 3 pieds, AXE-20 ses 2.'
        );
    }

    public function test_le_regroupement_ne_tente_rien_sans_les_colonnes(): void
    {
        Schema::table('panels', function (Blueprint $t) {
            $t->dropColumn('axe_id');
        });

        $lignes = collect([['id' => 30, 'reference' => 'P-30', 'commune' => 'COCODY']]);
        $groupes = app(\App\Services\DisponibilitesPdfBuilder::class)->regrouperLesPieds($lignes);

        $this->assertSame($lignes->all(), $groupes->all());
    }

    // ══════════════════════════════════════════════════════════════
    // Aucun écran ne propose plus un axe
    // ══════════════════════════════════════════════════════════════

    private function source(string $chemin): string
    {
        $fichier = __DIR__ . '/../../' . $chemin;
        $this->assertFileExists($fichier);

        return file_get_contents($fichier);
    }

    public function test_les_quatre_ecrans_qui_proposent_des_panneaux_ecartent_les_axes(): void
    {
        // Sans ce filtre, l'axe ET ses pieds s'affichent : le même
        // emplacement compté deux fois, et vendable deux fois.
        foreach ([
            'app/Http/Controllers/Admin/ReservationController.php' => 2,
            'app/Http/Controllers/Admin/CampaignController.php'     => 1,
            'app/Http/Controllers/Admin/QuoteController.php'        => 1,
        ] as $fichier => $attendu) {
            $this->assertSame(
                $attendu,
                substr_count($this->source($fichier), 'vendables()'),
                "{$fichier} : chaque liste de panneaux proposés doit écarter les axes."
            );
        }
    }

    public function test_le_catalogue_ecarte_les_axes_et_regroupe_les_pieds(): void
    {
        $source = $this->source('app/Services/Diffusion/DiffusionDisponibilitesService.php');

        $this->assertStringContainsString('->vendables()', $source);
        $this->assertStringContainsString('regrouperLesPieds(', $source,
            'Sans regroupement, le catalogue perd les photos (elles sont sur la ligne d\'axe) et gagne 90 pages.');
    }

    public function test_le_selecteur_de_reservation_applique_la_regle_des_axes(): void
    {
        // ⚠ Cet écran recalcule la disponibilité LUI-MÊME, il n'appelle pas
        // AvailabilityService. S'il oublie la règle, un pied d'axe vendu
        // s'affiche libre et on le revend.
        $source = $this->source('app/Http/Controllers/Admin/ReservationController.php');

        $this->assertStringContainsString('->axeParPied(', $source);
        $this->assertStringContainsString('->avecAxesParents(', $source);
        $this->assertStringContainsString('$idsOccupation', $source);
        $this->assertStringNotContainsString(
            "ReservationPanel::whereIn('panel_id', \$panels->pluck('id'))",
            $source,
            'Les requêtes d\'occupation doivent interroger aussi les axes parents.'
        );
    }

    public function test_le_selecteur_affiche_le_pied_et_sait_cocher_tout_l_axe(): void
    {
        $vue = $this->source('resources/views/admin/reservations/disponibilites.blade.php');

        $this->assertStringContainsString('_axeInfo(p)', $vue, 'La logique d\'affichage, écrite une fois.');
        $this->assertStringContainsString('selectAxe(axeId)', $vue);
        $this->assertStringContainsString('S._axes = data.axes', $vue);
        // Le badge et le bouton sont posés dans les DEUX vues (cartes, liste).
        $this->assertStringContainsString('${_ax.badge', $vue);
        $this->assertStringContainsString('${_ax.btn}', $vue);
        $this->assertStringContainsString('badgeLigne', $vue);
    }

    public function test_le_pdf_explique_au_client_comment_lire_un_axe(): void
    {
        $vue = $this->source('resources/views/admin/reservations/pdf/disponibilites-images.blade.php');

        $this->assertStringContainsString('Comment lire cette disponibilité', $vue);
        $this->assertStringContainsString('Pieds proposés', $vue);
        // L'encadré ne s'affiche que si le document contient un axe, et une
        // seule fois.
        $this->assertStringContainsString('$contientUnAxe', $vue);
        $this->assertStringContainsString('$encadreAxePose', $vue);
    }

    // ══════════════════════════════════════════════════════════════
    // Le code doit tourner AVANT que la migration soit jouée
    // ══════════════════════════════════════════════════════════════

    public function test_sans_les_colonnes_rien_ne_casse(): void
    {
        // Le code part en production avant `php artisan migrate`. Sans ce
        // garde-fou, toute réservation plante jusqu'à la migration.
        Schema::table('panels', function (Blueprint $t) {
            $t->dropColumn('axe_id');
        });

        $axes = new AxeService();
        $this->assertFalse($axes->colonnesPresentes());
        $this->assertSame([], $axes->axeParPied([11, 12]));
        $this->assertSame([11, 12], $axes->avecAxesParents([11, 12]));
        $this->assertTrue($axes->candidats()->isEmpty());

        // Et la disponibilité continue de répondre.
        $this->assertSame([], app(AvailabilityService::class)
            ->getUnavailablePanelIds([30], '2026-11-10', '2026-11-20'));
    }

    // ══════════════════════════════════════════════════════════════
    // ODP / TM d'un axe : à la main, jamais calculées (2026-11-12)
    // ══════════════════════════════════════════════════════════════

    public function test_les_axes_et_leurs_pieds_sortent_des_taxes_automatiques(): void
    {
        $ids = Panel::avecTaxesAutomatiques()->pluck('id')->all();

        $this->assertContains(30, $ids, 'Un panneau ordinaire garde ses taxes calculées.');
        foreach ([10, 11, 12, 13, 20, 21, 22] as $axeOuPied) {
            $this->assertNotContains($axeOuPied, $ids);
        }
        $this->assertTrue(Panel::find(10)->taxesSaisiesALaMain(), 'ligne d\'axe');
        $this->assertTrue(Panel::find(11)->taxesSaisiesALaMain(), 'pied');
        $this->assertFalse(Panel::find(30)->taxesSaisiesALaMain());
    }

    public function test_le_module_taxes_applique_la_regle_partout(): void
    {
        // Canari : chaque calcul de taxes qui lit le parc passe par la règle.
        // Sans elle, depuis le découpage, un axe de 11 pieds comptait 12 fois.
        $sources = [
            'app/Services/TaxCalculationService.php' => 2, // generateLines + panneauxPourCalcul
            'app/Services/TaxAutoService.php'        => 1,
            'app/Http/Controllers/Admin/TaxController.php' => 1,
        ];
        foreach ($sources as $fichier => $attendu) {
            $this->assertSame($attendu, substr_count(file_get_contents(base_path($fichier)), '->avecTaxesAutomatiques()'), $fichier);
        }
        $this->assertStringContainsString('taxesSaisiesALaMain()', file_get_contents(base_path('app/Services/TaxReportService.php')));
    }

    // ══════════════════════════════════════════════════════════════
    // Écran Disponibilités (2026-11-12, demande du MP) : une carte par axe
    // ══════════════════════════════════════════════════════════════

    private function grilleDispo(array $params): array
    {
        $req = \Illuminate\Http\Request::create('/admin/reservations/panneaux', 'GET', $params + ['source' => 'internal']);

        return app(\App\Http\Controllers\Admin\ReservationController::class)->panneauxAjax($req)->getData(true);
    }

    public function test_l_ecran_dispo_montre_une_carte_par_axe_avec_ses_pieds(): void
    {
        // Janvier 2027 : AXE-10 n'est plus vendu (contrat jusqu'au 31/12),
        // MOOV prend le pied 1 d'AXE-20.
        DB::table('reservations')->insert(['id' => 61, 'reference' => 'RES-MOOV', 'client_id' => 2, 'status' => 'confirme', 'start_date' => '2027-01-01', 'end_date' => '2027-01-31']);
        DB::table('reservation_panels')->insert(['reservation_id' => 61, 'panel_id' => 21, 'unit_price' => 130000, 'total_price' => 130000]);

        $data = $this->grilleDispo(['dispo_du' => '2027-01-01', 'dispo_au' => '2027-01-31']);
        $parRef = collect($data['panels'])->keyBy('reference');

        // Deux axes + le panneau simple : aucun pied seul dans la grille.
        $this->assertSame(['AXE-10', 'AXE-20', 'P-30'], $parRef->keys()->sort()->values()->all());
        $axe = $parRef['AXE-20'];
        $this->assertTrue($axe['est_axe']);
        $this->assertFalse($axe['is_selectable'], 'L\'axe ne se coche pas, ses pieds oui.');
        $this->assertSame([2, 1], [$axe['pieds_total'], $axe['pieds_libres']]);
        $this->assertSame([[21, 'occupe', false], [22, 'libre', true]],
            array_map(fn ($x) => [$x['id'], $x['display_status'], $x['is_selectable']], $axe['pieds']));
        $this->assertSame('Axe Libre', $axe['name'], 'Sans le « (2P x 3F) ».');

        // Compteurs en pieds : 3 pieds libres d'AXE-10 + 1 d'AXE-20 + P-30.
        $this->assertSame(5, $data['stats']['disponibles']);
        $this->assertSame(1, $data['stats']['occupes']);

        // Filtre « Libre » : un axe apparaît s'il lui reste un pied libre,
        // et sa liste montre quand même tous ses pieds.
        $libres = collect($this->grilleDispo(['dispo_du' => '2027-01-01', 'dispo_au' => '2027-01-31', 'statut' => 'libre'])['panels'])->keyBy('reference');
        $this->assertCount(2, $libres['AXE-20']['pieds']);

        // Filtre « Occupé » : seul AXE-20 (son pied 1).
        $occupes = collect($this->grilleDispo(['dispo_du' => '2027-01-01', 'dispo_au' => '2027-01-31', 'statut' => 'occupe'])['panels']);
        $this->assertSame(['AXE-20'], $occupes->pluck('reference')->all());
    }

    public function test_les_selecteurs_rangent_les_pieds_sous_leur_axe(): void
    {
        // Lignes au format des sélecteurs (campagne, réservation) : chaque
        // pied garde le statut que l'appelant a calculé.
        $lignes = collect([
            ['id' => 30, 'axe_id' => null, 'pied_numero' => null, 'available' => true],
            ['id' => 22, 'axe_id' => 20,   'pied_numero' => 2,    'available' => true],
            ['id' => 21, 'axe_id' => 20,   'pied_numero' => 1,    'available' => false],
        ]);

        $groupes = (new AxeService())->regrouperLignes($lignes, fn ($axe, array $pieds, int $total) => [
            'id' => 'axe_' . $axe->id, 'reference' => $axe->reference, 'total' => $total,
            'pieds' => array_column($pieds, 'id'), 'libres' => collect($pieds)->where('available', true)->count(),
        ]);

        $this->assertSame([30, 'axe_20'], $groupes->pluck('id')->all(), 'L\'axe prend la place de son premier pied.');
        $this->assertSame([21, 22], $groupes[1]['pieds'], 'Pieds dans l\'ordre du terrain.');
        $this->assertSame([2, 1], [$groupes[1]['total'], $groupes[1]['libres']]);
        $this->assertSame('Axe Libre', (new AxeService())->nomCourt('Axe Libre (2P x 3F)'));
    }

    // ══════════════════════════════════════════════════════════════
    // Point 1a (2026-11-12) — un axe entier repris = ses pieds libres
    // ══════════════════════════════════════════════════════════════

    public function test_un_axe_entier_repris_devient_ses_pieds_libres(): void
    {
        // AXE-20 libre : ses 2 pieds sont repris, le panneau simple aussi.
        $r = $this->dispo()->remplacerAxesParPiedsLibres([20, 30], '2027-01-01', '2027-03-31', 1);
        $this->assertEqualsCanonicalizing([21, 22, 30], $r['ids']);
        $this->assertSame([['axe' => 'AXE-20', 'total' => 2, 'repris' => 2, 'pris' => []]], $r['axes']);

        // MOOV a déjà pris le pied 1 en janvier : ORANGE ne récupère que le pied 2.
        DB::table('reservations')->insert(['id' => 61, 'reference' => 'RES-MOOV', 'client_id' => 2, 'status' => 'confirme', 'start_date' => '2027-01-01', 'end_date' => '2027-01-31']);
        DB::table('reservation_panels')->insert(['reservation_id' => 61, 'panel_id' => 21, 'unit_price' => 130000, 'total_price' => 130000]);

        $r = $this->dispo()->remplacerAxesParPiedsLibres([20], '2027-01-01', '2027-03-31', 1);
        $this->assertSame([22], $r['ids']);
        $this->assertSame(['AXE-20-P01 (RES-MOOV)'], $r['axes'][0]['pris']);
        $this->assertSame(
            'Axes : AXE-20 : 1 pied(s) sur 2 repris, 1 déjà pris (AXE-20-P01 (RES-MOOV)).',
            AvailabilityService::messageAxesRemplaces($r['axes'])
        );

        // Sans ligne d'axe, rien ne bouge.
        $this->assertSame(['ids' => [30], 'axes' => []], $this->dispo()->remplacerAxesParPiedsLibres([30], '2027-01-01', '2027-03-31', 1));
    }

    public function test_le_renouvellement_la_prolongation_et_l_import_passent_par_le_remplacement(): void
    {
        // Canari : les trois chemins qui reprennent un axe entier.
        $this->assertStringContainsString('remplacerAxesParPiedsLibres', file_get_contents(base_path('app/Http/Controllers/Admin/CampaignImportController.php')));
        $this->assertSame(2, substr_count(file_get_contents(base_path('app/Http/Controllers/Admin/CampaignController.php')), 'remplacerAxesParPiedsLibres('));
    }

    // ══════════════════════════════════════════════════════════════
    // Devis (2026-11-12) : une ligne par axe, ses pieds, ODP/TM à la main
    // ══════════════════════════════════════════════════════════════

    public function test_une_ligne_de_devis_par_axe_retient_ses_pieds(): void
    {
        $ligne = (new AxeService())->ligneDevis(Panel::find(20), Panel::whereIn('id', [21, 22])->orderBy('pied_numero')->get());

        $this->assertSame(20, $ligne['panel_id'], 'La ligne porte l\'axe…');
        $this->assertSame([21, 22], $ligne['pieds'], '… et retient ses pieds.');
        $this->assertSame('AXE-20 — Axe Libre — 2 pieds', $ligne['designation']);
        $this->assertSame(260000, $ligne['pu_ht_mensuel'], 'Somme des tarifs des pieds.');
        $this->assertSame([1, 0, 0], [$ligne['quantite'], $ligne['odp_amount_override'], $ligne['tm_amount_override']]);
    }

    public function test_le_devis_ne_calcule_pas_l_odp_ni_la_tm_d_un_axe(): void
    {
        $controleur = app(\App\Http\Controllers\Admin\QuoteController::class);
        $taxes = \Closure::bind(fn (array $l) => $this->taxesSaisies($l), $controleur, $controleur);

        $this->assertSame([0, 0], $taxes(['panel_id' => 20]), 'Axe : 0 tant que rien n\'est saisi.');
        $this->assertSame([15000, 3000], $taxes(['panel_id' => 20, 'odp_amount_override' => '15000', 'tm_amount_override' => 3000]));
        $this->assertSame([null, null], $taxes(['panel_id' => 30]), 'Panneau ordinaire : calcul automatique.');
    }

    public function test_la_migration_des_lignes_de_devis_est_rejouable(): void
    {
        Schema::create('quote_lines', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('quote_id'); $t->unsignedBigInteger('panel_id')->nullable(); $t->timestamps();
        });
        $migration = require base_path('database/migrations/2026_11_12_100000_add_axes_to_quote_lines.php');

        $migration->up();
        $migration->up(); // rejouée : sans effet
        foreach (['pieds', 'odp_amount_override', 'tm_amount_override'] as $col) {
            $this->assertTrue(Schema::hasColumn('quote_lines', $col), $col);
        }

        $migration->down();
        $this->assertFalse(Schema::hasColumn('quote_lines', 'pieds'));
    }

    public function test_un_devis_accepte_reserve_les_pieds_de_la_ligne(): void
    {
        // Canari : la conversion lit les pieds de la ligne, pas l'axe.
        $source = file_get_contents(base_path('app/Services/QuoteConverter.php'));
        $this->assertSame(2, substr_count($source, '->idsPieds()'));

        $ligne = new \App\Models\QuoteLine(['panel_id' => 20, 'pieds' => [21, 22]]);
        $this->assertSame([21, 22], $ligne->idsPieds());
        $this->assertSame([], (new \App\Models\QuoteLine(['panel_id' => 30]))->idsPieds());
    }

    public function test_deux_clients_sur_le_meme_axe_ne_se_bloquent_pas_a_la_facture(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('status');
            $t->unsignedBigInteger('campaign_id')->nullable(); $t->date('issued_at'); $t->timestamps();
        });
        Schema::create('invoice_lines', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('invoice_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->decimal('duree_mois', 6, 2)->default(1); $t->timestamps();
        });
        DB::table('campaigns')->insert([
            ['id' => 50, 'name' => 'Pieds 1 et 2 — ORANGE', 'client_id' => 1, 'status' => 'actif', 'start_date' => '2027-01-01', 'end_date' => '2027-03-31'],
            ['id' => 51, 'name' => 'Pied 3 — MOOV',         'client_id' => 2, 'status' => 'actif', 'start_date' => '2027-01-01', 'end_date' => '2027-03-31'],
        ]);
        // La facture d'ORANGE porte l'axe 10 (ses pieds 1 et 2) et le panneau 30.
        DB::table('invoices')->insert(['id' => 1, 'reference' => 'FAC-2027-001', 'status' => 'envoyee', 'campaign_id' => 50, 'issued_at' => '2027-01-05']);
        DB::table('invoice_lines')->insert([['invoice_id' => 1, 'panel_id' => 10], ['invoice_id' => 1, 'panel_id' => 30]]);

        $checker = app(\App\Services\PanelBillingChecker::class);
        $emise   = \Carbon\Carbon::parse('2027-01-05');

        // MOOV a un autre pied du même axe : sa facture passe.
        $this->assertSame([], $checker->assertNotAlreadyBilled([['panel_id' => 10, 'designation' => 'AXE-10 — 1 pied', 'duree_mois' => 3]], $emise, 51));
        // Mais la même campagne ne refacture pas son axe deux fois.
        $this->assertCount(1, $checker->assertNotAlreadyBilled([['panel_id' => 10, 'designation' => 'AXE-10 — 2 pieds', 'duree_mois' => 3]], $emise, 50));
        // Un panneau ordinaire garde la règle d'avant : une seule facture.
        $this->assertCount(1, $checker->assertNotAlreadyBilled([['panel_id' => 30, 'designation' => 'P-30', 'duree_mois' => 3]], $emise, 51));
    }
}
