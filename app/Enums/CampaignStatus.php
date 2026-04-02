<?php
namespace App\Enums;

enum CampaignStatus: string
{
    case ACTIF   = 'actif';
    case POSE    = 'pose';
    case TERMINE = 'termine';
    case ANNULE  = 'annule';

    public function label(): string
    {
        return match($this) {
            self::ACTIF   => 'En cours',
            self::POSE    => 'En pose',
            self::TERMINE => 'Terminée',
            self::ANNULE  => 'Annulée',
        };
    }

    public function uiConfig(): array
    {
        return match($this) {
            self::ACTIF   => [
                'icon'        => '📡',
                'color'       => '#22c55e',
                'bg'          => 'rgba(34,197,94,0.08)',
                'border'      => 'rgba(34,197,94,0.3)',
                'description' => 'Campagne active — panneaux en affichage',
            ],
            self::POSE    => [
                'icon'        => '🔧',
                'color'       => '#3b82f6',
                'bg'          => 'rgba(59,130,246,0.08)',
                'border'      => 'rgba(59,130,246,0.3)',
                'description' => 'En cours de pose terrain',
            ],
            self::TERMINE => [
                'icon'        => '✅',
                'color'       => '#6b7280',
                'bg'          => 'rgba(107,114,128,0.08)',
                'border'      => 'rgba(107,114,128,0.3)',
                'description' => 'Campagne terminée — archivée',
            ],
            self::ANNULE  => [
                'icon'        => '🚫',
                'color'       => '#ef4444',
                'bg'          => 'rgba(239,68,68,0.08)',
                'border'      => 'rgba(239,68,68,0.3)',
                'description' => 'Annulée — panneaux libérés',
            ],
        };
    }

    public function allowedTransitions(): array
    {
        return match($this) {
            self::ACTIF   => [self::TERMINE, self::ANNULE],
            self::POSE    => [self::ACTIF, self::TERMINE, self::ANNULE],
            self::TERMINE => [],
            self::ANNULE  => [],
        };
    }

    public function allowedTransitionsLabels(): array
    {
        return collect($this->allowedTransitions())
            ->mapWithKeys(fn($s) => [$s->value => $s->label()])
            ->toArray();
    }

    public function canTransitionTo(CampaignStatus $new): bool
    {
        $allowed = match($this->value) {
            'actif'   => ['pose', 'termine', 'annule'],
            'pose'    => ['actif', 'termine', 'annule'],
            'termine' => [],  // terminal
            'annule'  => [],  // terminal
        };
        return in_array($new->value, $allowed);
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, ['termine', 'annule']);
    }
}