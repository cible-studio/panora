<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'       => 'Administrateur',
                'email'      => 'admin@cible.ci',
                'password'   => Hash::make('password'),
                'role'       => 'admin',
                'agent_code' => 'AG-0001',
                'is_active'  => true,
            ],
            [
                'name'       => 'Commercial 1',
                'email'      => 'commercial@cible.ci',
                'password'   => Hash::make('password'),
                'role'       => 'commercial',
                'agent_code' => 'AG-0002',
                'is_active'  => true,
            ],
            [
                'name'       => 'Technique 1',
                'email'      => 'technique@cible.ci',
                'password'   => Hash::make('password'),
                'role'       => 'technique',
                'agent_code' => 'AG-0003',
                'is_active'  => true,
            ],
            [
                'name'       => 'Media Planner 1',
                'email'      => 'mediaplanner@cible.ci',
                'password'   => Hash::make('password'),
                'role'       => 'mediaplanner',
                'agent_code' => 'AG-0004',
                'is_active'  => true,
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(['email' => $user['email']], $user);
        }

        User::factory(5)->create();
    }
}