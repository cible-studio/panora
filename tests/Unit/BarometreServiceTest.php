<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\BarometreEnvoi;
use App\Models\BarometreReponse;
use App\Models\Client;
use App\Models\PublicLink;
use App\Models\User;
use App\Services\Barometre\BarometreService;
use App\Services\Brevo\BrevoClient;
use App\Support\Acces;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Baromètre clients (2026-11-17) — envoi, relance, lien, réponses.
 *
 * Décisions validées par écrit : un mail par entreprise ; exclusions
 * (régies, nouveaux clients, adresses écartées) ; le nombre affiché est
 * celui envoyé ; lien sécurisé qui expire seul à la date limite ; relance
 * par bouton aux seuls non-répondants (Google prévient Panora) ; mode test
 * par défaut ; accès admins + BAROMETRE_ACCES.
 */
class BarometreServiceTest extends TestCase
{
    private object $brevo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 09:00:00');

        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('contact_name')->nullable();
            $t->string('statut_commercial')->default('client'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('client_contacts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->string('name'); $t->string('email')->nullable();
            $t->boolean('is_primary')->default(false); $t->date('dernier_echange')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        foreach (['campaigns' => 'start_date', 'reservations' => null, 'quotes' => null] as $table => $date) {
            Schema::create($table, function (Blueprint $t) use ($date) {
                $t->id(); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('client_contact_id')->nullable();
                if ($date) { $t->date($date)->nullable(); }
                $t->softDeletes(); $t->timestamps();
            });
        }
        Schema::create('diffusion_desinscriptions', function (Blueprint $t) {
            $t->id(); $t->string('email'); $t->string('motif'); $t->string('source')->nullable(); $t->timestamp('survenu_at')->nullable(); $t->timestamps();
        });
        Schema::create('public_links', function (Blueprint $t) {
            $t->id(); $t->string('token', 64)->unique(); $t->string('type', 40); $t->string('target_type'); $t->unsignedBigInteger('target_id');
            $t->unsignedBigInteger('client_id')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('revoked_at')->nullable();
            $t->unsignedBigInteger('revoked_by_user_id')->nullable(); $t->string('revoked_reason')->nullable();
            $t->unsignedInteger('max_uses')->nullable(); $t->unsignedInteger('use_count')->default(0); $t->timestamp('used_at')->nullable();
            $t->unsignedInteger('access_count')->default(0); $t->timestamp('last_accessed_at')->nullable();
            $t->string('last_accessed_ip', 45)->nullable(); $t->string('last_accessed_ua')->nullable();
            $t->unsignedBigInteger('created_by_user_id')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('journal_mails', function (Blueprint $t) {
            $t->id(); $t->timestamp('envoye_le'); $t->string('statut'); $t->string('canal'); $t->string('type'); $t->string('sujet')->nullable();
            $t->text('destinataires'); $t->text('copie')->nullable(); $t->string('dossier_type')->nullable(); $t->unsignedBigInteger('dossier_id')->nullable();
            $t->text('erreur')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_17_100000_create_barometre_tables.php'))->up();
        (require base_path('database/migrations/2026_11_17_160000_add_reglages_to_barometre_editions.php'))->up();

        config([
            'barometre.edition'         => '2026',
            'barometre.mode'            => 'production',
            'barometre.date_limite'     => '2026-11-30',
            'barometre.formulaire'      => 'https://docs.google.com/forms/d/e/ABC/viewform?usp=pp_url',
            'barometre.champ_structure' => 'entry.288269504',
            'barometre.lot'             => 60,
            'brevo.sender.email'        => 'commercial@regie.test',
        ]);

        $this->brevo = new class extends BrevoClient {
            public array $envoyes = [];
            public ?\Throwable $panne = null;
            public ?int $restants = null;
            public function estConfigure(): bool { return true; }
            public function envoisRestants(): ?int { return $this->restants; }
            public function envoyerEmail(array $payload): ?string
            {
                if ($this->panne) { throw $this->panne; }
                $this->envoyes[] = $payload;

                return 'msg-' . count($this->envoyes);
            }
        };
        $this->app->instance(BrevoClient::class, $this->brevo);

        DB::table('clients')->insert([
            ['id' => 1, 'name' => 'ADDOHA', 'email' => null, 'statut_commercial' => 'client'],
            ['id' => 2, 'name' => 'AXA', 'email' => null, 'statut_commercial' => 'client'],
            ['id' => 3, 'name' => 'BANK', 'email' => null, 'statut_commercial' => 'client'],
            ['id' => 4, 'name' => 'GUYS MEDIA', 'email' => null, 'statut_commercial' => 'client'],
            ['id' => 5, 'name' => 'JCDECAUX', 'email' => null, 'statut_commercial' => 'client'],
            ['id' => 6, 'name' => 'PROSPECT SA', 'email' => 'p@prospect.ci', 'statut_commercial' => 'prospect'],
            ['id' => 7, 'name' => 'Société Générale', 'email' => 'contact@sgci.ci', 'statut_commercial' => 'client'],
        ]);
        DB::table('client_contacts')->insert([
            ['client_id' => 1, 'name' => 'Koffi KOUADJO', 'email' => 'k.kouadjo@addoha.ci', 'is_primary' => true, 'dernier_echange' => null],
            ['client_id' => 2, 'name' => 'Fabrice', 'email' => 'fabrice@axa.ci', 'is_primary' => false, 'dernier_echange' => '2026-10-01'],
            // GUYS MEDIA partage l'adresse d'AXA : un seul mail.
            ['client_id' => 4, 'name' => 'Fabrice', 'email' => 'Fabrice@AXA.ci', 'is_primary' => true, 'dernier_echange' => null],
            ['client_id' => 5, 'name' => 'Loïc', 'email' => 'loic@jcdecaux.com', 'is_primary' => true, 'dernier_echange' => null],
        ]);
        // Comme en prod : la régie est retirée par barometre:exclure.
        $r = app(BarometreService::class)->exclureParNoms(['jcdecaux', 'INCONNU SA'], 'Régie');
        $this->assertSame(['exclus' => ['JCDECAUX'], 'introuvables' => ['INCONNU SA'], 'ambigus' => []], $r);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): BarometreService
    {
        return app(BarometreService::class);
    }

    private function statuts(): array
    {
        return $this->service()->liste()->mapWithKeys(fn ($l) => [$l['client']->name => $l['statut']])->all();
    }

    public function test_la_liste_affiche_ce_qui_partira(): void
    {
        $this->assertSame([
            'ADDOHA'           => 'pret',
            'AXA'              => 'pret',
            'BANK'             => 'sans_email',
            'GUYS MEDIA'       => 'doublon',
            'JCDECAUX'         => 'exclu',
            'Société Générale' => 'pret',
        ], $this->statuts(), 'prospect absent, adresse partagée envoyée une fois');

        $c = $this->service()->compteurs();
        $this->assertSame(3, $c['a_envoyer']);
        $this->assertSame(1, $c['exclus']);
    }

    public function test_une_adresse_ecartee_des_envois_commerciaux_n_est_pas_utilisee(): void
    {
        DB::table('diffusion_desinscriptions')->insert(['email' => 'k.kouadjo@addoha.ci', 'motif' => 'rebond']);

        $this->assertSame('sans_email', $this->statuts()['ADDOHA']);
    }

    public function test_l_envoi_est_refuse_en_mode_test_ou_si_le_nombre_a_change(): void
    {
        config(['barometre.mode' => 'test']);
        try {
            $this->service()->demanderInvitations(3);
            $this->fail('mode test : refusé');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Mode test', $e->getMessage());
        }

        config(['barometre.mode' => 'production']);
        try {
            $this->service()->demanderInvitations(4);
            $this->fail('nombre différent : refusé');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('3 destinataires au lieu de 4', $e->getMessage());
        }
        $this->assertSame(0, BarometreEnvoi::whereNotNull('invite_statut')->count(), 'rien n\'est demandé');
    }

    public function test_envoi_complet_lien_personnel_qui_expire_a_la_date_limite(): void
    {
        $this->assertSame(3, $this->service()->demanderInvitations(3));
        $this->assertSame(['invitations' => 3, 'relances' => 0, 'echecs' => 0], $this->service()->traiterDemandes());
        $this->assertCount(3, $this->brevo->envoyes);

        $addoha = BarometreEnvoi::where('client_id', 1)->first();
        $this->assertSame(BarometreEnvoi::ENVOYE, $addoha->invite_statut);
        $this->assertSame('principal', $addoha->source);

        $lien = PublicLink::find($addoha->public_link_id);
        $this->assertSame('barometre', $lien->type);
        $this->assertSame(64, strlen($lien->token));
        $this->assertSame('2026-11-30 23:59:59', $lien->expires_at->format('Y-m-d H:i:s'));

        $p = $this->brevo->envoyes[0];
        $this->assertSame([['email' => 'k.kouadjo@addoha.ci', 'name' => 'Koffi KOUADJO']], $p['to']);
        $this->assertSame('Votre avis compte pour CIBLE | 5 minutes pour nous aider à progresser', $p['subject']);
        $this->assertStringContainsString('Bonjour Koffi KOUADJO (ADDOHA)', $p['htmlContent']);
        $this->assertStringContainsString('/p/' . $lien->token, $p['htmlContent']);
        $this->assertStringContainsString('30 novembre 2026', $p['htmlContent']);
        $this->assertStringContainsString('/p/' . $lien->token, $p['textContent']);
        $this->assertArrayHasKey('List-Unsubscribe', $p['headers']);
        $this->assertSame(['barometre-2026'], $p['tags']);
        $this->assertMatchesRegularExpression('/<a[^>]*class="cta"[^>]*style="[^"]*background/', $p['htmlContent'],
            'styles écrits en ligne : l\'API Brevo ne passe pas par l\'outil de Panora');

        $this->assertSame('client', DB::table('journal_mails')->where('destinataires', 'k.kouadjo@addoha.ci')->value('dossier_type'));

        // Jamais deux fois : plus personne à inviter.
        $this->assertSame(0, $this->service()->compteurs()['a_envoyer']);
        $this->assertSame(['invitations' => 0, 'relances' => 0, 'echecs' => 0], $this->service()->traiterDemandes());
    }

    public function test_une_demande_qui_ne_peut_pas_partir_passe_en_echec_et_repart_au_clic(): void
    {
        $this->service()->demanderInvitations(3);
        config(['barometre.mode' => 'test']);
        $this->assertSame(3, $this->service()->traiterDemandes()['echecs']);
        $this->assertCount(0, $this->brevo->envoyes);
        $this->assertStringContainsString('mode test', BarometreEnvoi::where('client_id', 1)->value('erreur'));

        // Quota Brevo insuffisant : rien ne part.
        config(['barometre.mode' => 'production']);
        $this->brevo->restants = 2;
        $this->assertSame(3, $this->service()->demanderInvitations(3), 'les échecs repartent au clic');
        $this->assertSame(3, $this->service()->traiterDemandes()['echecs']);
        $this->assertCount(0, $this->brevo->envoyes);
    }

    public function test_brevo_sans_reponse_compte_comme_envoye_un_refus_comme_echec(): void
    {
        $this->service()->demanderInvitations(3);
        $this->brevo->panne = new ConnectionException('cURL error 28');
        $this->assertSame(3, $this->service()->traiterDemandes()['invitations'], 'règle du 2026-11-03 : jamais renvoyé');
        $this->assertStringContainsString('peut-être parti', BarometreEnvoi::where('client_id', 1)->value('erreur'));

        BarometreEnvoi::whereNotNull('invite_statut')->delete();
        $this->service()->demanderInvitations(3);
        $this->brevo->panne = new \RuntimeException('Brevo : adresse refusée');
        $this->assertSame(3, $this->service()->traiterDemandes()['echecs']);
        $this->assertSame('echec', $this->statuts()['ADDOHA']);
    }

    public function test_les_reponses_google_sont_reconnues_sans_doublon(): void
    {
        $s = $this->service();
        $r = $s->enregistrerReponse('ref-1', 'societe generale', Carbon::parse('2026-11-18T10:00:00Z'));
        $this->assertSame(7, $r->client_id, 'majuscules, accents, espaces ignorés');
        $s->enregistrerReponse('ref-1', 'societe generale');
        $this->assertSame(1, BarometreReponse::count(), 'même référence : rien de nouveau');

        $test = $s->enregistrerReponse('ref-2', 'TEST — ADDOHA');
        $this->assertTrue($test->test);
        $this->assertNull($test->client_id, 'une réponse de test ne compte pour personne');

        $inconnu = $s->enregistrerReponse('ref-3', 'Addoha Afrique');
        $this->assertNull($inconnu->client_id);
        $this->assertCount(1, $s->reponsesNonReconnues());
        $s->rattacher($inconnu, Client::find(1));

        $statuts = $this->statuts();
        $this->assertSame('repondu', $statuts['Société Générale']);
        $this->assertSame('repondu', $statuts['ADDOHA'], 'rattachée à la main');
        $this->assertSame(1, $this->service()->compteurs()['a_envoyer'], 'qui a déjà répondu ne reçoit pas l\'invitation');
    }

    public function test_la_relance_ne_vise_que_les_invites_sans_reponse_avec_le_meme_lien(): void
    {
        $s = $this->service();
        $s->demanderInvitations(3);
        $s->traiterDemandes();
        $s->enregistrerReponse('ref-1', 'AXA');
        $lienAddoha = BarometreEnvoi::where('client_id', 1)->value('public_link_id');

        $s = $this->service();
        $this->assertSame(['ADDOHA', 'Société Générale'], $s->aRelancer()->map(fn ($l) => $l['client']->name)->values()->all());
        $this->assertSame(2, $s->demanderRelances(2));
        $this->assertSame(['invitations' => 0, 'relances' => 2, 'echecs' => 0], $s->traiterDemandes());

        $relance = $this->brevo->envoyes[3];
        $this->assertSame('Rappel | Votre avis compte pour CIBLE', $relance['subject']);
        $this->assertSame(['barometre-2026-relance'], $relance['tags']);
        $this->assertSame($lienAddoha, BarometreEnvoi::where('client_id', 1)->value('public_link_id'), 'même lien');
        $this->assertSame(0, $this->service()->aRelancer()->count(), 'une seule relance');
    }

    public function test_le_lien_ouvre_le_questionnaire_au_nom_du_client_et_se_coupe(): void
    {
        $s = $this->service();
        $s->demanderInvitations(3);
        $s->traiterDemandes();
        $envoi = BarometreEnvoi::where('client_id', 7)->first();
        $lien  = PublicLink::find($envoi->public_link_id);

        $url = $s->ouvrir($lien);
        $this->assertSame('https://docs.google.com/forms/d/e/ABC/viewform?usp=pp_url&entry.288269504=Soci%C3%A9t%C3%A9%20G%C3%A9n%C3%A9rale', $url);
        $s->ouvrir($lien->fresh());
        $this->assertSame(2, $envoi->fresh()->ouvertures);
        $this->assertNotNull($envoi->fresh()->ouvert_le);

        // Retiré après l'envoi : lien coupé ; remis : lien rétabli.
        $s->exclure(Client::find(7), 'Erreur');
        $this->assertTrue($lien->fresh()->isRevoked());
        $s->reintegrer(Client::find(7));
        $this->assertFalse($lien->fresh()->isRevoked());

        // Date prolongée : tous les liens suivent.
        $s->changerDateLimite(Carbon::parse('2026-12-05'));
        $this->assertSame('2026-12-05 23:59:59', $lien->fresh()->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_apres_la_date_limite_plus_rien_ne_part(): void
    {
        Carbon::setTestNow('2026-12-01 08:00:00');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('date limite est passée');
        $this->service()->demanderInvitations(3);
    }

    public function test_acces_admins_et_personnes_nommees(): void
    {
        config(['acces.modules.barometre.personnes' => ['josias@regie.test']]);
        $u = function (UserRole $role, string $email) {
            $user = new User();
            $user->role = $role;
            $user->email = $email;

            return $user;
        };

        $this->assertTrue(Acces::peut('barometre', $u(UserRole::ADMIN, 'chef@regie.test')));
        $this->assertTrue(Acces::peut('barometre', $u(UserRole::COMMERCIAL, ' Josias@Regie.test ')));
        $this->assertFalse(Acces::peut('barometre', $u(UserRole::COMMERCIAL, 'autre@regie.test')));
        $this->assertFalse(Acces::peut('barometre', $u(UserRole::TECHNIQUE, 'josias@regie.test')), 'jamais un technicien');
        $this->assertSame('acces:barometre', Acces::middleware('barometre'));
        $this->assertSame('role:admin,mediaplanner', Acces::middleware('poses'), 'les autres modules ne changent pas');
    }

    public function test_le_lien_public_redirige_sans_referer_puis_se_ferme(): void
    {
        $this->service()->demanderInvitations(3);
        $this->service()->traiterDemandes();
        $envoi = BarometreEnvoi::where('client_id', 1)->first();
        $token = PublicLink::find($envoi->public_link_id)->token;

        $r = $this->get('/p/' . $token);
        $r->assertRedirect('https://docs.google.com/forms/d/e/ABC/viewform?usp=pp_url&entry.288269504=ADDOHA');
        // Jamais le chemin (donc le jeton) vers Google : seulement le domaine.
        $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $r->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(1, $envoi->fresh()->ouvertures);

        // Lien coupé : message neutre, jamais la raison interne.
        $this->service()->exclure(Client::find(1), 'Régie concurrente');
        // La raison interne (« Régie concurrente ») ne s'affiche jamais. Pas
        // « Régie » seul : la page porte la charte (« Régie & Studio Créatif »).
        $this->get('/p/' . $token)->assertStatus(410)->assertSee('plus disponible')->assertDontSee('Régie concurrente')->assertDontSee('Retiré');
        $this->service()->reintegrer(Client::find(1));

        // Après la date limite : fermé tout seul.
        Carbon::setTestNow('2026-12-01 00:00:01');
        $this->get('/p/' . $token)->assertStatus(410)->assertSee('clos depuis le 30/11/2026');
        $this->assertSame(1, $envoi->fresh()->ouvertures, 'un lien expiré ne compte pas comme ouvert');
    }

    public function test_la_porte_d_entree_google_exige_le_jeton(): void
    {
        $corps = ['reference' => 'abc', 'structure' => 'AXA', 'repondu_le' => '2026-11-18T10:00:00Z'];

        config(['barometre.jeton_google' => null]);
        $this->postJson('/api/barometre/reponse', $corps, ['Authorization' => 'Bearer x'])->assertNotFound();

        config(['barometre.jeton_google' => 'secret-de-test']);
        $this->postJson('/api/barometre/reponse', $corps)->assertNotFound();
        $this->postJson('/api/barometre/reponse', $corps, ['Authorization' => 'Bearer faux'])->assertNotFound();
        $this->assertSame(0, BarometreReponse::count());

        $this->postJson('/api/barometre/reponse', $corps, ['Authorization' => 'Bearer secret-de-test'])
            ->assertOk()->assertJson(['ok' => true, 'reconnu' => true, 'test' => false]);
        $this->assertSame(2, BarometreReponse::value('client_id'));

        $this->getJson('/api/barometre/etat', ['Authorization' => 'Bearer secret-de-test'])
            ->assertOk()->assertJson(['edition' => '2026', 'date_limite' => '2026-11-30', 'clos' => false]);
    }

    private function ecran(array $params = []): string|array
    {
        // Habillage de l'admin remplacé par le strict nécessaire (menu, compteurs…).
        $dossier = sys_get_temp_dir() . '/barometre-vues-' . getmypid();
        @mkdir($dossier . '/components', 0777, true);
        file_put_contents($dossier . '/components/admin-layout.blade.php', "<main>{{ \$topbarActions ?? '' }}{{ \$slot }}</main>@stack('scripts')");
        app('view')->getFinder()->prependLocation($dossier);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $admin = new User();
        $admin->id = 1;
        $admin->role = UserRole::ADMIN;
        $admin->email = 'chef@regie.test';
        $this->actingAs($admin);

        $reponse = app(\App\Http\Controllers\Admin\BarometreController::class)
            ->index(\Illuminate\Http\Request::create('/admin/barometre', 'GET', $params));

        return $reponse instanceof \Illuminate\Http\JsonResponse ? $reponse->getData(true) : $reponse->render();
    }

    public function test_l_ecran_cartes_qui_filtrent_et_actions_en_fenetres(): void
    {
        $this->service()->enregistrerReponse('ref-x', 'Nom inconnu SARL');
        $html = $this->ecran();

        $this->assertStringContainsString('Envoyer à 3 clients', $html, 'bouton principal de la barre du haut');
        $this->assertStringContainsString('data-filtre="a_envoyer"', $html, 'les cartes filtrent');
        $this->assertMatchesRegularExpression('/data-filtre="sans_destinataire".*?kpi-card__value[^>]*>2</s', $html, 'BANK + GUYS MEDIA');
        $this->assertStringContainsString('Réponses à rattacher', $html);
        $this->assertStringContainsString('Nom inconnu SARL', $html);
        $this->assertStringContainsString('Même adresse que AXA', $html);
        $this->assertStringContainsString('30 novembre 2026', $html);
        $this->assertStringContainsString('id="bm-modal-test"', $html);
        $this->assertStringContainsString('Envoyer à chef@regie.test', $html, 'le test part à la personne connectée');

        // Réglage manquant : la fenêtre de test le dit, au lieu d'un bouton grisé muet.
        config(['barometre.formulaire' => null]);
        $html = $this->ecran();
        $this->assertStringContainsString('est pas relié : collez son lien prérempli', $html);
    }

    public function test_la_liste_se_recharge_sans_quitter_la_page(): void
    {
        $d = $this->ecran(['ajax' => 1, 'filtre' => 'exclus']);
        $this->assertSame(1, $d['total']);
        $this->assertStringContainsString('JCDECAUX', $d['lignes']);
        $this->assertStringContainsString('Remettre', $d['lignes']);
        $this->assertStringNotContainsString('ADDOHA', $d['lignes']);

        $d = $this->ecran(['ajax' => 1, 'q' => 'kouadjo']);
        $this->assertSame(1, $d['total'], 'recherche sur l\'interlocuteur');
        $this->assertStringContainsString('ADDOHA', $d['lignes']);
        $this->assertSame('', $d['pagination'], 'une seule page');

        $d = $this->ecran(['ajax' => 1, 'q' => 'Societe gen']);
        $this->assertStringContainsString('Société Générale', $d['lignes'], 'accents et majuscules ignorés');
    }

    public function test_le_lien_prerempli_colle_a_l_ecran_relie_le_questionnaire(): void
    {
        [$base, $champ] = BarometreService::lirePrerempli('https://docs.google.com/forms/d/e/XYZ/viewform?usp=pp_url&entry.288269504=ADDOHA');
        $this->assertSame('https://docs.google.com/forms/d/e/XYZ/viewform?usp=pp_url', $base);
        $this->assertSame('entry.288269504', $champ);

        foreach ([
            'https://evil.example/forms/d/e/XYZ/viewform?entry.1=A'                => 'Google Forms',
            'https://docs.google.com/forms/d/e/XYZ/viewform'                       => 'pas prérempli',
            'https://docs.google.com/forms/d/e/XYZ/viewform?entry.1=A&entry.2=B'   => 'plusieurs questions',
        ] as $lien => $attendu) {
            try {
                BarometreService::lirePrerempli($lien);
                $this->fail("refusé : {$lien}");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($attendu, $e->getMessage());
            }
        }

        config(['barometre.formulaire' => null, 'barometre.champ_structure' => null]);
        $this->assertNotNull($this->service()->reglageManquant());
        $this->service()->enregistrerReglages(['lien' => 'https://docs.google.com/forms/d/e/XYZ/viewform?usp=pp_url&entry.5=TEST']);
        $s = $this->service();
        $this->assertNull($s->reglageManquant());
        $this->assertSame('https://docs.google.com/forms/d/e/XYZ/viewform?usp=pp_url&entry.5=ADDOHA', $s->lienFormulaire('ADDOHA'));
    }

    public function test_jamais_de_redirection_hors_de_google_forms(): void
    {
        $this->service()->demanderInvitations(3);
        $this->service()->traiterDemandes();
        $lien = PublicLink::find(BarometreEnvoi::where('client_id', 1)->value('public_link_id'));

        config(['barometre.formulaire' => 'https://evil.example/forms/x?usp=pp_url']);
        $this->assertNull($this->service()->ouvrir($lien));
    }

    public function test_josiane_signe_et_recoit_les_reponses(): void
    {
        $this->service()->enregistrerReglages([
            'signature_nom' => 'Josiane SAY', 'signature_fonction' => 'Commerciale 360° & Customer Experience Officer',
            'signature_societe' => 'CIBLE CI', 'repondre_a' => 'josiane@regie.test',
        ]);
        $s = $this->service();
        $this->assertSame('Josiane SAY – CIBLE CI', $s->expediteur());

        $s->demanderInvitations(3);
        $s->traiterDemandes();
        $p = $this->brevo->envoyes[0];
        $this->assertSame(['email' => 'commercial@regie.test', 'name' => 'Josiane SAY – CIBLE CI'], $p['sender'],
            'l\'adresse reste celle validée chez Brevo');
        $this->assertSame(['email' => 'josiane@regie.test'], $p['replyTo']);
        $this->assertStringContainsString('Customer Experience Officer', $p['htmlContent']);

        $this->expectException(\DomainException::class);
        $s->enregistrerReglages(['repondre_a' => 'pas une adresse']);
    }

    public function test_un_nom_qui_n_est_pas_une_personne_salue_l_entreprise(): void
    {
        DB::table('client_contacts')->where('client_id', 1)->update(['name' => 'contact', 'email' => 'contact@addoha.ci']);
        $this->service()->demanderInvitations(3);
        $this->service()->traiterDemandes();

        $p = collect($this->brevo->envoyes)->first(fn ($p) => $p['to'][0]['email'] === 'contact@addoha.ci');
        $this->assertStringContainsString('Bonjour ADDOHA,', $p['htmlContent']);
        $this->assertStringNotContainsString('Bonjour contact', $p['htmlContent']);
        $this->assertSame([['email' => 'contact@addoha.ci']], $p['to'], 'pas de faux nom dans « À »');
    }

    public function test_contacts_a_completer_pour_les_commerciaux(): void
    {
        DB::table('client_contacts')->insert(['client_id' => 3, 'name' => 'Brice KRA', 'email' => null, 'is_primary' => false]);
        $lignes = $this->service()->aCompleter();

        $this->assertSame(['BANK', 'GUYS MEDIA'], array_column($lignes, 'client'), 'sans e-mail et adresse partagée ; ni prêts ni exclus');
        $this->assertStringContainsString('Aucun e-mail', $lignes[0]['probleme']);
        $this->assertSame('Brice KRA', $lignes[0]['connus'], 'ce que Panora connaît déjà');
        $this->assertStringContainsString('Même adresse que AXA', $lignes[1]['probleme']);
        $this->assertSame('Sans commercial attitré', $lignes[0]['commercial']);
        $this->assertStringContainsString('/admin/clients/3', $lignes[0]['fiche']);

        $export = new \App\Exports\BarometreACompleterExport($lignes);
        $this->assertSame(['Sans commercial attitré', 'BANK'], array_slice($export->array()[0], 0, 2));
        $this->assertCount(9, $export->headings());
    }

    public function test_une_fiche_completee_sort_aussitot_des_contacts_a_completer(): void
    {
        $this->assertSame(2, $this->service()->compteurs()['sans_email'] + $this->service()->compteurs()['doublons']);
        $this->assertCount(2, $this->service()->aCompleter());

        // Le commercial ajoute l'interlocuteur de BANK sur la fiche client.
        DB::table('client_contacts')->insert(['client_id' => 3, 'name' => 'Awa KONÉ', 'email' => 'awa@bank.ci', 'is_primary' => false]);

        // Rien n'est gardé en mémoire d'une page à l'autre : le compte suit la fiche.
        $s = $this->service();
        $this->assertSame('pret', collect($s->liste())->firstWhere('client.name', 'BANK')['statut']);
        $this->assertSame(['GUYS MEDIA'], array_column($s->aCompleter(), 'client'));
        $this->assertSame(4, $s->compteurs()['a_envoyer'], 'BANK rejoint « À envoyer »');

        // Le compteur du bouton de l'écran (nombres de la carte « Sans destinataire ») suit aussi.
        $this->assertStringContainsString('Contacts à compléter · 1', $this->ecran());
    }

    public function test_une_adresse_ecartee_est_nommee_avec_sa_raison(): void
    {
        // 12ÈME LIEU, 18/11 : l'interlocuteur a une adresse, mais elle a rebondi.
        DB::table('diffusion_desinscriptions')->insert(['email' => 'k.kouadjo@addoha.ci', 'motif' => 'rebond']);
        $s = $this->service();

        $ligne = collect($s->liste())->firstWhere('client.name', 'ADDOHA');
        $this->assertSame('sans_email', $ligne['statut']);
        $this->assertStringContainsString('Adresse écartée des envois : k.kouadjo@addoha.ci (Adresse invalide)', $ligne['detail']);

        $excel = collect($s->aCompleter())->firstWhere('client', 'ADDOHA');
        $this->assertStringContainsString('Adresse écartée des envois (Adresse invalide)', $excel['probleme']);
        $this->assertStringContainsString('k.kouadjo@addoha.ci (écartée : Adresse invalide)', $excel['connus']);
        $this->assertStringContainsString('Aucun e-mail', collect($s->aCompleter())->firstWhere('client', 'BANK')['probleme'],
            'un vrai « sans e-mail » garde son message');
    }

    /**
     * 2026-11-24 — l'interlocuteur a quitté l'entreprise (réponse
     * automatique) : on corrige la fiche, puis « Renvoyer » envoie
     * l'invitation au nouveau contact, avec le même lien. La relance suit.
     */
    public function test_renvoyer_au_contact_corrige_de_la_fiche(): void
    {
        $s = $this->service();
        $s->demanderInvitations(3);
        $s->traiterDemandes();
        $lien = BarometreEnvoi::where('client_id', 1)->value('public_link_id');
        $avant = count($this->brevo->envoyes);

        // Fiche pas encore corrigée : refusé, rien ne bouge.
        try {
            $this->service()->renvoyerAuContactDeLaFiche(Client::find(1));
            $this->fail('Renvoi accepté vers la même adresse.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('la fiche donne toujours k.kouadjo@addoha.ci', $e->getMessage());
        }

        // Fiche corrigée : nouveau contact principal (données fictives).
        DB::table('client_contacts')->where('client_id', 1)->update(['is_primary' => false]);
        DB::table('client_contacts')->insert(['client_id' => 1, 'name' => 'Awa NOUVELLE', 'email' => 'a.nouvelle@addoha.ci', 'is_primary' => true]);

        $envoi = $this->service()->renvoyerAuContactDeLaFiche(Client::find(1));
        $this->assertSame(['a.nouvelle@addoha.ci', 'Awa NOUVELLE', BarometreEnvoi::DEMANDE], [$envoi->email, $envoi->contact_nom, $envoi->invite_statut]);

        $this->assertSame(['invitations' => 1, 'relances' => 0, 'echecs' => 0], $this->service()->traiterDemandes());
        $this->assertCount($avant + 1, $this->brevo->envoyes);
        $this->assertSame('a.nouvelle@addoha.ci', end($this->brevo->envoyes)['to'][0]['email']);
        $this->assertSame($lien, BarometreEnvoi::where('client_id', 1)->value('public_link_id'), 'même lien personnel');

        // La relance part vers la nouvelle adresse.
        $s = $this->service();
        $this->assertContains('ADDOHA', $s->aRelancer()->map(fn ($l) => $l['client']->name)->all());
        $avantRelance = count($this->brevo->envoyes);
        $s->demanderRelances($s->aRelancer()->count());
        $s->traiterDemandes();
        $relances = array_map(fn ($p) => $p['to'][0]['email'], array_slice($this->brevo->envoyes, $avantRelance));
        $this->assertContains('a.nouvelle@addoha.ci', $relances);
        $this->assertNotContains('k.kouadjo@addoha.ci', $relances, "plus rien vers l'ancienne adresse");
    }

    public function test_renvoyer_est_refuse_sans_invitation_ou_apres_reponse(): void
    {
        $s = $this->service();
        $s->demanderInvitations(3);
        $s->traiterDemandes();
        $s->enregistrerReponse('ref-1', 'AXA');

        foreach ([2 => 'AXA', 3 => 'BANK'] as $id => $nom) {
            try {
                $this->service()->renvoyerAuContactDeLaFiche(Client::find($id));
                $this->fail("Renvoi accepté pour {$nom}.");
            } catch (\DomainException $e) {
                $this->assertStringContainsString('seule une invitation déjà partie, sans réponse', $e->getMessage(), $nom);
            }
        }
        $this->assertStringContainsString("route('admin.barometre.renvoyer'", file_get_contents(resource_path('views/admin/barometre/partials/lignes.blade.php')));
    }

    /**
     * Fenêtre « Renvoyer » (2026-11-24) : elle dit à qui partira
     * l'invitation, d'après la fiche à l'instant du clic. Même règle que le
     * renvoi (BarometreService::apercuRenvoi).
     */
    public function test_la_fenetre_de_renvoi_dit_a_qui_partira_l_invitation(): void
    {
        $s = $this->service();
        $s->demanderInvitations(3);
        $s->traiterDemandes();

        // Fiche inchangée : même personne, renvoi impossible.
        $a = $this->service()->apercuRenvoi(Client::find(1));
        $this->assertSame([false, 'identique'], [$a['possible'], $a['cas']]);
        $this->assertSame(['nom' => 'Koffi KOUADJO', 'email' => 'k.kouadjo@addoha.ci'], $a['ancien']);

        // Même nom, autre adresse : « même interlocuteur, nouvelle adresse ».
        DB::table('client_contacts')->where('client_id', 1)->update(['email' => 'koffi.kouadjo@addoha.ci']);
        $a = $this->service()->apercuRenvoi(Client::find(1));
        $this->assertSame([true, 'meme_personne'], [$a['possible'], $a['cas']]);

        // Autre personne : « vous allez envoyer à … au lieu de … ».
        DB::table('client_contacts')->where('client_id', 1)->update(['is_primary' => false]);
        DB::table('client_contacts')->insert(['client_id' => 1, 'name' => 'Awa NOUVELLE', 'email' => 'a.nouvelle@addoha.ci', 'is_primary' => true]);
        $a = $this->service()->apercuRenvoi(Client::find(1));
        $this->assertSame([true, 'nouveau'], [$a['possible'], $a['cas']]);
        $this->assertSame(['nom' => 'Awa NOUVELLE', 'email' => 'a.nouvelle@addoha.ci'], $a['nouveau']);
        $this->assertSame('k.kouadjo@addoha.ci', $a['ancien']['email']);

        $vue = file_get_contents(resource_path('views/admin/barometre/index.blade.php'));
        $this->assertStringContainsString('id="bm-modal-renvoi"', $vue);
        $this->assertStringContainsString("Vous allez envoyer l\'invitation à", $vue);
        $this->assertStringNotContainsString('confirm(', file_get_contents(resource_path('views/admin/barometre/partials/lignes.blade.php')), "plus d'alerte du navigateur");
    }
}
