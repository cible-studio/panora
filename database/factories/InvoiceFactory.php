<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference'   => 'FAC-' . now()->year . '-' . strtoupper($this->faker->bothify('####')),
            'client_id'   => Client::inRandomOrder()->first()?->id ?? Client::factory(),
            'campaign_id' => Campaign::inRandomOrder()->first()?->id ?? Campaign::factory(),
            'amount'      => $this->faker->randomFloat(2, 500000, 20000000),
            'issued_at'   => $this->faker->dateTimeBetween('-3 months', 'now'),
            'paid_at'     => $this->faker->optional()->dateTimeBetween('-2 months', 'now'),
            'status'      => $this->faker->randomElement(['en_attente', 'paye', 'annule']),
        ];
    }
}