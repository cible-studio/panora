<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * Libellé du rôle (2026-11-02) : un compte Comptable apparaissait
 * « Technicien » dans la liste des utilisateurs, qui ne connaissait que
 * admin / commercial / MP. Le libellé vient désormais de UserRole.
 */
class RoleAfficheTest extends TestCase
{
    public function test_chaque_role_a_son_libelle_et_son_badge(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotSame('', $role->label());
            $this->assertNotSame('', $role->icone());
            $this->assertStringStartsWith('badge-', $role->classeBadge());
        }
        $this->assertSame('Comptable', UserRole::COMPTABLE->label());
        $this->assertNotSame(UserRole::TECHNIQUE->label(), UserRole::COMPTABLE->label());
    }

    public function test_la_liste_des_utilisateurs_ne_traduit_plus_les_roles_elle_meme(): void
    {
        $vue = file_get_contents(__DIR__ . '/../../resources/views/admin/users/index.blade.php');

        $this->assertStringNotContainsString("role->value === '", $vue);
        $this->assertStringContainsString('$user->role->label()', $vue);
    }
}
