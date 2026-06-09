<?php

namespace Tests\Unit;

use App\Services\ReminderService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires ReminderService — Phase 5 + Phase 8C cahier §16
 * (« tranches d'ancienneté du recouvrement »).
 */
class ReminderServiceTest extends TestCase
{
    public function test_priority_critique_above_90_days(): void
    {
        $p = ReminderService::priorityForOverdueDays(91);
        $this->assertSame('critique', $p['key']);
        $this->assertSame('Critique', $p['label']);

        $p = ReminderService::priorityForOverdueDays(180);
        $this->assertSame('critique', $p['key']);
    }

    public function test_priority_eleve_between_60_and_90(): void
    {
        $this->assertSame('eleve', ReminderService::priorityForOverdueDays(60)['key']);
        $this->assertSame('eleve', ReminderService::priorityForOverdueDays(75)['key']);
        $this->assertSame('eleve', ReminderService::priorityForOverdueDays(90)['key']);
    }

    public function test_priority_moyen_between_30_and_60(): void
    {
        $this->assertSame('moyen', ReminderService::priorityForOverdueDays(30)['key']);
        $this->assertSame('moyen', ReminderService::priorityForOverdueDays(45)['key']);
        $this->assertSame('moyen', ReminderService::priorityForOverdueDays(59)['key']);
    }

    public function test_priority_faible_below_30(): void
    {
        $this->assertSame('faible', ReminderService::priorityForOverdueDays(0)['key']);
        $this->assertSame('faible', ReminderService::priorityForOverdueDays(15)['key']);
        $this->assertSame('faible', ReminderService::priorityForOverdueDays(29)['key']);
    }

    public function test_canal_labels_match_cahier(): void
    {
        // Cahier §9 : 5 canaux exigés
        $this->assertStringContainsString('Téléphone', ReminderService::canalLabel('telephone'));
        $this->assertStringContainsString('Email',     ReminderService::canalLabel('email'));
        $this->assertStringContainsString('WhatsApp',  ReminderService::canalLabel('whatsapp'));
        $this->assertStringContainsString('Visite',    ReminderService::canalLabel('visite'));
        $this->assertStringContainsString('Courrier',  ReminderService::canalLabel('courrier'));
        $this->assertStringContainsString('Autre',     ReminderService::canalLabel('autre'));
        // Fallback canal inconnu → "Autre"
        $this->assertStringContainsString('Autre',     ReminderService::canalLabel('xyz_inconnu'));
    }

    public function test_canals_constant_contains_5_canals_from_cahier(): void
    {
        $cahier = ['telephone', 'email', 'whatsapp', 'visite', 'courrier'];
        foreach ($cahier as $canal) {
            $this->assertContains($canal, ReminderService::CANALS, "Canal '$canal' manquant dans CANALS");
        }
    }
}
