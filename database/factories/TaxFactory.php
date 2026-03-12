<?php

namespace Database\Factories;

use App\Models\Commune;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxFactory extends Factory
{
    public function definition(): array
    {
        return [
            'commune_id' => Commune::inRandomOrder()->first()?->id ?? 1,
            'year'       => $this->faker->randomElement([2024, 2025, 2026]),
            'type'       => $this->faker->randomElement(['odp', 'tm']),
            'amount'     => $this->faker->randomFloat(2, 50000, 500000),
            'due_date'   => $this->faker->dateTimeBetween('now', '+6 months'),
            'paid_at'    => $this->faker->optional()->dateTimeBetween('-3 months', 'now'),
            'status'     => $this->faker->randomElement(['en_attente', 'payee', 'en_retard']),
        ];
    }
}