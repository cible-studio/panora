<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropositionFactory extends Factory
{
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+1 month');
        $end   = $this->faker->dateTimeBetween($start, '+3 months');

        return [
            'client_id'   => Client::inRandomOrder()->first()?->id ?? 1,
            'created_by'  => User::inRandomOrder()->first()?->id ?? 1,
            'numero'      => 'PROP-' . now()->year . '-' . strtoupper($this->faker->bothify('####')),
            'nb_panneaux' => $this->faker->numberBetween(1, 20),
            'date_debut'  => $start,
            'date_fin'    => $end,
            'montant'     => $this->faker->randomFloat(2, 500000, 10000000),
            'statut'      => $this->faker->randomElement(['en_attente', 'acceptee', 'refusee', 'expiree']),
            'notes'       => $this->faker->optional()->sentence(),
        ];
    }
}