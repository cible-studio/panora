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
    
    /**
     * Label par défaut — court et clair pour cohérence dans toute l'app.
     * Décision UX (feedback terrain) : on évite les libellés à rallonge
     * type "Réservé · Ferme" qui n'apportent pas plus d'info qu'un mot
     * unique mais brouillent la lecture en table compacte.
     */
    public function label(): string
    {
        return match($this) {
            self::LIBRE       => 'Libre',
            self::OPTION      => 'Option',
            self::CONFIRME    => 'Vendu',       // ex "Réservé" — désambiguïsation :
                                                // CONFIRME = engagement signé, pose à venir
                                                // ≠ OPTION = en attente client.
            self::OCCUPE      => 'En affichage', // = pose effective sur le terrain.
            self::MAINTENANCE => 'Maintenance',
        };
    }

    /**
     * Label court (alias de label() — conservé pour rétrocompat des
     * vues qui appelaient shortLabel() explicitement).
     */
    public function shortLabel(): string
    {
        return $this->label();
    }

    /**
     * Configuration UI pour l'affichage (couleurs, icônes, description courte)
     */
    public function uiConfig(): array
    {
        return match($this) {
            self::LIBRE => [
                'icon'        => '✅',
                'color'       => '#22c55e',
                'bg'          => 'rgba(34,197,94,0.08)',
                'border'      => 'rgba(34,197,94,0.3)',
                'description' => 'Disponible à la réservation — aucun blocage en cours.',
            ],
            self::OCCUPE => [
                'icon'        => '🔴',
                'color'       => '#ef4444',
                'bg'          => 'rgba(239,68,68,0.08)',
                'border'      => 'rgba(239,68,68,0.3)',
                'description' => 'Campagne ACTIVE — panneau actuellement en affichage sur le terrain. Non disponible.',
            ],
            self::OPTION => [
                'icon'        => '⏳',
                'color'       => '#f59e0b',
                'bg'          => 'rgba(245,158,11,0.08)',
                'border'      => 'rgba(245,158,11,0.3)',
                'description' => 'Réservation en attente de confirmation client — réversible si le client ne confirme pas.',
            ],
            self::CONFIRME => [
                'icon'        => '🔒',
                'color'       => '#8b5cf6',
                'bg'          => 'rgba(139,92,246,0.08)',
                'border'      => 'rgba(139,92,246,0.3)',
                'description' => 'Vendu — réservation ferme signée par le client. Pose à venir, non disponible.',
            ],
            self::MAINTENANCE => [
                'icon'        => '🔧',
                'color'       => '#6b7280',
                'bg'          => 'rgba(107,114,128,0.08)',
                'border'      => 'rgba(107,114,128,0.3)',
                'description' => 'Intervention technique en cours — indisponible jusqu\'à résolution.',
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