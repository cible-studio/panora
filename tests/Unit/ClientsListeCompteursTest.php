<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\ClientController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cartes du haut de la liste Clients (2026-10-30) : elles suivent le filtre
 * de statut, le secteur et la recherche, comme le tableau.
 *
 * Base : 3 clients (2 en Banque, dont 1 avec campagne active), 4 prospects
 * (1 en Banque).
 */
class ClientsListeCompteursTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('ncc')->nullable(); $t->string('email')->nullable();
            $t->string('contact_name')->nullable(); $t->string('phone')->nullable(); $t->string('sector')->nullable();
            $t->string('statut_commercial')->default('client'); $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->string('status'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->timestamps(); $t->softDeletes();
        });
        foreach ([
            ['BNI', 'client', 'Banque & Finance'], ['SGBCI', 'client', 'Banque & Finance'], ['MOOV', 'client', 'Télécommunications'],
            ['ECOBANK', 'prospect', 'Banque & Finance'], ['AUCHAN', 'prospect', null], ['WAVE', 'prospect', null], ['ZTE', 'prospect', null],
        ] as $i => [$nom, $statut, $secteur]) {
            DB::table('clients')->insert(['id' => $i + 1, 'name' => $nom, 'statut_commercial' => $statut, 'sector' => $secteur]);
        }
        DB::table('campaigns')->insert(['client_id' => 1, 'status' => 'actif']);
    }

    private function stats(array $filtres): array
    {
        $m = new \ReflectionMethod(ClientController::class, 'statsListe');

        return $m->invoke(app(ClientController::class), Request::create('/admin/clients', 'GET', $filtres));
    }

    public function test_les_cartes_suivent_le_statut(): void
    {
        $this->assertSame(['total' => 3, 'libelle' => 'Total clients', 'sous_titre' => '+ 4 prospects', 'actifs' => 1, 'unite' => 'client(s)'], $this->stats([]));
        $this->assertSame(['total' => 4, 'libelle' => 'Total prospects', 'sous_titre' => '+ 3 clients', 'actifs' => 0, 'unite' => 'prospect(s)'], $this->stats(['statut' => 'prospect']));
        $this->assertSame(['total' => 7, 'libelle' => 'Clients et prospects', 'sous_titre' => 'dont 4 prospects', 'actifs' => 1, 'unite' => 'fiche(s)'], $this->stats(['statut' => 'tous']));
    }

    public function test_les_cartes_suivent_le_secteur_et_la_recherche_mais_pas_campagne_active(): void
    {
        $s = $this->stats(['sector' => 'Banque & Finance']);
        $this->assertSame([2, '+ 1 prospect', 1], [$s['total'], $s['sous_titre'], $s['actifs']]);

        $s = $this->stats(['search' => 'MOO']);
        $this->assertSame([1, 'portefeuille complet', 0], [$s['total'], $s['sous_titre'], $s['actifs']]);

        // « Avec campagne active » coché : la carte Total garde le total du filtre.
        $this->assertSame(3, $this->stats(['active_only' => '1'])['total']);
    }
}
