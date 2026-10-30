<?php

namespace Tests\Unit;

use App\Http\Controllers\Client\ClientQuoteController;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Espace client « Devis » (2026-10-30) : le client connecté (guard
 * 'client', comme les autres écrans du portail) voit ses devis, jamais
 * ceux d'un autre. Avant, le contrôleur lisait session('client_id'),
 * écrite nulle part : liste vide et 403 sur chaque devis.
 *
 * Vraies migrations des devis ; tables voisines réduites.
 */
class EspaceClientDevisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['campaigns', 'communes', 'panels', 'external_panels', 'invoice_lines'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->timestamps(); });
        }
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable();
            $t->rememberToken(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('whatsapp_number')->nullable();
            $t->string('role'); $t->boolean('is_active')->default(true); $t->timestamps();
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

        DB::table('clients')->insert([
            ['id' => 10, 'name' => 'ORANGE CI', 'email' => 'contact@orange.ci'],
            ['id' => 11, 'name' => 'MTN CI', 'email' => 'contact@mtn.ci'],
        ]);
        DB::table('users')->insert(['id' => 2, 'name' => 'K. Yao', 'role' => 'commercial']);
    }

    private function devis(int $clientId, string $statut = 'envoye'): Quote
    {
        return Quote::create([
            'reference' => Quote::generateReference(), 'client_id' => $clientId, 'commercial_user_id' => 2, 'created_by' => 2,
            'title' => 'Devis ' . $statut, 'status' => $statut, 'version' => 1, 'valid_days' => 30,
            'remise_pct' => 0, 'tva' => 18, 'public_token' => bin2hex(random_bytes(32)),
        ]);
    }

    private function controleur(): ClientQuoteController
    {
        return app(ClientQuoteController::class);
    }

    private function refuse(callable $appel): void
    {
        try {
            $appel();
            $this->fail('403 attendu.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_le_client_connecte_voit_ses_devis_et_seulement_les_siens(): void
    {
        $sien     = $this->devis(10);
        $brouillon = $this->devis(10, 'brouillon');
        $autre    = $this->devis(11);
        Auth::guard('client')->login(Client::findOrFail(10));

        $ids = $this->controleur()->index()->getData()['quotes']->pluck('id')->all();

        $this->assertSame([$sien->id], $ids); // ni le brouillon, ni le devis de MTN
        $this->assertSame('client.quotes.show', $this->controleur()->show($sien)->name());
        $this->refuse(fn () => $this->controleur()->show($autre));
        $this->refuse(fn () => $this->controleur()->pdf($autre));
        $this->refuse(fn () => $this->controleur()->refuse(request(), $autre));
        $this->assertSame('envoye', $autre->fresh()->status->value); // aucune décision prise
    }

    public function test_un_membre_d_equipe_voit_les_devis_de_son_entreprise(): void
    {
        // Connexion ClientUser : guard 'client' sur la fiche parente + client_user_id en session.
        $sien = $this->devis(10);
        $this->devis(11);
        Auth::guard('client')->login(Client::findOrFail(10));
        session(['client_user_id' => 5, 'client_user_role' => 'viewer']);

        $ids = $this->controleur()->index()->getData()['quotes']->pluck('id')->all();

        $this->assertSame([$sien->id], $ids);
    }

    public function test_sans_client_connecte_aucun_devis_n_est_accessible(): void
    {
        $devis = $this->devis(10);

        $this->refuse(fn () => $this->controleur()->show($devis));
    }
}
