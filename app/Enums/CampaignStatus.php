<?php
namespace App\Enums;

enum CampaignStatus: string
{
    case ACTIF   = 'actif';
    case POSE    = 'pose';
    case TERMINE = 'termine';
    case ANNULE  = 'annule';
}
