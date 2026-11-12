<?php

namespace Tests\Unit;

use App\Http\Controllers\TechSpaceController;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\User;
use App\Services\GeoService;
use App\Support\TechCarte;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Espace technicien — refonte étape 1 : « Ma journée » et les axes
 * (2026-11-12). Règles validées par l'utilisateur :
 *
 *   - « Je suis arrivé » sur l'axe démarre TOUS ses pieds ;
 *   - un pied sans position est contrôlé sur celle de l'axe, à ± 500 m ;
 *   - axe entier d'un ancien contrat (choix 4b) : une photo par pied sur la
 *     même tâche, que le technicien ferme lui-même avec « Terminé ».
 *
 * Scénario : l'axe CDYLUP-004 (3 pieds) est posé pour MOOV par Yannick ;
 * le pied 3 est déjà fait aujourd'hui. COC-020 est un panneau ordinaire en
 * route. CDYLUP-009 est un axe entier vendu à BEM avant le découpage.
 *
 * Base SQLite réduite (les migrations ne passent pas sous SQLite).
 */
class EspaceTechAxesTest extends TestCase
{
    private const TOKEN = 'jeton-tech-axes';

    // Position de l'axe CDYLUP-004 (et de CDYLUP-009) ; 1° de latitude ≈ 111 195 m.
    private const AXE_LAT = 5.3600000;
    private const AXE_LNG = -3.9800000;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->nullable();
            $t->string('tech_public_token')->nullable(); $t->boolean('is_active')->default(true);
            $t->string('whatsapp_number')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->string('status')->default('actif');
            $t->date('start_date')->nullable(); $t->date('end_date')->nullable();
            $t->string('pige_token')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('panel_formats', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('panel_photos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->string('path');
            $t->unsignedInteger('ordre')->default(0); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('name')->nullable();
            $t->unsignedBigInteger('commune_id')->nullable(); $t->unsignedBigInteger('format_id')->nullable();
            $t->string('adresse')->nullable(); $t->string('quartier')->nullable();
            $t->decimal('latitude', 10, 7)->nullable(); $t->decimal('longitude', 10, 7)->nullable();
            $t->unsignedBigInteger('axe_id')->nullable(); $t->boolean('est_axe')->default(false);
            $t->unsignedSmallInteger('pied_numero')->nullable(); $t->unsignedSmallInteger('faces_par_pied')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('pose_teams', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('color_slug')->nullable(); $t->timestamps();
        });
        Schema::create('pose_tasks', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->unsignedBigInteger('assigned_user_id')->nullable(); $t->unsignedBigInteger('pose_team_id')->nullable();
            $t->string('team_name')->nullable(); $t->dateTime('scheduled_at')->nullable();
            $t->dateTime('started_at')->nullable(); $t->dateTime('arrived_at')->nullable();
            $t->dateTime('done_at')->nullable(); $t->unsignedInteger('real_minutes')->nullable();
            $t->string('status')->default('planifiee'); $t->unsignedTinyInteger('progress_percent')->default(0);
            $t->string('public_token')->nullable(); $t->string('tech_name_self')->nullable();
            $t->dateTime('tech_name_self_at')->nullable(); $t->string('tech_name_self_ip')->nullable();
            $t->unsignedBigInteger('completed_by_user_id')->nullable(); $t->string('completed_source')->nullable();
            $t->string('pose_kind')->nullable();
            $t->unsignedBigInteger('vague_id')->nullable(); $t->timestamps();
        });
        Schema::create('pose_task_actions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('pose_task_id'); $t->string('action'); $t->text('payload')->nullable();
            $t->string('actor')->nullable(); $t->string('ip_address')->nullable(); $t->timestamp('created_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
        });
        Schema::create('piges', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->unsignedBigInteger('pose_task_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('verified_by')->nullable(); $t->string('photo_path'); $t->string('photo_thumb')->nullable();
            $t->decimal('gps_lat', 10, 7)->nullable(); $t->decimal('gps_lng', 10, 7)->nullable();
            $t->unsignedSmallInteger('geo_distance_m')->nullable(); $t->string('geo_check')->nullable();
            $t->timestamp('taken_at')->nullable(); $t->timestamp('verified_at')->nullable();
            $t->string('status')->default('en_attente'); $t->text('rejection_reason')->nullable();
            $t->boolean('is_off_schedule')->default(false); $t->text('notes')->nullable();
            $t->string('client_uuid')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamps();
        });

