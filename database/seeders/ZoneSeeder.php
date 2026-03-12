<?php

namespace Database\Seeders;

use App\Models\Zone;
use App\Models\Commune;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['commune' => 'Cocody',      'name' => 'Angré',          'demand_level' => 'tres_haute'],
            ['commune' => 'Cocody',      'name' => 'Riviera',        'demand_level' => 'haute'],
            ['commune' => 'Cocody',      'name' => 'II Plateaux',    'demand_level' => 'haute'],
            ['commune' => 'Plateau',     'name' => 'Centre Plateau', 'demand_level' => 'tres_haute'],
            ['commune' => 'Plateau',     'name' => 'Indénié',        'demand_level' => 'haute'],
            ['commune' => 'Marcory',     'name' => 'Zone 4',         'demand_level' => 'haute'],
            ['commune' => 'Yopougon',    'name' => 'Selmer',         'demand_level' => 'normale'],
            ['commune' => 'Yopougon',    'name' => 'Niangon',        'demand_level' => 'normale'],
            ['commune' => 'Adjamé',      'name' => 'Carrefour',      'demand_level' => 'haute'],
            ['commune' => 'Treichville', 'name' => 'Port',           'demand_level' => 'normale'],
            ['commune' => 'Bouaké',      'name' => 'Centre Bouaké',  'demand_level' => 'normale'],
            ['commune' => 'Yamoussoukro','name' => 'Centre Yam.',    'demand_level' => 'faible'],
        ];

        foreach ($zones as $z) {
            $commune = Commune::where('name', $z['commune'])->first();
            if ($commune) {
                Zone::create([
                    'commune_id'   => $commune->id,
                    'name'         => $z['name'],
                    'demand_level' => $z['demand_level'],
                    'description'  => null,
                ]);
            }
        }
    }
}