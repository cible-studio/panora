<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index composite pour la requête de chevauchement sur `campaigns`,
 * pendant de `idx_reservations_overlap` (côté reservations).
 *
 * Désormais le check anti-double-booking interroge AUSSI campaign_panels
 * joint à campaigns avec filtre status + start_date/end_date — sans cet
 * index, le coût grimpe linéairement avec le nombre de campagnes.
 *
 * Requête cible :
 *   SELECT panel_id FROM campaign_panels
 *   JOIN campaigns ON campaigns.id = campaign_panels.campaign_id
 *   WHERE campaigns.status IN ('planifie','actif','pause')
 *     AND campaigns.start_date <= ?
 *     AND campaigns.end_date   >= ?
 *     AND campaigns.deleted_at IS NULL
 *
 * Ordre des colonnes : status (filtre constant) → start_date (range)
 * → end_date (range). MySQL utilise le préfixe gauche, donc on doit
 * aussi indexer (campaign_id, panel_id) côté campaign_panels — déjà
 * couvert par la PK existante.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!self::indexExists('campaigns', 'idx_campaigns_overlap')) {
                $table->index(
                    ['status', 'start_date', 'end_date'],
                    'idx_campaigns_overlap'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (self::indexExists('campaigns', 'idx_campaigns_overlap')) {
                $table->dropIndex('idx_campaigns_overlap');
            }
        });
    }

    /**
     * Vérifie si un index nommé existe sur une table — évite les
     * échecs de migration si elle est rejouée sur une base déjà à jour.
     */
    private static function indexExists(string $table, string $indexName): bool
    {
        $conn = Schema::getConnection();
        $db = $conn->getDatabaseName();
        $row = $conn->selectOne(
            'SELECT COUNT(1) as cnt FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$db, $table, $indexName]
        );
        return (int) ($row->cnt ?? 0) > 0;
    }
};
