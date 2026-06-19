<?php

namespace Tests\Unit;

use App\Helpers\HumanTimeDiff;
use Carbon\Carbon;
use Tests\TestCase;

class HumanTimeDiffTest extends TestCase
{
    /** @test */
    public function it_returns_null_when_diff_is_less_than_2h()
    {
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = $now->copy()->addMinutes(30);

        $this->assertNull(HumanTimeDiff::formatScheduleDiff($scheduled, $now));
    }

    /** @test */
    public function it_returns_null_when_diff_is_119_minutes()
    {
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = $now->copy()->addMinutes(119);

        $this->assertNull(HumanTimeDiff::formatScheduleDiff($scheduled, $now));
    }

    /** @test */
    public function it_formats_avance_6h()
    {
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = $now->copy()->addHours(6);

        $this->assertSame(
            "avec 6 heures d'avance",
            HumanTimeDiff::formatScheduleDiff($scheduled, $now)
        );
    }

    /** @test */
    public function it_formats_retard_6h()
    {
        $now      = Carbon::create(2026, 6, 19, 15, 0, 0);
        $scheduled = $now->copy()->subHours(6);

        $this->assertSame(
            "avec 6 heures de retard",
            HumanTimeDiff::formatScheduleDiff($scheduled, $now)
        );
    }

    /** @test */
    public function it_formats_moins_dun_jour_in_avance()
    {
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = $now->copy()->addHours(20);

        $this->assertSame(
            "avec moins d'un jour d'avance",
            HumanTimeDiff::formatScheduleDiff($scheduled, $now)
        );
    }

    /** @test */
    public function it_formats_3_jours_avance()
    {
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = $now->copy()->addDays(3);

        $this->assertSame(
            "avec 3 jours d'avance",
            HumanTimeDiff::formatScheduleDiff($scheduled, $now)
        );
    }

    /** @test */
    public function it_formats_1_jour_singular()
    {
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = $now->copy()->addHours(25); // ~1 jour

        $this->assertSame(
            "avec 1 jour d'avance",
            HumanTimeDiff::formatScheduleDiff($scheduled, $now)
        );
    }

    /** @test */
    public function it_formats_in_semaines_when_more_than_7_days()
    {
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = $now->copy()->addDays(15);

        $this->assertSame(
            "prévue dans 2 semaines",
            HumanTimeDiff::formatScheduleDiff($scheduled, $now)
        );
    }

    /** @test */
    public function it_formats_dans_1_semaine_singular()
    {
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = $now->copy()->addDays(8);

        $this->assertSame(
            "prévue dans 1 semaine",
            HumanTimeDiff::formatScheduleDiff($scheduled, $now)
        );
    }

    /** @test */
    public function it_formats_full_date_when_more_than_30_days()
    {
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = Carbon::create(2026, 8, 15, 14, 0, 0);

        $this->assertSame(
            "prévue le 15 août",
            HumanTimeDiff::formatScheduleDiff($scheduled, $now)
        );
    }

    /** @test */
    public function it_does_not_say_275h_for_far_future()
    {
        // Cas reproduit en prod : pose prévue dans 11 jours,
        // l'ancien code disait "275h 30 min en avance".
        $now      = Carbon::create(2026, 6, 19, 9, 0, 0);
        $scheduled = Carbon::create(2026, 6, 30, 14, 30, 0);

        $result = HumanTimeDiff::formatScheduleDiff($scheduled, $now);
        $this->assertNotNull($result);
        $this->assertStringNotContainsString('275', $result);
        $this->assertStringNotContainsString('h ', $result);
        // 11 jours → "prévue dans 1 semaine" (en fait absDays=11 → 11/7=1)
        $this->assertSame("prévue dans 1 semaine", $result);
    }
}
