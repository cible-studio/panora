<?php
namespace App\Enums;

enum PanelStatus: string
{
    case LIBRE       = 'libre';
    case OCCUPE      = 'occupe';
    case OPTION      = 'option';
    case CONFIRME    = 'confirme';
    case MAINTENANCE = 'maintenance';
    
    public function label(): string
    {
        return match($this) {
            self::LIBRE       => 'Libre',
            self::OPTION      => 'En option',
            self::CONFIRME    => 'Confirmé',
            self::OCCUPE      => 'Occupé',
            self::MAINTENANCE => 'Maintenance',
        };
    }
}


