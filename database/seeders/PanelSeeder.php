<?php

namespace Database\Seeders;

use App\Models\Panel;
use Illuminate\Database\Seeder;

class PanelSeeder extends Seeder
{
    public function run(): void
    {
        Panel::factory(50)->create();
    }
}