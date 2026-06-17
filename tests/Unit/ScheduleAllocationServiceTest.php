<?php

namespace Tests\Unit;

use App\Services\ScheduleAllocationService;
use Tests\TestCase;

/**
 * Tests de l'algorithme FIFO d'allocation versement → échéances.
 *
 * Mission BUG_ECHEANCIER (juin 2026). Source unique de vérité.
 * On teste l'algo PUR (computeAllocations) sans toucher la base — la
 * persistance via recomputeFromPayments est couverte par tests Feature
 * sur DB SQLite si nécessaire.
 *
 * Convention test : amounts en FCFA entier, schedules triés due_date ASC,
 * payments triés paid_at ASC.
 */
class ScheduleAllocationServiceTest extends TestCase
{
    public function test_invoice_without_schedules_returns_empty(): void
    {
        $result = ScheduleAllocationService::computeAllocations([], [
            ['id' => 1, 'montant' => 50_000, 'paid_at' => '2026-06-10'],
        ]);
        $this->assertSame([], $result);
    }

    public function test_invoice_without_payments_all_schedules_zero(): void
    {
        $result = ScheduleAllocationService::computeAllocations([
            ['id' => 1, 'amount' => 100_000],
            ['id' => 2, 'amount' => 200_000],
        ], []);

        $this->assertSame(0, $result[1]['paid_amount']);
        $this->assertNull($result[1]['paid_at']);
        $this->assertSame(0, $result[2]['paid_amount']);
        $this->assertNull($result[2]['paid_at']);
    }

    public function test_payment_exactly_matches_first_schedule(): void
    {
        $result = ScheduleAllocationService::computeAllocations([
            ['id' => 1, 'amount' => 100_000],
            ['id' => 2, 'amount' => 200_000],
        ], [
            ['id' => 10, 'montant' => 100_000, 'paid_at' => '2026-06-10'],
        ]);

        $this->assertSame(100_000, $result[1]['paid_amount']);
        $this->assertSame('2026-06-10', $result[1]['paid_at']);
        $this->assertSame(10, $result[1]['paid_payment_id']);
        $this->assertSame(0, $result[2]['paid_amount']);
        $this->assertNull($result[2]['paid_at']);
    }

    public function test_payment_less_than_first_schedule_partial(): void
    {
        // Cas FAC-2026-010 : 50 000 sur une 1re échéance de 119 610
        $result = ScheduleAllocationService::computeAllocations([
            ['id' => 1, 'amount' => 119_610],
            ['id' => 2, 'amount' => 279_090],
        ], [
            ['id' => 10, 'montant' => 50_000, 'paid_at' => '2026-06-10'],
        ]);

        $this->assertSame(50_000, $result[1]['paid_amount']);
        $this->assertNull($result[1]['paid_at'], 'paid_at doit rester NULL tant que pas totalement couvert');
        $this->assertNull($result[1]['paid_payment_id']);
        $this->assertSame(0, $result[2]['paid_amount']);
    }

    public function test_payment_exceeds_first_schedule_overflows_to_second(): void
    {
        $result = ScheduleAllocationService::computeAllocations([
            ['id' => 1, 'amount' => 100_000],
            ['id' => 2, 'amount' => 200_000],
        ], [
            ['id' => 10, 'montant' => 150_000, 'paid_at' => '2026-06-10'],
        ]);

        // Sch1 entièrement soldée, sch2 partielle 50 k
        $this->assertSame(100_000, $result[1]['paid_amount']);
        $this->assertSame('2026-06-10', $result[1]['paid_at']);
        $this->assertSame(10, $result[1]['paid_payment_id']);

        $this->assertSame(50_000, $result[2]['paid_amount']);
        $this->assertNull($result[2]['paid_at'], 'sch2 partielle → paid_at NULL');
    }

