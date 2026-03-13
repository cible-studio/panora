<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache des rôles
        app()[\Spatie\Permission\PermissionRegistrar::class]
            ->forgetCachedPermissions();

        // Créer les 4 rôles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'commercial']);
        Role::firstOrCreate(['name' => 'mediaplanner']);
        Role::firstOrCreate(['name' => 'technique']);

        // Créer un admin par défaut
        $admin = User::firstOrCreate(
            ['email' => 'admin@cibleci.com'],
            [
                'name'     => 'Administrateur',
                'password' => bcrypt('password'),
                'role'     => 'admin',
                'is_active'=> true,
            ]
        );

        $admin->assignRole('admin');

        echo " Rôles créés avec succès !\n";
    }
}
