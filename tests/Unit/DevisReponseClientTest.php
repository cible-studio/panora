<?php

namespace Tests\Unit;

use App\Mail\QuoteReponseClientMail;
use App\Mail\QuoteSentMail;
use App\Models\Quote;
use App\Services\QuoteDecisionService;
use App\Services\QuotePdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Réponses du client à un devis et lien privé (2026-11-23).
 *
 *   · Toute réponse (acceptation, refus, modification), depuis l'espace
 *     client ou le lien privé du mail, crée l'alerte ET un mail au
 *     commercial du devis (QuoteDecisionService, source unique).
 *   · Le lien privé permet aussi de demander une modification.
 *   · La page du lien privé est à la charte de la régie (logo, couleurs).
 *   · Espace client : « devis à décider » = envoyé et non expiré.
 *   · Nom du PDF joint modifiable avant l'envoi.
 *
 * Données fictives uniquement (aucun client réel dans le dépôt).
 */
class DevisReponseClientTest extends TestCase
{
    private const JETON = 'abababababababababababababababababababababababababababababababab';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable();
            $t->string('role')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('client_contacts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->string('name')->nullable(); $t->string('email')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('client_users', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference')->nullable(); $t->unsignedBigInteger('client_id')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) { $t->id(); $t->timestamps(); });
        Schema::create('panels', function (Blueprint $t) { $t->id(); $t->string('reference')->nullable(); $t->timestamps(); });
        Schema::create('external_panels', function (Blueprint $t) { $t->id(); $t->timestamps(); });
        foreach (['communes', 'invoice_lines'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->timestamps(); });
        }
        Schema::create('alerts', function (Blueprint $t) {
            $t->id(); $t->string('type'); $t->string('niveau')->nullable(); $t->string('title'); $t->text('message')->nullable();
            $t->string('related_type')->nullable(); $t->unsignedBigInteger('related_id')->nullable(); $t->string('dedup_key')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->string('lien')->nullable(); $t->boolean('is_read')->default(false);
            $t->timestamp('triggered_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamps();
        });
        foreach ([
            '2026_07_21_150448_create_quotes_table.php',
            '2026_07_21_150449_create_quote_lines_table.php',
            '2026_07_21_150450_create_quote_services_table.php',
            '2026_07_29_100000_add_campaign_dates_to_invoice_and_quote_lines.php',
            '2026_10_29_100000_add_interlocuteur_to_campaigns_reservations_quotes.php',
            '2026_10_30_100000_add_tva_applicable_to_quote_services.php',
            '2026_11_12_100000_add_axes_to_quote_lines.php',
        ] as $m) {
            (require base_path("database/migrations/$m"))->up();
        }

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Admin Essai', 'email' => 'admin@regie.test', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Commercial Essai', 'email' => 'commercial@regie.test', 'role' => 'commercial'],
        ]);
        DB::table('clients')->insert(['id' => 1, 'name' => 'CLIENT ESSAI', 'email' => 'achats@client-essai.test']);

        $quote = Quote::create([
            'reference' => 'DEV-TEST-REP', 'client_id' => 1, 'commercial_user_id' => 2, 'created_by' => 2,
            'title' => 'Campagne essai', 'status' => 'envoye', 'version' => 1, 'valid_days' => 30,
            'remise_pct' => 0, 'tva' => 18, 'public_token' => self::JETON,
            'sent_at' => now(), 'expires_at' => now()->addDays(10),
            'period_start' => '2026-12-01', 'period_end' => '2026-12-31', 'total_a_payer' => 317565,
        ]);
        $quote->lines()->create([
            'designation' => 'Cocody essai', 'dimension_m2' => 12, 'pu_ht_mensuel' => 85000,
            'quantite' => 1, 'duree_mois' => 1, 'odp_rate_applique' => 1000, 'tm_rate_applique' => 1000,
        ]);
    }

    private function devis(): Quote
    {
        return Quote::where('reference', 'DEV-TEST-REP')->firstOrFail();
    }

    public function test_le_lien_prive_permet_de_demander_une_modification_et_previent_le_commercial(): void
    {
        Mail::fake();

        $this->post(route('public.quote.request-modification', self::JETON), ['reason' => 'Retirer Yopougon, ajouter 2 faces Cocody.'])
            ->assertRedirect(route('public.quote.show', self::JETON));

        $q = $this->devis();
        $this->assertSame('en_negociation', $q->status->value);
        $this->assertSame('Retirer Yopougon, ajouter 2 faces Cocody.', $q->decision_reason);

        $alerte = DB::table('alerts')->where('type', 'devis_en_negociation')->first();
        $this->assertNotNull($alerte, "l'alerte est créée");
        $this->assertSame(2, (int) $alerte->user_id, 'pour le commercial du devis');
        $this->assertStringContainsString('via le lien privé du mail', $alerte->message);

        Mail::assertSent(QuoteReponseClientMail::class, fn ($m) => $m->hasTo('commercial@regie.test')
            && $m->reponse === QuoteDecisionService::MODIFICATION
            && $m->messageClient === 'Retirer Yopougon, ajouter 2 faces Cocody.'
            && $m->canal === QuoteDecisionService::CANAL_LIEN);
    }

    public function test_un_refus_par_le_lien_prive_previent_le_commercial(): void
    {
        Mail::fake();

        $this->post(route('public.quote.refuse', self::JETON), ['reason' => 'Budget reporté'])
            ->assertRedirect(route('public.quote.show', self::JETON));

        $this->assertSame('refuse', $this->devis()->status->value);
        $this->assertStringContainsString('(motif : Budget reporté)', DB::table('alerts')->where('type', 'devis_refuse')->value('message'));
        Mail::assertSent(QuoteReponseClientMail::class, fn ($m) => $m->hasTo('commercial@regie.test')
            && $m->reponse === QuoteDecisionService::REFUS && $m->messageClient === 'Budget reporté');
    }

    public function test_une_modification_est_refusee_sans_texte_ou_hors_delai(): void
    {
        Mail::fake();

        $this->post(route('public.quote.request-modification', self::JETON), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('envoye', $this->devis()->status->value);

        DB::table('quotes')->update(['expires_at' => now()->subDay()]);
        $this->post(route('public.quote.request-modification', self::JETON), ['reason' => 'Trop tard'])->assertStatus(410);
        $this->assertSame('envoye', $this->devis()->status->value);
        Mail::assertNothingSent();
    }

    public function test_sans_adresse_du_commercial_les_admins_sont_prevenus(): void
    {
        Mail::fake();
        DB::table('users')->where('id', 2)->update(['email' => null]);

        app(QuoteDecisionService::class)->prevenirCommercial($this->devis(), QuoteDecisionService::ACCEPTATION, null,
            QuoteDecisionService::CANAL_ESPACE, 'Réservation RES-1 créée en option.');

        Mail::assertSent(QuoteReponseClientMail::class, fn ($m) => $m->hasTo('admin@regie.test'));
    }

    public function test_le_mail_au_commercial_se_lit_sans_erreur(): void
    {
        $q = $this->devis();
        $mail = new QuoteReponseClientMail($q, QuoteDecisionService::MODIFICATION, 'Ajouter une face.', QuoteDecisionService::CANAL_LIEN);

        $this->assertSame('Devis DEV-TEST-REP : CLIENT ESSAI demande une modification', $mail->envelope()->subject);
        $this->assertSame('achats@client-essai.test', $mail->envelope()->replyTo[0]->address, 'répondre écrit au client');
        $html = $mail->render();
        $this->assertStringContainsString('« Ajouter une face. »', $html);
        $this->assertStringContainsString('lien privé du mail', $html);
        $this->assertStringContainsString(route('admin.quotes.show', $q->id), $html);
    }

    public function test_la_suite_de_l_acceptation_dit_au_commercial_quoi_faire(): void
    {
        $resa = new \App\Models\Reservation();
        $resa->forceFill(['reference' => 'RES-ESSAI', 'bc_attendu_jusqu_au' => '2026-12-23']);

        $this->assertSame(
            'Réservation RES-ESSAI créée en option, bon de commande attendu jusqu\'au 23/12/2026. '
            . 'Récupère le BC du client, puis clique sur « 📥 BC reçu » sur la réservation.',
            QuoteDecisionService::suiteAcceptation(['status' => 'converted', 'reservation' => $resa])
        );
        $this->assertStringContainsString('dates de campagne manquent',
            QuoteDecisionService::suiteAcceptation(['status' => 'conflict', 'motif' => 'periode_absente']));
        $this->assertStringContainsString('plus disponibles',
            QuoteDecisionService::suiteAcceptation(['status' => 'conflict', 'conflicts' => []]));
    }

    /** Les deux chemins (espace client, lien privé) passent par le même service. */
    public function test_les_deux_chemins_passent_par_le_service(): void
    {
        foreach (['app/Http/Controllers/Client/ClientQuoteController.php', 'app/Http/Controllers/PublicQuoteController.php'] as $f) {
            $src = File::get(base_path($f));
            $this->assertStringContainsString('$decisions->refuser(', $src, $f);
            $this->assertStringContainsString('$decisions->demanderModification(', $src, $f);
            $this->assertStringContainsString('QuoteDecisionService::ACCEPTATION', $src, "{$f} : mail après l'acceptation");
            $this->assertStringNotContainsString("'status'          => QuoteStatus::REFUSE->value", $src, "{$f} : plus d'écriture en double");
        }
    }

    public function test_la_page_du_lien_prive_est_a_la_charte(): void
    {
        $page = $this->get(route('public.quote.show', self::JETON))->assertOk();

        $page->assertSee(asset(config('charte.logos.clair')), false);
        $page->assertSee('--qw-accent: ' . strtoupper(config('charte.couleurs.rouge')), false);
        $page->assertSee('class="lisere"', false);
        $page->assertSee(route('public.quote.request-modification', self::JETON), false);
        $page->assertDontSee('linear-gradient(135deg, #0f172a', false);
    }

    public function test_le_client_retrouve_sa_demande_en_cours(): void
    {
        DB::table('quotes')->update(['status' => 'en_negociation', 'decision_reason' => 'Changer la période', 'decision_at' => now()]);

        $this->get(route('public.quote.show', self::JETON))->assertOk()
            ->assertSee('Votre demande de modification')
            ->assertSee('« Changer la période »', false)
            ->assertDontSee('Décision attendue');
    }

    public function test_devis_a_decider_envoye_et_non_expire(): void
    {
        DB::table('quotes')->delete();
        $faire = fn (string $ref, string $statut, $expire) => Quote::create([
            'reference' => $ref, 'client_id' => 1, 'commercial_user_id' => 2, 'created_by' => 2, 'title' => $ref,
            'status' => $statut, 'version' => 1, 'valid_days' => 30, 'public_token' => str_pad($ref, 64, '0'),
            'expires_at' => $expire,
        ]);
        $faire('DEV-A', 'envoye', now()->addDays(3));
        $faire('DEV-B', 'envoye', null);
        $faire('DEV-C', 'envoye', now()->subMinute());
        $faire('DEV-D', 'brouillon', now()->addDays(3));
        $faire('DEV-E', 'en_negociation', now()->addDays(3));

        $this->assertSame(['DEV-A', 'DEV-B'], Quote::aDecider()->orderBy('reference')->pluck('reference')->all());
        foreach (Quote::all() as $q) {
            $this->assertSame(in_array($q->reference, ['DEV-A', 'DEV-B'], true), $q->estADecider(), $q->reference);
        }
    }

    /** Badge du menu, encart du tableau de bord, liste : la même règle. */
    public function test_l_espace_client_signale_les_devis_a_decider(): void
    {
        $this->assertStringContainsString("'badge' => \$devisADecider", File::get(resource_path('views/client/layout.blade.php')));
        $this->assertStringContainsString('->aDecider()', File::get(resource_path('views/client/layout.blade.php')));
        $this->assertStringContainsString('->aDecider()', File::get(app_path('Http/Controllers/Client/ClientDashboardController.php')));
        $this->assertStringContainsString('attendent votre décision', File::get(resource_path('views/client/dashboard.blade.php')));
        $this->assertStringContainsString('$quote->estADecider()', File::get(resource_path('views/client/quotes/index.blade.php')));
        $this->assertStringContainsString('$quote->estADecider()', File::get(resource_path('views/quotes/_show_content.blade.php')));
    }

    public function test_nom_du_pdf_joint_nettoye(): void
    {
        $q = $this->devis();

        $this->assertSame('Devis-DEV-TEST-REP.pdf', QuotePdf::nomPieceJointe($q));
        $this->assertSame('Devis-DEV-TEST-REP.pdf', QuotePdf::nomPieceJointe($q, '   '));
        $this->assertSame('Devis CIBLE Black Friday.pdf', QuotePdf::nomPieceJointe($q, 'Devis CIBLE / Black Friday?'));
        $this->assertSame('Offre décembre.pdf', QuotePdf::nomPieceJointe($q, 'Offre décembre.PDF'));
        $this->assertSame('Devis-DEV-TEST-REP.pdf', QuotePdf::nomPieceJointe($q, '..\\..'));
        $this->assertSame(124, mb_strlen(QuotePdf::nomPieceJointe($q, str_repeat('é', 300))));
    }

    public function test_le_mail_du_devis_joint_le_pdf_sous_le_nom_choisi(): void
    {
        $mail = (new QuoteSentMail($this->devis(), 'Offre CIBLE décembre'))->build();
        $this->assertSame(['Offre CIBLE décembre.pdf'], array_column($mail->rawAttachments, 'name'));

        $mail = (new QuoteSentMail($this->devis()))->build();
        $this->assertSame(['Devis-DEV-TEST-REP.pdf'], array_column($mail->rawAttachments, 'name'), 'sans saisie : le nom habituel');
    }

    /**
     * Réservation issue d'un devis : « Créée par » dit que c'est le client
     * (avec le lien du devis), et le commercial assigné s'affiche toujours.
     */
    public function test_la_fiche_reservation_dit_qui_a_cree_le_dossier(): void
    {
        $vue = File::get(resource_path('views/admin/reservations/show.blade.php'));
        $this->assertStringContainsString('$devisOrigine = $reservation->devisOrigine;', $vue);
        $this->assertStringContainsString('Le client, en acceptant le devis', $vue);
        $this->assertStringNotContainsString('!$showIsSameAsCreator', $vue, 'le commercial n\'est plus masqué quand il a créé le dossier');
        $this->assertStringContainsString("'public:') => ' par le client, via le lien privé du mail'", File::get(app_path('Services/QuoteConverter.php')));
    }

    /** La fenêtre d'envoi propose le nom, l'envoi le transmet au mail. */
    public function test_la_fenetre_d_envoi_transmet_le_nom(): void
    {
        $this->assertStringContainsString("'champ' => 'nom_piece_jointe'", File::get(app_path('Http/Controllers/Admin/ApercuEnvoiController.php')));
        $this->assertStringContainsString("new \\App\\Mail\\QuoteSentMail(\$quote, \$request->string('nom_piece_jointe')", File::get(app_path('Http/Controllers/Admin/QuoteController.php')));
        $vue = File::get(resource_path('views/admin/partials/apercu-envoi.blade.php'));
        $this->assertStringContainsString('data-pj', $vue);
        $this->assertStringContainsString('champ.name = nom;', $vue);
    }
}
