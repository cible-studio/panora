<?php
namespace App\Enums;

enum ReservationStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case CONFIRME   = 'confirme';
    case REFUSE     = 'refuse';
    case ANNULE     = 'annule';

    public function label(): string
    {
        return match($this) {
            self::EN_ATTENTE => 'En attente',
            self::CONFIRME   => 'Confirmée',
            self::REFUSE     => 'Refusée',
            self::ANNULE     => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::EN_ATTENTE => 'orange',
            self::CONFIRME   => 'green',
            self::REFUSE     => 'red',
            self::ANNULE     => 'gray',
        };
    }

    public function uiConfig(): array
    {
        return match($this) {
            self::EN_ATTENTE => [
                'icon'   => '⏳',
                'color'  => '#e8a020',
                'bg'     => 'rgba(232,160,32,0.08)',
                'border' => 'rgba(232,160,32,0.3)',
            ],
            self::CONFIRME   => [
                'icon'   => '✅',
                'color'  => '#22c55e',
                'bg'     => 'rgba(34,197,94,0.08)',
                'border' => 'rgba(34,197,94,0.3)',
            ],
            self::REFUSE     => [
                'icon'   => '❌',
                'color'  => '#ef4444',
                'bg'     => 'rgba(239,68,68,0.08)',
                'border' => 'rgba(239,68,68,0.3)',
            ],
            self::ANNULE     => [
                'icon'   => '🚫',
                'color'  => '#6b7280',
                'bg'     => 'rgba(107,114,128,0.08)',
                'border' => 'rgba(107,114,128,0.3)',
            ],
        };
    }
}