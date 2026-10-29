<?php

namespace Tests\Unit;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Corbeille des devis (règle de la direction, 2026-10-29) :
 *   · l'admin met à la corbeille tout devis, sauf accepté ou devenu
 *     réservation (même archivé depuis) ;
 *   · le commercial, ses brouillons seulement ;
 *   · l'admin restaure ; un numéro DEV-AAAA-NNN n'est jamais réattribué.
 *
 * Vraies migrations des devis et de l'historique ; tables voisines réduites.
 */
class QuoteCorbeilleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['clients', 'campaigns', 'communes', 'panels', 'external_panels', 'invoice_lines'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->timestamps(); });
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role'); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('quote_id')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        foreach ([
            '2026_06_09_170034_create_audits_table.php',
            '2026_07_21_150448_create_quotes_table.php',
            '2026_07_21_150449_create_quote_lines_table.php',
            '2026_07_21_150450_create_quote_services_table.php',
            '2026_10_29_100000_add_interlocuteur_to_campaigns_reservations_quotes.php',
        ] as $m) {
            (require base_path("database/migrations/$m"))->up();
        }

        DB::table('clients')->insert(['id' => 10, 'name' => 'ORANGE CI']);
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Admin', 'role' => 'admin'],
            ['id' => 2, 'name' => 'K. Yao', 'role' => 'commercial'],
            ['id' => 3, 'name' => 'A. Traoré', 'role' => 'commercial'],
        ]);
    }

    private function devis(string $statut, int $commercial = 2, array $attr = []): Quote
    {
        return Quote::create($attr + [
            'reference' => Quote::generateReference(), 'client_id' => 10, 'commercial_user_id' => $commercial, 'created_by' => $commercial,
            'title' => 'Devis ' . $statut, 'status' => $statut, 'version' => 1, 'valid_days' => 30,
            'remise_pct' => 0, 'tva' => 18, 'public_token' => bin2hex(random_bytes(32)),
        ]);
    }

    private function peut(int $userId, Quote $quote): bool
    {
        return Gate::forUser(User::findOrFail($userId))->allows('delete', $quote);
    }

    public function test_un_numero_n_est_jamais_reattribue(): void
    {
        $this->devis('brouillon');
        $this->devis('brouillon');
        $troisieme = $this->devis('brouillon');
        $annee = now()->year;
        $this->assertSame("DEV-{$annee}-003", $troisieme->reference);

        $troisieme->delete();
        $this->assertSame("DEV-{$annee}-004", Quote::generateReference()); // avant : 003, déjà pris
    }

    public function test_admin_tout_sauf_accepte_ou_devenu_reservation(): void
    {
        foreach (['brouillon', 'envoye', 'en_negociation', 'expire', 'refuse', 'archive'] as $statut) {
            $this->assertTrue($this->peut(1, $this->devis($statut)), "admin, devis $statut");
        }
        $this->assertFalse($this->peut(1, $this->devis('accepte')));
        $this->assertFalse($this->peut(1, $this->devis('accepte_avec_conflit')));

        // Devenu réservation, par l'une ou l'autre trace.
        $resa = DB::table('reservations')->insertGetId(['quote_id' => null]);
        $this->assertFalse($this->peut(1, $this->devis('archive', attr: ['converted_reservation_id' => $resa])));
        $lie = $this->devis('envoye');
        DB::table('reservations')->insert(['quote_id' => $lie->id]);
        $this->assertFalse($this->peut(1, $lie));

        // Réservation supprimée depuis : le devis a quand même été accepté.
        $ancien = $this->devis('archive');
        DB::table('reservations')->insert(['quote_id' => $ancien->id, 'deleted_at' => now()]);
        $this->assertFalse($this->peut(1, $ancien));
    }

    public function test_un_devis_accepte_puis_archive_reste_protege(): void
    {
        $q = $this->devis('archive');
        DB::table('audits')->insert([
            'event' => 'updated', 'auditable_type' => Quote::class, 'auditable_id' => $q->id,
            'old_values' => '{"status":"envoye"}', 'new_values' => '{"status":"accepte_avec_conflit"}',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue($q->aEteAccepte());
        $this->assertFalse($this->peut(1, $q));
    }

    public function test_commercial_ses_brouillons_seulement(): void
    {
        $this->assertTrue($this->peut(2, $this->devis('brouillon', 2)));
        $this->assertFalse($this->peut(3, $this->devis('brouillon', 2)));  // brouillon d'un autre
        $this->assertFalse($this->peut(2, $this->devis('envoye', 2)));     // déjà envoyé
        $this->assertFalse($this->peut(2, $this->devis('refuse', 2)));
    }

    public function test_corbeille_puis_restauration_par_l_admin(): void
    {
        $q = $this->devis('envoye');
        $q->delete();
        $this->assertNull(Quote::find($q->id));
        $this->assertSame(1, Quote::onlyTrashed()->count());

        $this->assertTrue(Gate::forUser(User::findOrFail(1))->allows('restore', $q));
        $this->assertFalse(Gate::forUser(User::findOrFail(2))->allows('restore', $q));

        $q->restore();
        $this->assertSame($q->reference, Quote::findOrFail($q->id)->reference);
    }
}
