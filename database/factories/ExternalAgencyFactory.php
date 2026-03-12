<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExternalAgencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'    => $this->faker->company() . ' Pub',
            'contact' => $this->faker->name(),
            'email'   => $this->faker->unique()->companyEmail(),
            'address' => $this->faker->address(),
        ];
    }
}