<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PanelFormatFactory extends Factory
{
    public function definition(): array
    {
        $formats = [
            ['name' => '4m²',  'width' => 2.00, 'height' => 2.00, 'surface' => 4.00],
            ['name' => '8m²',  'width' => 4.00, 'height' => 2.00, 'surface' => 8.00],
            ['name' => '10m²', 'width' => 5.00, 'height' => 2.00, 'surface' => 10.00],
            ['name' => '12m²', 'width' => 4.00, 'height' => 3.00, 'surface' => 12.00],
            ['name' => '20m²', 'width' => 5.00, 'height' => 4.00, 'surface' => 20.00],
            ['name' => '36m²', 'width' => 9.00, 'height' => 4.00, 'surface' => 36.00],
            ['name' => '54m²', 'width' => 9.00, 'height' => 6.00, 'surface' => 54.00],
        ];

        $format = $this->faker->randomElement($formats);

        return [
            'name'       => $format['name'],
            'width'      => $format['width'],
            'height'     => $format['height'],
            'surface'    => $format['surface'],
            'print_type' => $this->faker->randomElement(['bâche', 'papier', 'led', 'vinyle']),
        ];
    }
}