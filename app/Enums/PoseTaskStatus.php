<?php
// app/Enums/PoseTaskStatus.php
namespace App\Enums;

enum PoseTaskStatus: string
{
    case PLANNED = 'planifiee';
    case IN_PROGRESS = 'en_cours';
    case COMPLETED = 'realisee';
    case CANCELLED = 'annulee';

    public function label(): string
    {
        return match($this) {
            self::PLANNED     => 'Planifiée',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED   => 'Réalisée',
            self::CANCELLED   => 'Annulée',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PLANNED     => '#e8a020',
            self::IN_PROGRESS => '#3b82f6',
            self::COMPLETED   => '#22c55e',
            self::CANCELLED   => '#ef4444',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::PLANNED     => '📅',
            self::IN_PROGRESS => '🔧',
            self::COMPLETED   => '✅',
            self::CANCELLED   => '🚫',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED]);
    }

    public function allowedTransitions(): array
    {
        return match($this) {
            self::PLANNED     => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED   => [],
            self::CANCELLED   => [],
        };
    }
}