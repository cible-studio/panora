<?php

namespace Tests\Unit;

use App\Mail\InterlocuteursAFournirMail;
use App\Services\Campagnes\CampagnesSansInterlocuteur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use Tests\Unit\Concerns\BaseRenouvellement;

/**
 * Liste des campagnes sans interlocuteur, par commercial (2026-11-09) :
 * chaque commercial remplit son fichier avec l'interlocuteur à renseigner.
 */
class CampagnesSansInterlocuteurTest extends TestCase
{
    use BaseRenouvellement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();
        \Illuminate\Support\Facades\Schema::table("campaigns", fn ($t) => $t->unsignedBigInteger("updated_by")->nullable());
    }

    private function groupes(bool $toutes = false)
    {
        return app(CampagnesSansInterlocuteur::class)->parCommercial($toutes);
    }

    public function test_une_campagne_sans_interlocuteur_est_rangee_chez_son_commercial(): void
    {
        $this->campagne(['name' => 'Annuelle Orange']);

        $ligne = $this->groupes()['K. Yao']->first();
        $this->assertSame('ORANGE CI', $ligne['client']);
        $this->assertSame('Annuelle Orange', $ligne['campagne']);
        $this->assertSame('31/12/2026', $ligne['echeance']);
        $this->assertSame('Oui', $ligne['reconduction']);
    }

    public function test_sans_commercial_la_campagne_va_a_son_createur(): void
    {
        $this->campagne(['commercial_user_id' => null]);

        $this->assertArrayHasKey('Créateur', $this->groupes()->all());
    }

    public function test_un_interlocuteur_valide_retire_la_campagne_mais_pas_un_interlocuteur_supprime(): void
    {
        $contact = DB::table('client_contacts')->where('client_id', 10)->value('id');
        $avec = $this->campagne(['name' => 'Avec', 'client_contact_id' => $contact]);

        $this->assertTrue($this->groupes()->isEmpty());

        DB::table('client_contacts')->where('id', $contact)->update(['deleted_at' => now()]);
        $this->assertSame('Avec', $this->groupes()['K. Yao']->first()['campagne']);
        $this->assertSame($contact, $avec->client_contact_id);
    }

    public function test_les_campagnes_terminees_ne_sortent_qu_avec_toutes(): void
    {
        $this->campagne(['name' => 'Finie', 'status' => 'termine', 'end_date' => '2026-06-30']);

        $this->assertTrue($this->groupes()->isEmpty());
        $this->assertSame('Finie', $this->groupes(toutes: true)['K. Yao']->first()['campagne']);
    }

    public function test_les_adresses_mortes_ne_sont_pas_proposees(): void
    {
        $this->campagne();
        $this->assertStringContainsString('awa@orange.ci', $this->groupes()['K. Yao']->first()['contacts']);

        DB::table('diffusion_desinscriptions')->insert(['email' => 'awa@orange.ci', 'motif' => 'rebond']);
        $this->assertStringNotContainsString('awa@orange.ci', $this->groupes()['K. Yao']->first()['contacts']);
    }

    public function test_sans_mail_la_commande_affiche_seulement_le_decompte(): void
    {
        $this->campagne();

        $this->artisan('campagnes:sans-interlocuteur')
            ->expectsOutputToContain('K. Yao : 1 campagne(s)')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_une_adresse_invalide_est_refusee(): void
    {
        $this->artisan('campagnes:sans-interlocuteur', ['--mail' => 'pas-une-adresse'])->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_chaque_commercial_a_son_fichier_a_remplir(): void
    {
        $this->campagne(['name' => 'Yao 1']);
        $this->campagne(['name' => 'Createur 1', 'commercial_user_id' => null]);

        $this->artisan('campagnes:sans-interlocuteur', ['--mail' => 'moi@cible-ci.com'])->assertSuccessful();

        Mail::assertSent(InterlocuteursAFournirMail::class, function (InterlocuteursAFournirMail $m) {
            $this->assertTrue($m->hasTo('moi@cible-ci.com'));
            $this->assertSame(2, $m->nbCampagnes);
            $this->assertSame(
                ['interlocuteurs-a-renseigner-createur.xlsx', 'interlocuteurs-a-renseigner-k-yao.xlsx'],
                array_keys($m->fichiers)
            );
            $this->assertCount(2, $m->attachments());

            $chemin = tempnam(sys_get_temp_dir(), 'xlsx');
            file_put_contents($chemin, $m->fichiers['interlocuteurs-a-renseigner-k-yao.xlsx']);
            $feuille = IOFactory::load($chemin)->getActiveSheet();
            @unlink($chemin);

            $this->assertSame("Nom de l'interlocuteur", $feuille->getCell('G1')->getValue());
            $this->assertSame('ORANGE CI', $feuille->getCell('B2')->getValue());
            $this->assertSame('Yao 1', $feuille->getCell('C2')->getValue());
            $this->assertEmpty($feuille->getCell('G2')->getValue());

            return true;
        });
    }

    // ── Écran « Interlocuteurs à renseigner » ──────────────────────

    private function utilisateur(int $id): \App\Models\User
    {
        return \App\Models\User::findOrFail($id);
    }

    private function choisir(\App\Models\Campaign $campagne, $contact, int $userId)
    {
        return $this->actingAs($this->utilisateur($userId))
            ->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class)
            ->postJson(route('admin.campaigns.interlocuteur', $campagne->id), ['client_contact_id' => $contact]);
    }

    public function test_le_commercial_choisit_l_interlocuteur_de_sa_campagne(): void
    {
        $campagne = $this->campagne();
        $contact = DB::table('client_contacts')->where('client_id', 10)->value('id');

        $this->choisir($campagne, $contact, 2)->assertOk();

        $this->assertSame($contact, $campagne->fresh()->client_contact_id);
        $this->assertSame(2, (int) $campagne->fresh()->updated_by);
        $this->assertTrue($this->groupes()->isEmpty());
    }

    public function test_un_commercial_ne_touche_pas_a_la_campagne_d_un_autre(): void
    {
        DB::table('users')->insert(['id' => 4, 'name' => 'Autre', 'email' => 'autre@cible-ci.com', 'role' => 'commercial']);
        $campagne = $this->campagne();
        $contact = DB::table('client_contacts')->where('client_id', 10)->value('id');

        $this->choisir($campagne, $contact, 4)->assertForbidden();

        $this->assertNull($campagne->fresh()->client_contact_id);
    }

    public function test_le_mp_peut_choisir_pour_tous_les_commerciaux(): void
    {
        $campagne = $this->campagne();
        $contact = DB::table('client_contacts')->where('client_id', 10)->value('id');

        $this->choisir($campagne, $contact, 3)->assertOk();
    }

    public function test_un_contact_d_un_autre_client_est_refuse(): void
    {
        DB::table('clients')->insert(['id' => 11, 'name' => 'MTN']);
        $autre = DB::table('client_contacts')->insertGetId(['client_id' => 11, 'name' => 'Ali', 'email' => 'ali@mtn.ci']);

        $this->choisir($this->campagne(), $autre, 2)->assertStatus(422);
    }

    public function test_une_adresse_morte_ou_sans_mail_est_refusee(): void
    {
        $campagne = $this->campagne();
        $sansMail = DB::table('client_contacts')->insertGetId(['client_id' => 10, 'name' => 'Sans', 'email' => null]);
        $morte = DB::table('client_contacts')->where('email', 'awa@orange.ci')->value('id');
        DB::table('diffusion_desinscriptions')->insert(['email' => 'awa@orange.ci', 'motif' => 'rebond']);

        $this->choisir($campagne, $sansMail, 2)->assertStatus(422);
        $this->choisir($campagne, $morte, 2)->assertStatus(422);

        $this->assertNull($campagne->fresh()->client_contact_id);
    }

    public function test_une_campagne_terminee_ne_se_modifie_pas_ici(): void
    {
        $campagne = $this->campagne(['status' => 'termine']);
        $contact = DB::table('client_contacts')->where('client_id', 10)->value('id');

        $this->choisir($campagne, $contact, 2)->assertForbidden();
    }

    public function test_l_ecran_affiche_les_campagnes_a_renseigner(): void
    {
        $this->campagne(["name" => "Annuelle Orange"]);

        $this->actingAs($this->utilisateur(2));

        $vue = app(\App\Http\Controllers\Admin\CampagnesInterlocuteursController::class)->index(
            request()->setUserResolver(fn () => $this->utilisateur(2)),
            app(CampagnesSansInterlocuteur::class)
        );
        $this->assertSame('admin.campaigns.interlocuteurs', $vue->name());
        $this->assertTrue($vue->getData()['commerciaux']->isEmpty(), "Un commercial n'a pas de filtre.");

        // Le corps de la page, sans le gabarit général (qui lit des tables absentes ici).
        $source = file_get_contents(resource_path('views/admin/campaigns/interlocuteurs.blade.php'));
        $corps = preg_replace(['/<x-admin-layout[^>]*>/', '/<\/x-admin-layout>/'], '', $source);
        $html = \Illuminate\Support\Facades\Blade::render($corps, $vue->getData());

        $this->assertStringContainsString('ORANGE CI', $html);
        $this->assertStringContainsString('Annuelle Orange', $html);
        $this->assertStringContainsString('awa@orange.ci', $html);
        $this->assertStringContainsString('Reconduction', $html);
    }

    public function test_l_interlocuteur_actuel_reste_visible_et_les_campagnes_a_faire_passent_d_abord(): void
    {
        $contact = DB::table('client_contacts')->where('client_id', 10)->value('id');
        $this->campagne(['name' => 'Deja renseignee', 'client_contact_id' => $contact, 'end_date' => '2026-11-30']);
        $this->campagne(['name' => 'A faire', 'end_date' => '2026-12-31']);
        $this->actingAs($this->utilisateur(3));

        $donnees = app(\App\Http\Controllers\Admin\CampagnesInterlocuteursController::class)->index(
            request()->setUserResolver(fn () => $this->utilisateur(3)),
            app(CampagnesSansInterlocuteur::class)
        )->getData();

        $this->assertSame(['A faire', 'Deja renseignee'], $donnees['lignes']->pluck('campagne')->all());
        $this->assertSame(1, $donnees['aRenseigner']);
        $this->assertNull($donnees['lignes'][0]['interlocuteur']);
        $this->assertSame('Awa Koné', $donnees['lignes'][1]['interlocuteur']['nom']);
        $this->assertStringContainsString('awa@orange.ci', $donnees['lignes'][1]['interlocuteur']['detail']);
    }

    public function test_appliquer_renvoie_l_interlocuteur_a_afficher(): void
    {
        $contact = DB::table('client_contacts')->where('client_id', 10)->value('id');

        $reponse = $this->choisir($this->campagne(), $contact, 2)->assertOk();

        $this->assertSame($contact, $reponse->json('id'));
        $this->assertSame('Awa Koné', $reponse->json('nom'));
        $this->assertStringContainsString('awa@orange.ci', $reponse->json('libelle'));
    }

    public function test_rien_n_est_enregistre_sans_cliquer_sur_appliquer(): void
    {
        $source = file_get_contents(resource_path('views/admin/campaigns/interlocuteurs.blade.php'));

        // Choisir dans la liste ne fait qu'activer le bouton.
        $this->assertStringContainsString("choix.addEventListener('change', function () { majBouton(ligne); });", $source);
        $this->assertStringContainsString('data-appliquer', $source);
        // .btn impose un display : sans cette règle, un bouton caché reste visible (vu au premier essai).
        $this->assertStringContainsString('.ecran-interlocuteurs [hidden] { display: none !important; }', $source);
    }

    public function test_un_commercial_ne_liste_que_ses_campagnes(): void
    {
        DB::table('users')->insert(['id' => 4, 'name' => 'Autre', 'email' => 'autre@cible-ci.com', 'role' => 'commercial']);
        $this->campagne(['name' => 'De Yao']);
        $this->campagne(['name' => "De l'autre", 'commercial_user_id' => 4]);

        $service = app(CampagnesSansInterlocuteur::class);
        $this->assertSame(['De Yao'], $service->campagnes(2)->pluck('name')->all());
        $this->assertCount(2, $service->campagnes());
    }
}
