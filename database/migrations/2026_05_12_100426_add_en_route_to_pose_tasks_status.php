<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE pose_tasks MODIFY COLUMN status ENUM('planifiee','en_route','en_cours','realisee','annulee') NOT NULL DEFAULT 'planifiee'");
    }

    public function down(): void
    {
        DB::statement("UPDATE pose_tasks SET status = 'planifiee' WHERE status = 'en_route'");
        DB::statement("ALTER TABLE pose_tasks MODIFY COLUMN status ENUM('planifiee','en_cours','realisee','annulee') NOT NULL DEFAULT 'planifiee'");
    }
};