    public function test_fac_2026_010_real_case(): void
    {
        // Versements réels : 50 000 (10/06) + 3 000 (11/06) = 53 000
        // Échéances : Acompte 30% 119 610 + Solde 70% 279 090
        $result = ScheduleAllocationService::computeAllocations([
            ['id' => 1, 'amount' => 119_610],
            ['id' => 2, 'amount' => 279_090],
        ], [
            ['id' => 100, 'montant' => 50_000, 'paid_at' => '2026-06-10'],
            ['id' => 101, 'montant' =>  3_000, 'paid_at' => '2026-06-11'],
        ]);

        // Sch1 : cumul 53 000 < 119 610 → partielle, paid_at NULL
        $this->assertSame(53_000, $result[1]['paid_amount']);
        $this->assertNull($result[1]['paid_at']);
        $this->assertNull($result[1]['paid_payment_id']);

        // Sch2 : aucun débordement
        $this->assertSame(0, $result[2]['paid_amount']);
        $this->assertNull($result[2]['paid_at']);

        // Vérification des states dérivés
        $this->assertSame('partial', ScheduleAllocationService::deriveState($result[1]['paid_amount'], 119_610, $result[1]['paid_at']));
        $this->assertSame('upcoming', ScheduleAllocationService::deriveState($result[2]['paid_amount'], 279_090, $result[2]['paid_at']));
    }

    public function test_multiple_payments_complete_first_then_partial_second(): void
    {
        // 3 versements : 50k + 60k + 40k = 150k sur des schedules 100k+200k
        $result = ScheduleAllocationService::computeAllocations([
            ['id' => 1, 'amount' => 100_000],
            ['id' => 2, 'amount' => 200_000],
        ], [
            ['id' => 10, 'montant' => 50_000, 'paid_at' => '2026-06-10'],
            ['id' => 11, 'montant' => 60_000, 'paid_at' => '2026-06-12'],
            ['id' => 12, 'montant' => 40_000, 'paid_at' => '2026-06-15'],
        ]);

        // Sch1 : sat après V2 (50+60=110, déborde 10 sur sch2)
        $this->assertSame(100_000, $result[1]['paid_amount']);
        $this->assertSame('2026-06-12', $result[1]['paid_at'], 'paid_at = date du paiement qui a fini de solder');
        $this->assertSame(11, $result[1]['paid_payment_id']);

        // Sch2 : 10 (déborde V2) + 40 (V3) = 50k partiel
        $this->assertSame(50_000, $result[2]['paid_amount']);
        $this->assertNull($result[2]['paid_at']);
    }

    public function test_payment_exactly_clears_all_schedules(): void
    {
        $result = ScheduleAllocationService::computeAllocations([
            ['id' => 1, 'amount' => 100_000],
            ['id' => 2, 'amount' => 200_000],
        ], [
            ['id' => 10, 'montant' => 300_000, 'paid_at' => '2026-06-15'],
        ]);

        $this->assertSame(100_000, $result[1]['paid_amount']);
        $this->assertSame('2026-06-15', $result[1]['paid_at']);
        $this->assertSame(200_000, $result[2]['paid_amount']);
        $this->assertSame('2026-06-15', $result[2]['paid_at']);
    }

    public function test_derive_state_thresholds(): void
    {
        // 0 → upcoming
        $this->assertSame('upcoming', ScheduleAllocationService::deriveState(0, 100_000, null));
        // 1..amount-1 → partial
        $this->assertSame('partial', ScheduleAllocationService::deriveState(50_000, 100_000, null));
        $this->assertSame('partial', ScheduleAllocationService::deriveState(99_999, 100_000, null));
        // == amount + paid_at posé → paid
        $this->assertSame('paid', ScheduleAllocationService::deriveState(100_000, 100_000, '2026-06-15'));
        // == amount mais sans paid_at → partial (cas dégradé, garde-fou)
        $this->assertSame('partial', ScheduleAllocationService::deriveState(100_000, 100_000, null));
        // amount <= 0 → upcoming par défaut
        $this->assertSame('upcoming', ScheduleAllocationService::deriveState(0, 0, null));
    }
}
