<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\TaxController;
use App\Services\TaxCalculationService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Taxes → Détail par panneau : sélection multiple (2026-11-18, « comme on
 * le fait pour les poses ») — cocher, décocher, garder la main sur la
 * sélection ; les téléchargements ne prennent que les lignes choisies.
 */
class TaxeDetailSelectionTest extends TestCase
{
    private function lignes(): \Illuminate\Support\Collection
    {
        return collect([
            ['panel_id' => 1, 'type' => 'odp', 'campaign_id' => 10, 'amount' => 36000],
            ['panel_id' => 1, 'type' => 'tm',  'campaign_id' => 10, 'amount' => 12000],
            ['panel_id' => 1, 'type' => 'tm',  'campaign_id' => 11, 'amount' => 24000],
            ['panel_id' => 2, 'type' => 'odp', 'campaign_id' => null, 'amount' => 18000],
        ]);
    }

    private function selection(array $params): array
    {
        $m = new \ReflectionMethod(TaxController::class, 'appliquerSelection');
        $m->setAccessible(true);

        return $m->invoke(app(TaxController::class), Request::create('/', 'POST', $params), $this->lignes());
    }

    public function test_la_cle_d_une_ligne(): void
    {
        $this->assertSame('1-odp', TaxCalculationService::cleLigne($this->lignes()[0]), 'ODP : une ligne par panneau');
        $this->assertSame('1-tm-10', TaxCalculationService::cleLigne($this->lignes()[1]));
        $this->assertSame('1-tm-11', TaxCalculationService::cleLigne($this->lignes()[2]), 'TM : une ligne par campagne');
    }

    public function test_sans_selection_tout_le_filtre_comme_avant(): void
    {
        [$lignes, $libelle] = $this->selection([]);
        $this->assertCount(4, $lignes);
        $this->assertNull($libelle);
    }

    public function test_seulement_les_lignes_cochees(): void
    {
        [$lignes, $libelle] = $this->selection(['selection' => 'cles', 'cles' => ['1-tm-11', '2-odp', 'pas<une>cle']]);

        $this->assertSame([24000, 18000], $lignes->pluck('amount')->all());
        $this->assertSame('2 ligne(s) choisie(s) sur 4', $libelle);
    }

    public function test_tout_le_filtre_sauf_les_lignes_decochees(): void
    {
        [$lignes, $libelle] = $this->selection(['selection' => 'sauf', 'cles' => ['1-tm-10']]);

        $this->assertSame([36000, 24000, 18000], $lignes->pluck('amount')->all());
        $this->assertSame('3 ligne(s) choisie(s) sur 4', $libelle);
    }

    public function test_les_telechargements_acceptent_la_selection_en_post(): void
    {
        foreach (['admin.taxes.details.pdf', 'admin.taxes.details.excel'] as $nom) {
            $route = app('router')->getRoutes()->getByName($nom);
            $this->assertContains('POST', $route->methods(), $nom);
            $this->assertContains('GET', $route->methods(), $nom . ' : les liens existants marchent toujours');
        }
    }

    public function test_la_vue_porte_la_selection_et_compile(): void
    {
        $vue = file_get_contents(resource_path('views/admin/taxes/details.blade.php'));
        $this->assertStringContainsString('class="tx-sel"', $vue, 'une case par ligne');
        $this->assertStringContainsString('id="tx-sel-page"', $vue, 'toute la page');
        $this->assertStringContainsString('id="tx-sel-tout"', $vue, 'tout le filtre');
        $this->assertStringContainsString("selection: etat.mode", $vue, 'les exports partent avec la sélection');

        $php = app('blade.compiler')->compileString($vue);
        $tmp = tempnam(sys_get_temp_dir(), 'vue') . '.php';
        file_put_contents($tmp, $php);
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $code);
        @unlink($tmp);
        $this->assertSame(0, $code, implode("\n", $sortie));
    }
}
