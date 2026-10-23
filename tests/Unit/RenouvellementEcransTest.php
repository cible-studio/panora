<?php

namespace Tests\Unit;

use App\Models\ModeleMail;
use App\Models\Renouvellement;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Écrans « Renouvellements » et « Textes des mails » (2026-10-22) :
 * affichage et droits d'accès par rôle.
 */
class RenouvellementEcransTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();

        // Ce que le gabarit admin lit à chaque page.
        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);
        Schema::create('alerts', function (Blueprint $t) {
            $t->id(); $t->boolean('is_read')->default(false); $t->timestamp('archived_at')->nullable(); $t->timestamps();
        });
        foreach (['admin.conflicts.count', 'admin.messages.new_count', 'admin.signalements.pending_count'] as $cle) {
            Cache::put($cle, 0, 600);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_l_admin_voit_la_liste_l_editeur_et_l_apercu(): void
    {
        $c = $this->campagne();
        $admin = User::findOrFail(1);

        $this->actingAs($admin)->get('/admin/renouvellements')
            ->assertOk()->assertSee('Campagnes annuelles')->assertSee('Affichage annuel')->assertSee('Comment ça marche', false);

        $this->actingAs($admin)->get('/admin/renouvellements/textes?cle=j15')
            ->assertOk()->assertSee('Disponibilité de vos emplacements');

        $this->actingAs($admin)->post('/admin/renouvellements/textes/apercu', [
            'cle' => 'j30', 'objet' => 'Échéance [Société]', 'corps' => "Bonjour [Nom du client],\n\n[Boutons]", 'campagne' => $c->id,
        ])->assertOk()->assertSee('Échéance ORANGE CI', false)->assertSee('Bonjour Awa Koné', false)->assertSee('Je souhaite renouveler ma campagne', false);
    }

    public function test_par_defaut_tout_est_visible_et_une_fenetre_vide_est_expliquee(): void
    {
        // Fin dans plus d'un an : visible par défaut (rien n'est caché).
        $this->campagne(['name' => 'Campagne lointaine', 'start_date' => '2026-10-23', 'end_date' => '2027-10-30']);
        $admin = User::findOrFail(1);

        $this->actingAs($admin)->get('/admin/renouvellements')
            ->assertOk()->assertSee('Campagne lointaine')->assertSee('Mail J-60 le 31/08')->assertDontSee('Prochaine échéance');

        // Filtre ≤ 90 j : liste vide, mais l'écran dit quand arrive la prochaine.
        $this->actingAs($admin)->get('/admin/renouvellements?horizon=90')
            ->assertOk()->assertSee('Prochaine échéance')->assertSee('31/08/2027');
    }

    public function test_onglets_autres_campagnes_et_journal(): void
    {
        $this->campagne(['name' => 'Courte', 'start_date' => '2026-10-01', 'end_date' => '2026-11-20']);
        $annuelle = $this->campagne();
        $this->service()->traiter($annuelle);
        $admin = User::findOrFail(1);

        $this->actingAs($admin)->get('/admin/renouvellements?onglet=courtes')
            ->assertOk()->assertSee('Courte')->assertSee('Ne partira pas'); // le client a une autre campagne qui continue après
        $this->actingAs($admin)->get('/admin/renouvellements?onglet=journal')
            ->assertOk()->assertSee('Renouvellement J-60');
    }

    public function test_lien_direct_vers_un_dossier_et_apercu_d_un_mail(): void
    {
        $c = $this->campagne();
        $admin = User::findOrFail(1);

        $this->actingAs($admin)->get('/admin/renouvellements?campagne=' . $c->id)
            ->assertOk()->assertSee('dossier: ' . $c->id, false);
        $this->actingAs($admin)->get("/admin/renouvellements/campagne/{$c->id}/apercu/j15")
            ->assertOk()->assertSee('La date limite de confirmation étant désormais dépassée', false);

        // Un commercial ne voit pas l'aperçu d'une campagne qui n'est pas la sienne.
        $autre = $this->campagne(['name' => 'Pas à lui', 'commercial_user_id' => 1, 'user_id' => 1]);
        $this->actingAs(User::findOrFail(2))->get("/admin/renouvellements/campagne/{$autre->id}/apercu/j60")->assertForbidden();
    }

    public function test_un_texte_modifie_s_applique_puis_se_retablit(): void
    {
        $admin = User::findOrFail(1);

        $this->actingAs($admin)->post('/admin/renouvellements/textes/j45', ['objet' => 'Nouvel objet [Société]', 'corps' => 'Nouveau texte'])
            ->assertRedirect();
        $this->assertSame('Nouvel objet [Société]', ModeleMail::where('cle', 'j45')->value('objet'));

        $this->actingAs($admin)->post('/admin/renouvellements/textes/j45', ['retablir' => 1])->assertRedirect();
        $this->assertSame(0, ModeleMail::count());
    }

    public function test_le_commercial_suit_ses_clients_sans_confirmer_ni_modifier_les_textes(): void
    {
        $c = $this->campagne();
        $commercial = User::findOrFail(2); // commercial de la campagne

        $this->actingAs($commercial)->get('/admin/renouvellements')->assertOk()->assertSee('Affichage annuel');

        $this->actingAs($commercial)->post("/admin/renouvellements/campagne/{$c->id}/statut", ['statut' => 'rappel', 'note' => 'Rappeler lundi.', 'rappel_le' => '2026-11-03'])
            ->assertRedirect();
        $this->assertSame('rappel', Renouvellement::firstOrFail()->statut);
        $this->assertSame('2026-11-03', Renouvellement::firstOrFail()->rappel_le->toDateString());

        // La confirmation reste au MP (règle validée).
        $this->actingAs($commercial)->post("/admin/renouvellements/campagne/{$c->id}/statut", ['statut' => 'confirme'])
            ->assertSessionHasErrors('statut');
        $this->actingAs($commercial)->post("/admin/renouvellements/campagne/{$c->id}/confirmer")->assertForbidden();
        $this->actingAs($commercial)->get('/admin/renouvellements/textes')->assertForbidden();
    }

    public function test_un_commercial_ne_touche_pas_aux_clients_d_un_autre(): void
    {
        $c = $this->campagne(['commercial_user_id' => 1, 'user_id' => 1]);
        $autre = User::findOrFail(2);

        $this->actingAs($autre)->get('/admin/renouvellements')->assertOk()->assertDontSee('Affichage annuel');
        $this->actingAs($autre)->post("/admin/renouvellements/campagne/{$c->id}/statut", ['statut' => 'refus'])->assertForbidden();
    }

    public function test_le_mp_confirme_le_renouvellement(): void
    {
        $c = $this->campagne();

        $this->actingAs(User::findOrFail(3))->post("/admin/renouvellements/campagne/{$c->id}/confirmer")->assertRedirect();

        $dossier = Renouvellement::firstOrFail();
        $this->assertSame('confirme', $dossier->statut);
        $this->assertSame(3, $dossier->confirme_par);
    }
}