        $aujourdhui = now()->setTime(9, 0)->toDateTimeString();

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Commercial', 'role' => 'commercial', 'tech_public_token' => null],
            ['id' => 2, 'name' => 'Yannick Kouassi', 'role' => 'technique', 'tech_public_token' => self::TOKEN],
            ['id' => 3, 'name' => 'Autre Tech', 'role' => 'technique', 'tech_public_token' => 'autre-jeton'],
        ]);
        DB::table('clients')->insert([['id' => 1, 'name' => 'MOOV AFRICA'], ['id' => 2, 'name' => 'BEM']]);
        DB::table('campaigns')->insert([
            ['id' => 1, 'name' => 'MOOV 2026', 'client_id' => 1, 'user_id' => 1, 'status' => 'actif'],
            ['id' => 2, 'name' => 'BEM 2026', 'client_id' => 2, 'user_id' => 1, 'status' => 'actif'],
        ]);
        DB::table('communes')->insert(['id' => 1, 'name' => 'Cocody']);
        DB::table('panels')->insert([
            ['id' => 10, 'reference' => 'CDYLUP-004', 'name' => 'Riviera 3 (3P x 2F)', 'commune_id' => 1,
             'est_axe' => true, 'axe_id' => null, 'pied_numero' => null,
             'latitude' => self::AXE_LAT, 'longitude' => self::AXE_LNG],
            // Les pieds naissent sans position : ils prennent celle de l'axe.
            ['id' => 11, 'reference' => 'CDYLUP-004-P01', 'name' => 'Riviera 3 — pied 1/3', 'commune_id' => 1,
             'est_axe' => false, 'axe_id' => 10, 'pied_numero' => 1, 'latitude' => null, 'longitude' => null],
            ['id' => 12, 'reference' => 'CDYLUP-004-P02', 'name' => 'Riviera 3 — pied 2/3', 'commune_id' => 1,
             'est_axe' => false, 'axe_id' => 10, 'pied_numero' => 2, 'latitude' => null, 'longitude' => null],
            ['id' => 13, 'reference' => 'CDYLUP-004-P03', 'name' => 'Riviera 3 — pied 3/3', 'commune_id' => 1,
             'est_axe' => false, 'axe_id' => 10, 'pied_numero' => 3, 'latitude' => null, 'longitude' => null],
            ['id' => 20, 'reference' => 'COC-020', 'name' => 'Riviera Golf', 'commune_id' => 1,
             'est_axe' => false, 'axe_id' => null, 'pied_numero' => null, 'latitude' => 5.3500000, 'longitude' => -3.9700000],
            // Axe entier d'un ancien contrat (jamais découpé pour BEM).
            ['id' => 30, 'reference' => 'CDYLUP-009', 'name' => 'Angré (4P x 2F)', 'commune_id' => 1,
             'est_axe' => true, 'axe_id' => null, 'pied_numero' => null,
             'latitude' => self::AXE_LAT, 'longitude' => self::AXE_LNG],
        ]);
        DB::table('pose_tasks')->insert([
            ['id' => 1, 'panel_id' => 11, 'campaign_id' => 1, 'assigned_user_id' => 2, 'status' => 'planifiee', 'scheduled_at' => $aujourdhui, 'done_at' => null],
            ['id' => 2, 'panel_id' => 12, 'campaign_id' => 1, 'assigned_user_id' => 2, 'status' => 'planifiee', 'scheduled_at' => $aujourdhui, 'done_at' => null],
            ['id' => 3, 'panel_id' => 13, 'campaign_id' => 1, 'assigned_user_id' => 2, 'status' => 'realisee', 'scheduled_at' => $aujourdhui, 'done_at' => now()->toDateTimeString()],
            ['id' => 4, 'panel_id' => 20, 'campaign_id' => 1, 'assigned_user_id' => 2, 'status' => 'en_route', 'scheduled_at' => $aujourdhui, 'done_at' => null],
            ['id' => 5, 'panel_id' => 30, 'campaign_id' => 2, 'assigned_user_id' => 2, 'status' => 'planifiee', 'scheduled_at' => $aujourdhui, 'done_at' => null],
            // Le même pied, pour un autre technicien : pas un frère.
            ['id' => 6, 'panel_id' => 12, 'campaign_id' => 1, 'assigned_user_id' => 3, 'status' => 'planifiee', 'scheduled_at' => $aujourdhui, 'done_at' => null],
        ]);
    }

    private function statut(int $id): string
    {
        return (string) DB::table('pose_tasks')->where('id', $id)->value('status');
    }

    /** Une position à N mètres au nord de l'axe. */
    private function aMetresDeLAxe(int $metres): array
    {
        return ['gps_lat' => number_format(self::AXE_LAT + $metres / 111195, 6, '.', ''), 'gps_lng' => '-3.980000'];
    }

    private function photo(array $extra = []): array
    {
        return ['photo' => UploadedFile::fake()->image('pige.jpg', 640, 480)] + $extra;
    }

    // ── Les pieds d'un axe avancent ensemble ───────────────────────

    public function test_les_freres_d_un_pied_sont_les_autres_pieds_ouverts_du_meme_axe(): void
    {
        // Pas le pied déjà fait (3), ni celui d'un autre technicien (6),
        // ni le panneau ordinaire (4), ni l'axe entier d'une autre campagne (5).
        $this->assertSame([2], PoseTask::find(1)->idsFreresDAxe());
        $this->assertSame([], PoseTask::find(4)->idsFreresDAxe());
        $this->assertSame([], PoseTask::find(5)->idsFreresDAxe());
    }

    public function test_je_suis_arrive_demarre_tous_les_pieds_de_l_axe(): void
    {
        $r = $this->postJson('/tech/' . self::TOKEN . '/poses/1/progress', ['percent' => 50]);

        $r->assertOk()->assertJson(['ok' => true, 'status' => 'en_cours']);
        foreach ([1, 2] as $id) {
            $tache = DB::table('pose_tasks')->where('id', $id)->first();
            $this->assertSame('en_cours', $tache->status, "pied de la tâche {$id}");
            $this->assertGreaterThanOrEqual(50, (int) $tache->progress_percent);
            $this->assertNotNull($tache->arrived_at);
        }
        // Un seul lieu à la fois : le panneau ordinaire repasse « à faire »,
        // mais pas les pieds de l'axe ; aucun pied n'est clos sans sa photo.
        $this->assertSame([4], $r->json('reverted_ids'));
        $this->assertSame('planifiee', $this->statut(4));
        $this->assertSame('realisee', $this->statut(3));
        $this->assertSame('planifiee', $this->statut(6));
    }

    public function test_y_aller_ne_fait_jamais_reculer_un_pied(): void
    {
        DB::table('pose_tasks')->where('id', 2)->update(['status' => 'en_cours', 'progress_percent' => 60, 'arrived_at' => now()]);

        $r = $this->postJson('/tech/' . self::TOKEN . '/poses/1/status', ['status' => 'en_route']);

        $r->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('en_route', $this->statut(1));
        $this->assertSame('en_cours', $this->statut(2));
        $this->assertSame(60, (int) DB::table('pose_tasks')->where('id', 2)->value('progress_percent'));
        $this->assertNotContains(2, $r->json('reverted_ids'));
    }

    // ── Photo d'un pied ────────────────────────────────────────────

    public function test_la_photo_d_un_pied_le_clot_et_renvoie_le_compteur_du_jour(): void
    {
        $r = $this->postJson('/tech/' . self::TOKEN . '/poses/1/photo', $this->photo($this->aMetresDeLAxe(300)));

        $r->assertOk()->assertJson(['ok' => true, 'task_done' => true, 'axe_entier' => false]);
        $this->assertSame('realisee', $this->statut(1));
        // Pied 3 (déjà fait) + pied 1 ; restent 2, 4 et 5 jusqu'à ce soir.
        $this->assertSame(['faites' => 2, 'total' => 5, 'pct' => 40], array_intersect_key($r->json('jour'), ['faites' => 0, 'total' => 0, 'pct' => 0]));
        // Le pied n'a pas de position : contrôlé sur l'axe, à ± 500 m.
        $pige = Pige::sole();
        $this->assertSame('ok', $pige->geo_check);
    }

    // ── Axe entier d'un ancien contrat (choix 4b) ──────────────────

    public function test_la_photo_d_un_axe_entier_laisse_la_tache_ouverte(): void
    {
        $r1 = $this->postJson('/tech/' . self::TOKEN . '/poses/5/photo', $this->photo($this->aMetresDeLAxe(300)));
        $r1->assertOk()->assertJson([
            'ok' => true, 'task_done' => false, 'axe_entier' => true, 'photos_count' => 1, 'is_last_pose_of_day' => false,
        ]);
        // La photo prouve qu'il est sur place : « Terminé » devient possible.
        $this->assertSame('en_cours', $this->statut(5));
        // L'axe entier a sa propre position, mais on y photographie chaque
        // pied le long de la route : même tolérance de 500 m.
        $this->assertSame('ok', Pige::sole()->geo_check);

        $this->postJson('/tech/' . self::TOKEN . '/poses/5/photo', $this->photo())
            ->assertOk()->assertJson(['task_done' => false, 'photos_count' => 2]);
        $this->assertSame('en_cours', $this->statut(5));

        // « Terminé » : c'est le technicien qui la ferme.
        $fin = $this->postJson('/tech/' . self::TOKEN . '/poses/5/status', ['status' => 'realisee']);
        $fin->assertOk()->assertJson(['ok' => true, 'status' => 'realisee']);
        $this->assertSame('realisee', $this->statut(5));
        $this->assertSame(2, $fin->json('jour.faites'));
    }

    public function test_un_envoi_en_double_sur_un_axe_entier_ne_le_dit_pas_termine(): void
    {
        $this->postJson('/tech/' . self::TOKEN . '/poses/5/photo', $this->photo(['client_uuid' => 'uuid-1']))->assertOk();
        $r = $this->postJson('/tech/' . self::TOKEN . '/poses/5/photo', $this->photo(['client_uuid' => 'uuid-1']));

        $r->assertOk()->assertJson(['ok' => true, 'task_done' => false, 'axe_entier' => true, 'deja_envoyee' => true, 'photos_count' => 1]);
        $this->assertSame(1, Pige::count());
    }

    public function test_une_pige_saisie_par_l_admin_ne_clot_pas_un_axe_entier(): void
    {
        foreach ([5 => 30, 1 => 11] as $tache => $panneau) {
            Pige::create([
                'panel_id' => $panneau, 'campaign_id' => $tache === 5 ? 2 : 1, 'pose_task_id' => $tache,
                'photo_path' => 'piges/x.jpg', 'taken_at' => now(), 'status' => 'en_attente',
            ]);
        }
        $this->assertSame('planifiee', $this->statut(5));
        $this->assertSame('realisee', $this->statut(1));
    }

    // ── Contrôle GPS : une seule règle, serveur et téléphone ───────

    public function test_un_pied_sans_position_est_controle_sur_l_axe_a_500_m(): void
    {
        $geo = app(GeoService::class);
        $pied = Panel::find(11);
        $verdict = fn (int $m) => $geo->verdictPourPanneau(self::AXE_LAT + $m / 111195, self::AXE_LNG, $pied)['check'];

        $this->assertSame('ok', $verdict(300));
        $this->assertSame('warn', $verdict(800));
        $this->assertNotContains($verdict(1500), ['ok', 'warn']);

        // Un panneau ordinaire garde ses 50 / 200 m.
        $ordinaire = $geo->verdictPourPanneau(5.3500000 + 300 / 111195, -3.9700000, Panel::find(20))['check'];
        $this->assertNotContains($ordinaire, ['ok', 'warn']);
    }

    public function test_le_telephone_recoit_les_seuils_du_serveur(): void
    {
        $this->assertSame(['ok' => 50.0, 'warn' => 200.0], array_map('floatval', GeoService::seuilsPour(Panel::find(20))));
        $this->assertSame(['ok' => 500.0, 'warn' => 1000.0], array_map('floatval', GeoService::seuilsPour(Panel::find(11))));
        $this->assertSame(['ok' => 500.0, 'warn' => 1000.0], array_map('floatval', GeoService::seuilsPour(Panel::find(30))));

        $pied = PoseTask::with('panel.axe')->find(1);
        $attrs = TechCarte::attributs($pied, TechCarte::donnees($pied));
        $this->assertStringContainsString('data-gps-ok="500"', $attrs);
        $this->assertStringContainsString('data-gps-repere="axe"', $attrs);
        // La position de l'axe sert aussi au « Y aller » du pied.
        $this->assertStringContainsString('data-lat="5.36"', $attrs);

        $ordinaire = PoseTask::with('panel')->find(4);
        $attrs = TechCarte::attributs($ordinaire, TechCarte::donnees($ordinaire));
        $this->assertStringContainsString('data-gps-ok="50"', $attrs);
        $this->assertStringContainsString('data-gps-repere="panneau"', $attrs);
    }

    // ── Ma journée ─────────────────────────────────────────────────

    public function test_les_pieds_d_un_axe_forment_une_seule_entree(): void
    {
        $taches = PoseTask::with('panel.axe')->whereIn('id', [2, 4, 1, 5])->get()
            ->sortBy(fn ($t) => array_search($t->id, [2, 4, 1, 5], true))->values();

        $entrees = TechSpaceController::entreesParAxe($taches);

        $this->assertSame(['axe', 'pose', 'pose'], array_column($entrees, 'type'));
        $this->assertSame(10, $entrees[0]['axe']->id);
        $this->assertSame([1, 2], $entrees[0]['tasks']->pluck('id')->all(), 'pieds dans l\'ordre du terrain');
        $this->assertSame(4, $entrees[1]['task']->id);
        $this->assertSame(5, $entrees[2]['task']->id, 'l\'axe entier reste une pose');
    }

    public function test_le_compteur_du_jour_compte_la_journee(): void
    {
        // Une pose de demain ne compte pas.
        DB::table('pose_tasks')->insert(['id' => 7, 'panel_id' => 20, 'campaign_id' => 1, 'assigned_user_id' => 2,
            'status' => 'planifiee', 'scheduled_at' => now()->addDay()->toDateTimeString()]);

        $jour = TechCarte::journee(User::find(2));

        $this->assertSame(1, $jour['faites']);
        $this->assertSame(5, $jour['total']);   // 1 faite + 1, 2, 4, 5
        $this->assertSame(20, $jour['pct']);
        $this->assertSame('Allez Yannick !', $jour['message']);
        $this->assertSame('Plus que 2 !', TechCarte::messageDuJour(3, 5, 'Yannick'));
        $this->assertSame('Journée terminée, bravo !', TechCarte::messageDuJour(5, 5, 'Yannick'));
        $this->assertSame('Rien de prévu aujourd\'hui', TechCarte::messageDuJour(0, 0, 'Yannick'));
    }

    public function test_ma_journee_affiche_une_carte_par_axe(): void
    {
        $r = $this->get('/tech/' . self::TOKEN . '/poses');

        $r->assertOk();
        $r->assertSee('data-axe-carte="10-1"', false);
        $r->assertSee('data-tx-pied="1"', false);
        $r->assertSee('data-tx-pied="2"', false);
        $r->assertSee('Je suis arrivé');
        $r->assertSee('data-axe-entier="1"', false);
        $r->assertSee('data-tx-terminer', false);
        $r->assertSee('1 / 5 aujourd', false);
        // Les pieds partagent la photo et la position de l'axe ; le pied 3,
        // déjà fait, dessine la route sans action.
        $r->assertSee('data-tx-jalon="3"', false);
        $this->assertSame(1, substr_count($r->getContent(), 'data-tx-segment="3"'));
    }
}
