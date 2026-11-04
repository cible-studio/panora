<?php

namespace Tests\Unit;

use App\Models\Pige;
use App\Services\Piges\PigeFiltres;
use App\Services\Piges\RapportPigesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Page Piges et rapport PDF prêt à envoyer (2026-11-04).
 *
 * Demande : « si on me demande toutes les piges de Moov, je dois pouvoir
 * filtrer et exporter en PDF prêt à être envoyé », sur le modèle du
 * document Word « MOOV _ MILLIONS A SE PARTAGER PHASE 2 ».
 * Règles validées : piges VÉRIFIÉES seulement ; toutes proposées, le MP
 * décoche ; au plus 200 photos par PDF.
 *
 * Base SQLite réduite (les migrations ne passent pas sous SQLite).
 */
class RapportPigesTest extends TestCase
{
    private const MOOV = 1;
    private const ORANGE = 2;
    private const COCODY = 10;
    private const YOPOUGON = 11;

    private string $dossierPhotos;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->nullable(); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id'); $t->string('status')->default('actif');
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('panel_formats', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('name')->nullable(); $t->unsignedBigInteger('commune_id')->nullable();
            $t->unsignedBigInteger('format_id')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('piges', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->unsignedBigInteger('pose_task_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('photo_path'); $t->string('photo_thumb')->nullable(); $t->string('status')->default('en_attente');
            $t->float('gps_lat')->nullable(); $t->float('gps_lng')->nullable(); $t->string('geo_check')->nullable();
            $t->timestamp('taken_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamps();
        });

        DB::table('clients')->insert([['id' => self::MOOV, 'name' => 'MOOV AFRICA'], ['id' => self::ORANGE, 'name' => 'ORANGE CI']]);
        DB::table('campaigns')->insert([
            ['id' => 1, 'name' => 'MOOV 2026', 'client_id' => self::MOOV, 'status' => 'termine'],
            ['id' => 2, 'name' => 'ORANGE NOËL', 'client_id' => self::ORANGE, 'status' => 'actif'],
        ]);
        DB::table('communes')->insert([['id' => self::COCODY, 'name' => 'COCODY'], ['id' => self::YOPOUGON, 'name' => 'YOPOUGON']]);
        DB::table('panel_formats')->insert(['id' => 1, 'name' => '12 m²']);
        DB::table('panels')->insert([
            ['id' => 1, 'reference' => 'YOP-001', 'name' => 'Carrefour Lokoua', 'commune_id' => self::YOPOUGON, 'format_id' => 1],
            ['id' => 2, 'reference' => 'COC-002', 'name' => 'Riviera Golf, face Ambassade USA', 'commune_id' => self::COCODY, 'format_id' => 1],
            ['id' => 3, 'reference' => 'COC-001', 'name' => 'Pont du Lycée Technique', 'commune_id' => self::COCODY, 'format_id' => null],
        ]);

        // Une vraie photo : le PDF la lit sur le disque « public ».
        $this->dossierPhotos = storage_path('app/public/test-rapport-piges');
        File::ensureDirectoryExists($this->dossierPhotos);
        $img = imagecreatetruecolor(400, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, 40, 90, 160));
        imagejpeg($img, $this->dossierPhotos . '/pige.jpg', 80);
        imagedestroy($img);

        $pige = fn (int $id, int $panel, int $campagne, string $statut, array $plus = []) => array_merge([
            'id' => $id, 'panel_id' => $panel, 'campaign_id' => $campagne, 'status' => $statut,
            'photo_path' => 'test-rapport-piges/pige.jpg', 'taken_at' => '2026-09-07 10:0' . $id . ':00',
            'archived_at' => null, 'gps_lat' => null, 'gps_lng' => null,
        ], $plus);

        DB::table('piges')->insert([
            $pige(1, 1, 1, 'verifie'),                                   // Moov, Yopougon
            $pige(2, 2, 1, 'verifie', ['gps_lat' => 5.36, 'gps_lng' => -3.98]), // Moov, Cocody
            $pige(3, 3, 1, 'verifie'),                                   // Moov, Cocody
            $pige(4, 3, 1, 'en_attente'),                                // jamais exportée
            $pige(5, 2, 1, 'rejete'),                                    // jamais exportée
            $pige(6, 1, 1, 'verifie', ['archived_at' => '2026-10-01 00:00:00']), // archivée
            $pige(7, 1, 2, 'verifie'),                                   // Orange
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dossierPhotos);
        parent::tearDown();
    }

    private function ids($requete): array
    {
        return $requete->orderBy('id')->pluck('id')->all();
    }

    // ── Filtres (source unique liste + export) ──────────────────────

    public function test_toutes_les_piges_de_moov(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], $this->ids(PigeFiltres::appliquer(Pige::active(), ['client_id' => self::MOOV])));
    }

    public function test_filtre_commune(): void
    {
        $this->assertSame([2, 3, 4, 5], $this->ids(PigeFiltres::appliquer(Pige::active(), ['commune_id' => self::COCODY])));
    }

    public function test_la_recherche_trouve_le_client_et_la_commune(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], $this->ids(PigeFiltres::appliquer(Pige::active(), ['q' => 'moov'])));
        $this->assertSame([1, 7], $this->ids(PigeFiltres::appliquer(Pige::active(), ['q' => 'yopougon'])));
    }

    public function test_les_listes_proposent_les_campagnes_terminees(): void
    {
        $listes = PigeFiltres::listes([]);

        $this->assertSame(['MOOV AFRICA', 'ORANGE CI'], $listes['clients']->pluck('name')->all());
        // Campagne terminée proposée (avant : actives seulement) ; en cours d'abord.
        $this->assertSame(['ORANGE NOËL', 'MOOV 2026'], $listes['campaigns']->pluck('name')->all());
        $this->assertSame(['COCODY', 'YOPOUGON'], $listes['communes']->pluck('name')->all());

        // Un client choisi restreint les campagnes aux siennes.
        $this->assertSame(['MOOV 2026'], PigeFiltres::listes(['client_id' => self::MOOV])['campaigns']->pluck('name')->all());
    }

    // ── Export : piges vérifiées seulement ──────────────────────────

    public function test_l_export_ne_prend_que_les_verifiees_non_archivees(): void
    {
        $rapport = app(RapportPigesService::class);

        $this->assertSame([1, 2, 3], $this->ids($rapport->selection(['client_id' => self::MOOV])));
        // Même si la page filtre « en attente » ou « rejetée ».
        $this->assertSame([], $rapport->choisies([4, 5, 6])->pluck('id')->all());
        $this->assertSame([2], $rapport->choisies([2, 4])->pluck('id')->all());
    }

    public function test_groupes_par_commune_puis_par_panneau(): void
    {
        $rapport = app(RapportPigesService::class);
        $groupes = $rapport->grouper($rapport->choisies([1, 2, 3]));

        $this->assertSame(['COCODY', 'YOPOUGON'], $groupes->keys()->all());
        $this->assertSame(['COC-001', 'COC-002'], $groupes['COCODY']->map(fn ($p) => $p->panel->reference)->values()->all());
    }

    public function test_titre_propose(): void
    {
        $rapport = app(RapportPigesService::class);

        $this->assertSame('MOOV 2026', $rapport->titreParDefaut(['campaign_id' => 1, 'client_id' => self::MOOV]));
        $this->assertSame('Piges MOOV AFRICA', $rapport->titreParDefaut(['client_id' => self::MOOV]));
        $this->assertSame('Rapport de piges', $rapport->titreParDefaut([]));
    }

    // ── Le PDF ──────────────────────────────────────────────────────

    public function test_le_pdf_est_genere_avec_les_photos(): void
    {
        $rapport = app(RapportPigesService::class);
        $reponse = $rapport->pdf($rapport->choisies([1, 2, 3]), 'MOOV _ MILLIONS A SE PARTAGER PHASE 2');

        $this->assertSame('application/pdf', $reponse->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $reponse->getContent());
        $this->assertStringContainsString('MOOV _ MILLIONS A SE PARTAGER PHASE 2 - piges', $reponse->headers->get('content-disposition'));
        // 3 photos intégrées (une par pige).
        $this->assertGreaterThanOrEqual(3, substr_count($reponse->getContent(), '/Subtype /Image'));
    }

    public function test_le_contenu_du_rapport(): void
    {
        $rapport = app(RapportPigesService::class);
        $piges = $rapport->choisies([1, 2, 3]);
        $groupes = $rapport->grouper($piges)->map(fn ($l) => $l->map(fn ($p) => ['pige' => $p, 'photo' => null])->values());

        $html = view('pdf.rapport-piges', [
            'titre' => 'MOOV 2026', 'groupes' => $groupes, 'nbPhotos' => 3, 'nbPanneaux' => 3,
            'debut' => $piges->min('taken_at'), 'fin' => $piges->max('taken_at'),
            'clients' => collect(['MOOV AFRICA']), 'campagnes' => collect(['MOOV 2026']),
        ])->render();

        foreach (['Rapport de piges', 'Client : MOOV AFRICA', 'COCODY', 'YOPOUGON', '❖',
                  'Pont du Lycée Technique', 'Réf. <strong>COC-001</strong>', 'Format 12 m²',
                  'Photo du 07/09/2026 à 10:03', 'Position GPS de la photo', 'Photos prises le 07/09/2026'] as $attendu) {
            $this->assertStringContainsString($attendu, $html, $attendu);
        }
        // Cocody : 2 panneaux sur une page ; Yopougon sur la suivante.
        $this->assertSame(2, substr_count($html, 'class="rp-saut"'), 'Saut après le récapitulatif et après Cocody.');
    }

    // ── La page Piges ───────────────────────────────────────────────

    public function test_la_page_piges_cherche_sur_le_serveur_et_propose_l_export(): void
    {
        $vue = file_get_contents(resource_path('views/admin/piges/index.blade.php'));

        $this->assertStringNotContainsString('filterLocal(', $vue, 'La recherche ne doit plus filtrer la seule page affichée.');
        $this->assertStringContainsString("route('admin.piges.export', \$filtres)", $vue);
        $this->assertStringContainsString('name="client_id"', $vue);
        $this->assertStringContainsString('name="commune_id"', $vue);
        $this->assertTrue(\Route::has('admin.piges.export') && \Route::has('admin.piges.export.pdf'));
    }
}
