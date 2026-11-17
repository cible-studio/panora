<?php

namespace Tests\Unit;

use App\Models\Invoice;
use PHPUnit\Framework\TestCase;

/**
 * Envoi d'une facture validée (2026-11-17, constaté en formation comptable).
 *
 * La fiche affiche « Envoyer au client » sur une facture VALIDÉE (« Prête à
 * être envoyée au client ») et « Sortir du litige » sur une facture en
 * litige — les deux postent sur InvoiceController::markSent, qui refusait
 * tout sauf le brouillon : « Seules les factures en brouillon peuvent être
 * envoyées (statut actuel : validee) ».
 *
 * Tests sur le code source (sans base) : chaque statut où la fiche propose
 * l'action doit être accepté par le contrôleur.
 */
class EnvoiFactureValideeTest extends TestCase
{
    private function src(string $chemin): string
    {
        return file_get_contents(__DIR__ . '/../../' . $chemin);
    }

    private function markSent(): string
    {
        $ctrl = $this->src('app/Http/Controllers/Admin/InvoiceController.php');
        $debut = strpos($ctrl, 'public function markSent(');
        return substr($ctrl, $debut, strpos($ctrl, "\n    }\n", $debut) - $debut);
    }

    public function test_une_facture_validee_peut_etre_envoyee(): void
    {
        $this->assertStringContainsString("['brouillon', 'validee']", $this->markSent());
        $this->assertContains('envoyee', Invoice::MANUAL_TRANSITIONS['validee'],
            'le parcours officiel passe par validee → envoyee');
    }

    public function test_sortir_du_litige_remet_en_envoyee_sans_renvoyer_le_mail(): void
    {
        $code = $this->markSent();
        $litige = substr($code, strpos($code, "=== 'litige'"), 600);

        $this->assertStringContainsString("transitionStatusTo('envoyee'", $litige);
        $this->assertStringNotContainsString('notifyClientInvoiceIssued', $litige,
            'sortir du litige ne renvoie pas la facture au client');
        $this->assertContains('envoyee', Invoice::MANUAL_TRANSITIONS['litige']);
    }

    public function test_chaque_bouton_d_envoi_de_la_fiche_est_accepte(): void
    {
        $vue = $this->src('resources/views/admin/invoices/show.blade.php');
        $code = $this->markSent();

        // Repère le bloc @if($status === 'xxx') qui précède chaque formulaire d'envoi.
        preg_match_all("/@if\(\\\$(?:invoice->)?status === '(\w+)'\)(?:(?!@endif).)*?route\('admin\.invoices\.send'/s", $vue, $m);
        $this->assertNotEmpty($m[1], 'aucun bouton d\'envoi repéré');

        foreach (array_unique($m[1]) as $statut) {
            $this->assertMatchesRegularExpression("/'{$statut}'/", $code,
                "La fiche propose l'envoi en statut « {$statut} » : markSent doit l'accepter.");
        }
    }
}
