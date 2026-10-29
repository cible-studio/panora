<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Ligne de liste cliquable (2026-10-29).
 *
 * Demande : ouvrir le détail depuis n'importe où dans la ligne, sans
 * devoir viser le bouton 👁️.
 *
 * Ces garde-fous tiennent le contrat du mécanisme : un clic sur une zone
 * déjà interactive ne doit jamais emmener ailleurs, et l'écouteur doit
 * rester délégué — les listes rechargent leur <tbody> en AJAX.
 */
class LigneCliquableTest extends TestCase
{
    private const PARTIAL = __DIR__ . '/../../resources/views/admin/partials/ligne-cliquable.blade.php';
    private const LIGNE   = __DIR__ . '/../../resources/views/admin/invoices/partials/row.blade.php';
    private const LISTE   = __DIR__ . '/../../resources/views/admin/invoices/index.blade.php';

    private function source(string $fichier): string
    {
        $this->assertFileExists($fichier);

        return file_get_contents($fichier);
    }

    // ── Le mécanisme ───────────────────────────────────────────────

    public function test_les_zones_deja_interactives_sont_epargnees(): void
    {
        $source = $this->source(self::PARTIAL);

        // Un clic sur l'une d'elles garde son rôle : lien client, bouton
        // d'action, formulaire, case à cocher, bouton des relances.
        foreach (['a', 'button', 'input', 'select', 'textarea', 'label', 'form', '[onclick]', '[data-ligne-ignore]'] as $zone) {
            $this->assertStringContainsString($zone, $source,
                "La zone « {$zone} » doit rester hors du clic de ligne.");
        }
    }

    public function test_une_selection_de_texte_ne_declenche_pas_la_navigation(): void
    {
        // On doit pouvoir surligner un montant pour le copier sans être
        // emmené sur la fiche.
        $this->assertStringContainsString('duTexteEstSelectionne', $this->source(self::PARTIAL));
    }

    public function test_l_ecouteur_est_delegue_au_document(): void
    {
        // Les listes remplacent leur <tbody> en AJAX à chaque filtre : des
        // écouteurs posés sur les lignes seraient perdus au premier filtre.
        $source = $this->source(self::PARTIAL);

        $this->assertStringContainsString("document.addEventListener('click'", $source);
        $this->assertStringNotContainsString('querySelectorAll(\'tr[data-ligne-url]\')', $source,
            'Pas d\'écouteur attaché ligne par ligne : il ne survivrait pas au rechargement AJAX.');
    }

    public function test_ctrl_clic_et_clic_milieu_ouvrent_un_nouvel_onglet(): void
    {
        $source = $this->source(self::PARTIAL);

        $this->assertStringContainsString('ctrlKey', $source);
        $this->assertStringContainsString('metaKey', $source);
        $this->assertStringContainsString("addEventListener('auxclick'", $source);
        $this->assertStringContainsString("'_blank', 'noopener'", $source);
    }

    public function test_le_survol_est_visible_dans_les_deux_themes(): void
    {
        // app.css teinte le survol en rgba(255,255,255,0.015) : invisible
        // en thème clair. Une ligne cliquable doit se voir.
        $source = $this->source(self::PARTIAL);

        $this->assertStringContainsString('tr[data-ligne-url] { cursor: pointer; }', $source);
        $this->assertStringContainsString('var(--surface2)', $source);
        $this->assertStringNotContainsString('#fff', $source,
            'Couleur en dur interdite : elle casserait l\'un des deux thèmes.');
    }

    // ── La liste des factures ──────────────────────────────────────

    public function test_la_ligne_facture_porte_l_adresse_du_detail(): void
    {
        $source = $this->source(self::LIGNE);
        $attribut = 'data-ligne-url="{{ route(\'admin.invoices.show\', $invoice) }}"';

        $this->assertStringContainsString($attribut, $source);

        // Porté par la ligne elle-même, pas par une cellule : il doit se
        // trouver dans l'ouverture du <tr>, donc avant la première <td>.
        $this->assertLessThan(
            strpos($source, '<td'),
            strpos($source, $attribut),
            'L\'adresse doit être sur le <tr>, sinon seule une cellule serait cliquable.'
        );
    }

    public function test_la_reference_est_un_vrai_lien(): void
    {
        // Pour le clavier, le clic droit et l'ouverture dans un onglet.
        $source = $this->source(self::LIGNE);

        $this->assertMatchesRegularExpression(
            '/<a href="\{\{ route\(\'admin\.invoices\.show\', \$invoice\) \}\}" data-ligne-principal/',
            $source
        );
        $this->assertStringContainsString('{{ $invoice->reference }}</a>', $source);
    }

    public function test_les_montants_ne_se_coupent_plus_en_deux_lignes(): void
    {
        // L'espace des milliers de number_format est un espace ordinaire :
        // « 3 021 424 » s'affichait sur deux lignes.
        $source = $this->source(self::LIGNE);

        foreach (['$total', '$paid', '$remaining'] as $montant) {
            $this->assertMatchesRegularExpression(
                '/<td style="[^"]*white-space:nowrap"[^>]*>\{\{ number_format\(' . preg_quote($montant, '/') . '/',
                $source,
                "Le montant {$montant} doit rester d'un seul bloc."
            );
        }
    }

    public function test_le_mecanisme_est_pose_une_fois_et_hors_du_tableau(): void
    {
        $source = $this->source(self::LISTE);

        $this->assertSame(1, substr_count($source, "@include('admin.partials.ligne-cliquable')"));

        // <style> et <script> dans un <table> sont du HTML invalide : le
        // navigateur les déplacerait hors du tableau.
        $avantTable = substr($source, 0, strpos($source, '</table>'));
        $this->assertStringNotContainsString('ligne-cliquable', $avantTable);
    }
}
