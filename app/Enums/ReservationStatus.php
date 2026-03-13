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
            self::CONFIRME   => 'Confirmé',
            self::REFUSE     => 'Refusé',
            self::ANNULE     => 'Annulé',
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
}