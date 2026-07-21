<?php

namespace App\Enums;

/**
 * Cycle de vie d'un devis (Quote).
 *
 * Transitions autorisées :
 *   brouillon         → envoye
 *   envoye            → accepte | accepte_avec_conflit | refuse | en_negociation | expire
 *   en_negociation    → envoye (nouvelle version) | refuse
 *   accepte_avec_conflit → accepte (une fois le conflit résolu) | refuse
 *   accepte           → archive (terminal — devis converti en résa)
 *   refuse | expire   → archive (terminal)
 *   archive           → aucune sortie
 */
enum QuoteStatus: string
{
    case BROUILLON             = 'brouillon';
    case ENVOYE                = 'envoye';
    case ACCEPTE               = 'accepte';
    case ACCEPTE_AVEC_CONFLIT  = 'accepte_avec_conflit';
    case REFUSE                = 'refuse';
    case EN_NEGOCIATION        = 'en_negociation';
    case EXPIRE                = 'expire';
    case ARCHIVE               = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::BROUILLON             => 'Brouillon',
            self::ENVOYE                => 'Envoyé',
            self::ACCEPTE               => 'Accepté',
            self::ACCEPTE_AVEC_CONFLIT  => 'Accepté (conflit à résoudre)',
            self::REFUSE                => 'Refusé',
            self::EN_NEGOCIATION        => 'En négociation',
            self::EXPIRE                => 'Expiré',
            self::ARCHIVE               => 'Archivé',
        };
    }

    /**
     * Config UI (couleur badge + icône) — cohérent avec la palette
     * utilisée pour Reservation et Invoice status.
     */
    public function uiConfig(): array
    {
        return match ($this) {
            self::BROUILLON             => ['bg' => 'rgba(148,163,184,.15)', 'color' => '#475569', 'border' => 'rgba(148,163,184,.3)', 'icon' => '📝'],
            self::ENVOYE                => ['bg' => 'rgba(59,130,246,.12)',  'color' => '#1d4ed8', 'border' => 'rgba(59,130,246,.3)',  'icon' => '📤'],
            self::ACCEPTE               => ['bg' => 'rgba(34,197,94,.12)',   'color' => '#15803d', 'border' => 'rgba(34,197,94,.3)',   'icon' => '✅'],
            self::ACCEPTE_AVEC_CONFLIT  => ['bg' => 'rgba(245,158,11,.14)',  'color' => '#b45309', 'border' => 'rgba(245,158,11,.35)', 'icon' => '⚠️'],
            self::REFUSE                => ['bg' => 'rgba(239,68,68,.10)',   'color' => '#991b1b', 'border' => 'rgba(239,68,68,.25)',  'icon' => '❌'],
            self::EN_NEGOCIATION        => ['bg' => 'rgba(139,92,246,.12)',  'color' => '#6d28d9', 'border' => 'rgba(139,92,246,.3)',  'icon' => '🔁'],
            self::EXPIRE                => ['bg' => 'rgba(100,116,139,.10)', 'color' => '#334155', 'border' => 'rgba(100,116,139,.25)','icon' => '⌛'],
            self::ARCHIVE               => ['bg' => 'rgba(100,116,139,.06)', 'color' => '#64748b', 'border' => 'rgba(100,116,139,.15)','icon' => '📦'],
        };
    }

    /** Statuts sur lesquels le devis est encore actionnable par le commercial. */
    public static function actionableStatuses(): array
    {
        return [
            self::BROUILLON->value,
            self::ENVOYE->value,
            self::EN_NEGOCIATION->value,
            self::ACCEPTE_AVEC_CONFLIT->value,
        ];
    }

    /** Statuts terminaux (plus aucune action possible). */
    public static function terminalStatuses(): array
    {
        return [
            self::ARCHIVE->value,
            self::EXPIRE->value,
        ];
    }
}
