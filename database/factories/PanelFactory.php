<?php

namespace Database\Factories;

use App\Enums\PanelStatus;
use App\Models\Commune;
use App\Models\Zone;
use App\Models\PanelCategory;
use App\Models\PanelFormat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PanelFactory extends Factory
{
    public function definition(): array
    {
        $prefixes = ['CDY', 'PLT', 'MRC', 'YOP', 'ABO', 'ADJ', 'TRC', 'KMS'];

        return [
            'reference'          => $this->faker->randomElement($prefixes) . '-' . $this->faker->numerify('###') . strtoupper($this->faker->randomLetter()),
            'name'               => $this->faker->streetAddress(),
            'commune_id'         => Commune::inRandomOrder()->first()?->id ?? 1,
            'zone_id'            => Zone::inRandomOrder()->first()?->id,
            'format_id'          => PanelFormat::inRandomOrder()->first()?->id ?? 1,
            'category_id'        => PanelCategory::inRandomOrder()->first()?->id,
            'latitude'           => $this->faker->latitude(5.2, 5.5),
            'longitude'          => $this->faker->longitude(-4.1, -3.8),
            'status'             => $this->faker->randomElement(PanelStatus::cases())->value,
            'is_lit'             => $this->faker->boolean(30),
            'monthly_rate'       => $this->faker->randomFloat(2, 200000, 2000000),
            'daily_traffic'      => $this->faker->optional()->numberBetween(5000, 80000),
            'maintenance_status' => $this->faker->randomElement(['bon', 'moyen', 'defaillant']),
            'zone_description'   => $this->faker->optional()->sentence(),
            'created_by'         => User::inRandomOrder()->first()?->id ?? 1,
        ];
    }
}