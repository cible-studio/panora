<?php
namespace App\Enums;

/**
 * Statuts campagne — simplifiés à 5.
 *
 * L'ancien statut POSE (« en cours de pose terrain ») a été supprimé :
 * il dupliquait l'information déjà portée par PoseTask (statuts
 * planifiee / en_cours / realisee). Les campagnes y étant restent
 * en ACTIF — la pose effective continue d'être suivie via PoseTask.
 *
 * Migration des données : 2026_05_11_140000_remove_pose_from_campaigns_status.php
 */
enum CampaignStatus: string
{
    case PLANIFIE = 'planifie';
    case ACTIF    = 'actif';
    case PAUSE    = 'pause';
    case TERMINE  = 'termine';
    case ANNULE   = 'annule';

    public function label(): string
    {
        return match($this) {
            self::PLANIFIE => 'Planifiée',
            self::ACTIF    => 'En cours',
            self::PAUSE    => 'En pause',
            self::TERMINE  => 'Terminée',
            self::ANNULE   => 'Annulée',
        };
    }

    public function uiConfig(): array
    {
        return match($this) {
            self::PLANIFIE => [
                'icon'        => '📅',
                'color'       => '#f97316',
                'bg'          => 'rgba(249,115,22,0.08)',
                'border'      => 'rgba(249,115,22,0.3)',
                'description' => 'Campagne planifiée — commence prochainement',
            ],
            self::ACTIF    => [
                'icon'        => '📡',
                'color'       => '#22c55e',
                'bg'          => 'rgba(34,197,94,0.08)',
                'border'      => 'rgba(34,197,94,0.3)',
                'description' => 'Campagne active — panneaux en affichage',
            ],
            self::PAUSE    => [
                'icon'        => '⏸',
                'color'       => '#f59e0b',
                'bg'          => 'rgba(245,158,11,0.08)',
                'border'      => 'rgba(245,158,11,0.3)',
                'description' => 'Campagne suspendue temporairement',
            ],
            self::TERMINE  => [
                'icon'        => '✅',
                'color'       => '#6b7280',
                'bg'          => 'rgba(107,114,128,0.08)',
                'border'      => 'rgba(107,114,128,0.3)',
                'description' => 'Campagne terminée — archivée',
            ],
            self::ANNULE   => [
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
            self::PLANIFIE => [self::ACTIF, self::ANNULE],
            self::ACTIF    => [self::PAUSE, self::TERMINE, self::ANNULE],
            self::PAUSE    => [self::ACTIF, self::ANNULE],
            self::TERMINE  => [],
            self::ANNULE   => [],
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
        return in_array($new, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::TERMINE, self::ANNULE], true);
    }
}
