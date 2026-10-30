<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientRegroupement;
use App\Services\Clients\RegroupementClients;
use App\Support\NomEntreprise;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regrouper deux fiches client (règles validées le 2026-10-30).
 *
 * Base calquée sur la structure MySQL réelle (relevée le 2026-10-30) :
 *   · ADDOHA (10) — la campagne C1, l'interlocutrice Kali (principale) ;
 *   · ADDOHA (11) — plus ancienne, NCC, e-mail, commercial ; la réservation
 *     R1 (mêmes panneaux, dates, montant que C1), une campagne et un devis à
 *     la corbeille, une facture brouillon ; interlocuteurs Franck (celui de
 *     R1), « Kali bis » (même adresse que Kali), Ancien (d'aucun dossier),
 *     et un interlocuteur supprimé.
 */
class RegroupementClientsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('role')->nullable(); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name', 150); $t->string('ncc', 20)->nullable()->unique(); $t->string('regime_imposition')->nullable();
            $t->string('rccm')->nullable(); $t->string('sector')->nullable(); $t->string('statut_commercial', 10)->default('client');
            $t->string('contact_name')->nullable(); $t->string('email')->nullable(); $t->string('ifu')->nullable();
            $t->string('password')->nullable(); $t->string('remember_token')->nullable(); $t->boolean('must_change_password')->default(true);
            $t->string('phone')->nullable(); $t->text('address')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('client_contacts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->string('name'); $t->string('email')->nullable();
            $t->string('phone')->nullable(); $t->string('position')->nullable(); $t->string('role')->default('autre');
            $t->boolean('is_primary')->default(false); $t->boolean('receives_notifications')->default(true);
            $t->string('statut', 10)->default('client'); $t->string('source')->nullable(); $t->text('notes')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->date('start_date'); $t->date('end_date'); $t->string('status'); $t->unsignedBigInteger('total_amount')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('reservation_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reservation_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('source')->default('interne');
            $t->date('panel_start_date')->nullable(); $t->unsignedBigInteger('unit_price')->default(0); $t->unsignedBigInteger('total_price')->default(0);
            $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->unsignedBigInteger('reservation_id')->nullable(); $t->date('start_date')->nullable(); $t->date('end_date')->nullable();
            $t->string('status'); $t->unsignedBigInteger('total_amount')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('type')->default('interne'); $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->string('status')->default('brouillon'); $t->timestamps();
        });
        Schema::create('quotes', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        foreach (['propositions', 'client_users', 'client_messages', 'public_links', 'relances', 'relances_fin_campagne', 'renouvellements', 'satisfaction_surveys', 'external_panels'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('client_id')->nullable(); $t->timestamps();
            });
        }
        (require base_path('database/migrations/2026_10_30_100000_create_client_regroupements_table.php'))->up();

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Direction', 'email' => 'dg@cible-ci.com', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Commerciale', 'email' => 'sc@cible-ci.com', 'role' => 'commercial'],
        ]);
        DB::table('clients')->insert(['id' => 10, 'name' => 'ADDOHA', 'contact_name' => 'KALI', 'ncc' => 'CLT-2026-0007', 'created_at' => '2026-05-20 10:00:00']);
        DB::table('clients')->insert(['id' => 11, 'name' => 'ADDOHA', 'contact_name' => 'Franck', 'ncc' => 'CI-123', 'email' => 'contact@addoha.ci',
            'phone' => '0700000000', 'rccm' => 'RC-1', 'user_id' => 2, 'created_at' => '2026-05-01 10:00:00']);
        DB::table('client_contacts')->insert([
            ['id' => 1, 'client_id' => 10, 'name' => 'Kali', 'email' => 'kali@addoha.ci', 'is_primary' => true, 'receives_notifications' => true, 'deleted_at' => null, 'phone' => null],
            ['id' => 2, 'client_id' => 11, 'name' => 'Franck', 'email' => 'franck@addoha.ci', 'is_primary' => true, 'receives_notifications' => true, 'deleted_at' => null, 'phone' => null],
            ['id' => 3, 'client_id' => 11, 'name' => 'Kali bis', 'email' => ' KALI@addoha.ci ', 'is_primary' => false, 'receives_notifications' => true, 'deleted_at' => null, 'phone' => '0102030405'],
            ['id' => 4, 'client_id' => 11, 'name' => 'Ancien', 'email' => 'ancien@addoha.ci', 'is_primary' => false, 'receives_notifications' => true, 'deleted_at' => null, 'phone' => null],
            ['id' => 5, 'client_id' => 11, 'name' => 'Parti', 'email' => 'parti@addoha.ci', 'is_primary' => false, 'receives_notifications' => true, 'deleted_at' => '2026-06-01 00:00:00', 'phone' => null],
        ]);
        DB::table('reservations')->insert([
            'id' => 100, 'reference' => 'RES-ADDOHA', 'client_id' => 11, 'client_contact_id' => 2, 'status' => 'confirme',
            'start_date' => '2026-11-01', 'end_date' => '2026-12-31', 'total_amount' => 500000,
        ]);
        DB::table('reservation_panels')->insert([
            ['reservation_id' => 100, 'panel_id' => 1, 'total_price' => 300000],
            ['reservation_id' => 100, 'panel_id' => 2, 'total_price' => 200000],
        ]);
        DB::table('campaigns')->insert([
            ['id' => 200, 'name' => 'ADDOHA fin d\'année', 'client_id' => 10, 'client_contact_id' => 1, 'status' => 'actif',
             'start_date' => '2026-11-01', 'end_date' => '2026-12-31', 'total_amount' => 500000, 'deleted_at' => null],
            ['id' => 201, 'name' => 'Ancienne', 'client_id' => 11, 'client_contact_id' => 3, 'status' => 'annule',
             'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'total_amount' => 0, 'deleted_at' => '2026-02-01 00:00:00'],
        ]);
        DB::table('campaign_panels')->insert([
            ['campaign_id' => 200, 'panel_id' => 1], ['campaign_id' => 200, 'panel_id' => 2],
        ]);
        DB::table('quotes')->insert(['reference' => 'DEV-1', 'client_id' => 11, 'client_contact_id' => 2, 'deleted_at' => '2026-07-01 00:00:00']);
        DB::table('invoices')->insert(['reference' => 'FAC-BROUILLON', 'client_id' => 11, 'status' => 'brouillon']);
    }

    private function service(): RegroupementClients
    {
        return app(RegroupementClients::class);
    }

    private function fiches(): array
    {
        return [Client::findOrFail(11), Client::findOrFail(10)]; // [à retirer, à garder]
    }

    public function test_apercu_annonce_tout_sans_rien_modifier(): void
    {
        $a = $this->service()->apercu(...$this->fiches());

        $this->assertSame([], $a['refus']);
        $compteurs = collect($a['compteurs'])->pluck('nombre', 'table')->all();
        $this->assertSame(['campaigns' => 1, 'reservations' => 1, 'invoices' => 1, 'quotes' => 1, 'client_contacts' => 3], $compteurs);
        $this->assertSame(['deplaces' => 2, 'fusionnes' => ['Kali bis'], 'decoches' => 1], $a['contacts']);
        $this->assertSame([], $a['factures_emises']);

        $champs = collect($a['champs'])->keyBy('champ');
        $this->assertSame('complete', $champs['ncc']['action']);   // NCC auto-généré = vide
        $this->assertSame('complete', $champs['email']['action']);
        $this->assertSame('complete', $champs['user_id']['action']);
        $this->assertSame('Commerciale', $champs['user_id']['retire']);
        $this->assertSame('conserve', $champs['contact_name']['action']);

        $this->assertCount(1, $a['liaisons']);
        $this->assertTrue($a['liaisons'][0]['possible'], implode(' ; ', $a['liaisons'][0]['raisons']));

        // Rien n'a bougé.
        $this->assertSame(2, Client::count());
        $this->assertSame(11, (int) DB::table('reservations')->where('id', 100)->value('client_id'));
    }

    public function test_regrouper_deplace_tout_corbeille_comprise(): void
    {
        [$retirer, $garder] = $this->fiches();
        $journal = $this->service()->regrouper($retirer, $garder, null, [[100, 200]]);

        foreach (array_keys(RegroupementClients::TABLES) as $table) {
            $this->assertSame(0, DB::table($table)->where('client_id', 11)->count(), $table);
        }
        $this->assertSame(10, (int) DB::table('campaigns')->where('id', 201)->value('client_id'));   // à la corbeille aussi
        $this->assertSame(10, (int) DB::table('quotes')->where('reference', 'DEV-1')->value('client_id'));
        $this->assertSame(100, (int) DB::table('campaigns')->where('id', 200)->value('reservation_id'));

        // L'ancienne fiche : à la corbeille, NCC libéré.
        $ancienne = Client::withTrashed()->find(11);
        $this->assertTrue($ancienne->trashed());
        $this->assertNull($ancienne->ncc);

        // La fiche gardée : ses valeurs restent, ses vides sont complétés.
        $g = Client::find(10);
        $this->assertSame('CI-123', $g->ncc);
        $this->assertSame('contact@addoha.ci', $g->email);
        $this->assertSame('KALI', $g->contact_name);
        $this->assertSame('RC-1', $g->rccm);
        $this->assertSame(2, (int) $g->user_id);
        $this->assertSame('2026-05-01', $g->created_at->toDateString());

        // Interlocuteurs.
        $ct = fn (int $id) => DB::table('client_contacts')->find($id);
        $this->assertSame([10, 0, 1], [(int) $ct(2)->client_id, (int) $ct(2)->is_primary, (int) $ct(2)->receives_notifications]); // Franck : interlocuteur de R1
        $this->assertSame([10, 0], [(int) $ct(4)->client_id, (int) $ct(4)->receives_notifications]);                          // Ancien : décoché
        $this->assertNotNull($ct(3)->deleted_at);                                                                                // Kali bis fusionnée…
        $this->assertSame(1, (int) DB::table('campaigns')->where('id', 201)->value('client_contact_id'));                     // … ses dossiers vont à Kali
        $this->assertSame('0102030405', $ct(1)->phone);                                                                          // … son téléphone complète Kali
        $this->assertSame(1, DB::table('client_contacts')->where('client_id', 10)->whereNull('deleted_at')->where('is_primary', true)->count());

        // Journal et nom.
        $this->assertSame([11, 'ADDOHA', 10], [$journal->ancien_client_id, $journal->ancien_nom, $journal->client_id]);
        $this->assertSame(10, ClientRegroupement::ficheGardee(11)?->id);
        $this->assertSame(10, app(NomEntreprise::class)->identique('addoha'));
    }

    public function test_ancien_nom_et_ancienne_adresse_menent_a_la_fiche_gardee(): void
    {
        DB::table('clients')->insert([
            ['id' => 12, 'name' => 'BGFI BANK', 'email' => 'info@bgfi.ci'],
            ['id' => 13, 'name' => 'BGFI', 'email' => 'contact@bgfi.ci'],
        ]);
        $this->service()->regrouper(Client::find(12), Client::find(13), null);

        $noms = app(NomEntreprise::class);
        $this->assertSame(13, $noms->identique('Bgfi-Bank'));
        $this->assertNull($noms->identique('Bgfi-Bank', 13)); // renommer BGFI en « BGFI BANK » reste permis
        $this->assertSame('ancien nom de cette fiche (regroupée)', $noms->verifier('BGFI BANK')['identique']['raison']);

        // L'adresse de l'ancienne fiche devient un interlocuteur, décoché.
        $ct = DB::table('client_contacts')->where('client_id', 13)->where('email', 'info@bgfi.ci')->first();
        $this->assertNotNull($ct);
        $this->assertSame(0, (int) $ct->receives_notifications);
    }

    public function test_prospect_et_client_donnent_client(): void
    {
        DB::table('clients')->where('id', 10)->update(['statut_commercial' => 'prospect']);
        DB::table('client_contacts')->where('id', 1)->update(['statut' => 'prospect']);
        $this->service()->regrouper(...$this->fiches());

        $this->assertSame('client', Client::find(10)->statut_commercial);
        $this->assertSame('client', DB::table('client_contacts')->where('id', 2)->value('statut'));
        $this->assertSame('client', DB::table('client_contacts')->where('id', 1)->value('statut')); // Kali, déjà sur la fiche gardée
    }

    public function test_refus(): void
    {
        $refus = fn () => $this->service()->refus(...$this->fiches());

        $this->assertSame(['Choisissez deux fiches différentes.'], $this->service()->refus(Client::find(10), Client::find(10)));

        DB::table('client_users')->insert(['client_id' => 11]);
        $this->assertStringContainsString("accès à l'espace client", implode(' ', $refus()));
        DB::table('client_users')->delete();

        DB::table('clients')->where('id', 11)->update(['password' => 'x']);
        $this->assertNotEmpty($refus());
        DB::table('clients')->where('id', 11)->update(['password' => null]);

        // NCC différents (deux vrais NCC) et facture émise : deux sociétés.
        DB::table('clients')->where('id', 10)->update(['ncc' => 'CI-999']);
        $this->assertSame([], $refus()); // pas encore de facture émise
        DB::table('invoices')->update(['status' => 'envoyee']);
        $this->assertStringContainsString('NCC différents', implode(' ', $refus()));

        // Une table inconnue porte un client_id : on bloque.
        DB::table('clients')->where('id', 10)->update(['ncc' => null]);
        Schema::create('table_inconnue', fn (Blueprint $t) => $t->unsignedBigInteger('client_id'));
        $this->assertStringContainsString('table_inconnue', implode(' ', $refus()));
    }

    public function test_facture_emise_exige_j_ai_compris(): void
    {
        DB::table('invoices')->update(['status' => 'payee']);
        $this->assertSame(['FAC-BROUILLON'], $this->service()->apercu(...$this->fiches())['factures_emises']);

        try {
            $this->service()->regrouper(...$this->fiches());
            $this->fail('Regroupement accepté sans « J\'ai compris ».');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('factures_comprises', $e->errors());
        }
        $this->assertFalse(Client::find(11)->trashed());

        $this->service()->regrouper(...[...$this->fiches(), null, [], true]);
        $this->assertTrue(Client::withTrashed()->find(11)->trashed());
    }

    public function test_relier_refuse_si_quelque_chose_se_declencherait(): void
    {
        $raisons = function () {
            $l = $this->service()->liaisons(...$this->fiches())[0];

            return [$l['possible'], implode(' ; ', $l['raisons'])];
        };

        // Planifiée, début passé : Panora la lancerait la nuit suivante.
        DB::table('campaigns')->where('id', 200)->update(['status' => 'planifie', 'start_date' => '2026-01-01']);
        DB::table('reservations')->where('id', 100)->update(['start_date' => '2026-01-01']);
        [$possible, $texte] = $raisons();
        $this->assertFalse($possible);
        $this->assertStringContainsString('lancez-la', $texte);

        // Réservation en option, montant différent.
        DB::table('campaigns')->where('id', 200)->update(['status' => 'actif', 'total_amount' => 450000]);
        DB::table('reservations')->where('id', 100)->update(['status' => 'en_attente']);
        [, $texte] = $raisons();
        $this->assertStringContainsString("n'est pas confirmée", $texte);
        $this->assertStringContainsString('montants différents', $texte);

        // Demander quand même : refusé, rien n'a bougé.
        try {
            $this->service()->regrouper(...[...$this->fiches(), null, [[100, 200]]]);
            $this->fail('Liaison impossible acceptée.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('liaisons', $e->errors());
        }
        $this->assertSame(11, (int) DB::table('reservations')->where('id', 100)->value('client_id'));
        $this->assertNull(DB::table('campaigns')->where('id', 200)->value('reservation_id'));
    }
}
