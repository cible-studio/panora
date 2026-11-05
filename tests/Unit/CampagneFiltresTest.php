<?php

namespace Tests\Unit;

use App\Exports\CampaignsExport;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Campagnes\CampagneFiltres;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Filtres de la liste des campagnes (2026-11-05).
 *
 * Constat : « les campagnes lancées en septembre » ne sortaient pas — le
 * filtre Du / Au gardait seulement celles débutées après le « Du » ET
 * finies avant le « Au ». Règles validées :
 *   · Lancée du / au : date de début dans la période, fin indifférente ;
 *   · Actives (lien de la Performance commerciale) : chevauchement, hors
 *     annulées, comme l'indicateur « CA TTC équipe » ;
 *   · Fin proche (bandeau) : Campaign::endingSoon, comme son compteur ;
 *   · exports = mêmes filtres que l'écran, périmètre du commercial compris.
 *
 * Base SQLite réduite (les migrations ne passent pas sous SQLite).
 */
class CampagneFiltresTest extends TestCase
{
    private const ALI = 10;    // commercial
    private const BINTA = 11;  // autre commercial

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->nullable(); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('commercial_user_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedBigInteger('reservation_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('commercial_user_id')->nullable(); $t->string('status');
            $t->date('start_date'); $t->date('end_date'); $t->unsignedBigInteger('total_amount')->default(0);
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('commune_id')->nullable();
            $t->unsignedBigInteger('zone_id')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->string('type')->default('interne'); $t->timestamps();
        });

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Admin', 'role' => 'admin'],
            ['id' => self::ALI, 'name' => 'Ali', 'role' => 'commercial'],
            ['id' => self::BINTA, 'name' => 'Binta', 'role' => 'commercial'],
        ]);
        DB::table('campaigns')->insert([
            // Lancée en septembre, finie en décembre : le cas qui ne sortait pas.
            ['id' => 1, 'name' => 'MOOV RENTRÉE', 'status' => 'actif', 'start_date' => '2026-09-10', 'end_date' => '2026-12-31', 'commercial_user_id' => self::ALI],
            // Entièrement dans septembre.
            ['id' => 2, 'name' => 'ORANGE FLASH', 'status' => 'termine', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'commercial_user_id' => self::BINTA],
            // Lancée en août, encore active en septembre.
            ['id' => 3, 'name' => 'MTN ÉTÉ', 'status' => 'termine', 'start_date' => '2026-08-01', 'end_date' => '2026-09-15', 'commercial_user_id' => self::ALI],
            // Lancée en septembre puis annulée.
            ['id' => 4, 'name' => 'ANNULÉE', 'status' => 'annule', 'start_date' => '2026-09-20', 'end_date' => '2026-10-20', 'commercial_user_id' => self::BINTA],
            // Finit dans 10 jours.
            ['id' => 5, 'name' => 'FIN PROCHE', 'status' => 'actif', 'start_date' => '2026-10-01', 'end_date' => '2026-11-15', 'commercial_user_id' => self::BINTA],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ids(array $filtres, ?User $user = null): array
    {
        return CampagneFiltres::appliquer(Campaign::query(), $filtres, $user ?? User::find(1))
            ->orderBy('id')->pluck('id')->all();
    }

    public function test_lancees_en_septembre_quelle_que_soit_la_fin(): void
    {
        $septembre = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30'];

        // 1 (finie en décembre) et 4 (annulée) sortent désormais ; 3, lancée en août, non.
        $this->assertSame([1, 2, 4], $this->ids($septembre));
        // L'ancien couple date_debut / date_fin suit la même règle.
        $this->assertSame([1, 2, 4], $this->ids(['date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']));
    }

    public function test_actives_pendant_la_periode_comme_l_indicateur_ca_ttc(): void
    {
        // Chevauchent septembre, hors annulée : 1, 2, 3.
        $this->assertSame([1, 2, 3], $this->ids(['actives_du' => '2026-09-01', 'actives_au' => '2026-09-30']));
    }

    public function test_fin_proche_comme_le_compteur_du_bandeau(): void
    {
        $this->assertSame([5], $this->ids(['fin_proche' => 14]));
        $this->assertSame(Campaign::endingSoon(14)->pluck('id')->all(), $this->ids(['fin_proche' => 14]));
    }

    public function test_un_commercial_ne_voit_que_ses_campagnes(): void
    {
        $this->assertSame([1, 3], $this->ids([], User::find(self::ALI)));
        $this->assertSame([1], $this->ids(['date_from' => '2026-09-01', 'date_to' => '2026-09-30'], User::find(self::ALI)));
    }

    public function test_l_export_excel_applique_les_memes_filtres_et_le_perimetre(): void
    {
        $septembre = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30'];
        $export = fn (array $f, int $user) => (new CampaignsExport($f, User::find($user)))->query()->pluck('id')->sort()->values()->all();

        $this->assertSame([1, 2, 4], $export($septembre, 1));
        $this->assertSame([1], $export($septembre, self::ALI));
        // Statut + commercial filtré (avant : ignoré par l'export).
        $this->assertSame([2], $export($septembre + ['status' => 'termine', 'commercial_user_id' => self::BINTA], 1));
        // Une sélection cochée ne sort jamais du périmètre du commercial.
        $this->assertSame([1], $export(['ids' => [1, 2, 4]], self::ALI));
    }

    public function test_l_ecran_et_les_liens_utilisent_les_nouvelles_regles(): void
    {
        $liste = file_get_contents(resource_path('views/admin/campaigns/index.blade.php'));
        $this->assertStringContainsString("'fin_proche' => 14", $liste);
        $this->assertStringContainsString('id="filter-mois"', $liste);
        $this->assertStringContainsString('function majLiensExport()', $liste);

        $perf = file_get_contents(resource_path('views/admin/performance/commerciaux/index.blade.php'));
        $this->assertStringContainsString("['actives_du' => \$fromStr, 'actives_au' => \$toStr]", $perf);

        // Plus aucune copie des filtres de dates hors CampagneFiltres.
        foreach (['app/Http/Controllers/Admin/CampaignController.php', 'app/Exports/CampaignsExport.php'] as $fichier) {
            $this->assertStringNotContainsString("where('end_date', '<='", file_get_contents(base_path($fichier)), $fichier);
        }
    }
}
