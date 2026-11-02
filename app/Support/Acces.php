<?php

namespace App\Support;

use App\Models\User;

/**
 * Qui voit quoi : lecture de config/acces.php, la source unique des droits
 * par module (2026-11-02). Menu, routes, policies et vues passent par ici
 * au lieu de recopier chacun leur liste de rôles.
 */
final class Acces
{
    /** @return list<string> rôles autorisés pour le module */
    public static function roles(string $module): array
    {
        $roles = config("acces.modules.{$module}.roles");
        if (!is_array($roles)) {
            throw new \InvalidArgumentException("Module d'accès inconnu : {$module} (config/acces.php).");
        }

        return $roles;
    }

    /** L'utilisateur (connecté par défaut) a-t-il accès au module ? */
    public static function peut(string $module, ?User $user = null): bool
    {
        $role = ($user ?? auth()->user())?->role?->value;

        return $role !== null && in_array($role, self::roles($module), true);
    }

    /** Middleware de route correspondant : « role:admin,commercial,… ». */
    public static function middleware(string $module): string
    {
        return 'role:' . implode(',', self::roles($module));
    }
}
