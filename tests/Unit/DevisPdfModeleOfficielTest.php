<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * PDF devis conforme au modèle officiel, et quantité sur les services
 * (2026-11-16).
 *
 * ═══ CE QUI A CHANGÉ ═══
 *
 * 1) QUANTITÉ SUR LES SERVICES ANNEXES.
 *    Le modèle officiel facture « 30 × 20 000 = 600 000 ». Panora ne
 *    portait qu'un libellé et un prix global : le client lisait 600 000
 *    sans pouvoir refaire le calcul.
 *    `prix_ht` devient le prix UNITAIRE, le montant de ligne vaut
 *    prix_ht × quantite. Défaut 1 → aucun devis ni aucune facture déjà
 *    émis ne change de total.
 *
 * 2) TROIS DÉFAUTS DE MONTANT, TROUVÉS EN COMPARANT AU MODÈLE.
 *    · Le PDF annonçait « Total Services Additionnels » en HT alors que
 *      le MONTANT NET additionne les services TTC : les chiffres du
 *      document ne se recoupaient pas.
 *    · La TVA des services n'apparaissait nulle part sur le PDF.
 *    · L'écran de détail du devis appliquait la TVA à TOUS les services,
 *      en ignorant l'interrupteur `tva_applicable` du 2026-10-30.
 *
 * 3) HABILLAGE. Le devis reprend la palette du modèle (bleu nuit + or),
 *    extraite des opérateurs de couleur du PDF de référence, et non la
 *    charte du 2026-10-01 restée en vigueur sur les autres documents.
 *
 * Tests sur le code source, sans base de données : ils tournent sur le
 * poste à base vide. Le rendu réel se vérifie en production
 * (docs/A_TESTER.md § 31).
 */
class DevisPdfModeleOfficielTest extends TestCase
{
    private function src(string $chemin): string
    {
        $absolu = __DIR__ . '/../../' . $chemin;
        $this->assertFileExists($absolu, "Fichier attendu absent : {$chemin}");

        return (string) file_get_contents($absolu);
    }

    // ══════════════════════════════════════════════════════════════
    // QUANTITÉ — LE MONTANT DE LIGNE
    // ══════════════════════════════════════════════════════════════

    /**
     * Le montant de ligne vit dans le modèle, pas recopié chez chaque
     * appelant : c'est la règle « prix unitaire × quantité ».
     */
    public function test_le_montant_de_ligne_est_porte_par_les_modeles(): void
    {
        foreach (['app/Models/QuoteService.php', 'app/Models/InvoiceService.php'] as $modele) {
            $src = $this->src($modele);

            $this->assertStringContainsString(
                'public function montantHt(): int',
                $src,
                "{$modele} doit porter montantHt() : prix unitaire × quantité."
            );

            $this->assertStringContainsString(
                "max(1, (int) (\$this->quantite ?: 1))",
                $src,
                "{$modele} : une quantité absente vaut 1, sinon les lignes "
                . "enregistrées avant la migration tomberaient à zéro."
            );

            $this->assertStringContainsString(
                "'quantite'",
                $src,
                "{$modele} : la quantité doit être dans \$fillable et \$casts."
            );
        }
    }

    /**
     * Devis et facture doivent compter PAREIL : un devis accepté devient
     * une facture. Si une seule des deux tables portait la quantité, les
     * deux montants divergeraient.
     */
    public function test_les_deux_tables_recoivent_la_quantite(): void
    {
        $src = $this->src(
            'database/migrations/2026_11_16_160000_add_quantite_to_services.php'
        );

        foreach (['quote_services', 'invoice_services'] as $table) {
            $this->assertStringContainsString(
                "'{$table}'",
                $src,
                "La migration doit poser la quantité sur {$table} aussi."
            );
        }

        $this->assertStringContainsString(
            "->default(1)",
            $src,
            "Défaut 1 : les lignes existantes gardent leur montant "
            . "(prix_ht × 1 = prix_ht). Sans ce défaut, toutes les "
            . "factures émises changeraient de total."
        );

        $this->assertStringContainsString(
            "Schema::hasColumn(\$table, 'quantite')",
            $src,
            "La migration doit être rejouable : les migrations Panora ont "
            . "divergé de la base réelle (règle n°2)."
        );
    }

