<?php

namespace Tests\Unit;

use App\Models\Pige;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Case « Joindre ma position » à l'envoi d'une photo de pige (2026-11-04).
 *
 * Demande : « le gps je veux que par défaut il soit coché mais possibilité
 * de le décocher » — cas du technicien qui a eu un souci de réseau et
 * envoie la photo depuis chez lui, sans vouloir donner la position de son
 * domicile. Case décochée = la photo part SANS gps_lat / gps_lng.
 *
 * Trois chemins d'envoi par un technicien, tous couverts :
 *   - espace technicien   POST /tech/{token}/poses/{task}/photo
 *   - lien de pose        POST /pige/{token}/photo (+ remplacement)
 *   - lien de campagne    POST /pige/{token}/upload (legacy)
 *
 * Règle métier inchangée : une pige sans position est classée « no_gps »
 * (badge « Sans GPS ») par PigeObserver, comme quand le GPS du téléphone
 * ne répond pas.
 *
 * Base SQLite réduite (les migrations ne passent pas sous SQLite).
 */
class PositionGpsFacultativeTest extends TestCase
{
    private const TOKEN_TECH = 'jeton-tech-de-test';
    private const TOKEN_POSE = 'AbCdEfGhIjKlMnOpQrStUvWxYz012345'; // 32 caractères
    private const TOKEN_CAMPAGNE = 'campagne-jeton-48-caracteres-0123456789abcdefgh';

