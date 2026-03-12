<?php

namespace Database\Seeders;

use App\Models\PanelFormat;
use Illuminate\Database\Seeder;

class PanelFormatSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            ['name' => '4m²',  'width' => 2.00, 'height' => 2.00, 'surface' => 4.00,  'print_type' => 'bâche'],
            ['name' => '8m²',  'width' => 4.00, 'height' => 2.00, 'surface' => 8.00,  'print_type' => 'bâche'],
            ['name' => '10m²', 'width' => 5.00, 'height' => 2.00, 'surface' => 10.00, 'print_type' => 'bâche'],
            ['name' => '12m²', 'width' => 4.00, 'height' => 3.00, 'surface' => 12.00, 'print_type' => 'bâche'],
            ['name' => '20m²', 'width' => 5.00, 'height' => 4.00, 'surface' => 20.00, 'print_type' => 'bâche'],
            ['name' => '36m²', 'width' => 9.00, 'height' => 4.00, 'surface' => 36.00, 'print_type' => 'bâche'],
            ['name' => '54m²', 'width' => 9.00, 'height' => 6.00, 'surface' => 54.00, 'print_type' => 'bâche'],
        ];

        foreach ($formats as $format) {
            PanelFormat::create($format);
        }
    }
}