    /**
     * Le calculateur est la source unique des montants. Il doit sommer le
     * montant de ligne, pas le prix unitaire.
     */
    public function test_le_calculateur_somme_le_montant_de_ligne(): void
    {
        $src = $this->src('app/Services/InvoiceCalculator.php');

        $this->assertStringContainsString(
            "array_sum(array_column(\$svcLines, 'montant_ht'))",
            $src,
            "Le total HT des services doit sommer montant_ht (prix × "
            . "quantité). Sommer prix_ht ne compterait qu'une unité par "
            . "ligne."
        );

        $this->assertStringNotContainsString(
            "array_sum(array_column(\$svcLines, 'prix_ht'))",
            $src,
            "Il reste une somme sur le prix unitaire : la quantité serait "
            . "ignorée dans le total."
        );
    }

    /**
     * Les quatre chemins qui alimentent le calculateur doivent transmettre
     * la quantité : en oublier un ferait diverger un écran d'un autre.
     */
    public function test_tous_les_appelants_transmettent_la_quantite(): void
    {
        foreach ([
            'devis (recalcul)'      => 'app/Services/QuoteBuilder.php',
            'facture (recalcul)'    => 'app/Services/InvoiceCalculator.php',
            'devis (aperçu + save)' => 'app/Http/Controllers/Admin/QuoteController.php',
            'facture (save)'        => 'app/Http/Controllers/Admin/InvoiceController.php',
        ] as $chemin => $fichier) {
            $this->assertStringContainsString(
                "'quantite'",
                $this->src($fichier),
                "Le chemin « {$chemin} » doit transmettre la quantité."
            );
        }
    }

    // ══════════════════════════════════════════════════════════════
    // LES DÉFAUTS DE MONTANT
    // ══════════════════════════════════════════════════════════════

    /**
     * Le défaut le plus grave : le total des services affiché en HT
     * alors que le net à payer compte le TTC.
     */
    public function test_le_pdf_devis_annonce_les_services_en_ttc(): void
    {
        $src = $this->src('resources/views/pdf/quote.blade.php');

        $this->assertStringContainsString(
            '$servicesTtcTot',
            $src,
            "Le total des services doit être le TTC : le MONTANT NET "
            . "TOTAL À PAYER additionne les services TTC, les deux doivent "
            . "se recouper."
        );

        $this->assertStringContainsString(
            '$servicesTvaAmount',
            $src,
            "La TVA des services doit apparaître, comme sur le modèle."
        );

        // Seuls les services soumis à TVA la portent.
        $this->assertStringContainsString(
            'filter(fn ($s) => (bool) $s->tva_applicable)',
            $src,
            "La TVA des services ne doit frapper que ceux qui y sont "
            . "soumis (interrupteur du 2026-10-30)."
        );
    }

    /**
     * L'écran de détail taxait tous les services en bloc : il pouvait
     * annoncer un montant différent du PDF et de la facture.
     */
    public function test_l_ecran_de_detail_respecte_la_tva_par_service(): void
    {
        $src = $this->src('resources/views/admin/quotes/show.blade.php');

        $this->assertStringNotContainsString(
            'round($quote->services_ht_total * ((float) $quote->tva / 100))',
            $src,
            "L'écran appliquait la TVA à TOUS les services en bloc, en "
            . "ignorant tva_applicable. Il divergeait du PDF et de la "
            . "facture."
        );

        $this->assertStringContainsString(
            '$s->montantHt()',
            $src,
            "L'écran doit compter la quantité de chaque service."
        );
    }

