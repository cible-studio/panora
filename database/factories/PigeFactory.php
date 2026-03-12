<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Panel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PigeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'panel_id'    => Panel::inRandomOrder()->first()?->id ?? 1,
            'campaign_id' => Campaign::inRandomOrder()->first()?->id,
            'user_id'     => User::inRandomOrder()->first()?->id ?? 1,
            'photo_path'  => 'piges/sample.jpg',
            'taken_at'    => $this->faker->dateTimeBetween('-1 month', 'now'),
            'gps_lat'     => $this->faker->optional()->latitude(5.2, 5.5),
            'gps_lng'     => $this->faker->optional()->longitude(-4.1, -3.8),
            'is_verified' => $this->faker->boolean(),
            'verified_by' => null,
            'verified_at' => null,
            'notes'       => $this->faker->optional()->sentence(),
        ];
    }
}