<?php

namespace Database\Seeders;

use App\Models\PanelCategory;
use Illuminate\Database\Seeder;

class PanelCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Classique',          'description' => 'Panneau standard rétroéclairé ou non'],
            ['name' => 'Lumipub',            'description' => 'Panneau lumineux rétroéclairé'],
            ['name' => 'Trivision',          'description' => 'Panneau à 3 faces rotatives'],
            ['name' => 'Borne Kilométrique', 'description' => 'Petit format bord de route'],
            ['name' => 'Planimètre',         'description' => 'Panneau plan grand format'],
        ];

        foreach ($categories as $category) {
            PanelCategory::create($category);
        }
    }
}