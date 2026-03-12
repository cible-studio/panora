<?php

namespace Database\Factories;

use App\Models\Commune;
use Illuminate\Database\Eloquent\Factories\Factory;

class ZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'commune_id'   => Commune::inRandomOrder()->first()?->id ?? 1,
            'name'         => $this->faker->randomElement([
                'Zone Nord', 'Zone Sud', 'Zone Est', 'Zone Ouest',
                'Centre Ville', 'Périphérie', 'Autoroute'
            ]),
            'description'  => $this->faker->optional()->sentence(),
            'demand_level' => $this->faker->randomElement([
                'faible', 'normale', 'haute', 'tres_haute'
            ]),
        ];
    }
}