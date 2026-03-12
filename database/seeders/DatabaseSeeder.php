<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Référentiels — pas de FK
            CommuneSeeder::class,
            PanelCategorySeeder::class,
            PanelFormatSeeder::class,

            // Zones — dépend de communes
            ZoneSeeder::class,

            // Utilisateurs
            UserSeeder::class,

            // Panneaux — dépend de tout ce qui précède
            PanelSeeder::class,

            // Dev B
            ClientSeeder::class,
            ExternalAgencySeeder::class,
            CampaignSeeder::class,
            ReservationSeeder::class,
        ]);
    }
}