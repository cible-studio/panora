<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\QuoteController;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Fiches client supprimées (soft delete) et validation (2026-10-30) :
 *   · NCC : l'index clients_ncc_unique couvre les fiches supprimées, la
 *     validation doit donc les compter (sinon 500 à l'INSERT/UPDATE) ;
 *   · création (réservation, devis…) : une fiche supprimée est refusée ;
 *     en modification d'un devis, son client actuel reste accepté.
 */
class ClientSupprimeValidationTest extends TestCase
{
    private const NCC_PRIS = 'Ce NCC est déjà utilisé par une autre fiche (y compris une fiche supprimée).';
    private const CLIENT_SUPPRIME = 'Ce client est introuvable ou a été supprimé.';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('ncc', 20)->nullable()->unique();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('client_contacts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->string('name')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('status')->default('libre'); $t->softDeletes(); $t->timestamps();
        });

        DB::table('clients')->insert([
            ['id' => 10, 'name' => 'ORANGE CI', 'ncc' => 'CI-0010', 'deleted_at' => null],
            ['id' => 12, 'name' => 'ANCIEN CLIENT', 'ncc' => 'CI-0012', 'deleted_at' => now()],
            ['id' => 13, 'name' => 'AUTRE ANCIEN', 'ncc' => null, 'deleted_at' => now()],
        ]);
    }

    // ─── NCC ──────────────────────────────────────────────────────

    private function erreursNcc(StoreClientRequest|UpdateClientRequest $requete, ?string $ncc): array
    {
        return Validator::make(['ncc' => $ncc], ['ncc' => $requete->rules()['ncc']], $requete->messages())
            ->errors()->get('ncc');
    }

    private function requeteModification(int $clientId): UpdateClientRequest
    {
        $requete = new UpdateClientRequest();
        $route = (new Route('PUT', 'admin/clients/{client}', []))->bind($requete);
        $route->setParameter('client', Client::withTrashed()->findOrFail($clientId));
        $requete->setRouteResolver(fn () => $route);

        return $requete;
    }

    public function test_creation_le_ncc_d_une_fiche_supprimee_est_refuse_proprement(): void
    {
        $this->assertSame([self::NCC_PRIS], $this->erreursNcc(new StoreClientRequest(), 'CI-0012'));
        $this->assertSame([self::NCC_PRIS], $this->erreursNcc(new StoreClientRequest(), 'CI-0010'));
        $this->assertSame([], $this->erreursNcc(new StoreClientRequest(), 'CI-0099'));
        $this->assertSame([], $this->erreursNcc(new StoreClientRequest(), null));
    }

    public function test_modification_la_fiche_garde_son_ncc_mais_pas_celui_d_une_fiche_supprimee(): void
    {
        $this->assertSame([], $this->erreursNcc($this->requeteModification(10), 'CI-0010'));
        $this->assertSame([self::NCC_PRIS], $this->erreursNcc($this->requeteModification(10), 'CI-0012'));
    }

    // ─── Client supprimé à la création ────────────────────────────

    public function test_reservation_refusee_sur_une_fiche_supprimee(): void
    {
        $requete = new StoreReservationRequest();
        $regles = ['client_id' => $requete->rules()['client_id']];

        $this->assertSame(
            [self::CLIENT_SUPPRIME],
            Validator::make(['client_id' => 12], $regles, $requete->messages())->errors()->get('client_id')
        );
        $this->assertTrue(Validator::make(['client_id' => 10], $regles)->passes());
    }

    private function validerDevis(int $clientId, ?Quote $devis = null): array
    {
        $requete = Request::create('/admin/quotes', 'POST', [
            'client_id' => $clientId, 'title' => 'Affichage Noël',
            'lines' => [['designation' => 'ABJ-100', 'dimension_m2' => 12, 'pu_ht_mensuel' => 100000, 'quantite' => 1, 'duree_mois' => 1]],
        ]);
        $methode = new \ReflectionMethod(QuoteController::class, 'validateQuotePayload');

        try {
            $methode->invoke(app(QuoteController::class), $requete, $devis);
            return [];
        } catch (ValidationException $e) {
            return $e->errors()['client_id'] ?? [];
        }
    }

    public function test_devis_fiche_supprimee_refusee_a_la_creation_et_vers_une_autre_fiche(): void
    {
        $this->assertSame([], $this->validerDevis(10));
        $this->assertSame([self::CLIENT_SUPPRIME], $this->validerDevis(12));

        // Modification : le client actuel (même supprimé depuis) reste valide…
        $devis = new Quote(['client_id' => 12]);
        $this->assertSame([], $this->validerDevis(12, $devis));
        // … mais pas le passage vers une autre fiche supprimée.
        $this->assertSame([self::CLIENT_SUPPRIME], $this->validerDevis(13, $devis));
    }
}
