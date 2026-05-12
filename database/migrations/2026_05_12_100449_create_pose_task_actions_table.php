<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pose_task_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pose_task_id')->constrained()->onDelete('cascade');
            // action types: status_changed, progress_updated, photo_uploaded,
            //               photo_replaced, photo_deleted, marked_done
            $table->string('action', 50);
            $table->json('payload')->nullable();   // context: old/new status, path, %…
            $table->string('actor', 100)->nullable(); // tech name or 'system'
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pose_task_actions');
    }
};
