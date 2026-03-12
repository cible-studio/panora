<?php

namespace Database\Seeders;

use App\Models\ExternalAgency;
use Illuminate\Database\Seeder;

class ExternalAgencySeeder extends Seeder
{
    public function run(): void
    {
        $agencies = [
            ['name' => 'Média Plus CI',  'contact' => 'Aka Serge',     'email' => 'contact@mediaplus.ci',  'address' => 'Adjamé, Abidjan'],
            ['name' => 'Afrique Pub',    'contact' => 'Traoré Luc',    'email' => 'contact@afriquepub.ci', 'address' => 'Cocody, Abidjan'],
            ['name' => 'PanneauxCom CI', 'contact' => 'Ouédraogo Ben', 'email' => 'info@panneauxcom.ci',   'address' => 'Plateau, Abidjan'],
        ];

        foreach ($agencies as $agency) {
            ExternalAgency::create($agency);
        }

        ExternalAgency::factory(4)->create();
    }
}