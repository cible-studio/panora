<?php

namespace Tests\Unit;

use App\Mail\CampaignStartedMail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Reservation;
use App\Rules\InterlocuteurDuClient;
use App\Services\CampaignService;
use App\Services\Diffusion\RenouvellementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Interlocuteur d'un dossier (2026-10-29) : un client a plusieurs
 * interlocuteurs ; la campagne, la réservation ou le devis précise lequel
 * suit le dossier.
 *   · mails de suivi : à lui seul, sinon aux contacts cochés ;
 *   · documents : à l'adresse de la fiche ET à lui ;
 *   · obligatoire dès que le client a des interlocuteurs.
 *
 * Base : ORANGE CI (client 10, fiche contact@orange.ci) avec Awa Koné (cochée).
 */
class InterlocuteurTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    private ClientContact $paul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();

        DB::table('client_contacts')->insert([
            ['client_id' => 10, 'name' => 'Jean Kouassi', 'email' => 'jean@orange.ci', 'receives_notifications' => true],
            ['client_id' => 10, 'name' => 'Paul Diallo', 'email' => 'paul@orange.ci', 'receives_notifications' => false],
        ]);
        DB::table('clients')->insert(['id' => 20, 'name' => 'MTN CI', 'email' => 'info@mtn.ci']);
        DB::table('client_contacts')->insert(['client_id' => 20, 'name' => 'Fatou Bamba', 'email' => 'fatou@mtn.ci']);
        $this->paul = ClientContact::where('email', 'paul@orange.ci')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function orange(): Client
    {
        return Client::with('contacts')->findOrFail(10);
    }

    // ── Mails de suivi ────────────────────────────────────────────

    public function test_suivi_a_l_interlocuteur_seul_meme_decoche(): void
    {
        $this->assertSame([['email' => 'paul@orange.ci', 'contact' => 'Paul Diallo']], $this->orange()->destinatairesSuivi($this->paul));
    }

    public function test_suivi_sans_interlocuteur_utilisable_aux_contacts_coches(): void
    {
        $coches = ['awa@orange.ci', 'jean@orange.ci'];
        $this->assertSame($coches, array_column($this->orange()->destinatairesSuivi(null), 'email'));

        // Interlocuteur d'un autre client : ignoré.
        $fatou = ClientContact::where('email', 'fatou@mtn.ci')->firstOrFail();
        $this->assertSame($coches, array_column($this->orange()->destinatairesSuivi($fatou), 'email'));

        // Interlocuteur supprimé depuis : ignoré.
        $this->paul->delete();
        $this->assertSame($coches, array_column($this->orange()->destinatairesSuivi($this->paul->fresh()), 'email'));
    }

    // ── Documents ─────────────────────────────────────────────────

    public function test_documents_a_la_fiche_et_a_l_interlocuteur_sans_doublon(): void
    {
        $this->assertSame(['contact@orange.ci', 'paul@orange.ci'], array_column($this->orange()->destinatairesDocuments($this->paul), 'email'));
        $this->assertSame(['contact@orange.ci'], array_column($this->orange()->destinatairesDocuments(null), 'email'));

        DB::table('clients')->where('id', 10)->update(['email' => 'PAUL@orange.ci']);
        $this->assertSame(['paul@orange.ci'], array_column($this->orange()->destinatairesDocuments($this->paul), 'email'));

        DB::table('clients')->where('id', 10)->update(['email' => null]);
        $this->assertSame(['paul@orange.ci'], array_column($this->orange()->destinatairesDocuments($this->paul), 'email'));
        $this->assertSame([], $this->orange()->destinatairesDocuments(null));
    }

    public function test_adresses_d_une_reservation(): void
    {
        // Réservation en mémoire : la table réduite de ce test n'a pas de colonne client.
        $resa = new Reservation(['client_id' => 10, 'client_contact_id' => $this->paul->id]);

        $this->assertSame(['contact@orange.ci', 'paul@orange.ci'], $resa->adressesClient());
        $this->assertSame(['contact@orange.ci'], (new Reservation(['client_id' => 10]))->adressesClient());
    }

    // ── Envois réels ──────────────────────────────────────────────

    public function test_renouvellement_part_a_l_interlocuteur_seul(): void
    {
        $c = $this->campagne(['client_contact_id' => $this->paul->id]);

        $this->assertSame('envoye', app(RenouvellementService::class)->traiter($c)->statut);
        $envois = Http::recorded(fn ($r) => str_ends_with($r->url(), '/smtp/email'))->map(fn ($p) => $p[0]['to'][0]['email'])->all();
        $this->assertSame(['paul@orange.ci'], $envois);

        $ligne = app(RenouvellementService::class)->lignes()->first();
        $this->assertSame(['contact' => 'Paul Diallo', 'email' => 'paul@orange.ci'], $ligne['contact']);
    }

    public function test_mail_de_demarrage_a_l_interlocuteur_seul(): void
    {
        $c = $this->campagne(['client_contact_id' => $this->paul->id]);

        $this->assertTrue(app(CampaignService::class)->sendStartedMailToClient($c));
        Mail::assertSent(CampaignStartedMail::class, fn ($m) => $m->hasTo('paul@orange.ci')
            && !$m->hasTo('awa@orange.ci') && !$m->hasTo('contact@orange.ci'));
    }

    public function test_mail_de_demarrage_sans_interlocuteur_aux_contacts_coches_jamais_a_tous(): void
    {
        $c = $this->campagne();

        app(CampaignService::class)->sendStartedMailToClient($c);
        Mail::assertSent(CampaignStartedMail::class, fn ($m) => $m->hasTo('awa@orange.ci') && $m->hasTo('jean@orange.ci')
            && !$m->hasTo('paul@orange.ci')); // décoché : plus de mail à tous les interlocuteurs
    }

    // ── Salutation (2026-10-29) ───────────────────────────────────

    public function test_salutation_interlocuteur_et_toujours_le_client(): void
    {
        $orange = $this->orange();
        $this->assertSame('Paul Diallo (ORANGE CI)', \App\Support\Salutation::suivi($orange, $this->paul));
        $this->assertSame('ORANGE CI', \App\Support\Salutation::suivi($orange, null));
        $fatou = ClientContact::where('email', 'fatou@mtn.ci')->firstOrFail();
        $this->assertSame('ORANGE CI', \App\Support\Salutation::suivi($orange, $fatou)); // d'un autre client
        $this->assertSame('ORANGE CI', \App\Support\Salutation::nom($orange, 'orange ci')); // pas « ORANGE CI (ORANGE CI) »
        $this->assertSame('Madame, Monsieur', \App\Support\Salutation::nom(null, null));
    }

    // ── Choix de l'interlocuteur ──────────────────────────────────

    public function test_interlocuteur_obligatoire_si_le_client_en_a(): void
    {
        $valide = fn (?int $client, $valeur) => Validator::make(
            ['client_contact_id' => $valeur],
            ['client_contact_id' => InterlocuteurDuClient::regles($client)],
            InterlocuteurDuClient::MESSAGES,
        );

        $v = $valide(10, null);
        $this->assertTrue($v->fails());
        $this->assertSame("Choisissez l'interlocuteur du client qui suit ce dossier.", $v->errors()->first('client_contact_id'));

        $this->assertFalse($valide(10, $this->paul->id)->fails());
        $fatou = ClientContact::where('email', 'fatou@mtn.ci')->value('id');
        $this->assertTrue($valide(10, $fatou)->fails()); // contact d'un autre client

        DB::table('clients')->insert(['id' => 30, 'name' => 'SANS CONTACT']);
        $this->assertFalse($valide(30, null)->fails()); // aucun interlocuteur : facultatif
    }
}
