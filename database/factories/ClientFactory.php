<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    public function definition(): array
    {
        $sectors = ['Télécommunications', 'Agroalimentaire', 'Banque', 'Assurance', 'Distribution', 'Énergie', 'Immobilier'];

        return [
            'name'         => $this->faker->company(),
            'sector'       => $this->faker->randomElement($sectors),
            'contact_name' => $this->faker->name(),
            'email'        => $this->faker->unique()->companyEmail(),
            'phone'        => '+225 0' . $this->faker->numerify('# ## ## ## ##'),
            'address'      => $this->faker->address(),
            'user_id'      => null,
        ];
    }
}