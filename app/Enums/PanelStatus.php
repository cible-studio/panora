<?php
// app/Enums/PanelStatus.php

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

    /**
     * Configuration UI pour l'affichage (couleurs, icônes)
     */
    public function uiConfig(): array
    {
        return match($this) {
            self::LIBRE => [
                'icon'        => '✅',
                'color'       => '#22c55e',
                'bg'          => 'rgba(34,197,94,0.08)',
                'border'      => 'rgba(34,197,94,0.3)',
                'description' => 'Panneau disponible à la réservation',
            ],
            self::OCCUPE => [
                'icon'        => '🔴',
                'color'       => '#ef4444',
                'bg'          => 'rgba(239,68,68,0.08)',
                'border'      => 'rgba(239,68,68,0.3)',
                'description' => 'Panneau actuellement occupé par une campagne',
            ],
            self::OPTION => [
                'icon'        => '⏳',
                'color'       => '#f59e0b',
                'bg'          => 'rgba(245,158,11,0.08)',
                'border'      => 'rgba(245,158,11,0.3)',
                'description' => 'Panneau en option — réservation temporaire',
            ],
            self::CONFIRME => [
                'icon'        => '🔒',
                'color'       => '#8b5cf6',
                'bg'          => 'rgba(139,92,246,0.08)',
                'border'      => 'rgba(139,92,246,0.3)',
                'description' => 'Panneau confirmé pour une réservation ferme',
            ],
            self::MAINTENANCE => [
                'icon'        => '🔧',
                'color'       => '#6b7280',
                'bg'          => 'rgba(107,114,128,0.08)',
                'border'      => 'rgba(107,114,128,0.3)',
                'description' => 'Panneau en maintenance — intervention en cours',
            ],
        };
    }

    /**
     * Vérifie si le panneau peut recevoir une pose
     */
    public function canBePosed(): bool
    {
        return in_array($this, [
            self::LIBRE,
            self::OCCUPE,
            self::CONFIRME,
            self::MAINTENANCE,
        ]);
    }

    /**
     * Type de pose recommandé
     */
    public function getRecommendedPoseType(): string
    {
        return match($this) {
            self::LIBRE       => 'new_campaign',
            self::OCCUPE      => 'renewal_or_change',
            self::CONFIRME    => 'planned_campaign',
            self::MAINTENANCE => 'technical_intervention',
            default           => 'unknown',
        };
    }

    /**
     * Icône du type de pose recommandé
     */
    public function getRecommendedPoseIcon(): string
    {
        return match($this->getRecommendedPoseType()) {
            'new_campaign'          => '🆕',
            'renewal_or_change'     => '🔄',
            'planned_campaign'      => '📋',
            'technical_intervention'=> '🔧',
            default                 => '❓',
        };
    }

    /**
     * Message explicatif pour la pose
     */
    public function getPoseMessage(): ?string
    {
        return match($this) {
            self::LIBRE       => null,
            self::OCCUPE      => '⚠️ Changement de visuel — vérifiez que le nouveau visuel est prêt.',
            self::CONFIRME    => null,
            self::MAINTENANCE => '⚠️ Intervention technique — pas de campagne associée.',
            default           => 'Ce panneau ne peut pas recevoir de pose.',
        };
    }
}