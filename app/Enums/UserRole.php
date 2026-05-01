<?php
namespace App\Enums;

enum UserRole: string
{
    case ADMIN        = 'admin';
    case COMMERCIAL   = 'commercial';
    case MEDIAPLANNER = 'mediaplanner';
    case TECHNIQUE    = 'technique';

    /** Libellé humain (FR) — source unique de vérité */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN        => 'Administrateur',
            self::COMMERCIAL   => 'Commercial',
            self::MEDIAPLANNER => 'Media Planner',
            self::TECHNIQUE    => 'Technicien',
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
