<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientContact;
use App\Observers\CampaignObserver;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use App\Services\Diffusion\RenouvellementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Clients et prospects, interlocuteurs sans contact principal (2026-10-28) :
 * à qui partent les mails de campagne et la diffusion des disponibilités,
 * et passage prospect → client à la première campagne.
 *
 * Base de référence : ORANGE CI (client 10) avec Awa Koné.
 */
class ClientStatutCommercialTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();

        // Un second interlocuteur client, un interlocuteur prospect, et une
        // entreprise prospect avec son contact.
        DB::table('client_contacts')->insert([
            ['client_id' => 10, 'name' => 'Jean Kouassi', 'email' => ' JEAN@orange.ci ', 'statut' => 'client', 'receives_notifications' => false],
            ['client_id' => 10, 'name' => 'Paul Diallo', 'email' => 'paul@orange.ci', 'statut' => 'prospect', 'receives_notifications' => false],
        ]);
        DB::table('clients')->insert(['id' => 20, 'name' => 'MTN CI', 'email' => 'info@mtn.ci', 'statut_commercial' => 'prospect']);
        DB::table('client_contacts')->insert([
            ['client_id' => 20, 'name' => 'Fatou Bamba', 'email' => 'fatou@mtn.ci', 'statut' => 'prospect', 'receives_notifications' => false],
            ['client_id' => 20, 'name' => 'Ali Touré', 'email' => 'ali@mtn.ci', 'statut' => 'prospect', 'receives_notifications' => false],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function client(int $id): Client
    {
        return Client::with('contacts')->findOrFail($id);
    }

    public function test_les_clients_existants_restent_clients(): void
    {
        $this->assertSame('client', $this->client(10)->statut_commercial);
        $this->assertFalse($this->client(10)->estProspect());
        $this->assertSame([10], Client::clients()->pluck('id')->all());
        $this->assertSame([20], Client::prospects()->pluck('id')->all());
    }

    public function test_mails_de_campagne_aux_seuls_interlocuteurs_coches(): void
    {
        $this->assertSame(
            [['email' => 'awa@orange.ci', 'contact' => 'Awa Koné']],
            $this->client(10)->destinatairesCampagne()
        );

        DB::table('client_contacts')->where('name', 'Jean Kouassi')->update(['receives_notifications' => true]);
        $this->assertSame(['awa@orange.ci', 'jean@orange.ci'], array_column($this->client(10)->destinatairesCampagne(), 'email'));
    }

    public function test_mails_de_campagne_sans_interlocuteur_coche_adresse_de_la_fiche(): void
    {
        DB::table('client_contacts')->update(['receives_notifications' => false]);

        $this->assertSame([['email' => 'contact@orange.ci', 'contact' => null]], $this->client(10)->destinatairesCampagne());

        DB::table('clients')->where('id', 10)->update(['email' => 'pas-une-adresse']);
        $this->assertSame([], $this->client(10)->destinatairesCampagne());
    }

    public function test_diffusion_a_chaque_interlocuteur_client_jamais_aux_prospects(): void
    {
        $this->assertSame(['awa@orange.ci', 'jean@orange.ci'], array_column($this->client(10)->destinatairesDiffusion(), 'email'));
        $this->assertSame([], $this->client(20)->destinatairesDiffusion());
    }

    public function test_liste_de_diffusion_des_disponibilites(): void
    {
        DB::table('clients')->insert(['id' => 30, 'name' => 'SUCAF', 'email' => 'direction@sucaf.ci']);
        DB::table('diffusion_desinscriptions')->insert(['email' => 'jean@orange.ci', 'motif' => 'desinscription']);

        /** @var Collection $liste */
        $liste = app(DiffusionDisponibilitesService::class)->destinataires();

        // Awa (Jean s'est désinscrit, Paul est prospect), la fiche SUCAF sans
        // interlocuteur ; rien de MTN, entreprise prospect.
        $this->assertSame(['awa@orange.ci', 'direction@sucaf.ci'], $liste->pluck('email')->all());
        $this->assertSame('ORANGE CI', $liste[0]['societe']);
        $this->assertSame(30, $liste[1]['client_id']);
    }

    public function test_premiere_campagne_le_prospect_et_ses_interlocuteurs_passent_clients(): void
    {
        $fatou = ClientContact::where('email', 'fatou@mtn.ci')->value('id');
        $id = DB::table('campaigns')->insertGetId([
            'name' => 'Lancement', 'client_id' => 20, 'client_contact_id' => $fatou, 'status' => 'planifie',
            'start_date' => '2026-12-01', 'end_date' => '2027-01-31',
        ]);

        CampaignObserver::convertirProspect(Campaign::findOrFail($id));

        $this->assertSame('client', Client::findOrFail(20)->statut_commercial);
        foreach (ClientContact::where('client_id', 20)->get() as $ct) {
            $this->assertSame('client', $ct->statut);
        }
        // Seul l'interlocuteur de la campagne est coché « reçoit les mails » (2026-10-29).
        $this->assertTrue((bool) ClientContact::find($fatou)->receives_notifications);
        $this->assertFalse((bool) ClientContact::where('email', 'ali@mtn.ci')->value('receives_notifications'));
        // Les autres entreprises ne bougent pas.
        $this->assertSame('prospect', ClientContact::where('email', 'paul@orange.ci')->value('statut'));
        $this->assertSame(['ali@mtn.ci', 'fatou@mtn.ci'], array_column($this->client(20)->destinatairesDiffusion(), 'email')); // par nom
    }

    public function test_fiche_passee_cliente_a_la_main_aucune_case_cochee(): void
    {
        Client::findOrFail(20)->passerClient();

        $this->assertSame('client', Client::findOrFail(20)->statut_commercial);
        $this->assertSame(['client', 'client'], ClientContact::where('client_id', 20)->orderBy('id')->pluck('statut')->all());
        $this->assertSame(0, ClientContact::where('client_id', 20)->where('receives_notifications', true)->count());
    }

    public function test_une_campagne_d_un_client_ne_change_rien(): void
    {
        $c = $this->campagne();

        CampaignObserver::convertirProspect($c);

        $this->assertFalse((bool) ClientContact::where('name', 'Jean Kouassi')->value('receives_notifications'));
        $this->assertSame('prospect', ClientContact::where('email', 'paul@orange.ci')->value('statut'));
    }

    public function test_renouvellement_part_a_chaque_interlocuteur_coche(): void
    {
        DB::table('client_contacts')->where('name', 'Jean Kouassi')->update(['receives_notifications' => true]);

        $ligne = app(RenouvellementService::class)->traiter($this->campagne());

        $this->assertSame('envoye', $ligne->statut);
        $envois = Http::recorded(fn($r) => str_ends_with($r->url(), '/smtp/email'))->map(fn($p) => $p[0]['to'][0]['email'])->all();
        $this->assertSame(['awa@orange.ci', 'jean@orange.ci'], $envois);
    }

    public function test_liste_des_renouvellements_affiche_tous_les_interlocuteurs(): void
    {
        $this->assertSame(
            ['contact' => 'Awa Koné, Jean Kouassi', 'email' => 'awa@orange.ci, jean@orange.ci'],
            RenouvellementService::contactsAffiches([
                ['email' => 'awa@orange.ci', 'contact' => 'Awa Koné'],
                ['email' => 'jean@orange.ci', 'contact' => 'Jean Kouassi'],
            ])
        );
        $this->assertSame(['contact' => null, 'email' => ''], RenouvellementService::contactsAffiches([]));
    }
}
