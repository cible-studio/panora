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

    /** Rôles du personnel (le technicien et le client n'ont jamais de module). */
    public const PERSONNEL = ['admin', 'commercial', 'mediaplanner', 'comptable'];

    /** L'utilisateur (connecté par défaut) a-t-il accès au module ? */
    public static function peut(string $module, ?User $user = null): bool
    {
        $user ??= auth()->user();
        $role = $user?->role?->value;

        if ($role === null) {
            return false;
        }
        if (in_array($role, self::roles($module), true)) {
            return true;
        }

        // Personnes nommées en plus des rôles (2026-11-17, baromètre) : un
        // compte du personnel dont l'adresse figure dans « personnes ».
        $personnes = self::personnes($module);

        return $personnes !== []
            && in_array($role, self::PERSONNEL, true)
            && in_array(mb_strtolower(trim((string) $user->email)), $personnes, true);
    }

    /** @return list<string> adresses (minuscules) qui ont le module en plus des rôles */
    public static function personnes(string $module): array
    {
        return array_values(array_filter(array_map(
            fn ($e) => mb_strtolower(trim((string) $e)),
            (array) config("acces.modules.{$module}.personnes", [])
        )));
    }

    /**
     * Middleware de route correspondant : « role:admin,commercial,… ».
     * Module avec des personnes nommées : « acces:<module> » (VerifierAcces),
     * qui applique peut() — un rôle seul ne suffirait pas à les laisser entrer.
     */
    public static function middleware(string $module): string
    {
        return config("acces.modules.{$module}.personnes") !== null
            ? 'acces:' . $module
            : 'role:' . implode(',', self::roles($module));
    }
}
