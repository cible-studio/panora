<?php

namespace Tests\Unit;

use App\Mail\RenouvellementReponseMail;
use App\Models\Campaign;
use App\Models\RelanceFinCampagne;
use App\Models\Renouvellement;
use App\Models\User;
use App\Services\Diffusion\RelanceFinCampagneService;
use App\Services\Diffusion\RenouvellementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Renouvellement des campagnes annuelles (2026-10-22) : déroulé complet
 * sur une base réduite. Brevo est simulé : aucun appel réseau.
 *
 * Campagne de référence : 2026-01-01 → 2026-12-31 (annuelle), fin dans
 * 60 jours le 2026-11-01.
 */
class RenouvellementServiceTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return Collection<int, \Illuminate\Http\Client\Request> */
    private function envoisBrevo(): Collection
    {
        return Http::recorded(fn($r) => str_ends_with($r->url(), '/smtp/email'))->map(fn($p) => $p[0])->values();
    }

    private function aujourdhui(string $date): void
    {
        Carbon::setTestNow($date . ' 10:00:00');
    }

    // ── Séquence ──────────────────────────────────────────────────

    public function test_j60_part_par_brevo_avec_trois_boutons_personnels(): void
    {
        $c = $this->campagne();

        $this->assertSame([$c->id], $this->service()->campagnesDuJour()->pluck('id')->all());
        $ligne = $this->service()->traiter($c);

        $this->assertSame('envoye', $ligne->statut);
        $this->assertSame('j60', $ligne->etape);
        $r = $this->envoisBrevo()->first();
        $this->assertSame('awa@orange.ci', $r['to'][0]['email']);
        $this->assertSame(['renouvellement-j60'], $r['tags']);
        $this->assertSame('k.yao@cible-ci.com', $r['replyTo']['email']);

        $token = Renouvellement::firstOrFail()->token;
        foreach (['renouveler', 'ajuster', 'refus'] as $choix) {
            $this->assertStringContainsString("/renouvellement/{$token}/{$choix}", $r['htmlContent']);
        }
        $this->assertStringNotContainsString('rappel', $r['htmlContent']);
        $this->assertStringContainsString('ABJ-100', $r['htmlContent']); // récapitulatif des emplacements
        Mail::assertNothingSent();
    }

    public function test_une_etape_ne_part_qu_une_fois_puis_la_suivante_a_sa_date(): void
    {
        $c = $this->campagne();
        $this->service()->traiter($c);
        $this->service()->traiter($c);
        $this->assertCount(1, $this->envoisBrevo());

        $this->aujourdhui('2026-11-16'); // J-45
        $this->assertSame('j45', $this->service()->traiter($c)->etape);
        $this->assertSame(['renouvellement-j45'], $this->envoisBrevo()->last()['tags']);
    }

    public function test_un_jour_manque_est_rattrape(): void
    {
        $this->aujourdhui('2026-11-11'); // J-50, rien n'est encore parti
        $this->assertSame('j60', $this->service()->traiter($this->campagne())->etape);
    }

    public function test_la_campagne_annuelle_n_a_pas_le_mail_j7_simple_mais_le_j7_de_la_sequence(): void
    {
        $c = $this->campagne();
        $this->aujourdhui('2026-12-24'); // J-7

        $this->assertSame([], app(RelanceFinCampagneService::class)->campagnesDuJour()->pluck('id')->all());
        $this->assertSame('j7', $this->service()->traiter($c)->etape);
        $r = $this->envoisBrevo()->first();
        $this->assertStringContainsString('/rappel', $r['htmlContent']);
    }

    public function test_une_campagne_de_moins_de_12_mois_n_a_pas_la_sequence(): void
    {
        $this->campagne(['start_date' => '2026-06-01', 'end_date' => '2026-12-31']);

        $this->assertCount(0, $this->service()->campagnesDuJour());
    }

    public function test_adresse_desinscrite_pas_de_mail(): void
    {
        DB::table('diffusion_desinscriptions')->insert(['email' => 'awa@orange.ci', 'motif' => 'desinscription']);

        $this->assertSame('exclu', $this->service()->traiter($this->campagne())->statut);
        $this->assertCount(0, $this->envoisBrevo());
    }

    public function test_mode_test_adresses_de_test_et_liens_essai(): void
    {
        config(['diffusion.fin_campagne.mode' => 'test']);

        $ligne = $this->service()->traiter($this->campagne());

        $this->assertSame('test', $ligne->mode);
        $r = $this->envoisBrevo()->first();
        $this->assertSame('studio@cible-ci.com', $r['to'][0]['email']);
        $this->assertStringStartsWith('[TEST]', $r['subject']);
        $this->assertStringContainsString('/renouvellement-essai/', $r['htmlContent']);
        $this->assertStringNotContainsString('/renouvellement/' . Renouvellement::firstOrFail()->token, $r['htmlContent']);
    }

    // ── Réponses ──────────────────────────────────────────────────

    public function test_reponse_du_client_arrete_la_sequence_accuse_et_commercial_prevenu(): void
    {
        $c = $this->campagne();
        $this->service()->traiter($c);
        $dossier = Renouvellement::firstOrFail();

        $this->service()->enregistrerReponse($dossier, 'ajuster', 'Retirer ABJ-101.');

        $this->assertSame('ajuster', $dossier->fresh()->statut);
        $accuse = $this->envoisBrevo()->last();
        $this->assertSame(['renouvellement-accuse'], $accuse['tags']);
        $this->assertSame('awa@orange.ci', $accuse['to'][0]['email']);
        $this->assertArrayNotHasKey('cc', $accuse->data());
        $this->assertStringContainsString('Retirer ABJ-101.', $accuse['textContent']);

        Mail::assertSent(RenouvellementReponseMail::class, fn($m) => $m->hasTo('k.yao@cible-ci.com')
            && $m->hasCc('commercial@cible-ci.com') && $m->choix === 'ajuster');

        // Le lendemain de J-45 : plus aucune relance.
        $this->aujourdhui('2026-11-16');
        $this->assertNull($this->service()->traiter($c));
    }

    public function test_reponse_depuis_un_mail_de_test_n_enregistre_rien(): void
    {
        $this->service()->traiter($this->campagne());
        $dossier = Renouvellement::firstOrFail();

        $this->service()->simulerReponse($dossier, 'refus', null);

        $this->assertSame('en_attente', $dossier->fresh()->statut);
        $this->assertSame('studio@cible-ci.com', $this->envoisBrevo()->last()['to'][0]['email']);
        Mail::assertSent(RenouvellementReponseMail::class, fn($m) => $m->hasTo('studio@cible-ci.com') && $m->noteTest !== null);
        Mail::assertNotSent(RenouvellementReponseMail::class, fn($m) => $m->hasTo('k.yao@cible-ci.com'));
    }

    public function test_statut_change_par_l_equipe_arrete_aussi_les_relances(): void
    {
        $c = $this->campagne();
        $this->service()->traiter($c);
        $commercial = User::findOrFail(2);

        $this->service()->changerStatut(Renouvellement::firstOrFail(), 'refus', 'Joint au téléphone : budget réorienté.', $commercial);

        $dossier = Renouvellement::firstOrFail();
        $this->assertSame('refus', $dossier->statut);
        $this->assertStringContainsString('budget réorienté', json_encode($dossier->historique, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->aujourdhui('2026-11-16');
        $this->assertNull($this->service()->traiter($c));
    }

    // ── Confirmation du MP et catalogue ───────────────────────────

    public function test_confirmation_du_mp_retire_les_panneaux_du_catalogue_pour_un_temps(): void
    {
        $c = $this->campagne();
        $this->assertSame([], RenouvellementService::panneauxRetenus());

        $this->service()->changerStatut($this->service()->dossier($c), 'confirme', null, User::findOrFail(3));

        $this->assertEqualsCanonicalizing([100, 101], RenouvellementService::panneauxRetenus());
        $this->assertSame(3, Renouvellement::firstOrFail()->confirme_par);

        // 30 jours après la fin, la nouvelle réservation a pris le relais.
        $this->aujourdhui('2027-01-31');
        $this->assertSame([], RenouvellementService::panneauxRetenus());
    }

    // ── Page du client ────────────────────────────────────────────

    public function test_page_du_client_ouvrir_n_enregistre_rien_confirmer_enregistre(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);
        $c = $this->campagne();
        $dossier = $this->service()->dossier($c);

        $this->get("/renouvellement/{$dossier->token}/renouveler")->assertOk()->assertSee('Confirmer ma réponse');
        $this->assertSame('en_attente', $dossier->fresh()->statut);

        $this->post("/renouvellement/{$dossier->token}", ['choix' => 'renouveler', 'message' => 'Mêmes emplacements.'])
            ->assertOk()->assertSee('votre réponse est bien enregistrée');
        $this->assertSame('renouveler', $dossier->fresh()->statut);
        $this->assertSame('Mêmes emplacements.', $dossier->fresh()->reponse_message);

        $this->get('/renouvellement/' . str_repeat('x', 48) . '/renouveler')->assertNotFound();
    }

    public function test_lien_essai_signe_et_inviolable(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);
        $dossier = $this->service()->dossier($this->campagne());
        $sig = RenouvellementService::signatureEssai($dossier);

        $this->get("/renouvellement-essai/{$dossier->id}/{$sig}/ajuster")->assertOk()->assertSee('Aperçu de test');
        $this->get("/renouvellement-essai/{$dossier->id}/" . str_repeat('0', 32) . '/ajuster')->assertNotFound();

        $this->post("/renouvellement-essai/{$dossier->id}/{$sig}", ['choix' => 'ajuster'])->assertOk();
        $this->assertSame('en_attente', $dossier->fresh()->statut);
    }

    public function test_renouvellement_confirme_le_client_ne_peut_plus_changer(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);
        $dossier = $this->service()->dossier($this->campagne());
        $this->service()->changerStatut($dossier, 'confirme', null, User::findOrFail(3));

        $this->post("/renouvellement/{$dossier->token}", ['choix' => 'refus'])->assertOk()->assertSee('est confirmé');
        $this->assertSame('confirme', $dossier->fresh()->statut);
    }

    // ── Suivi de l'équipe (2026-10-23) ────────────────────────────

    public function test_date_de_rappel_notee_puis_reprise_dans_le_mail_du_matin(): void
    {
        $c = $this->campagne();
        $this->service()->changerStatut($this->service()->dossier($c), 'en_attente', 'Absent, rappeler mardi.', User::findOrFail(2), '2026-11-03');

        $dossier = Renouvellement::firstOrFail();
        $this->assertSame('2026-11-03', $dossier->rappel_le->toDateString());
        $this->assertStringContainsString('Rappel prévu le 03/11/2026', json_encode($dossier->historique, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertSame(['Rappel prévu le 03/11', 'info'], $this->service()->lignes()->first()['action']);

        $this->aujourdhui('2026-11-03');
        $digest = $this->service()->digest();
        $this->assertCount(1, $digest[0]['a_rappeler']);
        $this->assertSame(['Rappel prévu aujourd\'hui', 'urgent'], $this->service()->lignes()->first()['action']);
    }

    public function test_renouveler_la_campagne_depuis_sa_fiche_arrete_les_relances(): void
    {
        $c = $this->campagne();
        $this->service()->traiter($c); // J-60 parti
        $nouvelle = $this->campagne(['name' => 'Affichage annuel (renouvelée)', 'status' => 'planifie', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31']);

        $this->service()->campagneRenouvelee($c, $nouvelle, User::findOrFail(2));

        $dossier = Renouvellement::where('campaign_id', $c->id)->firstOrFail();
        $this->assertSame('confirme', $dossier->statut);
        $this->assertStringContainsString('Affichage annuel (renouvelée)', json_encode($dossier->historique, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->aujourdhui('2026-11-16');
        $this->assertNull($this->service()->traiter($c));
    }

    public function test_une_campagne_courte_renouvelee_n_a_pas_de_dossier(): void
    {
        $courte = $this->campagne(['start_date' => '2026-09-01', 'end_date' => '2026-12-31']);
        $this->service()->campagneRenouvelee($courte, $this->campagne(['status' => 'planifie']), User::findOrFail(2));

        $this->assertSame(0, Renouvellement::count());
    }

    public function test_frise_des_mails_et_prochaine_action(): void
    {
        $c = $this->campagne();
        $this->service()->traiter($c);

        $l = $this->service()->lignes()->first();
        $this->assertSame(['envoye', 'prevu', 'prevu', 'prevu', 'prevu'], array_column($l['sequence'], 'etat'));
        $this->assertSame(['Mail J-45 le 16/11', 'info'], $l['action']);

        $this->service()->enregistrerReponse($this->service()->dossier($c), 'renouveler', null);
        $l = $this->service()->lignes()->first();
        $this->assertSame(['envoye', 'arrete', 'arrete', 'arrete', 'arrete'], array_column($l['sequence'], 'etat'));
        $this->assertSame(['À confirmer par le MP', 'alerte'], $l['action']);
        $this->assertSame('envoye', $l['accuse']->statut);
    }

    public function test_indicateurs_de_ca(): void
    {
        $c1 = $this->campagne(['name' => 'A', 'end_date' => '2026-11-20', 'start_date' => '2025-11-01']); // J-19, en attente → à risque
        $c2 = $this->campagne(['name' => 'B']);                                                           // J-60
        $this->service()->traiter($c2);
        $this->service()->enregistrerReponse($this->service()->dossier($c2), 'refus', null);

        $k = RenouvellementService::compteurs($this->service()->lignes(null, RenouvellementService::HORIZON_TOUTES));

        $this->assertSame(['nb' => 1, 'montant' => 18000000], $k['attente']);
        $this->assertSame(18000000, $k['a_risque']);
        $this->assertSame(['nb' => 1, 'montant' => 18000000], $k['perdus']);
        $this->assertSame(100, $k['taux_reponse']); // seul B a reçu un mail, et il a répondu
    }

    // ── Non-régression : bugs trouvés au test réel sur MySQL (2026-10-23) ──

    public function test_le_mail_au_commercial_se_genere_vraiment(): void
    {
        // $message est réservé par Laravel dans les vues de mail : le mail plantait.
        $c = $this->campagne();
        $mail = new \App\Mail\RenouvellementReponseMail($this->service()->dossier($c), $c, 'ajuster', 'Retirer ABJ-101.');

        $html = $mail->render();

        $this->assertStringContainsString('Retirer ABJ-101.', $html);
        $this->assertStringContainsString('Je souhaite ajuster mon dispositif', $html);
    }

    public function test_un_test_manuel_ne_compte_pas_comme_l_envoi_automatique(): void
    {
        config(['diffusion.fin_campagne.mode' => 'test']);
        $c = $this->campagne();

        $essai = $this->service()->traiter($c, forcerTest: true, etape: 'j60');
        $auto  = $this->service()->traiter($c);

        $this->assertSame('essai', $essai->mode);
        $this->assertSame('test', $auto->mode);
        $this->assertCount(2, $this->envoisBrevo()); // le test manuel n'a pas bloqué l'envoi du jour
        $this->assertSame('envoye', $this->service()->lignes()->first()['sequence'][0]['etat']);
    }

    public function test_la_prolongation_clot_l_ancien_dossier_et_ouvre_le_suivant(): void
    {
        $c = $this->campagne();
        $ancien = $this->service()->dossier($c);
        $ancien->update(['statut' => 'renouveler']);

        $c->update(['end_date' => '2027-02-28']);
        $this->service()->campagneProlongee($c->fresh(), '2026-12-31', User::findOrFail(1));

        $this->assertSame('confirme', $ancien->fresh()->statut);
        $nouveau = Renouvellement::whereDate('fin_campagne', '2027-02-28')->firstOrFail();
        $this->assertSame('en_attente', $nouveau->statut);
        $this->assertStringContainsString('31/12/2026', json_encode($nouveau->historique, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function test_la_commande_n_annonce_pas_un_envoi_deja_fait(): void
    {
        $this->campagne();
        $this->artisan('campaigns:notify-ending')->expectsOutputToContain('[j60]')->assertSuccessful();
        $this->artisan('campaigns:notify-ending')->expectsOutputToContain('déjà envoyé')->assertSuccessful();

        $this->assertCount(1, $this->envoisBrevo());
    }

    // ── Liste et mail du matin ────────────────────────────────────

    public function test_liste_compteurs_et_mail_du_matin(): void
    {
        $c = $this->campagne();
        $this->aujourdhui('2026-12-06'); // J-25 : sans réponse → à appeler

        $lignes = $this->service()->lignes();
        $this->assertCount(1, $lignes);
        $this->assertSame(18000000, $lignes[0]['montant']);
        $this->assertSame('+225 07 00 00 00', $lignes[0]['telephone']);

        $compteurs = RenouvellementService::compteurs($lignes);
        $this->assertSame(1, $compteurs['30']['nb']);
        $this->assertSame(1, $compteurs['sans_reponse']);

        $digest = $this->service()->digest();
        $this->assertSame(['k.yao@cible-ci.com'], $digest->pluck('email')->all());
        $this->assertCount(1, $digest[0]['appeler']);

        // Le client répond « renouveler » : réponse dans le mail du commercial, à confirmer pour le MP.
        $this->service()->enregistrerReponse($this->service()->dossier($c), 'renouveler', null);
        $digest = $this->service()->digest()->keyBy('role');
        $this->assertCount(1, $digest['commercial']['reponses']);
        $this->assertCount(0, $digest['commercial']['appeler']);
        $this->assertSame('mp@cible-ci.com', $digest['mediaplanner']['email']);
        $this->assertCount(1, $digest['mediaplanner']['a_confirmer']);
    }
}
