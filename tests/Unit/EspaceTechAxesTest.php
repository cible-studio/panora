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
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('contact_name')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->string('status')->default('actif');
            $t->date('start_date')->nullable(); $t->date('end_date')->nullable();
            $t->unsignedBigInteger('client_contact_id')->nullable();
            $t->string('pige_token')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id');
            $t->string('type')->default('interne'); $t->timestamps();
        });
        Schema::create('public_links', function (Blueprint $t) {
            $t->id(); $t->string('token'); $t->string('type'); $t->string('target_type'); $t->unsignedBigInteger('target_id');
            $t->unsignedBigInteger('client_id')->nullable(); $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable(); $t->unsignedBigInteger('revoked_by_user_id')->nullable(); $t->string('revoked_reason')->nullable();
            $t->unsignedInteger('max_uses')->nullable(); $t->unsignedInteger('use_count')->default(0); $t->timestamp('used_at')->nullable();
            $t->unsignedInteger('access_count')->default(0); $t->timestamp('last_accessed_at')->nullable();
            $t->string('last_accessed_ip')->nullable(); $t->string('last_accessed_ua')->nullable();
            $t->unsignedBigInteger('created_by_user_id')->nullable(); $t->text('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('panel_formats', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->decimal('width', 8, 2)->nullable(); $t->decimal('height', 8, 2)->nullable();
            $t->decimal('surface', 8, 2)->nullable(); $t->timestamps();
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
            $t->string('gps_source')->nullable(); $t->boolean('gps_dispersion_flag')->default(false);
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
        Schema::create('tech_notifications', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('type'); $t->string('title');
            $t->text('detail')->nullable(); $t->text('payload')->nullable(); $t->timestamp('read_at')->nullable(); $t->timestamps();
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

    // ── Étape 2 : plus de rechargement, la pose suivante vient du serveur ──

    public function test_apres_une_photo_la_reponse_porte_la_nouvelle_maintenant(): void
    {
        // L'axe entier, en retard, devient la plus urgente une fois COC-020 faite.
        DB::table('pose_tasks')->where('id', 5)->update(['scheduled_at' => now()->subDays(10)->toDateTimeString()]);

        $r = $this->postJson('/tech/' . self::TOKEN . '/poses/4/photo', $this->photo());

        $r->assertOk()->assertJson(['ok' => true, 'task_done' => true]);
        $s = $r->json('suivante');
        $this->assertSame(5, $s['task_id']);
        $this->assertSame('CDYLUP-009', $s['reference']);
        $this->assertNull($s['cle']);
        // Rendue par le gabarit de la page : le téléphone remplace la section.
        $this->assertStringContainsString('data-tx-maintenant', $s['html']);
        $this->assertStringContainsString('data-axe-entier="1"', $s['html']);
    }

    public function test_tant_qu_il_reste_des_pieds_la_carte_de_l_axe_suit_seule(): void
    {
        $r = $this->postJson('/tech/' . self::TOKEN . '/poses/1/photo', $this->photo());

        $r->assertOk()->assertJson(['task_done' => true, 'pieds_restants' => 1]);
        $this->assertArrayNotHasKey('suivante', $r->json());

        // Le dernier pied : la suite est proposée (COC-020, en route).
        $fin = $this->postJson('/tech/' . self::TOKEN . '/poses/2/photo', $this->aMetresDeLAxe(100) + $this->photo());
        $fin->assertOk()->assertJson(['pieds_restants' => 0]);
        $this->assertSame(4, $fin->json('suivante.task_id'));
        $this->assertStringContainsString('tx-hero', $fin->json('suivante.html'));
        // Distance depuis l'endroit de la photo (≈ 1,6 km de l'axe à COC-020).
        $this->assertGreaterThan(1000, $fin->json('suivante.distance_m'));
    }

    public function test_si_la_suite_ne_se_calcule_pas_la_photo_reste_envoyee(): void
    {
        // Sans la table des communes, la page ne se construit plus : la photo,
        // déjà enregistrée, doit quand même répondre « envoyée », sans la clé
        // « suivante » (la page garde alors sa liste telle quelle).
        Schema::drop('communes');

        $r = $this->postJson('/tech/' . self::TOKEN . '/poses/4/photo', $this->photo());

        $r->assertOk()->assertJson(['ok' => true, 'task_done' => true]);
        $this->assertArrayNotHasKey('suivante', $r->json());
        $this->assertSame('realisee', $this->statut(4));
        $this->assertSame(1, Pige::count());
    }

    public function test_termine_sur_un_axe_entier_propose_aussi_la_suite(): void
    {
        $this->postJson('/tech/' . self::TOKEN . '/poses/5/photo', $this->photo())->assertOk();

        $r = $this->postJson('/tech/' . self::TOKEN . '/poses/5/status', ['status' => 'realisee']);

        $r->assertOk()->assertJson(['ok' => true]);
        $this->assertArrayHasKey('suivante', $r->json());
        $this->assertNotNull($r->json('suivante.task_id'));
        // « Y aller » ne propose pas la suite (seul « Terminé » clôt).
        $this->assertArrayNotHasKey('suivante', $this->postJson('/tech/' . self::TOKEN . '/poses/1/status', ['status' => 'en_route'])->json());
    }

    // ── Étape 3 : Carte, Mon chemin, Mes photos, feuille de route ──────

    public function test_la_carte_montre_un_arret_par_axe_a_sa_position(): void
    {
        $r = $this->get('/tech/' . self::TOKEN . '/poses/map');

        $r->assertOk();
        $points = collect($r->viewData('points'));
        // Avant : les pieds (sans position propre) disparaissaient de la carte.
        $this->assertCount(3, $points, 'l\'axe, COC-020 et l\'axe entier');
        $axe = $points->firstWhere('axe', '10-1');
        $this->assertNotNull($axe);
        $this->assertSame('CDYLUP-004', $axe['ref']);
        $this->assertSame([1, 2], $axe['ids']);
        $this->assertSame(2, $axe['pieds']);
        $this->assertEqualsWithDelta(self::AXE_LAT, $axe['lat'], 0.000001);
        $this->assertSame([], $r->viewData('sansPosition'));
        // « Voir » : la fiche (?focus=task_…) ou la carte de l'axe (?axe=…), plus « #pose-… ».
        $r->assertSee('?axe=', false);
        $r->assertSee('?focus=task_', false);
        $r->assertDontSee('#pose-', false);
        $r->assertSee('Ma tournée');
    }

    public function test_un_pied_sans_aucune_position_est_annonce_sans_position(): void
    {
        DB::table('panels')->where('id', 10)->update(['latitude' => null, 'longitude' => null]);

        $r = $this->get('/tech/' . self::TOKEN . '/poses/map');

        $this->assertSame(['CDYLUP-004'], $r->viewData('sansPosition'));
        $r->assertSee('1 panneau sans position');
    }

    public function test_mon_chemin_passe_une_fois_par_axe(): void
    {
        $r = $this->getJson('/tech/' . self::TOKEN . '/poses/optimize?lat=5.36&lng=-3.98');

        $r->assertOk()->assertJson(['ok' => true, 'count' => 3]);
        $arret = collect($r->json('order'))->firstWhere('ref', 'CDYLUP-004');
        $this->assertSame([1, 2], $arret['ids']);
        $this->assertSame([], $r->json('no_gps'));
    }

    public function test_la_feuille_de_route_donne_au_pied_la_position_de_son_axe(): void
    {
        $r = $this->get('/tech/' . self::TOKEN . '/poses/route-sheet');

        $r->assertOk();
        $r->assertSee('CDYLUP-004-P01');
        $r->assertSee('5.36000');
    }

    public function test_mes_photos_en_galerie_avec_refaire(): void
    {
        DB::table('piges')->insert([
            ['panel_id' => 11, 'campaign_id' => 1, 'pose_task_id' => 1, 'user_id' => 2, 'photo_path' => 'piges/a.jpg',
             'status' => 'rejete', 'rejection_reason' => 'Photo floue', 'taken_at' => now()->subHour()],
            ['panel_id' => 13, 'campaign_id' => 1, 'pose_task_id' => 3, 'user_id' => 2, 'photo_path' => 'piges/b.jpg',
             'status' => 'verifie', 'rejection_reason' => null, 'taken_at' => now()->subHours(2)],
        ]);

        $r = $this->get('/tech/' . self::TOKEN . '/piges');

        $r->assertOk();
        $r->assertSee('tx-galerie', false);
        $r->assertSee('Photo floue');
        $r->assertSee('refaire=1', false);
        $r->assertSee('À valider');
        // La barre du bas, onglet « Mes photos » actif ; « Alertes » ramène à Ma journée.
        $r->assertSee('class="tx-nav"', false);
        $r->assertSee('alertes=1', false);
    }

    public function test_photos_a_refaire_affiche_la_distance_enregistree(): void
    {
        DB::table('piges')->insert(['panel_id' => 11, 'campaign_id' => 1, 'pose_task_id' => 1, 'user_id' => 2,
            'photo_path' => 'piges/a.jpg', 'status' => 'rejete', 'rejection_reason' => 'Floue',
            'gps_lat' => 5.3627, 'gps_lng' => -3.98, 'geo_distance_m' => 300, 'geo_check' => 'ok', 'taken_at' => now()]);

        // Le pied n'a pas de position : l'ancien calcul en Blade n'affichait rien.
        $this->get('/tech/' . self::TOKEN . '/poses')->assertOk()->assertSee('À 300 m');
    }

    // ── Suivi des axes côté admin, phase A : les défauts ───────────────

    /** Une pige, sans passer par l'observateur (pas de notification). */
    private function pige(array $attributs): int
    {
        return DB::table('piges')->insertGetId($attributs + [
            'campaign_id' => 1, 'user_id' => 2, 'photo_path' => 'piges/x.jpg', 'status' => 'en_attente',
            'taken_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_la_derniere_photo_par_panneau_garde_toutes_celles_d_une_meme_pose(): void
    {
        // Même pose : 2 photos (photos multiples, validé le 12/11), les deux restent.
        $photo1 = $this->pige(['panel_id' => 20, 'pose_task_id' => 4, 'taken_at' => now()->subHours(3)]);
        $photo2 = $this->pige(['panel_id' => 20, 'pose_task_id' => 4, 'taken_at' => now()->subHours(2)]);
        // Une rechange (AUTRE pose du panneau) remplace les photos d'avant.
        DB::table('pose_tasks')->insert(['id' => 8, 'panel_id' => 20, 'campaign_id' => 1, 'assigned_user_id' => 2,
            'status' => 'planifiee', 'pose_kind' => 'rechange', 'scheduled_at' => now()->toDateTimeString()]);
        $rechange = $this->pige(['panel_id' => 20, 'pose_task_id' => 8, 'taken_at' => now()->subHour()]);
        // Axe entier : une photo par pied, aucune ne remplace l'autre.
        $p1 = $this->pige(['panel_id' => 30, 'campaign_id' => 2, 'pose_task_id' => 5, 'taken_at' => now()->subHours(3)]);
        $p2 = $this->pige(['panel_id' => 30, 'campaign_id' => 2, 'pose_task_id' => 5, 'taken_at' => now()->subHours(2)]);
        $p3 = $this->pige(['panel_id' => 30, 'campaign_id' => 2, 'pose_task_id' => 5, 'taken_at' => now()->subHour()]);

        $courantes = Pige::derniereParPanneau()->pluck('id')->all();

        $this->assertNotContains($photo1, $courantes, 'remplacée par la rechange');
        $this->assertNotContains($photo2, $courantes, 'remplacée par la rechange');
        $this->assertContains($rechange, $courantes);
        foreach ([$p1, $p2, $p3] as $id) {
            $this->assertContains($id, $courantes, 'chaque photo d\'un pied reste');
        }

        // Sans rechange : les 2 photos de la même pose restent.
        DB::table('piges')->where('id', $rechange)->delete();
        $courantes = Pige::derniereParPanneau()->pluck('id')->all();
        $this->assertContains($photo1, $courantes);
        $this->assertContains($photo2, $courantes);
    }

    public function test_une_photo_refusee_d_un_axe_entier_est_refaite_par_une_photo_posterieure_au_refus(): void
    {
        $refusee = $this->pige(['panel_id' => 30, 'campaign_id' => 2, 'pose_task_id' => 5, 'status' => 'rejete',
            'taken_at' => now()->subHours(3), 'verified_at' => now()->subHours(2)]);
        // Une photo d'un AUTRE pied prise avant le refus ne la remplace pas.
        $this->pige(['panel_id' => 30, 'campaign_id' => 2, 'pose_task_id' => 5, 'taken_at' => now()->subHours(2)->subMinutes(30)]);
        $this->assertContains($refusee, Pige::derniereParPanneau()->pluck('id')->all(), 'encore à refaire');

        // Une nouvelle photo arrive après le refus : refaite.
        $this->pige(['panel_id' => 30, 'campaign_id' => 2, 'pose_task_id' => 5, 'taken_at' => now()->subHour()]);
        $this->assertNotContains($refusee, Pige::derniereParPanneau()->pluck('id')->all());
    }

    public function test_mes_photos_montre_toutes_les_photos_d_un_axe_entier(): void
    {
        foreach ([3, 2, 1] as $h) {
            $this->pige(['panel_id' => 30, 'campaign_id' => 2, 'pose_task_id' => 5, 'taken_at' => now()->subHours($h)]);
        }

        $r = $this->get('/tech/' . self::TOKEN . '/piges');

        // Avant : les trois photos (une par pied) se réduisaient à une.
        $this->assertSame(3, $r->viewData('kpi')['total']);
        $this->assertSame(0, max(0, $r->viewData('historyTotal') - $r->viewData('kpi')['total']));
    }

    public function test_filtrer_les_piges_sur_un_axe_trouve_celles_de_ses_pieds(): void
    {
        $surLePied = $this->pige(['panel_id' => 11, 'pose_task_id' => 1]);
        $surLAxe   = $this->pige(['panel_id' => 10, 'pose_task_id' => null]);
        $ailleurs  = $this->pige(['panel_id' => 20, 'pose_task_id' => 4]);

        $ids = \App\Services\Piges\PigeFiltres::appliquer(Pige::query(), ['panel_id' => 10])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$surLePied, $surLAxe], $ids);
        $this->assertNotContains($ailleurs, $ids);
    }

    public function test_la_modale_de_validation_d_un_pied_compare_a_son_axe(): void
    {
        DB::table('panel_photos')->insert(['panel_id' => 10, 'path' => 'panels/axe.jpg', 'ordre' => 0]);
        $id = $this->pige(['panel_id' => 11, 'pose_task_id' => 1] + $this->aMetresDeLAxe(300));

        $json = app(\App\Http\Controllers\Admin\PigeController::class)->detailJson(Pige::find($id))->getData(true);

        // Avant : ni photo de référence ni distance pour un pied.
        $this->assertStringEndsWith('storage/panels/axe.jpg', $json['ref_photo_url']);
        $this->assertEqualsWithDelta(300, $json['gps']['dist_m'], 3);
        $this->assertSame('CDYLUP-004', $json['panel']['axe']);
        $this->assertSame('axe', $json['panel']['repere']);
    }

    // ── Phase B : l'admin regroupé par axe ─────────────────────────────

    public function test_le_suivi_d_un_axe_compte_ses_pieds_sur_la_campagne(): void
    {
        $this->pige(['panel_id' => 13, 'pose_task_id' => 3, 'status' => 'verifie']);
        $this->pige(['panel_id' => 11, 'pose_task_id' => 1, 'status' => 'en_attente']);
        $this->pige(['panel_id' => 12, 'pose_task_id' => 2, 'status' => 'rejete']);

        // Seule la tâche 1 est « affichée » : le suivi porte quand même sur
        // TOUS les pieds de l'axe sur la campagne (la page peut couper l'axe).
        $suivi = app(\App\Services\Axes\AxeService::class)->suiviPoses(PoseTask::whereIn('id', [1, 4])->get());

        $this->assertSame(['cle' => '10-1', 'pied' => 1], $suivi['tache'][1]);
        $this->assertArrayNotHasKey(4, $suivi['tache'], 'un panneau ordinaire n\'a pas d\'axe');
        $axe = $suivi['axes']['10-1'];
        $this->assertSame('CDYLUP-004', $axe['axe']->reference);
        // Pieds 1, 2, 3 ; le 3 est posé ; une photo non refusée sur 1 et 3 ; validée sur 3.
        $this->assertSame([3, 1, 2, 1], [$axe['pieds'], $axe['poses'], $axe['piges'], $axe['validees']]);
    }

    public function test_le_tableau_des_poses_annonce_l_axe_au_dessus_de_ses_pieds(): void
    {
        $taches = PoseTask::with(['panel.commune', 'campaign', 'technicien'])->whereIn('id', [1, 2, 3, 4])->orderBy('id')->get();
        $page = new \Illuminate\Pagination\LengthAwarePaginator($taches, $taches->count(), 20);

        $html = view('admin.poses.partials.table-rows', ['poseTasks' => $page])->render();

        $this->assertSame(1, substr_count($html, 'class="trow trow-axe"'), 'une seule ligne pour l\'axe');
        $this->assertStringContainsString('1/3 posés', $html);
        $this->assertStringContainsString('Pied 2', $html);
        // La ligne d'axe se replie avec sa campagne.
        $this->assertMatchesRegularExpression('/class="trow trow-axe" data-campaign-group="1"/', $html);
    }

    // ── Phase C : ce que voit le client ────────────────────────────────

    public function test_le_rapport_de_piges_fait_une_fiche_par_axe(): void
    {
        $ids = [];
        foreach ([[11, 1], [13, 3], [20, 4]] as [$panneau, $tache]) {
            $ids[] = $this->pige(['panel_id' => $panneau, 'pose_task_id' => $tache, 'status' => 'verifie']);
        }
        $rapport = app(\App\Services\Piges\RapportPigesService::class);
        $piges = $rapport->choisies($ids);

        // 2 emplacements : l'axe (2 pieds photographiés) et COC-020.
        $volumes = $rapport->volumes($piges);
        $this->assertSame(['nbPhotos' => 3, 'nbPanneaux' => 3, 'nbFiches' => 2, 'nbAxes' => 1, 'nbPiedsAxes' => 2], $volumes);

        $groupes = $rapport->grouper($piges)->map(fn ($l) => $l->map(fn ($p) => ['pige' => $p, 'photo' => null])->values());
        $html = view('pdf.rapport-piges', $volumes + [
            'titre' => 'MOOV 2026', 'groupes' => $groupes, 'debut' => now(), 'fin' => now(),
            'clients' => collect(['MOOV AFRICA']), 'campagnes' => collect(['MOOV 2026']), 'vagues' => collect(),
        ])->render();

        $this->assertSame(2, substr_count($html, '<div class="lieu">'), 'une fiche pour l\'axe, une pour COC-020');
        $this->assertStringContainsString('<td class="etiquette">Axe</td><td>CDYLUP-004 · 2 pieds</td>', $html);
        $this->assertStringContainsString('<td class="etiquette">Pied 1</td>', $html);
        $this->assertStringContainsString('<td class="etiquette">Pied 3</td>', $html);
        $this->assertStringContainsString('<strong>2</strong> emplacements, dont <strong>1</strong> axe (2 pieds),', $html);
    }

    public function test_un_axe_n_envoie_qu_un_e_mail_au_dernier_pied_valide(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        DB::table('clients')->where('id', 1)->update(['email' => 'pub@moov.ci']);
        foreach ([11, 12, 13, 20] as $panneau) {
            DB::table('campaign_panels')->insert(['campaign_id' => 1, 'panel_id' => $panneau]);
        }
        $avis = app(\App\Services\Piges\AvisPigeValidee::class);
        $valider = fn (int $panneau, int $tache) => Pige::find($this->pige([
            'panel_id' => $panneau, 'pose_task_id' => $tache, 'status' => 'verifie', 'verified_at' => now(),
        ]));

        $this->assertSame($avis::AXE_EN_ATTENTE, $avis->apresValidation($valider(11, 1)));
        $this->assertSame($avis::AXE_EN_ATTENTE, $avis->apresValidation($valider(12, 2)));
        \Illuminate\Support\Facades\Mail::assertNothingSent();

        // Le dernier pied : UN e-mail avec les trois photos.
        $this->assertSame($avis::AXE_ENVOYE, $avis->apresValidation($valider(13, 3)));
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PigeValidatedMail::class, 1);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PigeValidatedMail::class,
            fn ($m) => $m->axe?->reference === 'CDYLUP-004' && $m->photosAxe->count() === 3);
        // Un pied revalidé plus tard ne renvoie pas tout l'axe.
        $this->assertSame($avis::AXE_DEJA_ENVOYE, $avis->apresValidation($valider(12, 2)));

        // Un panneau ordinaire garde son e-mail par photo.
        $this->assertSame($avis::ENVOYE, $avis->apresValidation($valider(20, 4)));
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PigeValidatedMail::class, 2);
    }

    public function test_le_lien_de_l_e_mail_montre_les_photos_de_l_axe(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        DB::table('clients')->where('id', 1)->update(['email' => 'pub@moov.ci']);
        foreach ([11, 12, 13] as $p) {
            DB::table('campaign_panels')->insert(['campaign_id' => 1, 'panel_id' => $p]);
            $this->pige(['panel_id' => $p, 'pose_task_id' => $p - 10, 'status' => 'verifie', 'verified_at' => now()]);
        }
        app(\App\Services\Piges\AvisPigeValidee::class)->apresValidation(Pige::where('panel_id', 13)->first());
        $lien = \App\Models\PublicLink::where('type', 'pige_bundle')->sole();

        $r = $this->get('/p/' . $lien->token);

        $r->assertOk();
        $r->assertSee('Axe CDYLUP-004');
        foreach (['Pied 1', 'Pied 2', 'Pied 3'] as $pied) {
            $r->assertSee($pied);
        }
    }

    public function test_le_portail_client_montre_une_carte_par_axe(): void
    {
        DB::table('panel_photos')->insert(['panel_id' => 10, 'path' => 'panels/axe.jpg', 'ordre' => 0]);
        $verifiee = $this->pige(['panel_id' => 13, 'pose_task_id' => 3, 'status' => 'verifie', 'verified_at' => now()]);
        $panneaux = Panel::with('commune', 'format')->whereIn('id', [11, 12, 13, 20])->get();

        $entrees = app(\App\Services\Axes\AxeService::class)->grouperParAxe($panneaux, fn ($p) => $p, photos: true);
        $this->assertCount(2, $entrees, 'l\'axe et COC-020');

        $html = view('client.partials._carte_axe', [
            'entree' => $entrees->first(fn ($e) => $e['axe'] !== null),
            'poses' => PoseTask::where('status', 'realisee')->get()->keyBy('panel_id'),
            'pigesVerif' => Pige::where('status', 'verifie')->get()->groupBy('panel_id'),
            'pigeIndexMap' => [$verifiee => 0],
        ])->render();

        $this->assertStringContainsString('⛓ Axe · 3 pieds', $html);
        $this->assertStringContainsString('1/3 pigés', $html);
        $this->assertStringContainsString('Pied 2', $html);
        $this->assertStringContainsString('storage/panels/axe.jpg', $html, 'la photo de l\'axe');
    }

    // ── Retours du test sur dev (12/11) ────────────────────────────────

    public function test_trois_photos_au_plus_par_pose_la_premiere_la_realise(): void
    {
        $url = '/tech/' . self::TOKEN . '/poses/4/photo';

        $this->postJson($url, $this->photo())->assertOk()->assertJson(['photos_pose' => 1, 'photos_max' => 3, 'task_done' => true]);
        $this->assertSame('realisee', $this->statut(4), 'la 1re photo réalise la pose');
        $this->postJson($url, $this->photo())->assertOk()->assertJson(['photos_pose' => 2]);
        $this->postJson($url, $this->photo())->assertOk()->assertJson(['photos_pose' => 3]);

        // La 4e est refusée…
        $this->postJson($url, $this->photo())->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertSame(3, Pige::where('pose_task_id', 4)->count());

        // … sauf si une photo a été refusée : on peut la refaire.
        DB::table('piges')->where('pose_task_id', 4)->limit(1)->update(['status' => 'rejete', 'verified_at' => now()]);
        $this->postJson($url, $this->photo())->assertOk()->assertJson(['photos_pose' => 3]);
    }

    public function test_les_listes_de_poses_comptent_les_photos_de_chaque_pose(): void
    {
        $this->pige(['panel_id' => 11, 'pose_task_id' => 1, 'status' => 'verifie']);
        $this->pige(['panel_id' => 11, 'pose_task_id' => 1, 'status' => 'en_attente']);
        // Une ancienne photo sans pose, rattachée par panneau + campagne.
        $this->pige(['panel_id' => 12, 'pose_task_id' => null]);

        // Comme la liste : un select('pose_tasks.*') AVANT le comptage.
        $taches = PoseTask::query()->select('pose_tasks.*')->avecComptePiges()->get()->keyBy('id');

        // Avant : withCount('piges') effacé par le select, et faux (0) pour
        // toute photo rattachée à sa pose.
        $this->assertSame([2, 1], [(int) $taches[1]->pige_count, (int) $taches[1]->pige_verifie_count]);
        $this->assertSame(1, (int) $taches[2]->pige_count);
        $this->assertSame(0, (int) $taches[4]->pige_count);
    }

    public function test_une_pose_reprogrammee_previent_le_technicien_et_ses_alertes_suivent(): void
    {
        $tache = PoseTask::find(1);
        $tache->update(['scheduled_at' => now()->setTime(19, 0)]);

        $notif = DB::table('tech_notifications')->where('type', 'pose_rescheduled')->sole();
        $this->assertSame(2, (int) $notif->user_id);
        $this->assertStringContainsString('CDYLUP-004-P01', $notif->title);
        $this->assertSame('Prévue le ' . now()->format('d/m') . ' à 19h00', $notif->detail);

        // Une alerte « Nouvelle pose » d'origine suit la date réelle.
        DB::table('tech_notifications')->insert(['user_id' => 2, 'type' => 'new_pose', 'title' => '🆕 Nouvelle pose : CDYLUP-004-P01',
            'detail' => 'Prévue le 01/01 à 00h00', 'payload' => json_encode(['task_id' => 1]), 'created_at' => now()->subDay()]);
        $items = collect($this->getJson('/tech/' . self::TOKEN . '/notifications')->json('items'));
        $this->assertSame('Prévue le ' . now()->format('d/m') . ' à 19h00', $items->firstWhere('type', 'new_pose')['detail']);

        // Minuit = date seule ; autre année = année affichée.
        $tache->update(['scheduled_at' => now()->addYear()->startOfYear()]);
        $this->assertSame('Prévue le 01/01/' . now()->addYear()->year, $tache->fresh()->libelleEcheance());
    }

    public function test_la_feuille_de_route_regroupe_les_pieds_sous_leur_axe(): void
    {
        $r = $this->get('/tech/' . self::TOKEN . '/poses/route-sheet');

        $r->assertOk();
        $r->assertSee('⛓ Axe · 2 pieds');
        $r->assertSee('Pied 1');
        $r->assertSee('Pied 2');
        $r->assertSee('COC-020');
        $this->assertSame(1, substr_count($r->getContent(), 'class="axe"'));
    }

    public function test_la_carte_admin_des_poses_garde_les_pieds_a_la_position_de_leur_axe(): void
    {
        $json = app(\App\Http\Controllers\Admin\PoseController::class)
            ->mapData(\Illuminate\Http\Request::create('/admin/poses/map-data'))->getData(true);

        $pied = collect($json['markers'])->firstWhere('id', 1);
        // Avant : les pieds (sans position propre) n'étaient pas sur la carte.
        $this->assertNotNull($pied);
        $this->assertEqualsWithDelta(self::AXE_LAT, $pied['lat'], 0.000001);
        $this->assertSame('CDYLUP-004', $pied['axe']);
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
        // Axe entier (2026-11-13) : forme compacte des axes ; sa pastille ne
        // porte plus .pose-dot (qui l'écrasait en rond).
        $r->assertSee('data-tx-axe-segments', false);
        $this->assertDoesNotMatchRegularExpression('/class="pose-dot[^"]*tx-pill/', $r->getContent());
        // Repliée en liste : ses boutons sont dans la partie dépliable.
        $this->assertMatchesRegularExpression('/<div class="tx-axe-route">\s*<div class="tx-axe-actions">/', $r->getContent());
        // Mêmes gestes qu'un axe : l'en-tête déplie, la fiche par son lien.
        $this->assertMatchesRegularExpression('/data-axe-entier="1"[^>]*>\s*<button type="button" class="tx-axe-tete" data-tx-axe-toggle/', $r->getContent());
        $r->assertSee('data-tx-fiche', false);
        // CSS et modules versionnés par la date du fichier (2026-11-13) : un
        // déploiement n'est plus masqué par le cache du service worker.
        $this->assertMatchesRegularExpression('/tx-refonte\.css\?v=\d{9,}/', $r->getContent());
        $r->assertSee('<script type="importmap">', false);
        $this->assertMatchesRegularExpression('#"/js/tech/features/refonte\.js":"/js/tech/features/refonte\.js\?v=\d{9,}"#', $r->getContent());
        $r->assertSee('1 / 5 aujourd', false);
        // Les pieds partagent la photo et la position de l'axe ; le pied 3,
        // déjà fait, dessine la route sans action.
        $r->assertSee('data-tx-jalon="3"', false);
        $this->assertSame(1, substr_count($r->getContent(), 'data-tx-segment="3"'));
        // Étape 2 : le menu de l'initiale et la feuille « Photo envoyée ».
        $r->assertSee('data-action="open-tx-menu"', false);
        $r->assertSee('id="tx-menu"', false);
        $r->assertSee('Ma feuille de route papier');
        $r->assertSee('id="tx-envoi"', false);
        $r->assertSee('EN VOITURE');
    }
}
