<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PanelCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->randomElement([
                'Classique', 'Lumipub', 'Trivision',
                'Borne Kilométrique', 'Planimètre'
            ]),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}