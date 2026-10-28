<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\EnvoiContactsGmail;
use App\Services\Contacts\ContactsGmailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Bouton « Envoyer à Panora » du Google Sheets (2026-10-28) : accès par
 * jeton et liste d'autorisés, puis règle de rattachement ligne par ligne.
 *
 * Base réduite (les migrations du projet ne passent pas sous SQLite, cf.
 * TECHNICAL_DEBT.md) + les vraies migrations du statut et du journal.
 * Données : ORANGE CI (client, domaine orange.ci, Awa Koné) et MTN CI
 * (prospect, fiche info@mtn.ci).
 */
class ContactsGmailTest extends TestCase
{
    private const JETON = 'jeton-de-test-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('ncc')->nullable(); $t->string('sector')->nullable();
            $t->string('contact_name')->nullable(); $t->string('email')->nullable(); $t->string('phone')->nullable();
            $t->text('address')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('client_contacts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->string('name'); $t->string('email')->nullable();
            $t->string('phone')->nullable(); $t->string('role')->default('autre'); $t->string('position')->nullable();
            $t->boolean('is_primary')->default(false); $t->boolean('receives_notifications')->default(true);
            $t->text('notes')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_28_120000_add_statut_commercial_to_clients.php'))->up();
        (require base_path('database/migrations/2026_10_28_160000_create_envois_contacts_gmail_table.php'))->up();

        config([
            'contacts.gmail.token'     => self::JETON,
            'contacts.gmail.autorises' => ['studio@cible-ci.com'],
        ]);

        DB::table('clients')->insert([
            ['id' => 10, 'name' => 'ORANGE CI', 'email' => 'contact@orange.ci', 'sector' => null, 'statut_commercial' => 'client'],
            ['id' => 20, 'name' => 'MTN CI', 'email' => 'info@mtn.ci', 'sector' => 'Télécommunications', 'statut_commercial' => 'prospect'],
        ]);
        DB::table('client_contacts')->insert([
            'client_id' => 10, 'name' => 'Awa Koné', 'email' => 'awa@orange.ci', 'phone' => null, 'position' => 'Directrice marketing', 'statut' => 'client',
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);
    }

    /** @param list<array<string, mixed>> $contacts */
    private function envoyer(array $contacts, bool $simulation = false, string $par = 'studio@cible-ci.com'): \Illuminate\Testing\TestResponse
    {
        return $this->withToken(self::JETON)->postJson('/api/contacts-gmail/envoyer', [
            'envoye_par' => $par, 'simulation' => $simulation, 'contacts' => $contacts,
        ]);
    }

    /** @return array<string, mixed> résultat de la seule ligne envoyée */
    private function une(array $contact): array
    {
        return $this->envoyer([$contact + ['relation' => 'Client']])->assertOk()->json('lignes.0');
    }

    // ── Accès ─────────────────────────────────────────────────────

    public function test_sans_jeton_ou_jeton_faux_la_porte_n_existe_pas(): void
    {
        $this->postJson('/api/contacts-gmail/envoyer', ['envoye_par' => 'studio@cible-ci.com', 'contacts' => [['email' => 'x@y.ci']]])->assertNotFound();
        $this->withToken('faux')->getJson('/api/contacts-gmail/listes')->assertNotFound();

        config(['contacts.gmail.token' => null]); // porte fermée tant que Coolify n'a pas le jeton
        $this->withToken('')->getJson('/api/contacts-gmail/listes')->assertNotFound();
        $this->assertSame(0, EnvoiContactsGmail::count());
    }

    public function test_une_personne_non_autorisee_ne_peut_pas_envoyer(): void
    {
        $this->envoyer([['email' => 'jean@orange.ci', 'relation' => 'Client']], par: 'intrus@gmail.com')
            ->assertForbidden()->assertJsonPath('ok', false);
        $this->assertSame(1, ClientContact::count());
    }

    public function test_listes_reprises_par_le_sheets(): void
    {
        $r = $this->withToken(self::JETON)->getJson('/api/contacts-gmail/listes')->assertOk();

        $this->assertSame(Client::SECTORS, $r->json('secteurs'));
        $this->assertContains('gmail.com', $r->json('messageries_personnelles'));
        $this->assertSame(['studio@cible-ci.com'], $r->json('autorises'));
    }

    public function test_lot_vide_ou_trop_gros_refuse(): void
    {
        $this->envoyer([])->assertStatus(422);
        config(['contacts.gmail.max_lignes' => 2]);
        $this->envoyer(array_fill(0, 3, ['email' => 'a@b.ci', 'relation' => 'Client']))->assertStatus(422);
    }

    // ── Rattachement d'une nouvelle adresse ───────────────────────

    public function test_nouvelle_adresse_rattachee_par_le_domaine(): void
    {
        $r = $this->une(['email' => 'Jean@Orange.ci', 'nom' => 'Jean Kouassi', 'poste' => 'Chef de produit', 'telephones' => '07 01 02 03 04 / 05 00 00 00', 'entreprise' => 'Orange Money']);

        $this->assertSame('cree', $r['resultat']);
        $this->assertSame(10, $r['client_id']);
        $this->assertStringContainsString('nom dans le Sheets : ORANGE MONEY', $r['message']);
        $ct = ClientContact::where('email', 'jean@orange.ci')->firstOrFail();
        $this->assertSame(10, $ct->client_id);
        $this->assertSame('07 01 02 03 04', $ct->phone);
        $this->assertSame('client', $ct->statut);
        $this->assertTrue($ct->receives_notifications);
        $this->assertSame('gmail', $ct->source);
        $this->assertFalse($ct->is_primary);
    }

    public function test_sans_domaine_connu_rattachee_par_le_nom_exact(): void
    {
        $r = $this->une(['email' => 'ali@mtn-group.com', 'nom' => 'Ali', 'entreprise' => ' mtn  ci ']);

        $this->assertSame('cree', $r['resultat']);
        $this->assertSame(20, $r['client_id']);
    }

    public function test_entreprise_inconnue_creee_au_statut_du_contact_sans_ncc(): void
    {
        $r = $this->une(['email' => 'fatou@sucaf.ci', 'nom' => 'Fatou', 'entreprise' => 'Sucaf', 'secteur' => 'Agro-industrie', 'relation' => 'Prospect']);

        $this->assertSame('cree', $r['resultat']);
        $client = Client::where('name', 'SUCAF')->firstOrFail();
        $this->assertSame('prospect', $client->statut_commercial);
        $this->assertNull($client->ncc);
        $this->assertNull($client->email); // pas d'adresse principale
        $ct = ClientContact::where('email', 'fatou@sucaf.ci')->firstOrFail();
        $this->assertSame('prospect', $ct->statut);
        $this->assertFalse($ct->receives_notifications);
        $this->assertSame(1, EnvoiContactsGmail::firstOrFail()->entreprises_creees);
    }

    public function test_secteur_inconnu_de_panora_signale_sans_bloquer(): void
    {
        $r = $this->une(['email' => 'k@nouvelle.ci', 'entreprise' => 'Nouvelle', 'secteur' => 'Spatial']);

        $this->assertSame('cree', $r['resultat']);
        $this->assertNull(Client::where('name', 'NOUVELLE')->value('sector'));
        $this->assertStringContainsString('secteur inconnu de Panora', $r['message']);
        $this->assertSame('k', ClientContact::where('email', 'k@nouvelle.ci')->value('name')); // nom vide : début de l'adresse
    }

    public function test_deux_contacts_d_une_nouvelle_entreprise_dans_le_meme_envoi(): void
    {
        $lignes = $this->envoyer([
            ['email' => 'a@cie.ci', 'nom' => 'A', 'entreprise' => 'CIE', 'relation' => 'Client'],
            ['email' => 'b@cie.ci', 'nom' => 'B', 'entreprise' => 'Compagnie Ivoirienne', 'relation' => 'Client'],
        ])->json('lignes');

        $this->assertSame(['cree', 'cree'], array_column($lignes, 'resultat'));
        $this->assertSame(1, Client::where('name', 'CIE')->count());
        $this->assertSame(0, Client::where('name', 'COMPAGNIE IVOIRIENNE')->count()); // le domaine prime
        $this->assertSame(2, ClientContact::where('client_id', $lignes[0]['client_id'])->count());
    }

    public function test_messagerie_personnelle_par_le_nom_seulement_sans_nom_refusee(): void
    {
        DB::table('client_contacts')->insert(['client_id' => 20, 'name' => 'X', 'email' => 'x@gmail.com']);

        // Gmail ne désigne pas MTN, même si un contact MTN a une adresse Gmail.
        $r = $this->une(['email' => 'perso@gmail.com', 'nom' => 'Perso']);
        $this->assertSame('refuse', $r['resultat']);
        $this->assertSame("Renseigner l'entreprise.", $r['message']);

        $r = $this->une(['email' => 'perso@gmail.com', 'nom' => 'Perso', 'entreprise' => 'ORANGE CI']);
        $this->assertSame('cree', $r['resultat']);
        $this->assertSame(10, $r['client_id']);
    }

    public function test_domaine_partage_par_deux_entreprises_refuse_sauf_si_le_nom_tranche(): void
    {
        DB::table('clients')->insert(['id' => 30, 'name' => 'ORANGE BUSINESS', 'email' => 'pro@orange.ci', 'statut_commercial' => 'client']);

        $r = $this->une(['email' => 'paul@orange.ci', 'entreprise' => 'Orange']);
        $this->assertSame('refuse', $r['resultat']);
        $this->assertStringContainsString('ORANGE BUSINESS, ORANGE CI', $r['message']);

        $r = $this->une(['email' => 'paul@orange.ci', 'entreprise' => 'Orange Business']);
        $this->assertSame('cree', $r['resultat']);
        $this->assertSame(30, $r['client_id']);
    }

    public function test_adresse_interne_statut_et_adresse_invalides_refuses(): void
    {
        $lignes = $this->envoyer([
            ['email' => 'collegue@cible-ci.com', 'entreprise' => 'CIBLE', 'relation' => 'Client'],
            ['email' => 'jean@orange.ci', 'relation' => 'À vérifier'],
            ['email' => 'pas-une-adresse', 'relation' => 'Client'],
        ])->json('lignes');

        $this->assertSame(['refuse', 'refuse', 'refuse'], array_column($lignes, 'resultat'));
        $this->assertSame(1, ClientContact::count());
    }

    // ── Adresse déjà connue ───────────────────────────────────────

    public function test_contact_connu_jamais_duplique_seuls_les_champs_vides_completes(): void
    {
        $r = $this->une(['email' => 'AWA@orange.ci', 'nom' => 'Awa K.', 'poste' => 'Autre poste', 'telephones' => '07 99 99 99 99', 'secteur' => 'Télécommunications']);

        $this->assertSame('complete', $r['resultat']);
        $this->assertSame(1, ClientContact::where('email', 'awa@orange.ci')->count());
        $ct = ClientContact::where('email', 'awa@orange.ci')->firstOrFail();
        $this->assertSame('Awa Koné', $ct->name);                 // saisie Panora gardée
        $this->assertSame('Directrice marketing', $ct->position);  // saisie Panora gardée
        $this->assertSame('07 99 99 99 99', $ct->phone);           // était vide
        $this->assertNull($ct->source);
        $this->assertSame('Télécommunications', Client::find(10)->sector); // était vide

        // Renvoyé tel quel : rien à faire.
        $this->assertSame('a_jour', $this->une(['email' => 'awa@orange.ci', 'telephones' => '01 00 00 00'])['resultat']);
    }

    public function test_adresse_de_la_fiche_reconnue(): void
    {
        $r = $this->une(['email' => 'info@mtn.ci', 'nom' => 'Accueil MTN', 'relation' => 'Prospect']);

        $this->assertSame('complete', $r['resultat']);
        $this->assertSame('Accueil MTN', Client::find(20)->contact_name);
        $this->assertSame(0, ClientContact::where('email', 'info@mtn.ci')->count());
    }

    public function test_supprime_dans_panora_ne_revient_pas(): void
    {
        ClientContact::where('email', 'awa@orange.ci')->delete();
        $this->assertSame('refuse', $this->une(['email' => 'awa@orange.ci'])['resultat']);

        Client::find(20)->delete();
        $r = $this->une(['email' => 'nouveau@mtn.ci', 'entreprise' => 'MTN CI']);
        $this->assertSame('refuse', $r['resultat']);
        $this->assertStringContainsString('supprimée dans Panora', $r['message']);
        $this->assertSame(0, Client::where('name', 'MTN CI')->count());
    }

    // ── Statuts ───────────────────────────────────────────────────

    public function test_un_contact_client_fait_passer_cliente_une_entreprise_prospect_elle_seule(): void
    {
        DB::table('client_contacts')->insert(['client_id' => 20, 'name' => 'Ali', 'email' => 'ali@mtn.ci', 'statut' => 'prospect', 'receives_notifications' => false]);

        $r = $this->une(['email' => 'fatou@mtn.ci', 'nom' => 'Fatou', 'relation' => 'Client']);

        $this->assertStringContainsString('entreprise passée cliente', $r['message']);
        $this->assertSame('client', Client::find(20)->statut_commercial);
        $this->assertSame('prospect', ClientContact::where('email', 'ali@mtn.ci')->value('statut')); // les autres ne bougent pas
    }

    public function test_un_contact_prospect_ne_fait_jamais_redescendre_un_client(): void
    {
        $this->une(['email' => 'awa@orange.ci', 'relation' => 'Prospect']);
        $this->une(['email' => 'neuf@orange.ci', 'relation' => 'Prospect']);

        $this->assertSame('client', Client::find(10)->statut_commercial);
        $this->assertSame('client', ClientContact::where('email', 'awa@orange.ci')->value('statut'));
        $this->assertSame('prospect', ClientContact::where('email', 'neuf@orange.ci')->value('statut'));
    }

    public function test_un_contact_prospect_devenu_client_dans_le_sheets_recoit_les_mails(): void
    {
        DB::table('client_contacts')->insert(['client_id' => 10, 'name' => 'Paul', 'email' => 'paul@orange.ci', 'statut' => 'prospect', 'receives_notifications' => false]);

        $this->assertSame('complete', $this->une(['email' => 'paul@orange.ci', 'relation' => 'Client'])['resultat']);
        $ct = ClientContact::where('email', 'paul@orange.ci')->firstOrFail();
        $this->assertSame('client', $ct->statut);
        $this->assertTrue($ct->receives_notifications);
    }

    // ── Vérification et journal ───────────────────────────────────

    public function test_verification_sans_ecriture_mais_notee_au_journal(): void
    {
        $r = $this->envoyer([
            ['email' => 'fatou@sucaf.ci', 'entreprise' => 'Sucaf', 'relation' => 'Client'],
            ['email' => 'awa@orange.ci', 'telephones' => '07', 'relation' => 'Client'],
        ], simulation: true)->assertOk();

        $this->assertTrue($r->json('simulation'));
        $this->assertSame(['cree', 'complete'], array_column($r->json('lignes'), 'resultat'));
        $this->assertNull($r->json('lignes.0.client_id')); // fiche annulée avec la vérification
        $this->assertSame(0, Client::where('name', 'SUCAF')->count());
        $this->assertNull(ClientContact::where('email', 'awa@orange.ci')->value('phone'));

        $envoi = EnvoiContactsGmail::firstOrFail();
        $this->assertTrue($envoi->simulation);
        $this->assertSame(['lignes' => 2, 'crees' => 1, 'completes' => 1, 'refuses' => 0], [
            'lignes' => $envoi->lignes, 'crees' => $envoi->crees, 'completes' => $envoi->completes, 'refuses' => $envoi->refuses,
        ]);
    }

    public function test_journal_d_un_envoi_et_numero_de_ligne_du_sheets(): void
    {
        $r = $this->envoyer([['ligne' => 42, 'email' => 'jean@orange.ci', 'nom' => 'Jean', 'relation' => 'Client']])->assertOk();

        $this->assertSame(42, $r->json('lignes.0.ligne'));
        $envoi = EnvoiContactsGmail::firstOrFail();
        $this->assertFalse($envoi->simulation);
        $this->assertSame('studio@cible-ci.com', $envoi->envoye_par);
        $this->assertSame('jean@orange.ci', $envoi->detail[0]['email']);
    }

    public function test_service_utilisable_sans_http(): void
    {
        $res = app(ContactsGmailService::class)->traiter([['email' => 'jean@orange.ci', 'relation' => 'client']], 'studio@cible-ci.com');

        $this->assertSame(1, $res['resume']['crees']);
    }
}
