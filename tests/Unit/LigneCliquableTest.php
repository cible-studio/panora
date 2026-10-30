<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Lignes et cartes de liste cliquables (2026-10-29).
 *
 * Demande : ouvrir le détail depuis n'importe où dans la ligne, sans
 * devoir viser le bouton 👁️. Étendu à toutes les listes le même jour.
 *
 * Ces garde-fous tiennent le contrat : un clic sur une zone déjà
 * interactive ne doit jamais emmener ailleurs, l'écouteur doit rester
 * délégué (les listes rechargent leur <tbody> en AJAX), et aucune liste
 * équipée ne doit perdre son adresse de détail au fil des refontes.
 */
class LigneCliquableTest extends TestCase
{
    private const VUES = __DIR__ . '/../../resources/views/admin/';

    /**
     * Toutes les listes équipées.
     *   [écran qui pose le mécanisme, fichier de la ligne, adresse attendue]
     *
     * @return array<string, array{0:string,1:string,2:string}>
     */
    public static function listes(): array
    {
        return [
            'factures'     => ['invoices/index.blade.php',          'invoices/partials/row.blade.php',            "{{ route('admin.invoices.show', \$invoice) }}"],
            'campagnes'    => ['campaigns/index.blade.php',         'campaigns/partials/row.blade.php',           "{{ route('admin.campaigns.show', \$campaign) }}"],
            'clients'      => ['clients/index.blade.php',           'clients/partials/table-rows.blade.php',      "{{ route('admin.clients.show', \$client) }}"],
            'panneaux'     => ['panels/index.blade.php',            'panels/partials/table-rows.blade.php',       "{{ route('admin.panels.show', \$panel) }}"],
            'reservations' => ['reservations/index.blade.php',      'reservations/partials/table-rows.blade.php', "{{ route('admin.reservations.show', \$res) }}"],
            'poses'        => ['poses/index.blade.php',             'poses/partials/table-rows.blade.php',        "{{ route('admin.pose-tasks.show', \$task) }}"],
            'devis'        => ['quotes/index.blade.php',            'quotes/index.blade.php',                     "{{ route('admin.quotes.show', \$quote) }}"],
            'propositions' => ['propositions/index.blade.php',      'propositions/index.blade.php',               "{{ route('admin.propositions.show', \$proposition) }}"],
            'maintenances' => ['maintenances/index.blade.php',      'maintenances/index.blade.php',               "{{ route('admin.maintenances.show', \$maintenance) }}"],
            'regies'       => ['external-agencies/index.blade.php', 'external-agencies/index.blade.php',          "{{ route('admin.external-agencies.show', \$agency) }}"],
            'piges'        => ['piges/index.blade.php',             'piges/index.blade.php',                      "{{ route('admin.piges.show', \$pige) }}"],
            // Le tableau des taxes est construit en JS : l'adresse y est
            // écrite en clair, pas par le générateur de routes.
            'taxes'        => ['taxes/index.blade.php',             'taxes/index.blade.php',                      '/admin/taxes/commune/${c.commune_id}'],
        ];
    }

    private function source(string $chemin): string
    {
        $fichier = self::VUES . $chemin;
        $this->assertFileExists($fichier);

        return file_get_contents($fichier);
    }

    private function mecanisme(): string
    {
        return $this->source('partials/ligne-cliquable.blade.php');
    }

    // ── Le mécanisme, écrit une seule fois ─────────────────────────

    public function test_les_zones_deja_interactives_sont_epargnees(): void
    {
        $source = $this->mecanisme();

        // Un clic sur l'une d'elles garde son rôle : lien client, bouton
        // d'action, formulaire, case à cocher, photo d'une pige.
        foreach (['a', 'button', 'input', 'select', 'textarea', 'label', 'form', '[onclick]', '[data-ligne-ignore]'] as $zone) {
            $this->assertStringContainsString($zone, $source,
                "La zone « {$zone} » doit rester hors du clic de ligne.");
        }
    }

    public function test_une_selection_de_texte_ne_declenche_pas_la_navigation(): void
    {
        // On doit pouvoir surligner un montant pour le copier sans être
        // emmené sur la fiche.
        $this->assertStringContainsString('duTexteEstSelectionne', $this->mecanisme());
    }

