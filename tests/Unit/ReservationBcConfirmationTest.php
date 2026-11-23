<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Exceptions\BookingConflictException;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BonDeCommandeService;
use App\Services\PropositionService;
use App\Services\ReservationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Bon de commande et confirmation — règles validées par écrit le 2026-11-19 :
 *   1. le BC reçu confirme la réservation et bloque fermement ses panneaux ;
 *   2. l'admin garde « Confirmer sans BC » (exception, motif obligatoire),
 *      le suivi du BC continue, pas d'annulation à l'échéance ;
 *   3. un dossier concurrent annulé garde son motif, son commercial est prévenu ;
 *   4. le commercial ne voit que ses réservations.
 *
 * Base : RES-BC (en attente de BC, commercial Awa) et RES-RIVAL (simple
 * option sur le même panneau P-10, commercial Koffi). P-20 est tenu par une
 * campagne active d'un autre client.
 */
class ReservationBcConfirmationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 10:00:00');

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable();
            $t->string('role')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('status')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->unsignedBigInteger('commercial_user_id')->nullable();
            $t->string('status'); $t->string('type')->nullable(); $t->timestamp('confirmed_at')->nullable();
            $t->unsignedBigInteger('total_amount')->default(0); $t->text('notes')->nullable();
            $t->date('start_date'); $t->date('end_date');
            $t->date('bc_attendu_jusqu_au')->nullable(); $t->timestamp('bc_recu_at')->nullable(); $t->unsignedBigInteger('bc_recu_par')->nullable();
            $t->string('bc_reference', 190)->nullable(); $t->timestamp('bc_relance_j15_at')->nullable(); $t->timestamp('bc_relance_j7_at')->nullable(); $t->timestamp('bc_relance_j2_at')->nullable();
            $t->string('cancel_type', 50)->nullable(); $t->text('cancel_reason')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->unsignedBigInteger('cancelled_by')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_19_150000_add_confirmation_auteur_to_reservations.php'))->up();
        (require base_path('database/migrations/2026_11_19_170000_add_bc_saisi_par_to_reservations.php'))->up();
        Schema::create('reservation_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reservation_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('source')->default('interne');
            $t->unsignedBigInteger('unit_price')->default(0); $t->unsignedBigInteger('total_price')->default(0); $t->date('panel_start_date')->nullable(); $t->timestamps();
        });
        Schema::create('external_panels', function (Blueprint $t) {
            $t->id(); $t->string('reference')->nullable(); $t->string('status')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->unsignedBigInteger('reservation_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('status'); $t->date('start_date'); $t->date('end_date');
            $t->unsignedInteger('total_panels')->default(0); $t->unsignedBigInteger('total_amount')->default(0);
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('type')->default('interne'); $t->timestamps();
        });
        Schema::create('pose_tasks', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id')->nullable(); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->unsignedBigInteger('assigned_user_id')->nullable(); $t->string('team_name')->nullable(); $t->timestamp('scheduled_at')->nullable();
            $t->string('status')->nullable(); $t->text('notes')->nullable(); $t->string('public_token')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('alerts', function (Blueprint $t) {
            $t->id(); $t->string('type'); $t->string('niveau')->nullable(); $t->string('title'); $t->text('message')->nullable();
            $t->string('related_type')->nullable(); $t->unsignedBigInteger('related_id')->nullable(); $t->string('dedup_key')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->string('lien')->nullable(); $t->boolean('is_read')->default(false);
            $t->timestamp('triggered_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamps();
        });

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Admin', 'email' => 'admin@regie.test', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Awa', 'email' => 'awa@regie.test', 'role' => 'commercial'],
            ['id' => 3, 'name' => 'Koffi', 'email' => 'koffi@regie.test', 'role' => 'commercial'],
            ['id' => 4, 'name' => 'Media', 'email' => 'mp@regie.test', 'role' => 'mediaplanner'],
        ]);
        DB::table('clients')->insert([['id' => 1, 'name' => 'ORANGE CI'], ['id' => 2, 'name' => 'MTN'], ['id' => 3, 'name' => 'MOOV']]);
        DB::table('panels')->insert([['id' => 10, 'reference' => 'P-10', 'status' => 'option'], ['id' => 20, 'reference' => 'P-20', 'status' => 'occupe']]);
        DB::table('reservations')->insert([
            ['id' => 1, 'reference' => 'RES-BC', 'client_id' => 1, 'user_id' => 2, 'commercial_user_id' => 2, 'status' => 'en_attente', 'type' => 'option',
             'start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'bc_attendu_jusqu_au' => '2026-12-13', 'notes' => "Nom de campagne prévu : Noël Orange"],
            ['id' => 2, 'reference' => 'RES-RIVAL', 'client_id' => 2, 'user_id' => 3, 'commercial_user_id' => 3, 'status' => 'en_attente', 'type' => 'option',
             'start_date' => '2026-12-10', 'end_date' => '2026-12-20', 'bc_attendu_jusqu_au' => null, 'notes' => null],
        ]);
        DB::table('reservation_panels')->insert([['reservation_id' => 1, 'panel_id' => 10], ['reservation_id' => 2, 'panel_id' => 10]]);
        DB::table('campaigns')->insert(['id' => 50, 'name' => 'Lancement', 'client_id' => 3, 'status' => 'actif', 'start_date' => '2026-11-01', 'end_date' => '2026-12-31']);
        DB::table('campaign_panels')->insert(['campaign_id' => 50, 'panel_id' => 20]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function bc(): BonDeCommandeService
    {
        return app(BonDeCommandeService::class);
    }

    private function resa(int $id): Reservation
    {
        return Reservation::with(['panels', 'externalPanels', 'client'])->findOrFail($id);
    }

    /** Règles 1 et 3 : le BC reçu confirme, bloque le panneau, fait tomber le rival avec son motif. */
    public function test_le_bc_recu_confirme_et_bloque_les_panneaux(): void
    {
        $awa = User::find(2);
        $this->actingAs($awa);
        $bc = $this->bc();
        $bc->enregistrerReception($this->resa(1), 'BC-778', $awa);

        $r = DB::table('reservations')->where('id', 1)->first();
        $this->assertSame('confirme', $r->status, 'le BC reçu confirme la réservation');
        $this->assertSame('ferme', $r->type);
        $this->assertSame(2, (int) $r->bc_recu_par, 'reçu par le commercial du dossier');
        $this->assertSame(2, (int) $r->bc_saisi_par, 'saisi par Awa elle-même');
        $this->assertSame(2, (int) $r->confirme_par, 'qui a confirmé');
        $this->assertNull($r->confirme_sans_bc_motif);
        $this->assertSame('confirme', DB::table('panels')->where('id', 10)->value('status'), 'le panneau est bloqué fermement');

        $rival = DB::table('reservations')->where('id', 2)->first();
        $this->assertSame('annule', $rival->status, 'le dossier concurrent tombe');
        $this->assertSame('panneaux_pris', $rival->cancel_type);
        $this->assertStringContainsString('Panneaux pris par RES-BC (ORANGE CI)', $rival->cancel_reason);
        $this->assertNotNull($rival->cancelled_at);
        $this->assertSame(['RES-RIVAL'], $bc->derniersConcurrentsAnnules->pluck('reference')->all(), 'le contrôleur annonce les dossiers tombés');

        $alerte = DB::table('alerts')->where('type', 'reservation_annulee_concurrence')->first();
        $this->assertNotNull($alerte, 'le commercial du dossier tombé est prévenu');
        $this->assertSame(3, (int) $alerte->user_id, 'alerte ciblée sur Koffi');
    }

    /** Un panneau déjà pris ferme : rien n'est enregistré, la réservation reste en option. */
    public function test_un_panneau_deja_pris_bloque_l_enregistrement_du_bc(): void
    {
        DB::table('reservation_panels')->insert(['reservation_id' => 1, 'panel_id' => 20]);
        $this->actingAs(User::find(2));

        try {
            $this->bc()->enregistrerReception($this->resa(1), null, User::find(2));
            $this->fail('BC enregistré alors que P-20 est pris par une campagne active.');
        } catch (BookingConflictException $e) {
            $this->assertStringContainsString('P-20', $e->getMessage());
        }

        $r = DB::table('reservations')->where('id', 1)->first();
        $this->assertSame('en_attente', $r->status);
        $this->assertNull($r->bc_recu_at, 'tout est annulé : le BC n\'est pas enregistré');
        $this->assertSame('en_attente', DB::table('reservations')->where('id', 2)->value('status'), 'le rival n\'est pas touché');
    }

    /** « Annuler la réception » : retour en option ; le rival tombé ne revient pas. */
    public function test_annuler_la_reception_remet_en_option(): void
    {
        $this->actingAs(User::find(2));
        $this->bc()->enregistrerReception($this->resa(1), null, User::find(2));
        $this->assertTrue($this->bc()->confirmeeParLeBc($this->resa(1)));

        $this->actingAs(User::find(1));
        $this->bc()->annulerReception($this->resa(1));

        $r = DB::table('reservations')->where('id', 1)->first();
        $this->assertSame('en_attente', $r->status);
        $this->assertSame('option', $r->type);
        $this->assertNull($r->bc_recu_at);
        $this->assertNull($r->confirmed_at);
        $this->assertSame('option', DB::table('panels')->where('id', 10)->value('status'), 'le panneau repasse en option');
        $this->assertSame('annule', DB::table('reservations')->where('id', 2)->value('status'), 'règle validée : le rival n\'est pas rétabli');
    }

    /** Règle 2 : « Confirmer sans BC » exige un motif ; le suivi du BC continue, sans annulation. */
    public function test_confirmer_sans_bc_exige_un_motif_et_garde_le_suivi(): void
    {
        $this->actingAs(User::find(1));
        $service = app(ReservationService::class);

        try {
            $service->changeStatus($this->resa(1), 'confirme');
            $this->fail('Confirmation sans BC acceptée sans motif.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('MOTIF_SANS_BC', $e->getMessage());
        }
        $this->assertSame('en_attente', DB::table('reservations')->where('id', 1)->value('status'));

        $service->changeStatus($this->resa(1), 'confirme', 'Client de confiance, BC promis par la direction');
        $r = $this->resa(1);
        $this->assertSame('confirme', $r->status->value);
        $this->assertSame('Client de confiance, BC promis par la direction', $r->confirme_sans_bc_motif);
        $this->assertSame(1, (int) $r->confirme_par);
        $this->assertTrue($this->bc()->confirmeSansBc($r));
        $this->assertTrue($this->bc()->peutCreerCampagne($r), 'l\'exception ouvre la création de la campagne');
        $this->assertSame('annule', DB::table('reservations')->where('id', 2)->value('status'), 'le rival tombe aussi (même source unique)');

        // Le suivi continue : relance J-15 le 28/11 ; jamais d'expiration automatique.
        Carbon::setTestNow('2026-11-28 09:00:00');
        $this->assertSame(['RES-BC'], $this->bc()->aRelancer(15)->pluck('reference')->all());
        Carbon::setTestNow('2026-12-14 09:00:00');
        $this->assertCount(0, $this->bc()->aExpirer(), 'confirmée exprès : pas d\'annulation à l\'échéance');
        $this->assertSame(['RES-BC'], $this->bc()->aSignalerEchus()->pluck('reference')->all(), 'admin et commercial prévenus');

        DB::table('reservations')->where('id', 1)->update(['bc_echeance_signalee_at' => now()]);
        $this->assertCount(0, $this->bc()->aSignalerEchus(), 'une seule fois');
    }

    /** La campagne se crée sur une réservation confirmée, avec le nom saisi. */
    public function test_creer_la_campagne_avec_son_nom(): void
    {
        $this->actingAs(User::find(2));
        $this->bc()->enregistrerReception($this->resa(1), null, User::find(2));

        $this->actingAs(User::find(4));
        $campagne = app(PropositionService::class)->confirmer($this->resa(1), 'Noël Orange');
        $this->assertSame('Noël Orange', $campagne->name);
        $this->assertSame('planifie', $campagne->status->value ?? $campagne->status);
        $this->assertSame([10], DB::table('campaign_panels')->where('campaign_id', $campagne->id)->pluck('panel_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('confirme', DB::table('panels')->where('id', 10)->value('status'), 'campagne planifiée : panneau confirmé');

        // Sans nom : « Campagne RES-… », comme avant.
        DB::table('campaigns')->where('id', $campagne->id)->delete();
        DB::table('campaign_panels')->where('campaign_id', $campagne->id)->delete();
        $this->assertSame('Campagne RES-BC', app(PropositionService::class)->creerCampagne($this->resa(1))->name);
    }

    /** Une option sans BC ne peut pas avoir sa campagne. */
    public function test_pas_de_campagne_sans_bc(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BC_MANQUANT:');
        app(PropositionService::class)->confirmer($this->resa(1), 'Noël Orange');
    }

    /** Règle 4 : un commercial ne touche pas la réservation d'un autre ; les autres rôles gardent leur accès. */
    public function test_le_commercial_ne_voit_que_ses_reservations(): void
    {
        $m = new \ReflectionMethod(\App\Http\Controllers\Admin\ReservationController::class, 'verifierAccesCommercial');
        $m->setAccessible(true);
        $ctrl = app(\App\Http\Controllers\Admin\ReservationController::class);

        $this->actingAs(User::find(2));
        $m->invoke($ctrl, $this->resa(1)); // la sienne : passe

        try {
            $m->invoke($ctrl, $this->resa(2));
            $this->fail('Awa ouvre la réservation de Koffi.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $comptable = new User();
        $comptable->forceFill(['id' => 9, 'name' => 'Compta', 'role' => UserRole::COMPTABLE->value]);
        $this->actingAs($comptable);
        $m->invoke($ctrl, $this->resa(2)); // comptable : lecture de toutes les réservations, inchangée
        $this->assertTrue(true);
    }

    /** La fiche dit qui a fait quoi et propose l'exception ; elle compile. */
    public function test_la_fiche_compile_et_nomme_les_auteurs(): void
    {
        $vue = file_get_contents(resource_path('views/admin/reservations/show.blade.php'));
        foreach ([
            'Reçu par <strong>{{ $reservation->bcRecuParUser->name }}</strong>',
            '— saisi par <strong>{{ $reservation->bcSaisiParUser->name }}</strong>',
            '⚠ Confirmer sans BC (exception)',
            'name="motif_sans_bc"',
            'name="nom_campagne"',
            "'panneaux_pris'  => 'Panneaux pris par un autre dossier'",
            'sera <strong>annulée elle aussi</strong>',
            'Enregistrer le BC <strong>confirme la réservation</strong>',
        ] as $texte) {
            $this->assertStringContainsString($texte, $vue);
        }
        $this->assertStringNotContainsString('devra être gérée séparément', $vue);

        $php = app('blade.compiler')->compileString($vue);
        $tmp = tempnam(sys_get_temp_dir(), 'vue') . '.php';
        file_put_contents($tmp, $php);
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $code);
        @unlink($tmp);
        $this->assertSame(0, $code, implode("\n", $sortie));
    }

    // ── 2026-11-19 — Choix 1a / 2a / 3a et rappels ─────────────────────

    /** Le BC est reçu par le commercial du dossier, même quand l'admin le saisit. */
    public function test_l_admin_saisit_le_bc_recu_par_le_commercial_du_dossier(): void
    {
        $admin = User::find(1);
        $this->actingAs($admin);
        $this->bc()->enregistrerReception($this->resa(1), 'BC-901', $admin);

        $r = DB::table('reservations')->where('id', 1)->first();
        $this->assertSame(2, (int) $r->bc_recu_par, 'reçu par Awa, commerciale du dossier');
        $this->assertSame(1, (int) $r->bc_saisi_par, 'saisi par l\'admin');
        $this->assertSame('confirme', $r->status);

        $this->bc()->annulerReception($this->resa(1));
        $r = DB::table('reservations')->where('id', 1)->first();
        $this->assertNull($r->bc_recu_par);
        $this->assertNull($r->bc_saisi_par, 'annuler la réception efface aussi qui l\'a saisi');
    }

    /** Sans commercial assigné : le créateur du dossier reçoit le BC. */
    public function test_sans_commercial_assigne_le_createur_recoit_le_bc(): void
    {
        DB::table('reservations')->where('id', 1)->update(['commercial_user_id' => null]);
        $this->actingAs(User::find(4));
        $this->bc()->enregistrerReception($this->resa(1), null, User::find(4));

        $r = DB::table('reservations')->where('id', 1)->first();
        $this->assertSame(2, (int) $r->bc_recu_par, 'Awa a créé le dossier');
        $this->assertSame(4, (int) $r->bc_saisi_par);
    }

    /** Choix 3a : une option concurrente ne bloque pas ; un engagement ferme, si. */
    public function test_une_option_concurrente_ne_bloque_pas_un_engagement_ferme_si(): void
    {
        $dispo = app(\App\Services\AvailabilityService::class);

        $this->assertContains(10, array_map('intval', $dispo->getUnavailablePanelIds([10, 20], '2026-12-01', '2026-12-31')),
            'le contrôle strict voit toujours l\'option');
        $this->assertSame([20], $dispo->panneauxPrisFermement([10, 20], '2026-12-01', '2026-12-31'),
            'P-10 n\'est qu\'en option : retenable ; P-20 est pris par une campagne');

        $carte = $dispo->getInternalConflictMap([10, 20], '2026-12-01', '2026-12-31');
        $this->assertSame([10], \App\Services\AvailabilityService::optionsConcurrentes($carte)->keys()->map(fn ($v) => (int) $v)->all());
        $this->assertSame([20], \App\Services\AvailabilityService::seulementBloquants($carte)->keys()->map(fn ($v) => (int) $v)->all());
    }

    /** Choix 3a : une seconde option sur un panneau déjà en option est acceptée. */
    public function test_une_seconde_option_sur_un_panneau_en_option_est_acceptee(): void
    {
        $this->actingAs(User::find(3));
        $res = app(ReservationService::class)->createFromSelection(
            ['client_id' => 3, 'start_date' => '2026-12-05', 'end_date' => '2026-12-25', 'type' => 'option'],
            [10]
        );

        $r = $res['reservation']->fresh();
        $this->assertSame('en_attente', $r->status->value);
        $this->assertSame('option', $r->type);
        $this->assertNull($r->bc_recu_at);
        $this->assertSame('en_attente', DB::table('reservations')->where('id', 1)->value('status'), 'les autres options restent vivantes');
        $this->assertSame('en_attente', DB::table('reservations')->where('id', 2)->value('status'));
    }

    /** Choix 1a : « Ferme » = BC déjà reçu ; il confirme et les options concurrentes tombent. */
    public function test_une_reservation_ferme_enregistre_le_bc_et_confirme(): void
    {
        $this->actingAs(User::find(3));
        $res = app(ReservationService::class)->createFromSelection(
            ['client_id' => 3, 'start_date' => '2026-12-05', 'end_date' => '2026-12-25', 'type' => 'ferme', 'bc_reference' => 'BC-MOOV-7'],
            [10],
            'Fêtes MOOV'
        );

        $r = DB::table('reservations')->where('id', $res['reservation']->id)->first();
        $this->assertSame('confirme', $r->status, 'le BC enregistré confirme');
        $this->assertSame('ferme', $r->type);
        $this->assertNotNull($r->bc_recu_at);
        $this->assertSame('BC-MOOV-7', $r->bc_reference);
        $this->assertSame(3, (int) $r->bc_recu_par);
        $this->assertSame(3, (int) $r->confirme_par);
        $this->assertStringContainsString('Nom de campagne prévu : Fêtes MOOV', (string) $r->notes);
        $this->assertSame('confirme', DB::table('panels')->where('id', 10)->value('status'));
        $this->assertSame('annule', DB::table('reservations')->where('id', 1)->value('status'), 'le premier BC l\'emporte');
        $this->assertSame('annule', DB::table('reservations')->where('id', 2)->value('status'));
        $this->assertSame(0, DB::table('campaigns')->where('reservation_id', $r->id)->count(), 'la campagne reste au MP');
    }

    /** Un panneau pris ferme bloque toujours la réservation « Ferme ». */
    public function test_une_reservation_ferme_sur_un_panneau_pris_est_refusee(): void
    {
        $this->actingAs(User::find(3));
        try {
            app(ReservationService::class)->createFromSelection(
                ['client_id' => 2, 'start_date' => '2026-12-05', 'end_date' => '2026-12-25', 'type' => 'ferme'],
                [20]
            );
            $this->fail('Réservation ferme acceptée sur P-20, tenu par une campagne active.');
        } catch (\RuntimeException $e) {
            $this->assertStringStartsWith('CONFLICT:', $e->getMessage());
        }
        $this->assertSame(2, DB::table('reservations')->count(), 'rien n\'est créé');
    }

    /** Un panneau à démarrage différé n'est vérifié qu'à partir de son propre début. */
    public function test_le_bc_confirme_une_reservation_avec_un_panneau_differe(): void
    {
        DB::table('reservations')->insert(['id' => 3, 'reference' => 'RES-DIFF', 'client_id' => 1, 'user_id' => 2, 'commercial_user_id' => 2,
            'status' => 'en_attente', 'type' => 'option', 'start_date' => '2026-12-15', 'end_date' => '2027-01-31', 'bc_attendu_jusqu_au' => '2026-12-19']);
        // P-20 est tenu par « Lancement » jusqu'au 31/12 : il rejoint le dossier le 01/01.
        DB::table('reservation_panels')->insert(['reservation_id' => 3, 'panel_id' => 20, 'panel_start_date' => '2027-01-01']);

        $this->actingAs(User::find(2));
        $this->bc()->enregistrerReception($this->resa(3), null, User::find(2));
        $this->assertSame('confirme', DB::table('reservations')->where('id', 3)->value('status'));

        // Le même panneau, sans démarrage différé : refusé.
        DB::table('reservations')->insert(['id' => 4, 'reference' => 'RES-PLEIN', 'client_id' => 1, 'user_id' => 2, 'commercial_user_id' => 2,
            'status' => 'en_attente', 'type' => 'option', 'start_date' => '2026-12-15', 'end_date' => '2026-12-28', 'bc_attendu_jusqu_au' => '2026-12-19']);
        DB::table('reservation_panels')->insert(['reservation_id' => 4, 'panel_id' => 20]);
        $this->expectException(BookingConflictException::class);
        $this->bc()->enregistrerReception($this->resa(4), null, User::find(2));
    }

    /** L'acceptation du client suit la même règle : le panneau différé ne la bloque pas. */
    public function test_le_client_accepte_une_proposition_avec_un_panneau_differe(): void
    {
        DB::table('reservations')->insert(['id' => 3, 'reference' => 'RES-DIFF', 'client_id' => 1, 'user_id' => 2, 'commercial_user_id' => 2,
            'status' => 'en_attente', 'type' => 'option', 'start_date' => '2026-12-15', 'end_date' => '2027-01-31']);
        DB::table('reservation_panels')->insert(['reservation_id' => 3, 'panel_id' => 20, 'panel_start_date' => '2027-01-01']);

        $r = app(PropositionService::class)->accepterClient($this->resa(3));
        $this->assertSame('en_attente', $r->status->value, 'l\'acceptation laisse la réservation en option');
        $this->assertNotNull($r->bc_attendu_jusqu_au, 'elle ouvre l\'attente du BC');
    }

    /** Choix 2a : le devis accepté crée une option en attente de BC (canari de source). */
    public function test_le_devis_accepte_cree_une_option_en_attente_de_bc(): void
    {
        $src = file_get_contents(base_path('app/Services/QuoteConverter.php'));
        $this->assertStringContainsString("'status'             => ReservationStatus::EN_ATTENTE->value,", $src);
        $this->assertStringContainsString("'type'               => 'option',", $src);
        $this->assertStringContainsString("'confirmed_at'       => null,", $src);
        $this->assertStringContainsString('->ouvrirAttente($reservation);', $src);
        $this->assertStringContainsString('->panneauxPrisFermement(', $src);
        $this->assertStringNotContainsString('ReservationStatus::CONFIRME', $src, 'le devis ne confirme plus : c\'est le BC');
    }

    /** Une proposition déjà acceptée (BC attendu) ne reçoit plus de rappel « répondez ». */
    public function test_plus_de_rappel_une_fois_la_proposition_acceptee(): void
    {
        foreach (['SendPropositionReminders', 'SendPropositionRappels'] as $commande) {
            $src = file_get_contents(base_path("app/Console/Commands/{$commande}.php"));
            $this->assertStringContainsString("->whereNull('bc_attendu_jusqu_au')", $src, $commande);
        }

        $ctrl = file_get_contents(base_path('app/Http/Controllers/Admin/PropositionController.php'));
        $this->assertStringContainsString('&& !$reservation->bc_attendu_jusqu_au;', $ctrl, 'page publique : plus de boutons');
        $this->assertStringContainsString('Vous avez déjà accepté cette proposition : nous attendons votre bon de commande.', $ctrl);

        foreach (['views/admin/propositions/show.blade.php', 'views/client/proposition-detail.blade.php'] as $chemin) {
            $vue = file_get_contents(resource_path($chemin));
            $this->assertStringContainsString('Proposition acceptée — bon de commande attendu', $vue, $chemin);
            // Le client n'entend plus « une campagne sera créée » au clic (règle du 13/11).
            $this->assertStringNotContainsString('une campagne sera automatiquement', $vue, $chemin);
            $this->assertStringNotContainsString('une campagne sera créée', $vue, $chemin);
            $this->assertVueCompile($vue, $chemin);
        }

        $devis = file_get_contents(resource_path('views/quotes/_show_content.blade.php'));
        $this->assertStringNotContainsString('une réservation ferme sera créée automatiquement', $devis, 'choix 2a : le devis accepté crée une option');
        $this->assertVueCompile($devis, 'quotes/_show_content');
    }

    /** Disponibilités : « Ferme — BC reçu », numéro de BC facultatif, options retenables. */
    public function test_l_ecran_disponibilites_dit_que_ferme_veut_dire_bc_recu(): void
    {
        $vue = file_get_contents(resource_path('views/admin/reservations/disponibilites.blade.php'));
        $this->assertStringContainsString('🔒 Ferme — BC reçu', $vue);
        $this->assertStringContainsString('name="bc_reference"', $vue);
        $this->assertStringContainsString('le premier bon de commande reçu l\'emporte', $vue);
        $this->assertStringNotContainsString('>Définitive<', $vue);
        $this->assertVueCompile($vue, 'disponibilites');
    }

    private function assertVueCompile(string $vue, string $nom): void
    {
        $php = app('blade.compiler')->compileString($vue);
        $tmp = tempnam(sys_get_temp_dir(), 'vue') . '.php';
        file_put_contents($tmp, $php);
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $code);
        @unlink($tmp);
        $this->assertSame(0, $code, $nom . "\n" . implode("\n", $sortie));
    }

    /**
     * 2026-11-23 — Vraie conversion d'un devis accepté, sur une table
     * reservation_panels aux colonnes réelles (pas de `quantity`). Avant,
     * l'écriture de `quantity` faisait échouer TOUTE acceptation de devis
     * (« Unknown column 'quantity' »), et un panneau externe était noté
     * « interne ». Aucun test ne convertissait réellement un devis.
     */
    public function test_un_devis_accepte_devient_une_option_avec_ses_panneaux(): void
    {
        foreach (['communes', 'invoice_lines'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->timestamps(); });
        }
        Schema::table('reservations', function (Blueprint $t) { $t->string('origin')->nullable(); });
        Schema::table('external_panels', function (Blueprint $t) { $t->string('availability_status')->nullable(); });
        foreach ([
            '2026_07_21_150448_create_quotes_table.php',
            '2026_07_21_150449_create_quote_lines_table.php',
            '2026_07_21_150450_create_quote_services_table.php',
            '2026_07_21_150451_add_quote_id_to_reservations.php',
            '2026_07_29_100000_add_campaign_dates_to_invoice_and_quote_lines.php',
            '2026_10_29_100000_add_interlocuteur_to_campaigns_reservations_quotes.php',
            '2026_10_30_100000_add_tva_applicable_to_quote_services.php',
            '2026_11_12_100000_add_axes_to_quote_lines.php',
            '2026_11_16_140000_add_remplacee_par_quote_id_to_reservations.php',
        ] as $m) {
            (require base_path("database/migrations/$m"))->up();
        }
        DB::table('external_panels')->insert(['id' => 7, 'reference' => 'EXT-7', 'status' => 'libre']);

        $quote = \App\Models\Quote::create([
            'reference' => 'DEV-TEST-CONV', 'client_id' => 1, 'commercial_user_id' => 2, 'created_by' => 2,
            'title' => 'Essai conversion', 'status' => 'envoye', 'version' => 1, 'valid_days' => 30,
            'remise_pct' => 0, 'tva' => 18, 'public_token' => str_repeat('c', 64),
            'period_start' => '2026-12-01', 'period_end' => '2026-12-31', 'total_a_payer' => 317565,
        ]);
        $quote->lines()->create([
            'designation' => 'P-10', 'panel_id' => 10, 'dimension_m2' => 12, 'pu_ht_mensuel' => 85000,
            'quantite' => 1, 'duree_mois' => 2, 'odp_rate_applique' => 1000, 'tm_rate_applique' => 1000,
        ]);
        $quote->lines()->create([
            'designation' => 'EXT-7', 'external_panel_id' => 7, 'dimension_m2' => 12, 'pu_ht_mensuel' => 40000,
            'quantite' => 1, 'duree_mois' => 1, 'odp_rate_applique' => 0, 'tm_rate_applique' => 0,
        ]);

        $resultat = app(\App\Services\QuoteConverter::class)->convertFromQuote($quote->fresh(['lines', 'client']), 'client:test');

        $this->assertSame('converted', $resultat['status'], 'le devis accepté doit devenir une réservation');
        // Le lien passe par quotes.converted_reservation_id (Reservation ne
        // déclare pas quote_id dans $fillable : dette notée le 23/11).
        $r = DB::table('reservations')->where('id', DB::table('quotes')->where('id', $quote->id)->value('converted_reservation_id'))->first();
        $this->assertNotNull($r, 'le devis pointe vers sa réservation');
        $this->assertSame('en_attente', $r->status, 'choix 2a : une option, pas une réservation confirmée');
        $this->assertSame('option', $r->type);
        $this->assertNotNull($r->bc_attendu_jusqu_au, 'le BC est attendu');

        $interne = DB::table('reservation_panels')->where('reservation_id', $r->id)->where('panel_id', 10)->first();
        $this->assertSame(85000, (int) $interne->unit_price);
        $this->assertSame(170000, (int) $interne->total_price, 'PU × quantité × durée, comme la ligne du devis');
        $this->assertSame('interne', $interne->source);

        $externe = DB::table('reservation_panels')->where('reservation_id', $r->id)->where('external_panel_id', 7)->first();
        $this->assertNotNull($externe, 'le panneau de régie est rattaché');
        $this->assertSame('externe', $externe->source, 'un panneau de régie est noté « externe »');
        $this->assertSame(40000, (int) $externe->total_price);
        $this->assertSame('accepte', DB::table('quotes')->where('id', $quote->id)->value('status'));
    }
}
