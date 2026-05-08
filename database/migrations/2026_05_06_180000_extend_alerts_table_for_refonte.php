<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refonte module Alertes :
 *   - archived_at : permet de "ranger" une alerte sans la supprimer (audit log)
 *   - dedup_key : empreinte unique pour éviter qu'un même évènement (par
 *     ex. "campagne X expire dans 7 jours") ne crée plusieurs alertes
 *     non lues identiques. Indexé pour lookup rapide.
 *   - Indexes composés pour les requêtes fréquentes (count non lues,
 *     filtre par type, tri par date).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            if (!Schema::hasColumn('alerts', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('triggered_at');
            }
            if (!Schema::hasColumn('alerts', 'dedup_key')) {
                $table->string('dedup_key', 191)->nullable()->after('related_id');
            }
        });

        Schema::table('alerts', function (Blueprint $table) {
            // Indexes — Laravel ignore silencieusement si déjà présents sur certains drivers,
            // sinon on protège via un try (cas où la migration tourne plusieurs fois).
            try { $table->index(['is_read', 'created_at'], 'alerts_unread_created_idx'); } catch (\Throwable) {}
            try { $table->index(['type', 'is_read'],       'alerts_type_unread_idx');    } catch (\Throwable) {}
            try { $table->index('dedup_key',                'alerts_dedup_key_idx');     } catch (\Throwable) {}
            try { $table->index('archived_at',              'alerts_archived_idx');      } catch (\Throwable) {}
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            try { $table->dropIndex('alerts_unread_created_idx'); } catch (\Throwable) {}
            try { $table->dropIndex('alerts_type_unread_idx');    } catch (\Throwable) {}
            try { $table->dropIndex('alerts_dedup_key_idx');      } catch (\Throwable) {}
            try { $table->dropIndex('alerts_archived_idx');       } catch (\Throwable) {}
            if (Schema::hasColumn('alerts', 'archived_at'))  $table->dropColumn('archived_at');
            if (Schema::hasColumn('alerts', 'dedup_key'))    $table->dropColumn('dedup_key');
        });
    }
};
