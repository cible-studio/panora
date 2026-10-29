<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\CampaignController;
use App\Imports\ClientsImport;
use App\Models\Client;
use App\Models\ClientContact;
use App\Rules\NomEntrepriseLibre;
use App\Support\NomEntreprise;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Nom d'entreprise unique (règle de la direction, 2026-10-29) :
 *   · refus du même nom, même écrit autrement (« Coris-Bank » = « CORIS BANK ») ;
 *   · alerte quand il ressemble à une fiche, sauf « C'est une autre entreprise » ;
 *   · « Utiliser cette fiche » dans la création rapide ;
 *   · import Excel : ligne ignorée ; Sheet : fiche reconnue ;
 *   · garde-fou : une campagne reste sur le client de sa réservation.
 *
 * Base : ORANGE CI (10), MOOV (30, 1 campagne), ASSEKE ORO (31), CORIS BANK (32).
 */
class NomEntrepriseTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();
        foreach (['ncc', 'sector', 'address', 'user_id'] as $col) {
            if (!Schema::hasColumn('clients', $col)) {
                Schema::table('clients', fn ($t) => $t->string($col)->nullable());
            }
        }
        foreach (['client_id', 'reference'] as $col) {
            if (!Schema::hasColumn('reservations', $col)) {
                Schema::table('reservations', fn ($t) => $t->string($col)->nullable());
            }
        }
        DB::table('clients')->insert([
            ['id' => 30, 'name' => 'MOOV'],
            ['id' => 31, 'name' => 'ASSEKE ORO'],
            ['id' => 32, 'name' => 'CORIS BANK'],
        ]);
        DB::table('campaigns')->insert(['name' => 'Lancement', 'client_id' => 30, 'status' => 'actif']);
        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);
    }

    private function noms(): NomEntreprise
    {
        return app(NomEntreprise::class);
    }

    // ── La règle ──────────────────────────────────────────────────

    public function test_meme_nom_ecrit_autrement(): void
    {
        $this->assertSame('CORISBANK', NomEntreprise::cle('Coris-Bank'));
        $this->assertSame(NomEntreprise::cle('CORIS BANK'), NomEntreprise::cle(' coris.bank '));
        $this->assertSame(NomEntreprise::cle("CÔTE D'IVOIRE"), NomEntreprise::cle('Cote divoire'));
        $this->assertNotSame(NomEntreprise::cle('MOOV'), NomEntreprise::cle('MOOV AFRICA'));
    }

    public function test_ce_qui_ressemble_et_ce_qui_ne_ressemble_pas(): void
    {
        $r = fn (string $a, string $b) => NomEntreprise::ressemblance(NomEntreprise::empreinte($a), NomEntreprise::empreinte($b));

        $this->assertSame(NomEntreprise::MEME_RACINE, $r('MOOV AFRICA', 'MOOV'));
        $this->assertSame(NomEntreprise::MEME_RACINE, $r('ADDOHA SA', 'Addoha Côte d\'Ivoire'));
        $this->assertSame(NomEntreprise::CONTENU, $r('ORO', 'ASSEKE ORO'));
        $this->assertSame(NomEntreprise::CONTENU, $r('ATHOME', 'ATHOME360'));
        $this->assertNotNull($r('VISUELCONCEPTS', 'VISUEL CONCEPT'));
        $this->assertSame(NomEntreprise::ORTHOGRAPHE, $r('KOM-NAYAH', 'KOUMNAYAH'));

        foreach ([['ORANGE CI', 'MTN CI'], ['BNI', 'BGFI'], ['DENTSU', 'DAIKIN'], ['SUNU ASSURANCE', 'AXA']] as [$a, $b]) {
            $this->assertNull($r($a, $b), "$a / $b");
        }
    }

    public function test_verification_dans_les_fiches(): void
    {
        $v = $this->noms()->verifier('Coris-Bank');
        $this->assertSame(32, $v['identique']['id']);
        $this->assertSame([], $v['proches']);

        $v = $this->noms()->verifier('Moov Africa');
        $this->assertNull($v['identique']);
        $this->assertSame([[30, 'MOOV', 1]], array_map(fn ($p) => [$p['id'], $p['name'], $p['campagnes']], $v['proches']));

        $this->assertSame(['identique' => null, 'proches' => []], $this->noms()->verifier('SOGELEC'));

        // Fiche supprimée : ne compte plus. Fiche renommée : ne se compare pas à elle-même.
        Client::findOrFail(32)->delete();
        $this->assertNull($this->noms()->verifier('Coris-Bank')['identique']);
        $this->assertNull($this->noms()->identique('MOOV', 30));
    }

    public function test_la_liste_suit_les_fiches_creees_et_renommees(): void
    {
        $this->assertNull($this->noms()->identique('SOGELEC')); // liste lue

        $c = Client::create(['name' => 'Sogelec']);
        $this->assertSame($c->id, $this->noms()->identique('SOGELEC'));

        $c->update(['name' => 'SOGELEC INDUSTRIE']);
        $this->assertNull($this->noms()->identique('SOGELEC'));
        $this->assertSame($c->id, $this->noms()->identique('Sogelec-Industrie'));
    }

    public function test_regle_de_validation(): void
    {
        $valide = fn (string $nom, ...$args) => Validator::make(['name' => $nom], ['name' => [new NomEntrepriseLibre(...$args)]]);

        $v = $valide('Coris-Bank');
        $this->assertTrue($v->fails());
        $this->assertSame('« CORIS BANK » existe déjà dans Panora : utilisez cette fiche.', $v->errors()->first('name'));

        // Nom proche : bloqué tant que « C'est une autre entreprise » n'est pas coché.
        $this->assertStringStartsWith('Ça ressemble à MOOV (1 campagne).', $valide('MOOV AFRICA')->errors()->first('name'));
        $this->assertFalse($valide('MOOV AFRICA', null, true)->fails());

        // Fiche renommée sans changer de nom : rien à vérifier, même si un doublon existe déjà.
        DB::table('clients')->insert(['id' => 33, 'name' => 'MOOV']);
        $this->assertFalse($valide('Moov', 33, false, 'MOOV')->fails());
        $this->assertTrue($valide('CORIS BANK', 33, false, 'MOOV')->fails());
    }

    // ── Création rapide (fenêtres réservation et campagne) ────────

    public function test_creation_rapide_refuse_le_meme_nom_et_propose_la_fiche(): void
    {
        $this->actingAs(\App\Models\User::findOrFail(2));

        $r = $this->postJson('/admin/clients/quick-store', ['name' => 'coris bank'])->assertStatus(422);
        $this->assertSame(32, $r->json('nom.identique.id'));
        $this->assertSame(1, Client::where('name', 'CORIS BANK')->count());

        $r = $this->postJson('/admin/clients/quick-store', ['name' => 'MOOV AFRICA'])->assertStatus(422);
        $this->assertSame([30], array_column($r->json('nom.proches'), 'id'));

        $this->postJson('/admin/clients/quick-store', ['name' => 'MOOV AFRICA', 'nom_proche_confirme' => true])->assertOk();
        $this->assertSame(1, Client::where('name', 'MOOV AFRICA')->count());
    }

    public function test_utiliser_cette_fiche_reprend_la_fiche_avec_l_interlocuteur(): void
    {
        $this->actingAs(\App\Models\User::findOrFail(2));
        $avant = Client::count();

        $r = $this->postJson('/admin/clients/quick-store', [
            'client_existant_id' => 32, 'contact_name' => 'Ibrahim Traoré', 'email' => 'i.traore@corisbank.ci',
        ])->assertOk();

        $this->assertSame([32, 'CORIS BANK', true], [$r->json('client.id'), $r->json('client.name'), $r->json('existant')]);
        $this->assertSame($avant, Client::count());
        $this->assertSame('Ibrahim Traoré', ClientContact::findOrFail($r->json('interlocuteur.id'))->name);
        $this->assertSame(32, ClientContact::findOrFail($r->json('interlocuteur.id'))->client_id);
    }

    public function test_verification_pendant_la_saisie(): void
    {
        $this->actingAs(\App\Models\User::findOrFail(2));

        $this->getJson('/admin/clients/verifier-nom?nom=Asseke-Oro')->assertOk()->assertJsonPath('identique.id', 31);
        $this->getJson('/admin/clients/verifier-nom?nom=ORO')->assertOk()->assertJsonPath('proches.0.name', 'ASSEKE ORO');
        $this->getJson('/admin/clients/verifier-nom?nom=MOOV&sauf=30')->assertOk()->assertJsonPath('identique', null);
    }

    // ── Import Excel ──────────────────────────────────────────────

    public function test_import_excel_ignore_un_nom_deja_pris(): void
    {
        $import = new ClientsImport();

        $this->assertNull($import->model(['nom' => 'Coris-Bank']));
        $this->assertNull($import->model(['nom' => 'Awa', 'entreprise' => 'moov']));

        $nouvelle = $import->model(['nom' => 'Sogelec', 'statut' => 'prospect']);
        $this->assertSame('SOGELEC', $nouvelle?->name);
        // Même nom deux fois dans le fichier : la seconde ligne est ignorée.
        $this->assertNull($import->model(['nom' => 'SOGELEC ', 'statut' => 'prospect']));

        $this->assertSame([1, 3], [$import->imported, $import->skipped]);
    }

    // ── Garde-fou campagne / réservation ──────────────────────────

    public function test_une_campagne_reste_sur_le_client_de_sa_reservation(): void
    {
        DB::table('reservations')->insert(['id' => 7, 'client_id' => 31, 'reference' => 'RES-0007']);
        $controle = new \ReflectionMethod(CampaignController::class, 'clientDifferentDeLaReservation');
        $controleur = app(CampaignController::class);

        $this->assertNull($controle->invoke($controleur, 7, 31));
        $this->assertSame(
            'La réservation RES-0007 est au nom de « ASSEKE ORO » : la campagne doit être sur le même client.',
            $controle->invoke($controleur, 7, 30),
        );
    }
}
