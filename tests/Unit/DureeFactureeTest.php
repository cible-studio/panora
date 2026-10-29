<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\DureeFactureeController;
use App\Http\Controllers\Admin\PropositionController;
use App\Models\Campaign;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Durée facturée en langage courant (2026-10-29) : les écrans la demandent au
 * serveur au lieu de recopier la règle en JavaScript. Le libellé doit dire,
 * sans décimales, exactement ce que facture Campaign::computeBillableMonths.
 */
class DureeFactureeTest extends TestCase
{
    private function duree(string $debut, string $fin): array
    {
        return Campaign::dureeFacturee(Carbon::parse($debut), Carbon::parse($fin));
    }

    /** @return array<string, array{string, string, int, string, string}> */
    public static function periodes(): array
    {
        return [
            '10 jours : forfait ½ mois'      => ['2026-11-01', '2026-11-10', 10, '½ mois (minimum)', '10 jours'],
            '15 jours : ½ mois'              => ['2026-11-01', '2026-11-15', 15, '½ mois', '15 jours'],
            '20 jours : ½ mois + prorata'    => ['2026-11-01', '2026-11-20', 20, '½ mois + 5 jours', '20 jours'],
            'mois pile (fin de mois)'        => ['2026-11-01', '2026-11-30', 30, '1 mois', '1 mois'],
            'mois pile (anniversaire)'       => ['2026-06-05', '2026-07-05', 31, '1 mois', '1 mois'],
            '29/10 → 01/12 : 1 mois + 2 j'   => ['2026-10-29', '2026-12-01', 34, '1 mois + 2 jours', '1 mois + 2 j'],
            '3 mois pile'                    => ['2026-01-01', '2026-03-31', 90, '3 mois', '3 mois'],
            '1 an'                           => ['2026-01-01', '2026-12-31', 365, '12 mois', '12 mois'],
        ];
    }

    /** @dataProvider periodes */
    public function test_libelle_humain_fidele_a_la_facturation(string $debut, string $fin, int $jours, string $facture, string $duree): void
    {
        $d = $this->duree($debut, $fin);

        $this->assertSame($jours, $d['jours']);
        $this->assertSame($facture, $d['facture']);
        $this->assertSame($duree, $d['duree']);
        $this->assertSame(Campaign::computeBillableMonths(Carbon::parse($debut), Carbon::parse($fin)), $d['mois']);
        $this->assertStringNotContainsString('.', $d['facture']); // jamais de décimales
    }

    public function test_le_libelle_de_duree_des_campagnes_ne_change_pas(): void
    {
        foreach (self::periodes() as [$debut, $fin, , , $duree]) {
            $c = new Campaign(['start_date' => $debut, 'end_date' => $fin]);
            $this->assertSame($duree, $c->durationLabel());
        }
    }

    public function test_route_de_l_equipe(): void
    {
        $r = app(DureeFactureeController::class)(Request::create('/', 'GET', ['debut' => '2026-10-29', 'fin' => '2026-12-01']));
        $this->assertSame('1 mois + 2 jours', $r->getData(true)['facture']);

        $this->expectException(ValidationException::class);
        app(DureeFactureeController::class)(Request::create('/', 'GET', ['debut' => '2026-12-01', 'fin' => '2026-10-29']));
    }

    public function test_route_publique_limitee_a_la_proposition(): void
    {
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('proposition_slug')->nullable(); $t->string('proposition_token')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        DB::table('reservations')->insert(['reference' => 'RES-1', 'proposition_slug' => 'abc12345', 'proposition_token' => str_repeat('t', 64)]);
        $requete = Request::create('/', 'GET', ['debut' => '2026-11-01', 'fin' => '2026-11-20']);

        $r = app(PropositionController::class)->dureeFacturee($requete, 'RES-1', 'abc12345');
        $this->assertSame('½ mois + 5 jours', $r->getData(true)['facture']);

        $this->expectException(NotFoundHttpException::class);
        app(PropositionController::class)->dureeFacturee($requete, 'RES-1', 'mauvais');
    }
}
