<?php

namespace Tests\Unit;

use App\Models\Invoice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Identité du client figée sur la facture (décision du 2026-10-30) : nom,
 * NCC, RCCM, régime, adresse figés quand la facture sort du brouillon ;
 * le brouillon suit la fiche ; une remise en brouillon refige à l'émission.
 */
class IdentiteClientFactureTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_30_110000_add_identite_client_to_invoices.php';

    protected function setUp(): void
    {
        parent::setUp();
        config(['audit.enabled' => false]);

        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('ncc')->nullable(); $t->string('rccm')->nullable();
            $t->string('regime_imposition')->nullable(); $t->text('address')->nullable(); $t->string('email')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->string('status')->default('brouillon');
            $t->date('issued_at')->nullable(); $t->timestamps();
        });
        Schema::create('invoice_status_history', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('invoice_id'); $t->string('from_status')->nullable(); $t->string('to_status');
            $t->unsignedBigInteger('user_id')->nullable(); $t->string('reason')->nullable(); $t->boolean('auto')->default(false); $t->timestamps();
        });

        DB::table('clients')->insert([
            ['id' => 10, 'name' => 'BGFI BANK', 'ncc' => 'CI-111', 'rccm' => 'RC-A', 'regime_imposition' => 'Réel normal', 'address' => 'Plateau'],
            ['id' => 11, 'name' => 'BGFI', 'ncc' => 'CI-222', 'rccm' => null, 'regime_imposition' => null, 'address' => null],
        ]);
    }

    private function migrer(): void
    {
        (require base_path(self::MIGRATION))->up();
    }

    private function renommer(string $nom, string $ncc): void
    {
        DB::table('clients')->where('id', 10)->update(['name' => $nom, 'ncc' => $ncc]);
    }

    public function test_brouillon_suit_la_fiche_emise_garde_son_identite(): void
    {
        $this->migrer();
        $f = Invoice::create(['reference' => 'FAC-1', 'client_id' => 10, 'status' => 'brouillon']);

        $this->renommer('BGFI BANK CI', 'CI-112');
        $this->assertSame(['BGFI BANK CI', 'CI-112', false], [$f->fresh()->identiteClient()['nom'], $f->fresh()->identiteClient()['ncc'], $f->fresh()->identiteClient()['fige']]);

        // Émission : l'identité du moment est figée…
        $f = $f->fresh(); $f->status = 'generee'; $f->save();
        $this->renommer('BGFI', 'CI-999');
        $id = $f->fresh()->identiteClient();
        $this->assertSame(['BGFI BANK CI', 'CI-112', 'RC-A', 'Réel normal', 'Plateau', true, 'BGFI'],
            [$id['nom'], $id['ncc'], $id['rccm'], $id['regime_imposition'], $id['adresse'], $id['fige'], $id['nom_actuel']]);

        // … et ne bouge plus aux étapes suivantes.
        $f = $f->fresh(); $f->status = 'envoyee'; $f->save();
        $this->assertSame('BGFI BANK CI', $f->fresh()->identiteClient()['nom']);
    }

    public function test_remise_en_brouillon_puis_nouvelle_emission(): void
    {
        $this->migrer();
        $f = Invoice::create(['reference' => 'FAC-2', 'client_id' => 10, 'status' => 'envoyee']);
        $this->assertSame('BGFI BANK', $f->fresh()->identiteClient()['nom']); // figée dès la création

        $this->renommer('BGFI BANK SA', 'CI-333');
        $f = $f->fresh(); $f->status = 'brouillon'; $f->save();
        $this->assertFalse($f->fresh()->identiteClient()['fige']);
        $this->assertSame('BGFI BANK SA', $f->fresh()->identiteClient()['nom']);

        $f = $f->fresh(); $f->status = 'generee'; $f->save();
        $this->renommer('AUTRE', 'CI-444');
        $this->assertSame(['BGFI BANK SA', 'CI-333'], [$f->fresh()->identiteClient()['nom'], $f->fresh()->identiteClient()['ncc']]);
    }

    public function test_migration_fige_les_factures_deja_emises_pas_les_brouillons(): void
    {
        DB::table('invoices')->insert([
            ['id' => 1, 'reference' => 'FAC-OLD', 'client_id' => 10, 'status' => 'payee'],
            ['id' => 2, 'reference' => 'FAC-BR', 'client_id' => 10, 'status' => 'brouillon'],
        ]);
        $this->migrer();

        $this->renommer('NOUVEAU NOM', 'CI-000');
        $this->assertSame(['BGFI BANK', 'CI-111'], [Invoice::find(1)->identiteClient()['nom'], Invoice::find(1)->identiteClient()['ncc']]);
        $this->assertSame('NOUVEAU NOM', Invoice::find(2)->identiteClient()['nom']);
        $this->migrer(); // rejouable
    }

    public function test_regroupement_de_fiches_la_facture_garde_son_identite(): void
    {
        $this->migrer();
        $f = Invoice::create(['reference' => 'FAC-3', 'client_id' => 10, 'status' => 'generee']);

        // Le regroupement déplace la facture en base, sans passer par le modèle.
        DB::table('invoices')->where('id', $f->id)->update(['client_id' => 11]);

        $id = $f->fresh()->identiteClient();
        $this->assertSame(['BGFI BANK', 'CI-111', 'BGFI'], [$id['nom'], $id['ncc'], $id['nom_actuel']]);
    }
}
