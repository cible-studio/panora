<?php
namespace App\Enums;

enum ReservationStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case CONFIRME   = 'confirme';
    case REFUSE     = 'refuse';
    case ANNULE     = 'annule';
}
