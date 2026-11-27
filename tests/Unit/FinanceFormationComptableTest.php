<?php

namespace Tests\Unit;

use App\Console\Commands\InvoiceAlertsCommand;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\RapportController;
use App\Models\Invoice;
use App\Models\InvoiceAlert;
use App\Models\InvoicePayment;
use App\Models\InvoiceSchedule;
use App\Models\InvoiceService;
use App\Models\User;
use App\Policies\InvoicePolicy;
use App\Services\AdminAlertNotifier;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Corrections finance validées le 2026-11-26 avant la vidéo des comptables
 * (docs/formation/plan-videos-formation.md, section 3, points 3 à 24) :
 * échéance et « en retard » uniques, relances calées sur l'échéance,
 * numérotation sans doublon, droits du comptable et du commercial,
 * totaux affichés identiques partout.
 */
class FinanceFormationComptableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 9));
        config(['billing.delai_paiement_jours' => 30]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Outils ─────────────────────────────────────────────────────

    private function facture(array $attributs = [], array $echeances = [], int $paye = 0): Invoice
    {
        $f = (new Invoice())->forceFill(array_merge([
            'id' => 1, 'reference' => 'FAC-2026-001', 'status' => 'envoyee',
            'issued_at' => '2026-10-01', 'total_a_payer' => 1000000, 'amount_ttc' => 900000,
        ], $attributs));
        $f->setRelation('schedules', collect($echeances));
        $f->setRelation('payments', collect($paye > 0 ? [(new InvoicePayment())->forceFill(['montant' => $paye])] : []));

        return $f;
    }

    private function echeance(int $id, string $date, ?string $payeeLe = null): InvoiceSchedule
    {
        return (new InvoiceSchedule())->forceFill([
            'id' => $id, 'due_date' => $date, 'amount' => 500000, 'label' => 'Échéance ' . $id, 'paid_at' => $payeeLe,
        ]);
    }

    private function etape(Invoice $f, string $kind, int $offset, string $date, ?InvoiceSchedule $s = null, string $canal = 'email'): InvoiceAlert
    {
        $a = (new InvoiceAlert())->forceFill([
            'id' => 9, 'invoice_id' => $f->id, 'schedule_id' => $s?->id,
            'kind' => $kind, 'offset_days' => $offset, 'due_date' => $date, 'channel' => $canal, 'status' => 'pending',
        ]);
        $a->setRelation('invoice', $f);
        $a->setRelation('schedule', $s);

        return $a;
    }

    // ── Points 10 : échéance et « en retard » ──────────────────────

    public function test_sans_echeancier_l_echeance_est_l_emission_plus_30_jours(): void
    {
        $this->assertSame('2026-10-31', $this->facture()->echeance()->toDateString());
    }

    public function test_avec_echeancier_c_est_la_plus_ancienne_echeance_non_soldee(): void
    {
        $f = $this->facture([], [
            $this->echeance(1, '2026-10-15', '2026-10-14'),
            $this->echeance(2, '2026-12-15'),
            $this->echeance(3, '2026-11-15'),
        ]);
        $this->assertSame('2026-11-15', $f->echeance()->toDateString());

        $soldee = $this->facture([], [$this->echeance(1, '2026-10-15', '2026-10-14')]);
        $this->assertNull($soldee->echeance(), 'échéancier entièrement soldé : plus d\'échéance');
    }

    public function test_en_retard_echeance_depassee_et_reste_a_payer(): void
    {
        $this->assertTrue($this->facture()->isOverdue(), 'émise le 01/10, sans échéancier : due le 31/10');
        $this->assertFalse($this->facture(['issued_at' => '2026-11-10'])->isOverdue(), 'due le 10/12');
        $this->assertFalse($this->facture([], [], 1000000)->isOverdue(), 'soldée');
        $this->assertFalse($this->facture(['status' => 'litige'])->isOverdue(), 'litige : jamais en retard');
        $this->assertFalse($this->facture(['status' => 'brouillon'])->isOverdue(), 'pas encore partie');
        $this->assertTrue($this->facture(['status' => 'partiellement_payee'], [], 400000)->isOverdue());
        $this->assertSame('en_retard', $this->facture()->paymentStatus());
    }

    // ── Points 11 et 12 : relances calées sur l'échéance ───────────

    public function test_le_calendrier_est_j_moins_7_puis_j_plus_7_15_30(): void
    {
        $this->assertSame(
            [['before', -7], ['after', 7], ['after', 15], ['after', 30]],
            InvoiceAlertsCommand::etapes()
        );
    }

    public function test_les_echeances_suivies(): void
    {
        $sans = InvoiceAlertsCommand::echeancesDe($this->facture());
        $this->assertCount(1, $sans);
        $this->assertNull($sans[0][0], 'sans échéancier : échéance par défaut, sans schedule_id');
        $this->assertSame('2026-10-31', $sans[0][1]->toDateString());

        $avec = InvoiceAlertsCommand::echeancesDe($this->facture([], [
            $this->echeance(1, '2026-10-15', '2026-10-14'),
            $this->echeance(2, '2026-12-15'),
        ]));
        $this->assertSame([2], array_column($avec, 0), 'seules les échéances non soldées');
    }

    public function test_une_relance_due_part(): void
    {
        // Émise le 10/10 : échéance le 09/11, J+15 le 24/11 (rattrapable).
        $f = $this->facture(['issued_at' => '2026-10-10']);
        $this->assertNull(InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($f, 'after', 15, '2026-11-24')));

        // Émise le 03/11 : échéance le 03/12, rappel J-7 aujourd'hui.
        $g = $this->facture(['issued_at' => '2026-11-03']);
        $this->assertNull(InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($g, 'before', -7, '2026-11-26')));
    }

    public function test_aucune_relance_pour_une_facture_soldee_annulee_ou_en_litige(): void
    {
        foreach (['payee', 'annulee', 'litige'] as $statut) {
            $f = $this->facture(['status' => $statut]);
            $this->assertNotNull(
                InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($f, 'after', 15, '2026-11-15')),
                "statut {$statut} : pas de relance"
            );
        }
        $paye = $this->facture([], [], 1000000);
        $this->assertNotNull(InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($paye, 'after', 15, '2026-11-15')));
    }

    public function test_une_etape_retiree_trop_ancienne_ou_deplacee_ne_part_pas(): void
    {
        $f = $this->facture();
        $this->assertStringContainsString('retirée',
            InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($f, 'before', -15, '2026-10-16')));
        $this->assertStringContainsString('plus de 7 jours',
            InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($f, 'after', 7, '2026-11-07')));
        $this->assertStringContainsString('modifiée',
            InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($f, 'after', 15, '2026-11-20')));
        $this->assertNotNull(
            InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($f, 'before', -7, '2026-10-24')),
            'rappel J-7 d\'une échéance déjà passée'
        );
    }

    public function test_une_echeance_soldee_ou_remplacee_ne_relance_plus(): void
    {
        $soldee = $this->echeance(2, '2026-11-10', '2026-11-12');
        $f = $this->facture([], [$soldee, $this->echeance(3, '2026-12-10')]);
        $this->assertSame('Échéance soldée.', InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($f, 'after', 15, '2026-11-25', $soldee)));

        $avecEcheancier = $this->facture([], [$this->echeance(3, '2026-12-10')]);
        $this->assertStringContainsString('échéancier',
            InvoiceAlertsCommand::raisonDeNePasEnvoyer($this->etape($avecEcheancier, 'after', 15, '2026-11-15')));
    }

    public function test_les_anciennes_commandes_de_relance_ne_sont_plus_planifiees(): void
    {
        $planning = file_get_contents(base_path('routes/console.php'));
        $this->assertStringNotContainsString("Schedule::command('finance:check-due-dates')", $planning);
        $this->assertStringNotContainsString("Schedule::command('invoices:send-reminders')", $planning);
        $this->assertStringContainsString("Schedule::command('invoices:alerts')", $planning);
    }

    // ── Point 13 : le comptable reçoit les mails de finance ────────

    public function test_les_mails_comptable_vont_aux_comptables_sinon_aux_admins(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role');
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        DB::table('users')->insert([
            ['name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin', 'is_active' => true],
            ['name' => 'Compta', 'email' => 'compta@example.com', 'role' => 'comptable', 'is_active' => true],
            // 2026-11-27 (point 18 des taxes) : un compte désactivé ne reçoit rien.
            ['name' => 'Ancien', 'email' => 'ancien@example.com', 'role' => 'comptable', 'is_active' => false],
        ]);
        $resoudre = new \ReflectionMethod(AdminAlertNotifier::class, 'resolveRecipients');

        $this->assertSame(['compta@example.com'], $resoudre->invoke(null, ['comptable']));
        $this->assertEqualsCanonicalizing(['compta@example.com', 'admin@example.com'], $resoudre->invoke(null, ['comptable', 'admin']));

        DB::table('users')->where('email', 'compta@example.com')->update(['is_active' => false]);
        $this->assertSame(['admin@example.com'], $resoudre->invoke(null, ['comptable']), 'comptables tous désactivés : les admins');

        DB::table('users')->where('role', 'comptable')->delete();
        $this->assertSame(['admin@example.com'], $resoudre->invoke(null, ['comptable']), 'sans comptable : les admins');
    }

    public function test_le_comptable_voit_les_alertes_d_echeance(): void
    {
        $types = \App\Services\AlertService::broadcastTypesForRole('comptable');
        $this->assertContains('echeance_proche_j7', $types);
        $this->assertContains('echeance_depassee', $types);
        $this->assertContains('facture_envoyee', $types);
    }

    // ── Points 15 à 17 : droits et boutons ─────────────────────────

    public function test_la_facturation_reste_au_comptable_et_a_l_admin(): void
    {
        $policy = new InvoicePolicy();
        $commercial = (new User())->forceFill(['id' => 5, 'role' => UserRole::COMMERCIAL]);
        $comptable  = (new User())->forceFill(['id' => 6, 'role' => UserRole::COMPTABLE]);
        $f = $this->facture(['status' => 'brouillon']);

        $this->assertFalse($policy->create($commercial));
        $this->assertFalse($policy->update($commercial, $f));
        $this->assertTrue($policy->create($comptable));
        $this->assertTrue($policy->update($comptable, $f));
        $this->assertFalse($policy->update($comptable, $this->facture(['locked_at' => '2026-11-01 10:00:00'])), 'verrouillée : admin seul');
        $this->assertFalse($policy->revertDraft($comptable, $f));
        $this->assertFalse($policy->markCancelled($comptable, $f));
    }

    public function test_rapports_du_comptable(): void
    {
        $onglets = RapportController::ONGLETS_PAR_ROLE['comptable'];
        foreach (['ca', 'clients', 'zones'] as $o) {
            $this->assertContains($o, $onglets);
        }
        $this->assertNotContains('insights', $onglets, 'Insights : direction');
        $this->assertContains('comptable', RapportController::ACCES['zones']);
        $this->assertNotContains('comptable', RapportController::ACCES['occupation'], 'exports d\'occupation : MP');
        $this->assertNotContains('comptable', RapportController::ACCES['direction']);
        $this->assertSame('role:admin,mediaplanner,comptable', RapportController::middlewareAcces('zones'));
    }

    // ── Point 20 : transitions alignées sur les boutons ───────────

    public function test_valider_et_figer_un_brouillon_est_permis(): void
    {
        $this->assertContains('validee', Invoice::MANUAL_TRANSITIONS['brouillon']);
        $this->assertContains('envoyee', Invoice::MANUAL_TRANSITIONS['brouillon']);
        $this->assertContains('envoyee', Invoice::MANUAL_TRANSITIONS['litige'], 'Sortir du litige');
    }

    // ── Point 21 : numérotation ────────────────────────────────────

    public function test_le_numero_suit_le_plus_grand_de_l_annee(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->timestamps();
        });
        $this->assertSame('FAC-2026-001', Invoice::prochaineReference(2026));

        DB::table('invoices')->insert([
            ['reference' => 'FAC-2026-007'], ['reference' => 'FAC-2026-012'],
            ['reference' => 'FAC-2025-099'], ['reference' => 'AV-2026-050'],
        ]);
        $this->assertSame('FAC-2026-013', Invoice::prochaineReference(2026), 'plus grand + 1, pas le nombre de factures');
        $this->assertSame('FAC-2025-100', Invoice::prochaineReference(2025));

        DB::table('invoices')->where('reference', 'FAC-2026-012')->delete();
        $this->assertSame('FAC-2026-008', Invoice::prochaineReference(2026));

        $recu = Invoice::creerAvecNumero(2026, fn (string $numero) => $numero);
        $this->assertSame('FAC-2026-008', $recu, 'la création reçoit le numéro sous verrou');
    }

    public function test_plus_aucun_numero_calcule_en_comptant(): void
    {
        foreach ([
            'app/Http/Controllers/Admin/InvoiceController.php',
            'app/Http/Controllers/Admin/CampaignController.php',
            'app/Services/InvoiceFromCampaignBuilder.php',
        ] as $fichier) {
            $source = file_get_contents(base_path($fichier));
            $this->assertDoesNotMatchRegularExpression("/whereYear\\('created_at'[^;]*->count\\(\\)/", $source, $fichier);
        }
    }

    // ── Déjà facturé d'une campagne : HT après remise (règle A, 27/11) ──

    public function test_le_deja_facture_compte_le_ht_apres_remise(): void
    {
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedBigInteger('reservation_id')->nullable(); $t->string('status')->default('actif');
            $t->decimal('total_amount', 14, 2)->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->string('status'); $t->unsignedBigInteger('amount')->default(0); $t->unsignedBigInteger('net_ht')->nullable();
            $t->unsignedBigInteger('credit_note_for_id')->nullable(); $t->timestamps();
        });
        // Campagne issue d'un devis : 240 000 avant remise de 5 % → 228 000.
        DB::table('campaigns')->insert(['id' => 2, 'name' => 'KALABA – Fêtes', 'total_amount' => 228000]);
        DB::table('invoices')->insert([
            ['reference' => 'FAC-2026-005', 'campaign_id' => 2, 'status' => 'envoyee', 'amount' => 240000, 'net_ht' => 228000],
            ['reference' => 'FAC-2026-009', 'campaign_id' => 2, 'status' => 'annulee', 'amount' => 50000, 'net_ht' => 50000],
        ]);
        $c = \App\Models\Campaign::find(2);
        $this->assertSame(228000.0, $c->alreadyBilledHt(), 'après remise ; l\'annulée ne compte pas');
        $this->assertSame(0.0, $c->remainingToBillHt(), 'plus de « facturé au-delà du total »');

        // Facture ancienne sans HT après remise renseigné : repli sur le HT.
        DB::table('campaigns')->insert(['id' => 3, 'name' => 'Ancienne', 'total_amount' => 300000]);
        DB::table('invoices')->insert(['reference' => 'FAC-2025-001', 'campaign_id' => 3, 'status' => 'payee', 'amount' => 100000, 'net_ht' => null]);
        $this->assertSame(100000.0, \App\Models\Campaign::find(3)->alreadyBilledHt());
        $this->assertSame(200000.0, \App\Models\Campaign::find(3)->remainingToBillHt());

        $this->assertSame(228000, (new Invoice())->forceFill(['amount' => 240000, 'net_ht' => 228000])->htApresRemise());
        $this->assertSame(100000, (new Invoice())->forceFill(['amount' => 100000, 'net_ht' => 0])->htApresRemise());
    }

    public function test_la_fiche_campagne_charge_le_ht_et_le_statut_des_factures(): void
    {
        $ctrl = file_get_contents(base_path('app/Http/Controllers/Admin/CampaignController.php'));
        $this->assertStringContainsString("'invoices:id,campaign_id,reference,status,amount,net_ht,amount_ttc'", $ctrl,
            'sans ces colonnes, les cartes affichaient « 0 HT » et un statut vide');
        $vue = file_get_contents(base_path('resources/views/admin/campaigns/show.blade.php'));
        $this->assertStringContainsString('Invoice::COULEURS_STATUT', $vue);
        $this->assertStringContainsString('$inv->htApresRemise()', $vue);
        $this->assertCount(9, Invoice::COULEURS_STATUT, 'les 9 statuts');
    }

    // ── Point 3 : totaux affichés ──────────────────────────────────

    public function test_les_totaux_affiches_reprennent_ceux_du_pdf(): void
    {
        $f = $this->facture([
            'amount' => 1000000, 'net_ht' => 900000, 'remise_pct' => 10, 'tva' => 18,
            'tva_amount' => 162000, 'amount_ttc' => 1062000,
            'tsp_amount' => 27000, 'tm_total' => 12000, 'odp_total' => 60000,
            'total_a_payer' => 1062000 + 27000 + 12000 + 60000 + 118000 + 50000,
        ]);
        $f->setRelation('services', collect([
            (new InvoiceService())->forceFill(['prix_ht' => 50000, 'quantite' => 2, 'tva_applicable' => true]),
            (new InvoiceService())->forceFill(['prix_ht' => 50000, 'quantite' => 1, 'tva_applicable' => false]),
        ]));

        $tx = $f->totauxAffiches();
        $this->assertSame(100000, $tx['remise']);
        $this->assertSame(150000, $tx['services_ht']);
        $this->assertSame(168000, $tx['services_ttc'], 'quantité comprise : 2 × 50 000 + TVA, + 50 000 sans TVA');
        $this->assertSame(162000 + 18000, $tx['tva']);
        $this->assertSame(99000, $tx['autres_taxes']);
        $this->assertSame($tx['ttc'] + $tx['autres_taxes'], $tx['total_a_payer']);
    }
}
