<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\PoseController;
use App\Models\PoseTask;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Poses annulées masquées des listes (validé le 2026-11-02) : une pose
 * annulée (panneau retiré de la campagne…) reste en base mais ne
 * s'affiche plus. Gestion des poses la montre seulement via la carte
 * « Annulées » ; page Poses d'une campagne, calendrier et carte ne la
 * montrent jamais par défaut.
 */
class PosesAnnuleesMasqueesTest extends TestCase
{
    private const RACINE = __DIR__ . '/../../';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('pose_tasks', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id')->nullable(); $t->string('status'); $t->timestamps();
        });
        foreach (['planifiee', 'en_route', 'en_cours', 'realisee', 'annulee', 'annulee'] as $s) {
            DB::table('pose_tasks')->insert(['campaign_id' => 7, 'status' => $s]);
        }
    }

    /** Filtre de statut de Gestion des poses (aussi utilisé par la carte et la validation groupée). */
    private function filtrer(array $params): array
    {
        $query = PoseTask::query();
        (new \ReflectionMethod(PoseController::class, 'applyStatusFilter'))
            ->invoke(app(PoseController::class), $query, Request::create('/', 'GET', $params));

        return $query->pluck('status')->all();
    }

    public function test_le_scope_retire_seulement_les_annulees(): void
    {
        $this->assertEqualsCanonicalizing(
            ['planifiee', 'en_route', 'en_cours', 'realisee'],
            PoseTask::sansAnnulees()->pluck('status')->all()
        );
        $this->assertSame(6, PoseTask::count(), 'Rien n\'est supprimé : l\'historique reste en base.');
    }

    public function test_sans_filtre_la_liste_est_propre(): void
    {
        $this->assertNotContains('annulee', $this->filtrer([]));
        $this->assertCount(4, $this->filtrer(['status' => '']));
    }

    public function test_la_carte_annulees_les_montre(): void
    {
        $this->assertSame(['annulee', 'annulee'], $this->filtrer(['status' => 'annulee']));
        $this->assertSame(['realisee'], $this->filtrer(['status' => 'realisee']));
    }

    public function test_calendrier_et_page_campagne_passent_par_la_regle(): void
    {
        $pose = file_get_contents(self::RACINE . 'app/Http/Controllers/Admin/PoseController.php');
        $calendrier = substr($pose, strpos($pose, 'public function calendar('), 1500);
        $this->assertStringContainsString('->sansAnnulees()', $calendrier);

        $campagne = file_get_contents(self::RACINE . 'app/Http/Controllers/Admin/CampaignController.php');
        $poses = substr($campagne, strpos($campagne, 'public function poses('), 2500);
        $this->assertSame(2, substr_count($poses, 'sansAnnulees()'), 'Liste ET carte « Total » de la page Poses.');

        $this->assertStringContainsString(
            '$this->applyStatusFilter($query, $request);',
            substr($pose, strpos($pose, 'public function mapData('), 1500),
            'La carte suit le même filtre que la liste.'
        );
    }
}
