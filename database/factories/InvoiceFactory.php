<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 500000, 20000000);
        $tva    = 18.00;
        $ttc    = $amount * (1 + $tva / 100);

        return [
            'reference'   => 'FAC-' . now()->year . '-' . strtoupper($this->faker->bothify('####')),
            'client_id'   => Client::inRandomOrder()->first()?->id ?? 1,
            'campaign_id' => Campaign::inRandomOrder()->first()?->id,
            'created_by'  => User::inRandomOrder()->first()?->id ?? 1,
            'amount'      => $amount,
            'tva'         => $tva,
            'amount_ttc'  => round($ttc, 2),
            'issued_at'   => $this->faker->dateTimeBetween('-3 months', 'now'),
            'paid_at'     => $this->faker->optional()->dateTimeBetween('-2 months', 'now'),
            'status'      => $this->faker->randomElement(['brouillon', 'envoyee', 'payee', 'annulee']),
        ];
    }
}