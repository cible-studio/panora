<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Un panneau sorti du parc ne doit casser aucune page (2026-11-16).
 *
 * ═══ LE DÉFAUT D'ORIGINE ═══
 * /admin/piges renvoyait une 500 :
 *   UrlGenerationException — Missing required parameter for
 *   [Route: admin.panels.show] [URI: admin/panels/{panel}]
 *
 * Cause : `Panel` utilise SoftDeletes. Démanteler ou supprimer un panneau
 * pose `deleted_at` sans toucher aux enregistrements qui le citent —
 * `piges.panel_id` est NOT NULL avec ON DELETE CASCADE, donc la ligne pige
 * survit intacte. Mais `belongsTo(Panel::class)` applique le scope de
 * suppression douce : la relation renvoie `null`, et `route('…', null)`
 * lève l'exception.
 *
 * Le remède est celui déjà retenu pour `Maintenance::panel()` le
 * 2026-10-08 : un enregistrement d'historique garde son panneau visible
 * via `withTrashed()`, et la fiche panneau reste consultable en lecture
 * seule.
 *
 * Ces garde-fous sont volontairement textuels : ils tiennent le contrat
 * sans base de données, et c'est la disparition du `withTrashed()` — pas
 * son comportement — qui a provoqué la panne.
 */
class PanneauDemanteleLiensTest extends TestCase
{
    private const RACINE = __DIR__ . '/../../';

    /**
     * Les relations qui doivent survivre au démantèlement du panneau.
     *
     * Chacune porte un enregistrement d'HISTORIQUE : la trace doit rester
     * lisible même quand le panneau a quitté le parc.
     *
     * @return array<string, array{0:string}>
     */
    public static function relationsHistoriques(): array
    {
        return [
            'pige'           => ['app/Models/Pige.php'],
            'tâche de pose'  => ['app/Models/PoseTask.php'],
            'ligne facture'  => ['app/Models/InvoiceLine.php'],
            'ligne devis'    => ['app/Models/QuoteLine.php'],
            'photo panneau'  => ['app/Models/PanelPhoto.php'],
            'maintenance'    => ['app/Models/Maintenance.php'],
        ];
    }

    /**
     * @dataProvider relationsHistoriques
     */
    public function test_la_relation_panel_recupere_les_panneaux_demanteles(string $fichier): void
    {
        $source = $this->lire($fichier);

        $this->assertStringContainsString(
            'belongsTo(Panel::class)->withTrashed()',
            $source,
            "{$fichier} : la relation panel() doit porter ->withTrashed(). "
            . "Sans cela, démanteler un panneau rend la relation nulle et "
            . "toute vue qui construit route('admin.panels.show', \$x->panel) "
            . "renvoie une 500."
        );

        $this->assertStringNotContainsString(
            'belongsTo(Panel::class);',
            $source,
            "{$fichier} : il reste un belongsTo(Panel::class) sans "
            . "withTrashed() — le défaut peut revenir par cette relation."
        );
    }

    public function test_la_fiche_panneau_accepte_un_panneau_sorti_du_parc(): void
    {
        $routes = $this->lire('routes/admin.php');

        $this->assertMatchesRegularExpression(
            "/Route::get\('panels\/\{panel\}', \[PanelController::class, 'show'\]\)\s*\n\s*->whereNumber\('panel'\)->withTrashed\(\)->name\('panels\.show'\);/",
            $routes,
            "La route admin.panels.show doit accepter un panneau supprimé. "
            . "Les piges, poses, factures et devis d'un panneau démantelé "
            . "pointent vers elle : sans withTrashed() le lien renvoie un 404."
        );
    }

    public function test_seule_la_consultation_est_ouverte_sur_un_panneau_supprime(): void
    {
        $routes = $this->lire('routes/admin.php');

        // Les routes qui MODIFIENT un panneau ne doivent jamais accepter un
        // panneau sorti du parc : on consulte l'historique, on ne l'édite pas.
        foreach (["'edit'", "'update'", "'destroy'"] as $action) {
            $lignes = array_filter(
                explode("\n", $routes),
                fn($l) => str_contains($l, 'panels/{panel}') && str_contains($l, $action)
            );

            foreach ($lignes as $ligne) {
                $this->assertStringNotContainsString(
                    'withTrashed()',
                    $ligne,
                    "La route panels {$action} ne doit pas accepter un "
                    . "panneau supprimé : " . trim($ligne)
                );
            }
        }
    }

    public function test_la_fiche_panneau_signale_la_sortie_du_parc(): void
    {
        $vue = $this->lire('resources/views/admin/panels/show.blade.php');

        $this->assertStringContainsString(
            'Panneau sorti du parc',
            $vue,
            "La fiche d'un panneau supprimé doit le dire : sans bandeau, "
            . "elle se lit comme un panneau encore exploitable."
        );

        $this->assertStringContainsString(
            '@unless($panel->trashed())',
            $vue,
            "Les actions PDF / Modifier / Disponibilités doivent être "
            . "masquées sur un panneau supprimé : leurs routes n'acceptent "
            . "pas un panneau sorti du parc et renverraient un 404."
        );
    }

    public function test_le_pdf_des_piges_ne_depend_pas_d_un_panneau_present(): void
    {
        $pdf = $this->lire('resources/views/pdf/piges-report.blade.php');

        $this->assertStringNotContainsString(
            '$pige->panel->reference',
            $pdf,
            "Le PDF des piges doit passer par \$pige->panel?->reference : un "
            . "export ne doit jamais échouer sur un panneau introuvable."
        );
    }

    private function lire(string $chemin): string
    {
        $absolu = self::RACINE . $chemin;

        $this->assertFileExists($absolu, "Fichier attendu absent : {$chemin}");

        return (string) file_get_contents($absolu);
    }
}