    public function test_le_pdf_facture_compte_la_quantite(): void
    {
        $src = $this->src('resources/views/pdf/invoice.blade.php');

        $this->assertStringContainsString(
            "sum(fn(\$s) => \$s->montantHt())",
            $src,
            "Le PDF facture doit sommer le montant de ligne, quantité "
            . "comprise."
        );

        $this->assertStringNotContainsString(
            "\$invoice->services->sum('prix_ht')",
            $src,
            "Il reste une somme sur le prix unitaire dans le PDF facture."
        );
    }

    // ══════════════════════════════════════════════════════════════
    // HABILLAGE DU MODÈLE OFFICIEL
    // ══════════════════════════════════════════════════════════════

    /**
     * Les couleurs ne sont pas approximées : elles viennent du PDF de
     * référence. Ce garde-fou empêche qu'on les « réaligne » un jour sur
     * la charte sans décision.
     */
    public function test_la_palette_du_modele_officiel_est_appliquee(): void
    {
        $src = $this->src('resources/views/pdf/quote.blade.php');

        foreach ([
            '#1C2E3D' => 'bleu nuit (bandeaux, en-têtes)',
            '#327DAF' => 'bleu moyen (titres des conditions générales)',
            '#FAAF00' => 'or (montants mis en avant)',
        ] as $hex => $role) {
            $this->assertStringContainsString(
                $hex,
                $src,
                "La couleur {$hex} — {$role} — vient du modèle officiel."
            );
        }

        // Les couleurs de la charte ne doivent plus piloter ce document ;
        // seules les POLICES en viennent encore.
        foreach (["\$charte['rouge']", "\$charte['noir']", "\$charte['jaune']"] as $reste) {
            $this->assertStringNotContainsString(
                $reste,
                $src,
                "Le devis ne doit plus lire les couleurs de la charte : "
                . "il suit le modèle officiel (décision du 2026-11-16)."
            );
        }

        $this->assertStringContainsString(
            "\$charte['ff_titres']",
            $src,
            "Les POLICES restent celles de la charte : seules les "
            . "couleurs changent."
        );
    }

    /**
     * La ligne « Total HT » répétait « Montant Total HT » à l'identique,
     * juste en dessous.
     */
    public function test_le_sous_total_n_est_plus_affiche_deux_fois(): void
    {
        $src = $this->src('resources/views/pdf/quote.blade.php');

        $this->assertSame(
            1,
            substr_count($src, '$fmt($netHt) }} FCFA'),
            "Le net HT ne doit apparaître qu'une fois dans le bloc de "
            . "totaux : « Montant Total HT » et « Total HT » faisaient "
            . "doublon."
        );
    }

    /**
     * Le modèle n'affiche que l'emplacement dans la colonne désignation.
     */
    public function test_la_designation_ne_porte_que_l_emplacement(): void
    {
        $src = $this->src('resources/views/pdf/quote.blade.php');

        foreach (['<strong>Période :</strong>', '<strong>Code :</strong>'] as $retire) {
            $this->assertStringNotContainsString(
                $retire,
                $src,
                "La colonne désignation ne doit porter que l'emplacement, "
                . "comme le modèle officiel."
            );
        }
    }

    /**
     * Un panneau démantelé depuis l'émission ne doit pas vider la
     * désignation d'un devis déjà envoyé.
     */
    public function test_un_panneau_demantele_ne_vide_pas_la_designation(): void
    {
        $this->assertStringContainsString(
            'Panel::withTrashed()->find($line->panel_id)',
            $this->src('resources/views/pdf/quote.blade.php'),
            "Le PDF doit retrouver un panneau sorti du parc : un devis "
            . "déjà émis reste lisible tel qu'il a été envoyé."
        );
    }

    /**
     * La page de signatures occupait une page entière à elle seule.
     */
    public function test_les_signatures_suivent_le_devis(): void
    {
        $src = $this->src('resources/views/pdf/quote.blade.php');

        $this->assertStringNotContainsString(
            '<div class="sig-title-block">',
            $src,
            "La page de signatures séparée doit avoir disparu : le modèle "
            . "pose les cadres sous le bandeau « Bon pour accord »."
        );
    }
}
