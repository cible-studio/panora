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

    /**
     * PDF du détail = relevé pour la mairie (2026-11-24) : un relevé par
     * commune, période en gras (trimestres pour la TM), tableau numéroté,
     * total à payer en chiffres et en lettres. Jamais les filtres
     * (catégories exclues…). Le soir : retour à l'ancien design (paysage,
     * en-tête CIBLE), mêmes informations, en grand et en gras.
     */
    private function releve(array $lignes): string
    {
        $lines = collect($lignes);

        // Texte décodé : Blade écrit l'apostrophe « &#039; ».
        return html_entity_decode(view('pdf.taxes-details', [
            'lines' => $lines, 'totals' => app(TaxCalculationService::class)->summarize($lines),
            'periodLabel' => 'Novembre 2026', 'filterSummary' => '', 'filterMeta' => [],
            'periodeDebut' => \Illuminate\Support\Carbon::create(2026, 11, 1), 'periodeFin' => \Illuminate\Support\Carbon::create(2026, 11, 30),
        ])->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function ligneReleve(string $commune, string $ref, int $panel, string $type, int $m2, ?string $client = null): array
    {
        $p = \Illuminate\Support\Carbon::create(2026, 11, 1);

        return [
            'commune' => $commune, 'commune_id' => 1, 'panel_id' => $panel, 'reference' => $ref, 'name' => 'Essai',
            'dimensions' => $m2 . 'm²', 'surface' => $m2, 'type' => $type, 'statut' => 'occupe', 'client_name' => $client,
            'campaign_name' => $client ? 'Campagne essai' : null, 'campaign_id' => $client ? 20 : null,
            'campaign_start' => $client ? $p : null, 'campaign_end' => $client ? $p->copy()->addMonths(2)->subDay() : null,
            'period_start' => $p, 'period_end' => $p->copy()->endOfMonth(), 'months' => 1, 'unit' => 'mois',
            'rate' => 1000, 'rate_applied' => 1000, 'amount' => 1000 * $m2,
        ];
    }

    public function test_le_releve_odp_suit_la_maquette(): void
    {
        $html = $this->releve([
            $this->ligneReleve('YOPOUGON', 'YOP-CH001', 1, 'odp', 6),
            $this->ligneReleve('YOPOUGON', 'YOP-CH002', 2, 'odp', 20),
        ]);

        // Modèle de l'utilisateur, sans « À l'attention de Monsieur le Maire… »
        // ni signature (demande du 24/11).
        $this->assertStringNotContainsString('Monsieur le Maire', $html);
        $this->assertStringNotContainsString('Signature', $html);
        $this->assertStringContainsString('Taxes communales', $html, 'ancien design : en-tête des documents internes');
        $this->assertStringContainsString('Abidjan, le ', $html);
        $this->assertStringContainsString('sur le territoire de la Commune de Yopougon', $html);
        $this->assertStringContainsString("Relevé des taxes d'occupation du domaine public", $html);
        $this->assertStringContainsString('<span class="val">YOPOUGON</span>', $html, 'la commune, en grand et en gras');
        $this->assertStringContainsString('<span class="val">du 01/11/2026 au 30/11/2026</span>', $html);
        $this->assertStringContainsString('(novembre 2026)', $html);
        $this->assertMatchesRegularExpression('#Opérateur</span>\s*<span class="val">CIBLE CI</span>#', $html);
        $this->assertMatchesRegularExpression('#Nombre de panneaux</span>\s*<span class="val">2</span>#', $html);
        $this->assertStringContainsString('TOTAL À PAYER', $html);
        $this->assertStringContainsString('vingt-six mille (26 000) francs CFA', $html);
        $this->assertStringContainsString('Un panneau double face est compté une seule fois.', $html);
        $this->assertStringNotContainsString('>Annonceur<', $html, 'ODP : pas de colonne annonceur');
        $this->assertStringNotContainsString('exclu', mb_strtolower(strip_tags($html)), 'aucune mention des catégories exclues');
    }

    public function test_le_releve_tm_donne_les_trimestres_et_l_annonceur(): void
    {
        $html = $this->releve([$this->ligneReleve('YOPOUGON', 'YOP-CH002', 2, 'tm', 20, 'CLIENT ESSAI')]);

        $this->assertStringContainsString('Relevé de la taxe municipale', $html);
        $this->assertStringContainsString('<div class="rv-trim">Trimestre 4 : novembre 2026</div>', $html, 'TM : trimestres en toutes lettres');
        $this->assertStringContainsString('>Campagne<', $html, 'TM : la campagne');
        $this->assertStringContainsString('Campagne essai', $html);
        $this->assertSame(
            ['Trimestre 1 : de janvier à mars 2026', "Trimestre 2 : d'avril à juin 2026"],
            TaxCalculationService::trimestresEnLettres(\Illuminate\Support\Carbon::create(2026, 1, 1), \Illuminate\Support\Carbon::create(2026, 6, 30))
        );
        $this->assertStringContainsString('>Annonceur<', $html);
        $this->assertStringContainsString('CLIENT ESSAI', $html);
        $this->assertStringContainsString('01/11/2026 → 31/12/2026', $html, 'paysage : dates sur une ligne');
        $this->assertStringContainsString("nombre de mois d'affichage de chaque campagne", $html);
    }

    public function test_un_releve_par_commune_et_deux_tableaux_si_odp_et_tm(): void
    {
        $html = $this->releve([
            $this->ligneReleve('ADJAME', 'ADJ-002', 4, 'odp', 12),
            $this->ligneReleve('ADJAME', 'ADJ-002', 4, 'tm', 12, 'CLIENT ESSAI'),
            $this->ligneReleve('YOPOUGON', 'YOP-CH001', 1, 'odp', 6),
        ]);

        $this->assertStringContainsString("Commune d'Adjame", $html, "« d' » devant une voyelle");
        $this->assertStringContainsString('Commune de Yopougon', $html);
        $this->assertSame(1, substr_count($html, 'class="rv rv-saut"'), 'la seconde commune commence une nouvelle page');
        $this->assertStringContainsString('Sous-total ODP', $html);
        $this->assertStringContainsString('Sous-total TM', $html);
        $this->assertStringContainsString('vingt-quatre mille (24 000) francs CFA', $html);
        $this->assertStringContainsString('six mille (6 000) francs CFA', $html);
    }

    public function test_montant_en_lettres_et_categories_jamais_ecrites(): void
    {
        $this->assertSame('deux cent mille', \App\Support\MontantEnLettres::en(200000));
        $this->assertSame('quatre-vingt mille', \App\Support\MontantEnLettres::en(80000));
        $this->assertSame('soixante et onze', \App\Support\MontantEnLettres::en(71));
        $this->assertSame('un million deux cent cinquante mille cinq cents', \App\Support\MontantEnLettres::en(1250500));
        $this->assertSame('T1 et T2 2026', TaxCalculationService::libelleTrimestres(\Illuminate\Support\Carbon::create(2026, 2, 1), \Illuminate\Support\Carbon::create(2026, 6, 30)));

        $controleur = file_get_contents(app_path('Http/Controllers/Admin/TaxController.php'));
        $this->assertStringNotContainsString("'Catégories exclues'", $controleur, 'plus de mention sur le PDF');
        $this->assertStringNotContainsString('categories_exclues=', $controleur, "ni sur l'Excel");
        $this->assertStringContainsString("setPaper('a4', 'landscape')", $controleur, 'ancien design : paysage');
    }
}
