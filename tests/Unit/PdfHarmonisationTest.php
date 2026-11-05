<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Mise en page des PDF harmonisée (2026-11-05).
 *
 * Retour utilisateur : « ces cases font trop IA », logo et titre mal
 * alignés, date d'édition au pied, « donne le résultat d'un expert ».
 * Décisions : tous les documents internes passent à l'en-tête et aux styles
 * éditoriaux ; proposition, disponibilités et catalogue gardent leur corps
 * mais prennent le pied harmonisé ; devis et facture ne changent pas.
 */
class PdfHarmonisationTest extends TestCase
{
    /** Documents internes : en-tête doc-entete + styles doc-styles. */
    private const INTERNES = [
        'admin/campaigns/pdf/list', 'pdf/rapport-piges',
        'admin/campaigns/fiche-pose-pdf', 'admin/campaigns/fiche-pose-pdf-liste', 'admin/clients/pdf/list',
        'admin/finance/recap-pdf', 'admin/finance/relances-pdf', 'admin/performance/commerciaux/pdf',
        'admin/performance/equipes/pdf', 'admin/performance/techniciens/pdf', 'admin/sla/pdf',
        'admin/rapports/decap-pdf', 'admin/rapports/occupation-details-pdf',
        'admin/rapports/panneaux-classification-pdf', 'admin/rapports/synthese-pdf',
        'admin/rapports/zones-communes-pdf', 'pdf/invoices-list', 'pdf/network-report', 'admin/panels/pdf/fiche',
    ];

    /** Documents clients : leur en-tête reste, seul le pied est commun. */
    private const CLIENTS = [
        'admin/propositions/pdf', 'admin/reservations/pdf/disponibilites-list',
        'admin/reservations/pdf/disponibilites-images',
    ];

    private function source(string $vue): string
    {
        return file_get_contents(resource_path("views/{$vue}.blade.php"));
    }

    public function test_les_documents_internes_ont_l_en_tete_et_les_styles_editoriaux(): void
    {
        foreach (self::INTERNES as $vue) {
            $s = $this->source($vue);
            $this->assertStringContainsString("pdf.partials.doc-styles", $s, $vue);
            $this->assertStringContainsString("pdf.partials.doc-entete", $s, $vue);
            $this->assertStringNotContainsString("pdf.partials.charte-header", $s, $vue);
        }
        // Taxes : par leurs partiels communs.
        $this->assertStringContainsString('pdf.partials.doc-entete', $this->source('pdf/partials/tax-doc-header'));
        $this->assertStringContainsString('pdf.partials.doc-styles', $this->source('pdf/partials/tax-doc-styles'));
    }

    public function test_les_documents_clients_gardent_leur_corps(): void
    {
        foreach (self::CLIENTS as $vue) {
            $s = $this->source($vue);
            $this->assertStringNotContainsString('pdf.partials.doc-styles', $s, $vue);
            $this->assertStringNotContainsString('pdf.partials.doc-entete', $s, $vue);
            $this->assertStringContainsString('pdf.partials.charte-footer', $s, "{$vue} : pied harmonisé");
        }
    }

    public function test_devis_et_facture_inchanges(): void
    {
        // Décision « 1-a » : le devis garde son pied (copie figée).
        $this->assertStringContainsString("pdf.partials.charte-footer-devis", $this->source('pdf/quote'));
        $this->assertStringNotContainsString("pdf.partials.doc-", $this->source('pdf/quote'));
        $this->assertStringNotContainsString("pdf.partials.doc-", $this->source('pdf/invoice'));
    }

    public function test_un_seul_pied_sans_redites(): void
    {
        $html = view('pdf.partials.charte-footer', ['footerHint' => 'Document confidentiel'])->render();

        $this->assertStringContainsString(config('charte.coordonnees.activite') === null ? '' : e(config('charte.coordonnees.activite')), $html);
        $this->assertStringContainsString('Document confidentiel · édité le ' . now()->format('d/m/Y'), $html);
        $this->assertStringContainsString('class="ch-pagenum"', $html);

        // Plus de mentions recopiées à la main d'un PDF à l'autre.
        $vues = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($f) => str_contains($f->getContents(), "pdf.partials.charte-footer'"))
            ->map(fn ($f) => $f->getRelativePathname());
        $this->assertNotEmpty($vues);
        foreach ($vues as $vue) {
            // Seulement le texte passé au pied (l'en-tête de la proposition,
            // inchangé par décision, garde « Régie OOH »).
            preg_match_all("/'footerHint'\\s*=>[^\\]]{0,300}/u", file_get_contents(resource_path('views/' . $vue)), $pieds);
            foreach ($pieds[0] as $pied) {
                foreach (['Régie Publicitaire', 'Régie OOH', 'Édité par Panora', 'Document généré automatiquement'] as $redite) {
                    $this->assertStringNotContainsString($redite, $pied, "{$vue} : « {$redite} »");
                }
            }
        }
    }

    public function test_l_en_tete_ecarte_la_date_d_edition(): void
    {
        $html = view('pdf.partials.doc-entete', [
            'docKicker' => 'Rapport', 'docTitle' => 'Synthèse exécutive',
            'docMeta'   => ['Édité le 05/11/2026 10:00', 'Généré le 05/11/2026', 'Par Abdoul'],
        ])->render();

        $this->assertStringContainsString('<div class="doc-titre">Synthèse exécutive</div>', $html);
        $this->assertStringContainsString('Par Abdoul', $html);
        $this->assertStringNotContainsString('Édité le', $html);
        $this->assertStringNotContainsString('Généré le', $html);
    }
}
