<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Services\Diffusion\RenouvellementService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Renouvellement des campagnes annuelles (2026-10-22) : quelles campagnes,
 * et quel mail de la séquence part quel jour. Fonctions pures.
 */
class RenouvellementSequenceTest extends TestCase
{
    public function test_etape_due_selon_les_jours_restants(): void
    {
        $attendu = [
            61 => null, 60 => 'j60', 50 => 'j60', 46 => 'j60', 45 => 'j45', 31 => 'j45',
            30 => 'j30', 16 => 'j30', 15 => 'j15', 8 => 'j15', 7 => 'j7', 1 => 'j7', 0 => null, -3 => null,
        ];

        foreach ($attendu as $jours => $etape) {
            $this->assertSame($etape, RenouvellementService::etapePour($jours), "J-{$jours}");
        }
    }

    private function campagne(string $debut, string $fin): Campaign
    {
        $c = new Campaign();
        $c->start_date = Carbon::parse($debut);
        $c->end_date   = Carbon::parse($fin);

        return $c;
    }

    public function test_une_campagne_d_un_an_civil_est_annuelle(): void
    {
        $this->assertTrue($this->campagne('2026-01-01', '2026-12-31')->estAnnuelle());
        $this->assertTrue($this->campagne('2026-03-15', '2027-03-14')->estAnnuelle());
        $this->assertTrue($this->campagne('2026-01-01', '2027-06-30')->estAnnuelle());
    }

    public function test_une_campagne_de_moins_de_12_mois_ne_l_est_pas(): void
    {
        $this->assertFalse($this->campagne('2026-01-01', '2026-11-30')->estAnnuelle());
        $this->assertFalse($this->campagne('2026-01-01', '2026-12-15')->estAnnuelle());
        $this->assertFalse($this->campagne('2026-10-01', '2026-10-31')->estAnnuelle());
    }

    public function test_prochain_mail_de_la_sequence(): void
    {
        $c = $this->campagne('2026-01-01', '2026-12-31');
        Carbon::setTestNow('2026-10-22');

        // J-70 : rien n'est dû, le prochain est J-60 le 1er novembre.
        [$etape, $date] = RenouvellementService::prochainMail($c, 70, []);
        $this->assertSame('j60', $etape);
        $this->assertSame('2026-11-01', $date->toDateString());

        // J-50, J-60 déjà parti : prochain J-45 le 16 novembre.
        [$etape, $date] = RenouvellementService::prochainMail($c, 50, ['j60']);
        $this->assertSame(['j45', '2026-11-16'], [$etape, $date->toDateString()]);

        // J-50, J-60 pas encore parti (jour manqué) : rattrapé aujourd'hui.
        [$etape, $date] = RenouvellementService::prochainMail($c, 50, []);
        $this->assertSame(['j60', '2026-10-22'], [$etape, $date->toDateString()]);

        Carbon::setTestNow();
    }
}
