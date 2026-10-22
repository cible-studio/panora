<?php

namespace Tests\Unit;

use App\Services\Diffusion\ModelesMail;
use PHPUnit\Framework\TestCase;

/**
 * Mise en forme des textes de mails modifiables dans Panora (2026-10-22) :
 * paragraphes, gras, listes, champs, encadrés, échappement.
 */
class ModelesMailTest extends TestCase
{
    private const VALEURS = [
        '[Nom du client]'      => 'Awa Koné',
        '[Nom de la campagne]' => 'Rentrée & Fêtes',
        "[Date d'échéance]"    => '31 décembre 2026',
        '[Votre message]'      => '',
    ];

    private function html(string $corps, array $blocs = []): array
    {
        return ModelesMail::html($corps, self::VALEURS, $blocs);
    }

    public function test_les_champs_sont_remplaces_et_echappes(): void
    {
        $p = $this->html("Bonjour [Nom du client],\n\nVotre campagne « [Nom de la campagne] ».");

        $this->assertSame('Bonjour Awa Koné,', $p[0]['html']);
        $this->assertSame('Votre campagne « Rentrée &amp; Fêtes ».', $p[1]['html']);
    }

    public function test_le_texte_saisi_est_echappe(): void
    {
        $p = $this->html('Attention <script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $p[0]['html']);
        $this->assertStringContainsString('&lt;script&gt;', $p[0]['html']);
    }

    public function test_gras_et_retour_a_la_ligne(): void
    {
        $p = $this->html("**Anticipons ensemble**\nNous vous invitons :");

        $this->assertSame('<strong>Anticipons ensemble</strong><br>Nous vous invitons :', $p[0]['html']);
    }

    public function test_le_gras_peut_entourer_un_champ(): void
    {
        $p = $this->html("Échéance le **[Date d'échéance]**.");

        $this->assertSame('Échéance le <strong>31 décembre 2026</strong>.', $p[0]['html']);
    }

    public function test_une_liste(): void
    {
        $p = $this->html("- **Une reconduction** ;\n- Les emplacements libres.");

        $this->assertSame('liste', $p[0]['type']);
        $this->assertSame('<strong>Une reconduction</strong> ;<br>Les emplacements libres.', $p[0]['html']);
    }

    public function test_les_encadres_seuls_sur_leur_ligne(): void
    {
        $p = $this->html("Avant\n\n[Boutons]\n\n[Récapitulatif]\n\nAprès", ['[Boutons]' => '<b>BOUTONS</b>', '[Récapitulatif]' => '']);

        $this->assertSame(['texte', 'bloc', 'texte'], array_column($p, 'type'));
        $this->assertSame('<b>BOUTONS</b>', $p[1]['html']); // HTML de l'encadré non échappé
    }

    public function test_un_paragraphe_vide_apres_remplacement_disparait(): void
    {
        // Accusé de réception sans message du client.
        $p = $this->html("Merci.\n\n[Votre message]\n\nÀ bientôt.");

        $this->assertCount(2, $p);
    }

    public function test_un_champ_inconnu_reste_visible(): void
    {
        // Une faute de frappe doit se voir dans l'aperçu.
        $this->assertSame('Bonjour [Nom du clien],', $this->html('Bonjour [Nom du clien],')[0]['html']);
    }

    public function test_apostrophe_typographique_et_casse_toleres(): void
    {
        $this->assertSame('Le 31 décembre 2026', $this->html('Le [date d’échéance]')[0]['html']);
    }

    public function test_objet_sans_gras_ni_espaces_en_trop(): void
    {
        $this->assertSame(
            'Votre campagne « Rentrée & Fêtes » se termine le 31 décembre 2026',
            ModelesMail::remplir("Votre campagne « **[Nom de la campagne]** »  se termine le [Date d'échéance]", self::VALEURS)
        );
    }

    public function test_version_texte(): void
    {
        $t = ModelesMail::texte("Bonjour [Nom du client],\n\n- **Un** ;\n- Deux.\n\n[Boutons]", self::VALEURS, ['[Boutons]' => 'Renouveler : https://x']);

        $this->assertSame("Bonjour Awa Koné,\n\n- Un ;\n- Deux.\n\nRenouveler : https://x", $t);
    }

    public function test_chaque_etape_a_un_texte_d_origine_et_des_boutons_valides(): void
    {
        foreach (array_keys(ModelesMail::ETAPES) as $cle) {
            $this->assertNotEmpty(ModelesMail::DEFAUTS[$cle]['objet'], $cle);
            $this->assertNotEmpty(ModelesMail::DEFAUTS[$cle]['corps'], $cle);
        }
        foreach (ModelesMail::BOUTONS as $etape => $boutons) {
            $this->assertStringContainsString('[Boutons]', ModelesMail::DEFAUTS[$etape]['corps'], $etape);
            foreach ($boutons as $b) {
                $this->assertArrayHasKey($b, ModelesMail::CHOIX);
            }
        }
        // Textes validés : J-15 et J-7 proposent « échanger avec un conseiller ».
        $this->assertSame(['renouveler', 'rappel'], ModelesMail::BOUTONS['j15']);
        $this->assertSame(['renouveler', 'ajuster', 'refus'], ModelesMail::BOUTONS['j60']);
    }
}
