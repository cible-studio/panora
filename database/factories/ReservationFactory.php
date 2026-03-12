<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+2 months');
        $end   = $this->faker->dateTimeBetween($start, '+4 months');

        return [
            'reference'    => 'RES-' . now()->year . '-' . strtoupper($this->faker->bothify('####??')),
            'client_id'    => Client::inRandomOrder()->first()?->id ?? 1,
            'user_id'      => User::inRandomOrder()->first()?->id ?? 1,
            'start_date'   => $start,
            'end_date'     => $end,
            'status'       => $this->faker->randomElement(ReservationStatus::cases())->value,
            'total_amount' => $this->faker->randomFloat(2, 500000, 10000000),
            'notes'        => $this->faker->optional()->sentence(),
            'confirmed_at' => null,
        ];
    }
}