<?php

namespace App\Enums;

/**
 * Type d'une PoseTask — ajout 2026-08-04 pour supporter le multi-poses
 * sur une campagne (ex : campagne de 12 mois avec changement de créa
 * mensuel). Chaque nouvelle pose sur un même panneau × campagne peut
 * être typée pour distinguer la 1re pose des interventions suivantes.
 *
 * Choix VARCHAR (pas d'ENUM SQL) côté migration pour éviter les ALTER
 * lourds si on ajoute un nouveau kind. Contrainte applicative uniquement.
 */
enum PoseTaskKind: string
{
    /** 1re pose de la campagne sur ce panneau. */
    case INITIAL = 'initial';

    /**
     * Changement d'affiche en cours de campagne (le client a fourni
     * un nouveau visuel). Auto-marque l'ancienne pose comme "remplacée".
     */
    case RECHANGE = 'rechange';

    /**
     * Retouche / réparation d'une affiche déjà en place (recollage,
     * nettoyage graffiti, remplacement bâche déchirée). Ne clôture
     * pas la pose précédente — c'est une intervention corrective.
     */
    case RETOUCHE = 'retouche';

    /** Libellé UI FR. */
    public function label(): string
    {
        return match ($this) {
            self::INITIAL  => 'Pose initiale',
            self::RECHANGE => 'Rechange affiche',
            self::RETOUCHE => 'Retouche',
        };
    }

    /** Icône emoji pour badges compacts. */
    public function icon(): string
    {
        return match ($this) {
            self::INITIAL  => '🆕',
            self::RECHANGE => '🔄',
            self::RETOUCHE => '🔧',
        };
    }

    /**
     * Couleur badge — accent neutre pour initial, orange pour rechange
     * (opération à impact terrain), bleu pour retouche (technique).
     */
    public function color(): string
    {
        return match ($this) {
            self::INITIAL  => '#64748b',
            self::RECHANGE => '#f59e0b',
            self::RETOUCHE => '#3b82f6',
        };
    }

    /**
     * True si ce kind implique que l'ancienne pose est retirée
     * (rechange marque replaced_at sur la précédente ; retouche non
     * car l'ancienne affiche reste en place).
     */
    public function supersedesPrevious(): bool
    {
        return $this === self::RECHANGE;
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $k) => [$k->value => $k->label()])
            ->all();
    }
}
