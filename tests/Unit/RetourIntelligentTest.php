<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Retour intelligent entre les pages (2026-10-29).
 *
 * Demande : depuis Performance commerciale, un clic sur « CA HT équipe »
 * ouvrait les factures sans aucun moyen de revenir. Le bouton se déduit
 * maintenant de la provenance, puis de la page précédente.
 *
 * Ce qui est verrouillé ici : aucune redirection hors du site, aucun
 * retour vers soi-même, aucun bouton inutile sur les pages du menu, et
 * la carte des provenances reste une source unique.
 */
class RetourIntelligentTest extends TestCase
{
    /**
     * Rend le partial dans le contexte d'une requête donnée.
     *
     * @param  string|null  $precedente  la page d'où l'on vient
     */
    private function rendre(string $url, ?string $precedente = null, array $defaut = []): string
    {
        $requete = Request::create($url, 'GET', [], [], [], array_filter([
            'HTTP_REFERER' => $precedente,
        ]));

        // request()->route()?->getName() doit répondre : on route la requête.
        $requete->setRouteResolver(function () use ($requete) {
            try {
                return Route::getRoutes()->match($requete);
            } catch (\Throwable) {
                return null;
            }
        });

        $this->app->instance('request', $requete);
        URL::setRequest($requete);

        return trim(view('admin.partials._smart_back', $defaut ? ['defaut' => $defaut] : [])->render());
    }

    // ── 1. Provenance explicite (?back=) ───────────────────────────

    public function test_une_provenance_connue_nomme_sa_destination(): void
    {
        // Le cas signalé : arriver sur les factures depuis « CA HT équipe ».
        $html = $this->rendre('/admin/invoices?back=performance.commercial');

        $this->assertStringContainsString('Performance commerciale', $html);
        $this->assertStringContainsString(route('admin.performance.commercial.index'), $html);
    }

    public function test_une_provenance_inconnue_n_affiche_rien(): void
    {
        // Liste blanche stricte : pas d'open-redirect possible.
        $this->assertSame('', $this->rendre('/admin/invoices?back=https://exemple.test'));
        $this->assertSame('', $this->rendre('/admin/invoices?back=nimporte-quoi'));
        $this->assertSame('', $this->rendre('/admin/invoices?back=' . urlencode('//evil.test')));
    }

    // ── 2. Page précédente ─────────────────────────────────────────

    public function test_la_page_precedente_donne_un_retour_sur_une_page_de_detail(): void
    {
        $html = $this->rendre(
            '/admin/taxes/commune/3',
            url('/admin/rapports')
        );

        $this->assertStringContainsString('Retour', $html);
        $this->assertStringContainsString(url('/admin/rapports'), $html);
    }

    public function test_aucun_retour_vers_soi_meme(): void
    {
        // Changer de filtre sur le même écran ne doit pas proposer d'y
        // « revenir » : la comparaison ignore les paramètres.
        $this->assertSame('', $this->rendre(
            '/admin/taxes/commune/3?annee=2026',
            url('/admin/taxes/commune/3?annee=2025')
        ));
    }

    public function test_une_page_precedente_hors_du_site_est_ignoree(): void
    {
        $this->assertSame('', $this->rendre('/admin/taxes/commune/3', 'https://exemple.test/piege'));
    }

    public function test_un_domaine_qui_imite_le_notre_est_ignore(): void
    {
        // « http://localhost.piege.test » commence par « http://localhost »
        // sans être le même site : le séparateur est obligatoire.
        $usurpateur = rtrim(url('/'), '/') . '.piege.test/admin/invoices';

        $this->assertSame('', $this->rendre('/admin/taxes/commune/3', $usurpateur));
    }

    public function test_sans_page_precedente_il_n_y_a_pas_de_bouton(): void
    {
        $this->assertSame('', $this->rendre('/admin/taxes/commune/3'));
    }

    public function test_les_pages_du_menu_n_ont_pas_de_retour_deduit(): void
    {
        // On y arrive normalement par le menu : un bouton y serait presque
        // toujours présent pour rien.
        $this->assertSame('', $this->rendre('/admin/invoices', url('/admin/clients')));

        // Mais une provenance explicite reste honorée.
        $this->assertStringContainsString(
            'Performance commerciale',
            $this->rendre('/admin/invoices?back=performance.commercial', url('/admin/clients'))
        );
    }

    // ── 3. Repli déclaré par la vue ────────────────────────────────

    public function test_le_repli_de_la_vue_sert_quand_il_n_y_a_pas_de_provenance(): void
    {
        $html = $this->rendre('/admin/taxes/commune/3', null, [
            'url'   => route('admin.taxes.index'),
            'label' => 'Taxes communales',
        ]);

        $this->assertStringContainsString('Taxes communales', $html);
        $this->assertStringContainsString(route('admin.taxes.index'), $html);
    }

    public function test_la_page_precedente_passe_avant_le_repli(): void
    {
        $html = $this->rendre('/admin/taxes/commune/3', url('/admin/rapports'), [
            'url'   => route('admin.taxes.index'),
            'label' => 'Taxes communales',
        ]);

        $this->assertStringContainsString(url('/admin/rapports'), $html);
        $this->assertStringNotContainsString('Taxes communales', $html);
    }

    public function test_la_provenance_passe_avant_tout(): void
    {
        $html = $this->rendre('/admin/taxes/commune/3?back=rapports', url('/admin/clients'), [
            'url'   => route('admin.taxes.index'),
            'label' => 'Taxes communales',
        ]);

        $this->assertStringContainsString('Rapports', $html);
        $this->assertStringNotContainsString(url('/admin/clients'), $html);
    }

    // ── La carte des provenances ───────────────────────────────────

    public function test_toutes_les_routes_de_la_carte_existent(): void
    {
        foreach (config('navigation.retours') as $cle => $cfg) {
            $this->assertTrue(Route::has($cfg['route']),
                "La provenance « {$cle} » pointe vers une route inexistante : {$cfg['route']}.");
        }
    }

    public function test_toutes_les_pages_du_menu_existent(): void
    {
        foreach (config('navigation.pages_du_menu') as $route) {
            $this->assertTrue(Route::has($route), "Route du menu inexistante : {$route}.");
        }
    }

    public function test_la_carte_n_est_recopiee_nulle_part(): void
    {
        // Elle l'était à quatre endroits — _smart_back, teams/create,
        // teams/edit et PoseTeamController — et avait divergé.
        $racine = __DIR__ . '/../../';
        $copies = [];

        foreach ([
            'resources/views/admin/partials/_smart_back.blade.php',
            'resources/views/admin/teams/create.blade.php',
            'resources/views/admin/teams/edit.blade.php',
            'app/Http/Controllers/Admin/PoseTeamController.php',
        ] as $fichier) {
            $source = file_get_contents($racine . $fichier);
            if (preg_match('/\$backMap\s*=\s*\[/', $source)) {
                $copies[] = $fichier;
            }
            $this->assertStringContainsString('navigation.retours', $source,
                "{$fichier} doit lire config/navigation.php.");
        }

        $this->assertSame([], $copies, 'Carte recopiée : ' . implode(', ', $copies));
    }
}
