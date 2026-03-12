<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CommuneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'     => $this->faker->randomElement([
                'Cocody', 'Plateau', 'Marcory', 'Treichville',
                'Yopougon', 'Abobo', 'Adjamé', 'Attécoubé',
                'Port-Bouët', 'Koumassi'
            ]),
            'city'     => 'Abidjan',
            'region'   => 'Lagunes',
            'odp_rate' => $this->faker->randomFloat(2, 10000, 100000),
            'tm_rate'  => $this->faker->randomFloat(2, 5000, 50000),
        ];
    }
}