<?php
namespace App\Enums;

enum UserRole: string
{
    case ADMIN        = 'admin';
    case COMMERCIAL   = 'commercial';
    case MEDIAPLANNER = 'mediaplanner';
    case COMPTABLE    = 'comptable';
    case TECHNIQUE    = 'technique';

    /** Libellé humain (FR) — source unique de vérité */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN        => 'Administrateur',
            self::COMMERCIAL   => 'Commercial',
            self::MEDIAPLANNER => 'Media Planner',
            self::COMPTABLE    => 'Comptable',
            self::TECHNIQUE    => 'Technicien',
        };
    }

    /**
     * Badge de la liste des utilisateurs (2026-11-02) : icône + couleur.
     * Avant, la vue ne connaissait que admin / commercial / MP et affichait
     * « Technicien » pour tout le reste — les comptables compris.
     */
    public function icone(): string
    {
        return match ($this) {
            self::ADMIN        => '🛡️',
            self::COMMERCIAL   => '💼',
            self::MEDIAPLANNER => '🗓️',
            self::COMPTABLE    => '📊',
            self::TECHNIQUE    => '🔧',
        };
    }

    public function classeBadge(): string
    {
        return match ($this) {
            self::ADMIN        => 'badge-red',
            self::COMMERCIAL   => 'badge-blue',
            self::MEDIAPLANNER => 'badge-purple',
            self::COMPTABLE    => 'badge-green',
            self::TECHNIQUE    => 'badge-orange',
        };
    }

    /** Libellé sûr pour une valeur éventuellement non-castée (string, enum ou null) */
    public static function labelFor(string|self|null $role): string
    {
        if ($role === null) return '—';
        if ($role instanceof self) return $role->label();
        return self::tryFrom($role)?->label() ?? ucfirst($role);
    }
}
