<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SM2c B1 — Marque les piges envoyées HORS CRÉNEAU (tolérance 60 min par
 * défaut autour de scheduled_at). Permet à l'admin de filtrer / repérer
 * les exécutions non planifiées dans la timeline A2.
 *
 * Le flag est posé côté serveur quand la PoseTask passe en 'realisee' :
 *   $isOff = abs(now()->diffInMinutes($task->scheduled_at)) > 60
 * sans dépendance du flag côté client (qui n'est qu'un consentement UX).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('piges', function (Blueprint $t) {
            if (!Schema::hasColumn('piges', 'is_off_schedule')) {
                $t->boolean('is_off_schedule')->default(false)->after('rejection_reason');
                $t->index('is_off_schedule');
            }
        });
    }

    public function down(): void
    {
        Schema::table('piges', function (Blueprint $t) {
            if (Schema::hasColumn('piges', 'is_off_schedule')) {
                $t->dropIndex(['is_off_schedule']);
                $t->dropColumn('is_off_schedule');
            }
        });
    }
};