    // Panneau de test (Cocody) ; « devant » = à quelques mètres.
    private const PANNEAU_LAT = 5.3600000;
    private const PANNEAU_LNG = -3.9800000;

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
            $t->string('pige_token')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('name')->nullable();
            $t->decimal('latitude', 10, 7)->nullable(); $t->decimal('longitude', 10, 7)->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id');
            $t->string('type')->default('interne'); $t->timestamps();
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

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Commercial', 'role' => 'commercial', 'tech_public_token' => null],
            ['id' => 2, 'name' => 'Yannick', 'role' => 'technique', 'tech_public_token' => self::TOKEN_TECH],
        ]);
        DB::table('clients')->insert(['id' => 1, 'name' => 'MOOV AFRICA']);
        DB::table('campaigns')->insert([
            'id' => 1, 'name' => 'MOOV 2026', 'client_id' => 1, 'user_id' => 1,
            'status' => 'actif', 'pige_token' => self::TOKEN_CAMPAGNE,
        ]);
        DB::table('panels')->insert([
            'id' => 1, 'reference' => 'COC-001', 'name' => 'Riviera Golf',
            'latitude' => self::PANNEAU_LAT, 'longitude' => self::PANNEAU_LNG,
        ]);
        DB::table('campaign_panels')->insert(['campaign_id' => 1, 'panel_id' => 1, 'type' => 'interne']);
        DB::table('pose_tasks')->insert([
            // Pose de l'espace technicien (tech assigné).
            ['id' => 1, 'panel_id' => 1, 'campaign_id' => 1, 'assigned_user_id' => 2,
             'public_token' => null, 'scheduled_at' => now()->toDateTimeString()],
            // Pose du lien public (sans tech assigné : pas de redirection vers l'espace).
            ['id' => 2, 'panel_id' => 1, 'campaign_id' => 1, 'assigned_user_id' => null,
             'public_token' => self::TOKEN_POSE, 'scheduled_at' => now()->toDateTimeString()],
        ]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('pige.jpg', 640, 480);
    }

    /** Position « devant le panneau » (≈ 10 m). */
    private function devantLePanneau(): array
    {
        return ['gps_lat' => '5.360090', 'gps_lng' => '-3.980000'];
    }

    // ── Espace technicien ──────────────────────────────────────────

    public function test_espace_tech_case_decochee_la_photo_part_sans_position(): void
    {
        // Case décochée : le navigateur n'envoie ni gps_lat ni gps_lng.
        $r = $this->postJson('/tech/' . self::TOKEN_TECH . '/poses/1/photo', ['photo' => $this->photo()]);

        $r->assertOk()->assertJson(['ok' => true]);
        $pige = Pige::sole();
        $this->assertNull($pige->gps_lat);
        $this->assertNull($pige->gps_lng);
        $this->assertSame('no_gps', $pige->geo_check);
        $this->assertNull($pige->geo_distance_m);
        $this->assertSame('en_attente', $pige->status);
        $this->assertSame('Sans GPS', $pige->geoBadge()['short']);
        // La pose est faite, comme avec une position.
        $this->assertSame('realisee', DB::table('pose_tasks')->where('id', 1)->value('status'));
    }

    public function test_espace_tech_case_cochee_la_position_part_comme_avant(): void
    {
        $r = $this->postJson('/tech/' . self::TOKEN_TECH . '/poses/1/photo',
            ['photo' => $this->photo()] + $this->devantLePanneau());

        $r->assertOk()->assertJson(['ok' => true]);
        $pige = Pige::sole();
        $this->assertEqualsWithDelta(5.36009, $pige->gps_lat, 0.000001);
        $this->assertSame('ok', $pige->geo_check);
        $this->assertLessThanOrEqual(15, $pige->geo_distance_m);
    }

    // ── Lien de pose (PoseTask public) ─────────────────────────────

    public function test_lien_de_pose_case_decochee_la_photo_part_sans_position(): void
    {
        $r = $this->postJson('/pige/' . self::TOKEN_POSE . '/photo', ['photo' => $this->photo()]);

        $r->assertOk()->assertJson(['ok' => true]);
        $pige = Pige::sole();
        $this->assertNull($pige->gps_lat);
        $this->assertSame('no_gps', $pige->geo_check);
    }

    public function test_lien_de_pose_case_cochee_la_position_part_comme_avant(): void
    {
        $this->postJson('/pige/' . self::TOKEN_POSE . '/photo', ['photo' => $this->photo()] + $this->devantLePanneau())
            ->assertOk();

        $this->assertSame('ok', Pige::sole()->geo_check);
    }

    public function test_lien_de_pose_remplacement_case_decochee_efface_la_position(): void
    {
        $this->postJson('/pige/' . self::TOKEN_POSE . '/photo', ['photo' => $this->photo()] + $this->devantLePanneau())
            ->assertOk();
        $id = Pige::sole()->id;

        $this->postJson('/pige/' . self::TOKEN_POSE . "/photo/{$id}/replace", ['photo' => $this->photo()])
            ->assertOk()->assertJson(['ok' => true]);

        $pige = Pige::find($id);
        $this->assertNull($pige->gps_lat);
        $this->assertNull($pige->gps_lng);
    }

    // ── Lien de campagne (legacy, plusieurs panneaux) ──────────────

    public function test_lien_de_campagne_case_decochee_la_photo_part_sans_position(): void
    {
        $r = $this->postJson('/pige/' . self::TOKEN_CAMPAGNE . '/upload',
            ['panel_id' => 1, 'photo' => $this->photo(), 'tech_name' => 'Yannick']);

        $r->assertOk()->assertJson(['ok' => true]);
        $pige = Pige::sole();
        $this->assertNull($pige->gps_lat);
        $this->assertSame('no_gps', $pige->geo_check);
    }

    public function test_lien_de_campagne_case_cochee_la_position_part_comme_avant(): void
    {
        $this->postJson('/pige/' . self::TOKEN_CAMPAGNE . '/upload',
            ['panel_id' => 1, 'photo' => $this->photo(), 'tech_name' => 'Yannick'] + $this->devantLePanneau())
            ->assertOk();

        $this->assertSame('ok', Pige::sole()->geo_check);
    }

    // ── La case : une seule, cochée par défaut, sur les trois écrans ──

    public function test_la_case_est_cochee_par_defaut_avec_aide_derriere_l_icone(): void
    {
        $html = Blade::render('<x-joindre-gps />');

        $this->assertMatchesRegularExpression('/<input type="checkbox" data-joindre-gps checked>/', $html);
        $this->assertStringContainsString('📍 Joindre ma position', $html);
        // Aide : ⓘ avec title (survol) + ligne masquée (toucher).
        $this->assertMatchesRegularExpression('/data-joindre-gps-aide[^>]*title="À décocher si l\'envoi se fait loin du panneau/u', $html);
        $this->assertMatchesRegularExpression('/<span class="px-gps-aide" hidden>/', $html);
        // Lecture commune : case absente (page en cache) = on joint.
        $this->assertStringContainsString('return !c || c.checked;', $html);
    }

    public function test_les_trois_ecrans_d_envoi_du_technicien_ont_la_case(): void
    {
        foreach (['public/tech/partials/_modal_photo_preview', 'public/pose-task', 'public/pige-collect'] as $vue) {
            $this->assertStringContainsString('<x-joindre-gps', file_get_contents(resource_path("views/{$vue}.blade.php")), $vue);
        }

        // Aperçu T3 de l'espace technicien : la classe propre s'ajoute à celle du composant.
        $t3 = view('public.tech.partials._modal_photo_preview')->render();
        $this->assertStringContainsString('class="px-gps sm2-t3-gps"', $t3);
        $this->assertStringContainsString('data-joindre-gps checked', $t3);
    }

    public function test_chaque_envoi_ne_joint_la_position_que_case_cochee(): void
    {
        // Espace technicien : T3 rend le choix, l'envoi (et la file hors-ligne) le suit.
        $js = file_get_contents(public_path('js/tech/features/upload.js'));
        $this->assertStringContainsString('close({ gps: capturedGps, joindreGps: joindreGps(overlay) });', $js);
        $this->assertStringContainsString('let gps = avecPosition ? previewResult.gps : null;', $js);
        $this->assertStringContainsString('if (avecPosition && !gps) {', $js);
        $this->assertStringContainsString('window.PanoraGps.recocher(overlay)', $js);

        // Lien de pose : nouvelle photo ET remplacement.
        $pose = file_get_contents(resource_path('views/public/pose-task.blade.php'));
        $this->assertSame(2, substr_count($pose, 'const gps = await gpsSiCoche(avecPosition);'));
        $this->assertSame(0, substr_count($pose, 'const gps = await getGps();'));
        $this->assertSame(2, substr_count($pose, 'window.PanoraGps?.recocher();'));

        // Lien de campagne.
        $collecte = file_get_contents(resource_path('views/public/pige-collect.blade.php'));
        $this->assertStringContainsString('if (avecPosition && currentGps.lat !== null) {', $collecte);
        $this->assertStringContainsString('window.PanoraGps?.recocher(modal);', $collecte);
    }

    public function test_la_photo_de_pige_peut_venir_de_la_galerie(): void
    {
        // Décision du 2026-11-04 (option a) : plus de capture= sur les champs
        // photo de pige — le téléphone propose « Appareil photo ou Galerie »,
        // pour envoyer plus tard une photo déjà prise.
        $champs = [
            'views/public/tech/partials/_drawer_pose_detail.blade.php' => 1,
            'views/public/pose-task.blade.php'                         => 2, // nouvelle + remplacement
            'views/public/pige-collect.blade.php'                      => 1,
        ];
        foreach ($champs as $vue => $nombre) {
            $source = file_get_contents(resource_path($vue));
            $this->assertSame($nombre, substr_count($source, '<input type="file"'), $vue);
            $this->assertStringNotContainsString('capture=', $source, $vue);
        }

        // La photo d'un SIGNALEMENT n'est pas une pige : inchangée.
        $this->assertStringContainsString('capture="environment"',
            file_get_contents(resource_path('views/public/tech/partials/_modal_report.blade.php')));
    }
}
