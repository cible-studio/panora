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

    /** 2026-11-18 : PDF de l'ODP seule, sans Client / Campagne / Période. */
    public function test_le_pdf_de_l_odp_seule_n_a_ni_client_ni_campagne_ni_periode(): void
    {
        $p = \Illuminate\Support\Carbon::create(2026, 11, 1);
        $ligne = fn ($type, $montant) => [
            'commune' => 'ADJAME', 'commune_id' => 1, 'panel_id' => 2, 'reference' => 'ADJ-002', 'name' => 'Route du Zoo',
            'dimensions' => '12m²', 'surface' => 12, 'type' => $type, 'statut' => 'occupe', 'client_name' => 'ECKO',
            'campaign_name' => 'BIDC', 'campaign_id' => 20, 'campaign_start' => $p, 'campaign_end' => $p->copy()->endOfMonth(),
            'period_start' => $p, 'period_end' => $p->copy()->endOfMonth(), 'months' => 1, 'unit' => 'mois',
            'rate' => 1000, 'rate_applied' => 1000, 'amount' => $montant,
        ];
        $rendre = function (array $lignes) {
            $lines = collect($lignes);

            return view('pdf.taxes-details', [
                'lines' => $lines, 'totals' => app(TaxCalculationService::class)->summarize($lines),
                'periodLabel' => 'Novembre 2026', 'filterSummary' => '', 'filterMeta' => [],
            ])->render();
        };

        $odp = $rendre([$ligne('odp', 12000)]);
        $this->assertStringNotContainsString('>Client<', $odp);
        $this->assertStringNotContainsString('>Campagne<', $odp);
        // En-têtes du tableau des lignes seulement (le bloc du haut affiche aussi la période du filtre).
        $entetes = fn (string $html) => substr($html, strpos($html, 'tdoc-details">'), strpos($html, '</thead>') - strpos($html, 'tdoc-details">'));
        $this->assertStringNotContainsString('Période', $entetes($odp), 'colonne Période retirée');
        $this->assertStringContainsString('Période', $entetes($rendre([$ligne('tm', 12000)])), 'PDF TM : colonne gardée');
        $this->assertStringNotContainsString('ECKO', $odp);
        $this->assertStringContainsString('colspan="4" class="right">TOTAL GÉNÉRAL', $odp);

        $mixte = $rendre([$ligne('odp', 12000), $ligne('tm', 12000)]);
        $this->assertStringContainsString('>Client<', $mixte, 'PDF mixte : inchangé');
        $this->assertStringContainsString('>Campagne<', $mixte);
        $this->assertStringContainsString('colspan="7" class="right">TOTAL GÉNÉRAL', $mixte);

        // Mise en page pour la DG (18/11) : note gardée d'un bloc et juste.
        $this->assertStringContainsString('class="tdoc-note tdoc-details-fin"', $odp);
        $this->assertStringContainsString('.tdoc-details tr.tdoc-total { page-break-before:avoid; }', $odp, 'TOTAL jamais seul en haut de page');
        $this->assertStringContainsString('en maintenance sont inclus', $odp, 'la note disait « exclus » : faux depuis le 13/11');
        $this->assertStringNotContainsString('TM = tarif', $odp, 'PDF ODP : la note ne parle que de l\'ODP');
    }

    /**
     * 2026-11-24 (demande de l'utilisateur) : période en gras, bien visible
     * (TM : trimestres + dates ; ODP : dates) ; les catégories exclues
     * (chevalets…) ne sont plus jamais écrites sur le PDF ni l'Excel.
     */
    public function test_la_periode_en_gras_et_aucune_categorie_exclue_sur_le_pdf(): void
    {
        $p = \Illuminate\Support\Carbon::create(2026, 10, 1);
        $fin = \Illuminate\Support\Carbon::create(2026, 12, 31);
        $ligne = fn ($type) => [
            'commune' => 'ADJAME', 'commune_id' => 1, 'panel_id' => 2, 'reference' => 'ADJ-002', 'name' => 'Route du Zoo',
            'dimensions' => '12m²', 'surface' => 12, 'type' => $type, 'statut' => 'occupe', 'client_name' => 'CLIENT ESSAI',
            'campaign_name' => 'ESSAI', 'campaign_id' => 20, 'campaign_start' => $p, 'campaign_end' => $fin,
            'period_start' => $p, 'period_end' => $fin, 'months' => 3, 'unit' => 'mois',
            'rate' => 1000, 'rate_applied' => 1000, 'amount' => 36000,
        ];
        $rendre = function (string $type) use ($ligne, $p, $fin) {
            $lines = collect([$ligne($type)]);

            return view('pdf.taxes-details', [
                'lines' => $lines, 'totals' => app(TaxCalculationService::class)->summarize($lines),
                'periodLabel' => 'T4 2026', 'filterSummary' => '', 'filterMeta' => [],
                'periodeDebut' => $p, 'periodeFin' => $fin,
            ])->render();
        };

        $tm = $rendre('tm');
        $this->assertStringContainsString('<span class="val">T4 2026</span>', $tm);
        $this->assertStringContainsString('<span class="val">du 01/10/2026 au 31/12/2026</span>', $tm);
        $this->assertStringContainsString('.tdoc-periode-bande .val { font-size:14px; font-weight:700;', $tm, 'en gras, bien visible');

        $odp = $rendre('odp');
        $this->assertStringContainsString('<span class="val">du 01/10/2026 au 31/12/2026</span>', $odp);
        $this->assertStringNotContainsString('<span class="val">T4 2026</span>', $odp, 'ODP : pas de trimestres');

        $this->assertSame('T4 2026', TaxCalculationService::libelleTrimestres($p, $fin));
        $this->assertSame('T1 et T2 2026', TaxCalculationService::libelleTrimestres(\Illuminate\Support\Carbon::create(2026, 2, 1), \Illuminate\Support\Carbon::create(2026, 6, 30)));
        $this->assertSame('T1 à T4 2026', TaxCalculationService::libelleTrimestres(\Illuminate\Support\Carbon::create(2026, 1, 1), \Illuminate\Support\Carbon::create(2026, 12, 31)));

        $controleur = file_get_contents(app_path('Http/Controllers/Admin/TaxController.php'));
        $this->assertStringNotContainsString("'Catégories exclues'", $controleur, 'plus de mention sur le PDF');
        $this->assertStringNotContainsString('categories_exclues=', $controleur, "ni sur l'Excel");
    }
}
