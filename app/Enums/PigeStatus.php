<?php
// app/Enums/PigeStatus.php
namespace App\Enums;

enum PigeStatus: string
{
    case PENDING = 'en_attente';
    case VERIFIED = 'verifie';
    case REJECTED = 'rejete';

    public function label(): string
    {
        return match($this) {
            self::PENDING  => 'En attente',
            self::VERIFIED => 'Vérifiée',
            self::REJECTED => 'Rejetée',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING  => '#f97316',
            self::VERIFIED => '#22c55e',
            self::REJECTED => '#ef4444',
        };
    }

    public function bg(): string
    {
        return match($this) {
            self::PENDING  => 'rgba(249,115,22,0.08)',
            self::VERIFIED => 'rgba(34,197,94,0.08)',
            self::REJECTED => 'rgba(239,68,68,0.08)',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::PENDING  => '⏳',
            self::VERIFIED => '✅',
            self::REJECTED => '❌',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match($this) {
            self::PENDING  => in_array($to, [self::VERIFIED, self::REJECTED]),
            self::VERIFIED => false,
            self::REJECTED => false,
        };
    }
}