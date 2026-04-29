<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE campaigns 
            MODIFY COLUMN status ENUM('planifie','actif','pose','termine','annule') 
            NOT NULL DEFAULT 'actif'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE campaigns 
            MODIFY COLUMN status ENUM('actif','pose','termine','annule') 
            NOT NULL DEFAULT 'actif'");
    }
};