<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        // Clients réels pour les tests
        $clients = [
            ['name' => 'Orange Côte d\'Ivoire', 'sector' => 'Télécommunications', 'contact_name' => 'Koné Ibrahim', 'email' => 'i.kone@orange.ci', 'phone' => '+225 07 07 07 07', 'address' => 'Plateau, Abidjan'],
            ['name' => 'MTN Côte d\'Ivoire', 'sector' => 'Télécommunications', 'contact_name' => 'Touré Fatima', 'email' => 'f.toure@mtn.ci', 'phone' => '+225 05 05 05 05', 'address' => 'Cocody, Abidjan'],
            ['name' => 'Brassivoire', 'sector' => 'Agroalimentaire', 'contact_name' => 'Coulibaly Moussa', 'email' => 'm.coulibaly@brassivoire.ci', 'phone' => '+225 01 01 01 01', 'address' => 'Yopougon, Abidjan'],
            ['name' => 'Société Générale CI', 'sector' => 'Banque', 'contact_name' => 'Bamba Awa', 'email' => 'a.bamba@sgci.com', 'phone' => '+225 09 09 09 09', 'address' => 'Plateau, Abidjan'],
            ['name' => 'Moov Africa CI', 'sector' => 'Télécommunications', 'contact_name' => 'Diallo Seydou', 'email' => 's.diallo@moov.ci', 'phone' => '+225 01 02 03 04', 'address' => 'Marcory, Abidjan'],
            ['name' => 'CFAO Motors', 'sector' => 'Distribution', 'contact_name' => 'Yao Kouamé', 'email' => 'k.yao@cfao.ci', 'phone' => '+225 07 08 09 10', 'address' => 'Treichville, Abidjan'],
        ];

        foreach ($clients as $client) {
            Client::create($client);
        }

        // 20 clients aléatoires supplémentaires
        Client::factory(20)->create();
    }
}