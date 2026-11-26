<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Reservation;
use App\Services\Affaires\ChangementClientAffaire;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Changer le client d'une affaire (2026-11-16).
 *
 * Constat : campagne 122 au nom de 2A CONSULTING au lieu de 2A ÉDITIONS.
 * La campagne refusait un client différent de sa réservation, la
 * réservation refusait de changer dès qu'elle avait sa campagne : aucun
 * des deux écrans ne permettait de corriger.
 *
 * Règles validées par écrit le 2026-11-16 : facture validée ou envoyée =
 * changement impossible (avoir), brouillons suivent ; relances, renouvel-
 * lement et enquête pas encore partis suivent ; devis et proposition
 * suivent ; admin et Media Planner.
 */
class ChangementClientAffaireTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->string('status')->default('confirme'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->unsignedBigInteger('reservation_id')->nullable(); $t->string('status')->default('actif'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->string('status'); $t->timestamps();
        });
        Schema::create('quotes', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->unsignedBigInteger('converted_reservation_id')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('relances_fin_campagne', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('client_id'); $t->string('statut'); $t->timestamps();
        });
        Schema::create('renouvellements', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('client_id'); $t->string('statut'); $t->timestamps();
        });
        Schema::create('satisfaction_surveys', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('client_id'); $t->timestamp('sent_at')->nullable(); $t->timestamps();
        });
        Schema::create('external_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id')->nullable(); $t->unsignedBigInteger('campaign_id')->nullable(); $t->timestamps();
        });

        DB::table('clients')->insert([['id' => 78, 'name' => '2A CONSULTING'], ['id' => 80, 'name' => '2A ÉDITIONS']]);
        DB::table('reservations')->insert(['id' => 119, 'reference' => 'RES-ISKWZZ1R', 'client_id' => 78, 'client_contact_id' => 5]);
        DB::table('campaigns')->insert(['id' => 122, 'name' => 'Campagne 2A CONSULTING', 'client_id' => 78, 'client_contact_id' => 5, 'reservation_id' => 119]);
        DB::table('external_panels')->insert([['id' => 1, 'client_id' => 78, 'campaign_id' => 122], ['id' => 2, 'client_id' => 78, 'campaign_id' => 122]]);
    }

    private function service(): ChangementClientAffaire
    {
        return new ChangementClientAffaire();
    }

    public function test_une_facture_emise_empeche_le_changement(): void
    {
        DB::table('invoices')->insert(['reference' => 'FAC-1', 'client_id' => 78, 'campaign_id' => 122, 'status' => 'brouillon']);
        DB::table('invoices')->insert(['reference' => 'FAC-0', 'client_id' => 78, 'campaign_id' => 122, 'status' => 'annulee']);
        $this->assertNull($this->service()->empechement(Campaign::find(122)), 'brouillon et annulée n\'empêchent rien');

        DB::table('invoices')->insert(['reference' => 'FAC-2', 'client_id' => 78, 'campaign_id' => 122, 'status' => 'validee']);
        $message = $this->service()->empechement(null, Reservation::find(119));
        $this->assertStringContainsString('FAC-2', $message, 'vu depuis la réservation aussi');
        // 2026-11-26 (point 18) : l'avoir n'existe pas dans Panora, le message
        // renvoie vers l'admin pour corriger une facture envoyée.
        $this->assertStringContainsString('admin', $message);
        $this->assertStringNotContainsString('avoir', $message);
    }

    public function test_depuis_la_campagne_toute_l_affaire_suit(): void
    {
        DB::table('invoices')->insert([
            ['reference' => 'FAC-B', 'client_id' => 78, 'campaign_id' => 122, 'status' => 'brouillon'],
            ['reference' => 'FAC-A', 'client_id' => 78, 'campaign_id' => 122, 'status' => 'annulee'],
        ]);
        DB::table('quotes')->insert(['reference' => 'DEV-1', 'client_id' => 78, 'client_contact_id' => 5, 'converted_reservation_id' => 119]);
        DB::table('relances_fin_campagne')->insert([
            ['campaign_id' => 122, 'client_id' => 78, 'statut' => 'envoye'],
            ['campaign_id' => 122, 'client_id' => 78, 'statut' => 'echec'],
        ]);
        DB::table('renouvellements')->insert([
            ['campaign_id' => 122, 'client_id' => 78, 'statut' => 'en_attente'],
            ['campaign_id' => 122, 'client_id' => 78, 'statut' => 'confirme'],
        ]);
        DB::table('satisfaction_surveys')->insert([
            ['campaign_id' => 122, 'client_id' => 78, 'sent_at' => '2026-11-01 10:00:00'],
            ['campaign_id' => 122, 'client_id' => 78, 'sent_at' => null],
        ]);

        // Ce que fait l'écran de la campagne : il enregistre la campagne
        // (nouveau client, interlocuteur 9), puis propage.
        Campaign::whereKey(122)->update(['client_id' => 80, 'client_contact_id' => 9]);
        $campagne = Campaign::find(122);
        $faits = $this->service()->propager($campagne, $campagne->reservation, 80, 9);

        $this->assertSame(80, (int) DB::table('reservations')->where('id', 119)->value('client_id'));
        $this->assertSame(9, (int) DB::table('reservations')->where('id', 119)->value('client_contact_id'));
        $this->assertSame([80, 78], DB::table('invoices')->orderBy('reference', 'desc')->pluck('client_id')->map(fn ($v) => (int) $v)->all(),
            'le brouillon suit, l\'annulée reste');
        $this->assertSame(80, (int) DB::table('quotes')->value('client_id'), 'le devis suit (décision 3)');
        $this->assertSame([78, 80], DB::table('relances_fin_campagne')->orderBy('id')->pluck('client_id')->map(fn ($v) => (int) $v)->all(),
            'la relance envoyée reste, l\'autre suit');
        $this->assertSame([80, 78], DB::table('renouvellements')->orderBy('id')->pluck('client_id')->map(fn ($v) => (int) $v)->all(),
            'le renouvellement sans réponse suit, le confirmé reste');
        $this->assertSame([78, 80], DB::table('satisfaction_surveys')->orderBy('id')->pluck('client_id')->map(fn ($v) => (int) $v)->all(),
            'l\'enquête envoyée reste, l\'autre suit');
        $this->assertSame(2, DB::table('external_panels')->where('client_id', 80)->count());
        $this->assertSame(1, $faits['reservation']);
    }

    public function test_depuis_la_reservation_la_campagne_suit_et_son_nom_aussi(): void
    {
        Reservation::whereKey(119)->update(['client_id' => 80, 'client_contact_id' => null]);
        $resa = Reservation::find(119);
        $this->service()->propager(Campaign::find(122), $resa, 80, null);

        $c = Campaign::find(122);
        $this->assertSame(80, (int) $c->client_id);
        $this->assertNull($c->client_contact_id, 'l\'interlocuteur de l\'ancien client ne suit pas');
        $this->assertSame('Campagne 2A ÉDITIONS', $c->name, 'le nom par défaut suit le client');
    }

    public function test_un_nom_deja_pris_chez_le_nouveau_client_n_est_pas_doublonne(): void
    {
        DB::table('campaigns')->insert(['id' => 200, 'name' => 'Campagne 2A ÉDITIONS', 'client_id' => 80]);
        $this->service()->propager(Campaign::find(122), Reservation::find(119), 80, null);
        $this->assertSame('Campagne 2A CONSULTING', Campaign::find(122)->name, 'nom par défaut pris : l\'ancien est gardé');

        $s = $this->service();
        $this->assertSame('Campagne Été', $s->nomParDefaut('Campagne Été', '2A CONSULTING', '2A ÉDITIONS'), 'un nom choisi ne change pas');
    }

    public function test_les_formulaires_compilent(): void
    {
        foreach (['admin/campaigns/edit.blade.php', 'admin/reservations/edit.blade.php'] as $vue) {
            $php = app('blade.compiler')->compileString(file_get_contents(resource_path('views/' . $vue)));
            $tmp = tempnam(sys_get_temp_dir(), 'vue') . '.php';
            file_put_contents($tmp, $php);
            exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $code);
            @unlink($tmp);
            $this->assertSame(0, $code, $vue . ' : ' . implode("\n", $sortie));
            $sortie = [];
        }
    }

    public function test_les_deux_ecrans_ne_bloquent_plus_le_client(): void
    {
        $campagnes = file_get_contents(app_path('Http/Controllers/Admin/CampaignController.php'));
        $reservations = file_get_contents(app_path('Http/Controllers/Admin/ReservationController.php'));

        $this->assertStringNotContainsString('son client ne peut plus changer', $reservations);
        $this->assertStringContainsString('$changementClient->propager(', $reservations);
        $this->assertStringContainsString('$changementClient->propager(', $campagnes);
        // À la création, une campagne tirée d'une réservation garde son client.
        $this->assertSame(2, substr_count($campagnes, 'clientDifferentDeLaReservation('), 'déclaration + création seulement');
    }
}
