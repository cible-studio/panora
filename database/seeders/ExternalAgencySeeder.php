<?php

namespace Database\Seeders;

use App\Models\ExternalAgency;
use Illuminate\Database\Seeder;

class ExternalAgencySeeder extends Seeder
{
    public function run(): void
    {
        $agencies = [
            ['name' => 'Média Plus CI',  'contact_name' => 'Aka Serge',    'email' => 'contact@mediaplus.ci',  'phone' => '+225 07 11 11 11', 'address' => 'Adjamé, Abidjan'],
            ['name' => 'Afrique Pub',    'contact_name' => 'Traoré Luc',   'email' => 'contact@afriquepub.ci', 'phone' => '+225 07 22 22 22', 'address' => 'Cocody, Abidjan'],
            ['name' => 'PanneauxCom CI', 'contact_name' => 'Ouédraogo Ben','email' => 'info@panneauxcom.ci',  'phone' => '+225 07 33 33 33', 'address' => 'Plateau, Abidjan'],
        ];

        foreach ($agencies as $agency) {
            ExternalAgency::create($agency);
        }

        ExternalAgency::factory(4)->create();
    }
}