    public function test_l_ecouteur_est_delegue_au_document(): void
    {
        // Les listes remplacent leur <tbody> en AJAX à chaque filtre, et le
        // tableau des taxes est construit en JS après coup : des écouteurs
        // posés sur les lignes ne couvriraient rien de tout cela.
        $source = $this->mecanisme();

        $this->assertStringContainsString("document.addEventListener('click'", $source);
        $this->assertStringNotContainsString("querySelectorAll('[data-ligne-url]')", $source,
            "Pas d'écouteur attaché ligne par ligne : il ne survivrait pas au rechargement.");
    }

    public function test_le_mecanisme_vise_les_lignes_comme_les_cartes(): void
    {
        // Les piges sont une grille de cartes, pas un tableau.
        $this->assertStringContainsString("closest('[data-ligne-url]')", $this->mecanisme());
    }

    public function test_ctrl_clic_et_clic_milieu_ouvrent_un_nouvel_onglet(): void
    {
        $source = $this->mecanisme();

        $this->assertStringContainsString('ctrlKey', $source);
        $this->assertStringContainsString('metaKey', $source);
        $this->assertStringContainsString("addEventListener('auxclick'", $source);
        $this->assertStringContainsString("'_blank', 'noopener'", $source);
    }

    public function test_le_survol_est_visible_dans_les_deux_themes(): void
    {
        // app.css teinte le survol en rgba(255,255,255,0.015) : invisible
        // en thème clair. Une ligne cliquable doit se voir.
        $source = $this->mecanisme();

        $this->assertStringContainsString('[data-ligne-url] { cursor: pointer; }', $source);
        $this->assertStringContainsString('var(--surface2)', $source);
        $this->assertStringNotContainsString('#fff', $source,
            "Couleur en dur interdite : elle casserait l'un des deux thèmes.");
    }

    // ── Chaque liste équipée ───────────────────────────────────────

    /**
     * @dataProvider listes
     */
    public function test_la_ligne_porte_l_adresse_du_detail(string $ecran, string $ligne, string $adresse): void
    {
        $this->assertStringContainsString('data-ligne-url="' . $adresse . '"', $this->source($ligne));
    }

    /**
     * @dataProvider listes
     */
    public function test_le_mecanisme_est_pose_une_fois_et_hors_du_tableau(string $ecran): void
    {
        $source = $this->source($ecran);

        $this->assertSame(1, substr_count($source, "@include('admin.partials.ligne-cliquable')"),
            "L'écran {$ecran} doit poser le mécanisme exactement une fois.");

        // <style> et <script> dans un <table> sont du HTML invalide : le
        // navigateur les déplacerait hors du tableau.
        $finDuTableau = strpos($source, '</table>');
        if ($finDuTableau !== false) {
            $this->assertGreaterThan(
                $finDuTableau,
                strpos($source, "@include('admin.partials.ligne-cliquable')"),
                "Dans {$ecran}, le mécanisme doit être posé après la fermeture du tableau."
            );
        }
    }

    public function test_les_colonnes_de_selection_restent_neutres(): void
    {
        // Dans ces listes, on vise une case à cocher, jamais le détail.
        foreach ([
            'campaigns/partials/row.blade.php',
            'clients/partials/table-rows.blade.php',
            'poses/partials/table-rows.blade.php',
            'reservations/partials/table-rows.blade.php',
        ] as $ligne) {
            $this->assertStringContainsString('data-ligne-ignore', $this->source($ligne),
                "La colonne de sélection de {$ligne} doit être neutralisée.");
        }
    }

    // ── Les montants de la liste des factures ──────────────────────

    public function test_les_montants_ne_se_coupent_plus_en_deux_lignes(): void
    {
        // L'espace des milliers de number_format est un espace ordinaire :
        // « 3 021 424 » s'affichait sur deux lignes.
        $source = $this->source('invoices/partials/row.blade.php');

        foreach (['$total', '$paid', '$remaining'] as $montant) {
            $this->assertMatchesRegularExpression(
                '/<td style="[^"]*white-space:nowrap"[^>]*>\{\{ number_format\(' . preg_quote($montant, '/') . '/',
                $source,
                "Le montant {$montant} doit rester d'un seul bloc."
            );
        }
    }
}
