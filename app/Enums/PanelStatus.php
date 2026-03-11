<?php
namespace App\Enums;

enum PanelStatus: string
{
    case LIBRE       = 'libre';
    case OCCUPE      = 'occupe';
    case OPTION      = 'option';
    case CONFIRME    = 'confirme';
    case MAINTENANCE = 'maintenance';
}
