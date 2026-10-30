<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\InvoiceController;
use App\Models\Campaign;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Règle unique du CA (validée le 2026-10-30) :
 *   - CA facturé = toutes les factures sauf brouillon, annulée et avoir ;
 *   - dû = ces mêmes factures moins les versements ;
 *   - déjà facturé d'une campagne = toutes sauf l'annulée, brouillons compris ;
 *   - « Marquer soldée » enregistre un versement du solde, que le retour en
 *     brouillon retire.
 */
class RegleCaFactureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['audit.enabled' => false]);

        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('status')->nullable(); $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedBigInteger('campaign_id')->nullable(); $t->string('status')->default('brouillon');
            $t->unsignedBigInteger('credit_note_for_id')->nullable();
            $t->bigInteger('amount')->default(0); $t->bigInteger('amount_ttc')->default(0); $t->bigInteger('total_a_payer')->default(0);
            $t->date('issued_at')->nullable(); $t->timestamp('paid_at')->nullable();
            $t->timestamp('locked_at')->nullable(); $t->unsignedBigInteger('locked_by')->nullable();
            $t->string('litige_motif')->nullable(); $t->text('litige_note')->nullable(); $t->timestamp('litige_opened_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable(); $t->unsignedBigInteger('commercial_user_id')->nullable();
            $t->timestamps();
        });
        Schema::create('invoice_payments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('invoice_id'); $t->date('paid_at'); $t->bigInteger('montant');
            $t->string('mode'); $t->string('reference')->nullable(); $t->string('bank')->nullable();
            $t->boolean('is_acompte')->default(false); $t->string('attachment_path')->nullable();
            $t->string('attachment_original_name')->nullable(); $t->text('note')->nullable();
            $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('invoice_schedules', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('invoice_id'); $t->date('due_date')->nullable(); $t->bigInteger('amount')->default(0);
            $t->unsignedTinyInteger('order_index')->default(0); $t->date('paid_at')->nullable();
            $t->unsignedBigInteger('paid_payment_id')->nullable(); $t->timestamps();
        });
        Schema::create('invoice_status_history', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('invoice_id'); $t->string('from_status')->nullable(); $t->string('to_status');
            $t->unsignedBigInteger('user_id')->nullable(); $t->string('reason')->nullable(); $t->boolean('auto')->default(false); $t->timestamps();
        });

        // Les droits ne sont pas l'objet de ce test.
        Gate::before(fn ($user = null) => true);
    }

    private function facture(string $ref, string $status, int $ttc, array $extra = []): Invoice
    {
        $id = DB::table('invoices')->insertGetId(array_merge([
            'reference' => $ref, 'status' => $status, 'amount' => $ttc, 'amount_ttc' => $ttc, 'total_a_payer' => $ttc,
        ], $extra));

        return Invoice::find($id);
    }

    private function versement(Invoice $f, int $montant, string $reference): void
    {
        DB::table('invoice_payments')->insert([
            'invoice_id' => $f->id, 'paid_at' => now()->toDateString(), 'montant' => $montant,
            'mode' => 'virement', 'reference' => $reference,
        ]);
    }

    private function controleur(): InvoiceController
    {
        return app(InvoiceController::class);
    }

    public function test_ca_facture_exclut_brouillon_annulee_et_avoir(): void
    {
        foreach (['brouillon', 'generee', 'validee', 'envoyee', 'partiellement_payee', 'payee', 'en_retard', 'litige', 'annulee'] as $s) {
            $this->facture("F-{$s}", $s, 1000);
        }
        $this->facture('AV-1', 'envoyee', -500, ['credit_note_for_id' => 1]);

        $this->assertEqualsCanonicalizing(
            ['generee', 'validee', 'envoyee', 'partiellement_payee', 'payee', 'en_retard', 'litige'],
            Invoice::facturees()->pluck('status')->all()
        );
        $this->assertSame(7000, (int) Invoice::sommeMontantTotal(Invoice::query()->facturees()));
        // Même règle sur une facture déjà chargée (PDF de la liste).
        $this->assertEqualsCanonicalizing(
            Invoice::facturees()->pluck('id')->all(),
            Invoice::all()->filter->compteAuCa()->pluck('id')->all()
        );
    }

    public function test_deja_facture_d_une_campagne_compte_tout_sauf_l_annulee(): void
    {
        $montants = ['brouillon' => 100, 'generee' => 200, 'validee' => 300, 'partiellement_payee' => 400,
                     'en_retard' => 500, 'litige' => 600, 'annulee' => 700];
        foreach ($montants as $s => $m) {
            $this->facture("C-{$s}", $s, $m, ['campaign_id' => 5]);
        }
        $this->facture('AUTRE', 'envoyee', 9999, ['campaign_id' => 6]);

        $campagne = new Campaign();
        $campagne->id = 5;

        // Avant : seuls brouillon / envoyée / payée comptaient (100 ici).
        $this->assertSame(2100.0, $campagne->alreadyBilledHt());
    }

    public function test_marquer_soldee_enregistre_le_versement_du_solde(): void
    {
        $f = $this->facture('F-ENV', 'envoyee', 1000000);

        $this->controleur()->markPaid(Request::create('/', 'PATCH'), $f, app(PaymentService::class));

        $f = $f->fresh(['payments']);
        $this->assertSame('payee', $f->status);
        $this->assertNotNull($f->paid_at);
        $this->assertCount(1, $f->payments);
        $this->assertSame(1000000, $f->payments->first()->montant);
        $this->assertStringStartsWith('SOLDE_AUTO_', $f->payments->first()->reference);
        $this->assertSame(0.0, $f->remainingAmount());
    }

    public function test_marquer_soldee_un_brouillon_puis_retour_en_brouillon(): void
    {
        $f = $this->facture('F-BR', 'brouillon', 1000000);
        $this->versement($f, 400000, 'VIR-CLIENT');

        $this->controleur()->markPaid(Request::create('/', 'PATCH'), $f, app(PaymentService::class));

        $f = $f->fresh(['payments']);
        $this->assertSame('payee', $f->status);
        // Seul le reste (600 000) est versé : pas de double encaissement.
        $this->assertSame([400000, 600000], $f->payments->sortBy('id')->pluck('montant')->values()->all());

        // Retour en brouillon : le versement automatique part, celui du client reste.
        $this->controleur()->revertDraft(Request::create('/', 'PATCH'), $f, app(PaymentService::class));

        $f = $f->fresh(['payments']);
        $this->assertSame('brouillon', $f->status);
        $this->assertSame(['VIR-CLIENT'], $f->payments->pluck('reference')->all());
        $this->assertSame(600000.0, $f->remainingAmount());
    }

    public function test_tuiles_de_la_liste_suivent_la_regle(): void
    {
        $generee = $this->facture('F-GEN', 'generee', 1000);
        $partielle = $this->facture('F-PART', 'partiellement_payee', 2000);
        $litige = $this->facture('F-LIT', 'litige', 3000);
        $brouillon = $this->facture('F-BR', 'brouillon', 4000);
        $annulee = $this->facture('F-ANN', 'annulee', 5000);
        $this->versement($partielle, 500, 'P1');
        $this->versement($brouillon, 100, 'P2');
        $this->versement($annulee, 200, 'P3');

        $montants = (new \ReflectionMethod(InvoiceController::class, 'montantsListe'))
            ->invoke($this->controleur(), false, 0);

        // Encaissé : seul le versement sur une facture comptée au CA.
        $this->assertSame(500, $montants['encaisse']);
        // Restant : générée et litige comprises (avant : oubliées).
        $this->assertSame(1000 + 1500 + 3000, $montants['restant']);
    }
}
