<?php

namespace Tests\Unit;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Admin\PropositionController;
use App\Http\Controllers\Admin\QuoteController;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Policies\QuotePolicy;
use App\Services\AvailabilityService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Incohérences du parcours devis → réservation, relevées en préparant la
 * vidéo de formation des commerciaux et corrigées le 2026-11-25 (validé par
 * l'utilisateur : « ok pour tout ce qui est recommandé »).
 *
 *   A. Textes : confirmer une proposition ne crée pas la campagne (règle du BC).
 *   B. « Créer une campagne » : seulement pour qui en a le droit (MP, admin).
 *   C. Devis : un panneau en simple option ailleurs se propose (1er BC gagne).
 *   D. Devis de renouvellement : l'option qu'il remplace ne bloque pas.
 *   E. « Proposition acceptée » : mail au commercial assigné.
 *   F. Un seul rappel de proposition au client.
 *   G. Devis accepté avec conflit : modifiable et renvoyable.
 *   H. /admin/propositions ne plante plus.
 *   I. Validité prolongeable (envoyé ou en négociation), avec un bouton.
 *
 * Base de C/D : P-201 tenu par une simple option d'un autre client, P-202
 * par une réservation confirmée, P-203 par l'option automatique de
 * renouvellement du client du devis (client 1).
 */
class CoherenceDevisPropositionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('status')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->string('status');
            $t->string('type')->nullable(); $t->timestamp('confirmed_at')->nullable();
            $t->unsignedBigInteger('remplacee_par_quote_id')->nullable();
            $t->date('start_date'); $t->date('end_date'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservation_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reservation_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('source')->default('interne');
            $t->unsignedBigInteger('unit_price')->default(0); $t->unsignedBigInteger('total_price')->default(0);
            $t->date('panel_start_date')->nullable(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('reservation_id')->nullable();
            $t->string('status'); $t->date('start_date'); $t->date('end_date'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('type')->default('interne'); $t->timestamps();
        });

        DB::table('clients')->insert([['id' => 1, 'name' => 'Client du devis'], ['id' => 2, 'name' => 'Autre client']]);
        DB::table('panels')->insert([
            ['id' => 201, 'reference' => 'P-201'], ['id' => 202, 'reference' => 'P-202'],
            ['id' => 203, 'reference' => 'P-203'], ['id' => 204, 'reference' => 'P-204'],
        ]);
        DB::table('reservations')->insert([
            // L'option court plus loin que l'engagement ferme : la date de
            // libération doit être celle du ferme.
            ['id' => 80, 'reference' => 'RES-OPTION-AUTRE', 'client_id' => 2, 'status' => 'en_attente', 'start_date' => '2027-01-01', 'end_date' => '2027-03-31'],
            ['id' => 81, 'reference' => 'RES-FERME', 'client_id' => 2, 'status' => 'confirme', 'start_date' => '2027-01-01', 'end_date' => '2027-02-28'],
            ['id' => 82, 'reference' => 'RES-RENOUV', 'client_id' => 1, 'status' => 'en_attente', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31'],
        ]);
        DB::table('reservation_panels')->insert([
            ['reservation_id' => 80, 'panel_id' => 201],
            ['reservation_id' => 81, 'panel_id' => 202], ['reservation_id' => 80, 'panel_id' => 202],
            ['reservation_id' => 82, 'panel_id' => 203],
        ]);
    }

    private function dispo(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    private function source(string $chemin): string
    {
        return file_get_contents(base_path($chemin));
    }

    /** Appelle QuoteController::refuserPanneauxOccupes() (protégée) comme à l'enregistrement. */
    private function enregistrer(array $panneaux, ?int $remplace = null): void
    {
        $data = [
            'client_id' => 1, 'period_start' => '2027-01-05', 'period_end' => '2027-01-25',
            'remplace_reservation_id' => $remplace,
            'lines' => array_map(fn ($id) => ['panel_id' => $id], $panneaux),
        ];
        $methode = new \ReflectionMethod(QuoteController::class, 'refuserPanneauxOccupes');
        $methode->setAccessible(true);
        $methode->invoke(app(QuoteController::class), $data, null);
    }

    // ── C. « Le premier BC gagne » aussi dans le devis ─────────────────

    public function test_une_option_d_un_autre_dossier_ne_bloque_plus_le_devis(): void
    {
        $d = $this->dispo()->disponibilitePourDevis([201, 202, 204], '2027-01-05', '2027-01-25', 1);

        $this->assertTrue($d[201]['available'], 'simple option ailleurs : choisissable');
        $this->assertSame('RES-OPTION-AUTRE', $d[201]['options'][0]['reservation_ref']);

        $this->assertFalse($d[202]['available'], 'réservation confirmée : toujours bloquant');
        $this->assertSame('2027-02-28', $d[202]['release_date'], "libre à la fin de l'engagement ferme, pas de l'option");
        $this->assertCount(1, $d[202]['options']);

        $this->assertTrue($d[204]['available']);
        $this->assertSame([], $d[204]['options']);

        // La disponibilité brute, elle, continue de compter l'option (réservations).
        $this->assertFalse($this->dispo()->getPanelAvailabilityData([201], '2027-01-05', '2027-01-25')[201]['available']);
    }

    public function test_l_enregistrement_accepte_l_option_et_le_signale(): void
    {
        $this->enregistrer([201, 204]);

        $this->assertStringContainsString('P-201', session('warning'));
        $this->assertStringContainsString('Le premier bon de commande reçu l\'emporte', session('warning'));
    }

    public function test_l_enregistrement_refuse_toujours_un_panneau_pris_fermement(): void
    {
        try {
            $this->enregistrer([202]);
            $this->fail('Un panneau confirmé ailleurs doit être refusé.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('P-202 est occupé sur la période du devis (libre le 01/03/2027)', implode(' ', $e->validator->errors()->all()));
        }
    }

    public function test_optionsNonBloquantes_garde_une_campagne_bloquante(): void
    {
        $entree = ['available' => false, 'release_date' => '2027-03-31', 'blocking_status' => 'planifie', 'occupations' => [
            ['source' => 'campaign', 'status' => 'planifie', 'end_date' => '31/03/2027'],
        ]];

        $this->assertSame($entree + ['options' => []], AvailabilityService::optionsNonBloquantes($entree));
    }

    public function test_le_formulaire_affiche_en_option_sans_bloquer(): void
    {
        $form = $this->source('resources/views/admin/quotes/partials/_form.blade.php');

        $this->assertStringContainsString('pr-badge-option">EN OPTION', $form);
        $this->assertStringContainsString("le premier bon de commande reçu l\\'emporte", $form);
        $this->assertStringContainsString('remplace_reservation_id: optionRemplaceeId()', $form);
    }

    // ── D. Devis de renouvellement ─────────────────────────────────────

    public function test_l_option_remplacee_n_est_ni_bloquante_ni_signalee(): void
    {
        $sans = $this->dispo()->disponibilitePourDevis([203], '2027-01-05', '2027-01-25', 1);
        $this->assertTrue($sans[203]['available'], 'déjà vrai depuis C : une option ne bloque pas');
        $this->assertCount(1, $sans[203]['options']);

        $avec = $this->dispo()->disponibilitePourDevis([203], '2027-01-05', '2027-01-25', 1, 82);
        $this->assertTrue($avec[203]['available']);
        $this->assertSame([], $avec[203]['options'], "le même dossier n'est pas « un autre dossier »");

        $this->enregistrer([203], 82);
        $this->assertNull(session('warning'));
    }

    public function test_l_option_remplacee_doit_appartenir_au_client_du_devis(): void
    {
        $methode = new \ReflectionMethod(QuoteController::class, 'optionRemplaceeRecevable');
        $methode->setAccessible(true);
        $controleur = app(QuoteController::class);

        $this->assertSame(82, $methode->invoke($controleur, 82, 1));
        $this->assertNull($methode->invoke($controleur, 80, 1), "l'option d'un autre client n'est jamais ignorée");
        $this->assertNull($methode->invoke($controleur, 82, null));
    }

    // ── A. Textes alignés sur la règle du BC ───────────────────────────

    public function test_aucun_texte_ne_promet_la_campagne_a_la_confirmation(): void
    {
        $this->assertStringNotContainsString('créer votre campagne', $this->source('resources/views/admin/propositions/show.blade.php'));
        $this->assertStringNotContainsString('email récapitulatif sous peu', $this->source('resources/views/admin/propositions/confirmed.blade.php'));
        $this->assertStringContainsString('Transmettez votre bon de commande', $this->source('resources/views/admin/propositions/confirmed.blade.php'));
        $this->assertStringNotContainsString('déclenche la création de votre campagne', $this->source('resources/views/client/proposition-detail.blade.php'));

        foreach (['resources/views/emails/proposition-decision.blade.php', 'resources/views/emails/plain/proposition-decision.blade.php'] as $mail) {
            $texte = $this->source($mail);
            $this->assertStringNotContainsString('marquée comme', $texte, $mail);
            $this->assertStringNotContainsString('créer la campagne associée', $texte, $mail);
            $this->assertStringContainsString('BC reçu', $texte, $mail);
            $this->assertStringContainsString('bcEcheance', $texte, $mail);
        }
        $this->assertStringContainsString("'bcEcheance'", $this->source('app/Mail/PropositionDecisionMail.php'));
    }

    // ── B. Bouton « Créer une campagne » ───────────────────────────────

    public function test_chaque_lien_de_creation_de_campagne_est_garde_par_la_policy(): void
    {
        foreach ([
            'resources/views/admin/reservations/show.blade.php',
            'resources/views/admin/reservations/partials/table-rows.blade.php',
            'resources/views/admin/campaigns/partials/table-rows.blade.php',
            'resources/views/admin/campaigns/index.blade.php',
        ] as $vue) {
            $src = $this->source($vue);
            $this->assertMatchesRegularExpression('/route\(\'admin\.campaigns\.create\'/', $src, $vue);
            preg_match_all('/route\(\'admin\.campaigns\.create\'/', $src, $liens, PREG_OFFSET_CAPTURE);
            foreach ($liens[0] as [, $position]) {
                $avant = substr($src, max(0, $position - 600), 600);
                $this->assertMatchesRegularExpression(
                    "/can\('create', \\\\?App\\\\Models\\\\Campaign::class\)/",
                    $avant,
                    "{$vue} : lien de création de campagne sans contrôle du droit"
                );
            }
        }
    }

    // ── E. Mail au commercial assigné ──────────────────────────────────

    public function test_la_decision_part_au_commercial_du_dossier(): void
    {
        $src = $this->source('app/Http/Controllers/Admin/PropositionController.php');
        $methode = substr($src, strpos($src, 'private function notifyDecision'));
        $methode = substr($methode, 0, strpos($methode, 'sendSilently'));

        $this->assertStringContainsString('resolveCommercialContact()', $methode);
        $this->assertStringNotContainsString('$reservation->user?->email', $methode);
    }

    // ── F. Un seul rappel ──────────────────────────────────────────────

    public function test_un_seul_rappel_de_proposition_est_planifie(): void
    {
        $commandes = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");

        $this->assertStringContainsString('propositions:send-reminders', $commandes);
        $this->assertStringNotContainsString('propositions:send-rappels', $commandes);
    }

    // ── G. Devis accepté avec conflit ──────────────────────────────────

    public function test_le_devis_en_conflit_se_modifie_et_se_renvoie(): void
    {
        $commercial = new User(['role' => 'commercial']);
        $commercial->id = 7;
        $devis = new Quote(['status' => QuoteStatus::ACCEPTE_AVEC_CONFLIT->value]);
        $devis->commercial_user_id = 7;
        $devis->setRelation('lines', collect([new QuoteLine()]));

        $this->assertTrue($devis->isEditable());
        $this->assertTrue($devis->isSendable());
        $this->assertTrue((new QuotePolicy())->update($commercial, $devis));
        $this->assertTrue((new QuotePolicy())->send($commercial, $devis));

        $devis->status = QuoteStatus::ACCEPTE;
        $this->assertFalse($devis->isEditable(), 'un devis accepté et converti reste figé');

        $this->assertStringContainsString('QuoteStatus::ACCEPTE_AVEC_CONFLIT], true);', $this->source('app/Http/Controllers/Admin/QuoteController.php'), 'version suivante');
        $this->assertStringContainsString('des panneaux ont été pris entre-temps', $this->source('resources/views/admin/quotes/show.blade.php'));
    }

    // ── H. /admin/propositions ─────────────────────────────────────────

    public function test_la_liste_des_propositions_renvoie_vers_les_reservations(): void
    {
        $reponse = app(PropositionController::class)->index(Request::create('/admin/propositions'));

        $this->assertSame(route('admin.reservations.index'), $reponse->getTargetUrl());
    }

    // ── I. Prolonger la validité ───────────────────────────────────────

    public function test_la_validite_se_prolonge_pendant_l_envoi_et_la_negociation(): void
    {
        $commercial = new User(['role' => 'commercial']);
        $commercial->id = 7;

        foreach (QuoteStatus::cases() as $statut) {
            $devis = new Quote(['status' => $statut->value]);
            $devis->commercial_user_id = 7;
            $attendu = in_array($statut, [QuoteStatus::ENVOYE, QuoteStatus::EN_NEGOCIATION], true);

            $this->assertSame($attendu, $devis->peutEtreProlonge(), $statut->value);
            $this->assertSame($attendu, (new QuotePolicy())->extend($commercial, $devis), $statut->value);
        }

        $fiche = $this->source('resources/views/admin/quotes/show.blade.php');
        $this->assertStringContainsString("route('admin.quotes.extend', \$quote)", $fiche);
        $this->assertStringContainsString('abort_unless($quote->peutEtreProlonge()', $this->source('app/Http/Controllers/Admin/QuoteController.php'));
    }
}
