<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fêtes musulmanes 2026-2027 pour le calendrier de diffusion des
 * disponibilités (2026-09-29).
 *
 * Ces dates dépendent de l'observation de la lune et sont fixées chaque
 * année par décret : Panora ne peut pas les calculer. Livrées ici par
 * migration pour qu'elles arrivent en même temps sur le staging et la
 * prod, sans ressaisie. Les années suivantes se saisissent dans l'écran
 * Administration → Diffusion dispos.
 *
 * 2026 : dates fixées (sources : FratMat, Presse Côte d'Ivoire,
 *        allAfrica, iCalendrier — transmises par la patronne).
 * 2027 : dates ESTIMÉES, à confirmer par décret. L'Aïd el-Fitr 2027
 *        tombera le 9 ou le 10 mars : les deux jours sont bloqués. Un jour
 *        férié de trop ne fait que décaler un envoi d'un jour, un jour
 *        manquant ferait partir un mail un jour chômé.
 *
 * insertOrIgnore : une date déjà saisie à la main par l'admin (contrainte
 * unique sur la date) est conservée telle quelle.
 */
return new class extends Migration
{
    private const FERIES = [
        ['2026-03-16', 'Lendemain de la Nuit du Destin'],
        ['2026-03-20', 'Aïd el-Fitr (Korité)'],
        ['2026-05-27', 'Tabaski (Aïd el-Kébir)'],
        ['2026-08-25', 'Maouloud'],
        ['2027-03-05', 'Lendemain de la Nuit du Destin (date estimée)'],
        ['2027-03-09', 'Aïd el-Fitr (Korité) — 9 ou 10 mars, date estimée'],
        ['2027-03-10', 'Aïd el-Fitr (Korité) — 9 ou 10 mars, date estimée'],
        ['2027-05-17', 'Tabaski (Aïd el-Kébir) (date estimée)'],
        ['2027-08-14', 'Maouloud (date estimée)'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('jours_feries')) {
            return;
        }

        DB::table('jours_feries')->insertOrIgnore(array_map(fn($f) => [
            'date'       => $f[0],
            'libelle'    => $f[1],
            'source'     => 'musulman',
            'created_at' => now(),
            'updated_at' => now(),
        ], self::FERIES));
    }

    public function down(): void
    {
        if (!Schema::hasTable('jours_feries')) {
            return;
        }

        DB::table('jours_feries')
            ->where('source', 'musulman')
            ->whereIn('date', array_column(self::FERIES, 0))
            ->delete();
    }
};
