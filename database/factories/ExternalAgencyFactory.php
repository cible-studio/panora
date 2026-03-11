<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExternalAgencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => $this->faker->company() . ' Pub',
            'contact_name' => $this->faker->name(),
            'email'        => $this->faker->companyEmail(),
            'phone'        => '+225 0' . $this->faker->numerify('# ## ## ## ##'),
            'address'      => $this->faker->address(),
        ];
    }
}