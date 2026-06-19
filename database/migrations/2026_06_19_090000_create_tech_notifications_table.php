<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SM2c B3 — Table légère de notifications côté tech.
 *
 * Pas d'utilisation de la table notifications native Laravel pour rester
 * simple et autonome (les techs n'ont pas de session web admin, on n'a
 * pas besoin du DatabaseChannel complet).
 *
 * Types canoniques :
 *   - photo_rejected  : admin a refusé une photo
 *   - new_pose        : nouvelle PoseTask assignée
 *   - photo_validated : admin a validé une photo (info positive)
 *
 * Index (user_id, read_at) pour la requête "non lues" qui pilote le
 * badge rouge sur le bouton aide du header.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tech_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            $t->string('type', 40);
            $t->string('title', 200);
            $t->text('detail')->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'read_at']);
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_notifications');
    }
};
