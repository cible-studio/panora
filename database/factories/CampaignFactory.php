<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+1 month');
        $end   = $this->faker->dateTimeBetween($start, '+3 months');

        $campagnes = [
            'Campagne Ramadan', 'Promo Noël', 'Lancement Produit',
            'Campagne Image', 'Promo Été', 'Ouverture Agence',
        ];

        return [
            'name'          => $this->faker->randomElement($campagnes) . ' ' . now()->year,
            'client_id'     => Client::inRandomOrder()->first()?->id ?? Client::factory(),
            'reservation_id'=> null,
            'start_date'    => $start,
            'end_date'      => $end,
            'status'        => $this->faker->randomElement(['actif', 'pose', 'termine', 'annule']),
            'total_panels'  => $this->faker->numberBetween(3, 25),
            'total_amount'  => $this->faker->randomFloat(2, 1000000, 50000000),
            'notes'         => $this->faker->optional()->sentence(),
        ];
    }
}