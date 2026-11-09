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
}
