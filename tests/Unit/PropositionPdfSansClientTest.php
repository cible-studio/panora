<?php

namespace Tests\Unit;

use App\Models\Proposition;
use App\Models\User;
use Tests\TestCase;

/**
 * PDF « proposition » (2026-10-30) : une fiche client absente ou
 * supprimée (relation nulle) donne des champs vides, pas une erreur.
 */
class PropositionPdfSansClientTest extends TestCase
{
    public function test_le_pdf_se_rend_sans_fiche_client(): void
    {
        $proposition = new Proposition([
            'numero' => 'PROP-2026-001', 'nb_panneaux' => 2, 'date_debut' => '2026-11-01', 'date_fin' => '2026-11-30',
            'montant' => 1200000, 'statut' => 'en_attente', 'notes' => null,
        ]);
        $proposition->created_at = now();
        $proposition->setRelation('client', null);
        $proposition->setRelation('creator', (new User())->forceFill(['name' => 'K. Yao', 'email' => 'k.yao@cible-ci.com']));

        $html = view('pdf.proposition', ['proposition' => $proposition])->render();

        $this->assertStringContainsString('Informations client', $html);
        $this->assertStringContainsString('PROP-2026-001', $html);
    }
}
