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
        // Étape 2 : le menu de l'initiale et la feuille « Photo envoyée ».
        $r->assertSee('data-action="open-tx-menu"', false);
        $r->assertSee('id="tx-menu"', false);
        $r->assertSee('Ma feuille de route papier');
        $r->assertSee('id="tx-envoi"', false);
        $r->assertSee('EN VOITURE');
    }
}
