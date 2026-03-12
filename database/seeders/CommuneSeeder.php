<?php

namespace Database\Seeders;

use App\Models\Commune;
use Illuminate\Database\Seeder;

class CommuneSeeder extends Seeder
{
    public function run(): void
    {
        $communes = [
            ['name' => 'Cocody',       'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 75000, 'tm_rate' => 35000],
            ['name' => 'Plateau',      'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 90000, 'tm_rate' => 45000],
            ['name' => 'Marcory',      'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 60000, 'tm_rate' => 30000],
            ['name' => 'Treichville',  'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 55000, 'tm_rate' => 25000],
            ['name' => 'Yopougon',     'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 65000, 'tm_rate' => 30000],
            ['name' => 'Abobo',        'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 50000, 'tm_rate' => 25000],
            ['name' => 'Adjamé',       'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 70000, 'tm_rate' => 35000],
            ['name' => 'Attécoubé',    'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 45000, 'tm_rate' => 20000],
            ['name' => 'Port-Bouët',   'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 55000, 'tm_rate' => 25000],
            ['name' => 'Koumassi',     'city' => 'Abidjan',      'region' => 'Lagunes', 'odp_rate' => 50000, 'tm_rate' => 22000],
            ['name' => 'Bouaké',       'city' => 'Bouaké',       'region' => 'Vallée du Bandama', 'odp_rate' => 40000, 'tm_rate' => 18000],
            ['name' => 'Daloa',        'city' => 'Daloa',        'region' => 'Haut-Sassandra',    'odp_rate' => 35000, 'tm_rate' => 15000],
            ['name' => 'San-Pédro',    'city' => 'San-Pédro',    'region' => 'Bas-Sassandra',     'odp_rate' => 38000, 'tm_rate' => 16000],
            ['name' => 'Yamoussoukro', 'city' => 'Yamoussoukro', 'region' => 'Lacs',              'odp_rate' => 42000, 'tm_rate' => 19000],
            ['name' => 'Korhogo',      'city' => 'Korhogo',      'region' => 'Savanes',           'odp_rate' => 33000, 'tm_rate' => 14000],
        ];

        foreach ($communes as $commune) {
            Commune::create($commune);
        }
    }
}