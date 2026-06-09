<?php

namespace Tests\Unit;

use App\Services\ScheduleGenerator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires ScheduleGenerator — Phase 3 + Phase 8C cahier §16
 * (« génération d'échéanciers : 50/50, trimestriel, mensuel »).
 *
 * Les tests utilisent les méthodes protégées exposées via réflexion
 * pour éviter de dépendre d'une Invoice persistée (Unit, pas Feature).
 */
class ScheduleGeneratorTest extends TestCase
{
    private function generator(): ScheduleGenerator
    {
        return new ScheduleGenerator();
    }

    private function call(ScheduleGenerator $gen, string $method, array $args)
    {
        $ref = new \ReflectionMethod($gen, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($gen, $args);
    }

    public function test_legacy_30_70_split(): void
    {
        $start = Carbon::create(2026, 6, 1);
        $out = $this->call($this->generator(), 'buildLegacy30_70', [1_000_000, $start]);

        $this->assertCount(2, $out);
        $this->assertSame(300000, $out[0]['amount']);
        $this->assertSame(700000, $out[1]['amount']);
        $this->assertSame('Acompte 30 %', $out[0]['label']);
        $this->assertSame('2026-06-01', $out[0]['due']->toDateString());
        $this->assertSame('2026-07-01', $out[1]['due']->toDateString());
    }

    public function test_legacy_50_50_split(): void
    {
        $start = Carbon::create(2026, 6, 1);
        $out = $this->call($this->generator(), 'buildLegacy50_50', [999_999, $start]);

        // L'arrondi de 50 % donne 500_000 + le solde 499_999
        $this->assertCount(2, $out);
        $this->assertSame(500000, $out[0]['amount']);
        $this->assertSame(499999, $out[1]['amount']);
        $sum = $out[0]['amount'] + $out[1]['amount'];
        $this->assertSame(999_999, $sum, 'La somme doit égaler le total à l\'unité près');
    }

    public function test_monthly_n_with_remainder_on_last(): void
    {
        // 1 000 000 / 6 = 166 666.66… → 5 × 166 666 + 1 × 166 670 = 1 000 000
        $out = $this->call($this->generator(), 'buildMonthly', [
            1_000_000,
            Carbon::create(2026, 1, 15),
            6,
        ]);

        $this->assertCount(6, $out);
        $sum = array_sum(array_column($out, 'amount'));
        $this->assertSame(1_000_000, $sum, 'Somme des mensualités = total');

        // Dernier corrige les arrondis : il prend le surplus
        $this->assertSame(166666, $out[0]['amount']);
        $this->assertSame(166670, $out[5]['amount']);

        // Espacement mensuel
        $this->assertSame('2026-01-15', $out[0]['due']->toDateString());
        $this->assertSame('2026-02-15', $out[1]['due']->toDateString());
        $this->assertSame('2026-06-15', $out[5]['due']->toDateString());
    }

    public function test_monthly_rejects_invalid_count(): void
    {
        $this->expectException(\DomainException::class);
        $this->call($this->generator(), 'buildMonthly', [
            1_000_000,
            Carbon::create(2026, 1, 15),
            1, // < 2 → rejet
        ]);
    }

    public function test_quarterly_finds_next_4_quarters(): void
    {
        $start = Carbon::create(2026, 2, 5); // Démarre fin de Q1 → cherche Q2/Q3/Q4 + Q1 2027
        $out = $this->call($this->generator(), 'buildQuarterly', [4_000_000, $start]);

        $this->assertCount(4, $out);
        $this->assertSame('2026-04-01', $out[0]['due']->toDateString());
        $this->assertSame('2026-07-01', $out[1]['due']->toDateString());
        $this->assertSame('2026-10-01', $out[2]['due']->toDateString());
        $this->assertSame('2027-01-01', $out[3]['due']->toDateString());

        $sum = array_sum(array_column($out, 'amount'));
        $this->assertSame(4_000_000, $sum);
    }

    public function test_custom_milestones_sum_must_match_total(): void
    {
        // Somme correcte (±1 F absorbé sur le dernier jalon)
        $out = $this->call($this->generator(), 'buildCustomMilestones', [
            [
                ['label' => 'Acompte', 'due_date' => '2026-06-01', 'amount' => 500_000],
                ['label' => 'Solde',   'due_date' => '2026-08-01', 'amount' => 500_000],
            ],
            1_000_000,
        ]);
        $this->assertCount(2, $out);
        $this->assertSame(1_000_000, array_sum(array_column($out, 'amount')));
    }

    public function test_custom_milestones_rejects_amount_mismatch(): void
    {
        // 200k + 300k = 500k mais total demandé = 1M → écart > 1 F → rejet
        $this->expectException(\DomainException::class);
        $this->call($this->generator(), 'buildCustomMilestones', [
            [
                ['label' => 'A', 'due_date' => '2026-06-01', 'amount' => 200_000],
                ['label' => 'B', 'due_date' => '2026-07-01', 'amount' => 300_000],
            ],
            1_000_000,
        ]);
    }

    public function test_custom_milestones_sorts_by_due_date(): void
    {
        $out = $this->call($this->generator(), 'buildCustomMilestones', [
            [
                ['label' => 'Tard',  'due_date' => '2026-12-01', 'amount' => 700_000],
                ['label' => 'Tôt',   'due_date' => '2026-06-01', 'amount' => 300_000],
            ],
            1_000_000,
        ]);
        $this->assertSame('2026-06-01', $out[0]['due']->toDateString());
        $this->assertSame('2026-12-01', $out[1]['due']->toDateString());
    }
}
