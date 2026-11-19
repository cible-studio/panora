<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteService;
use App\Services\InvoiceCalculator;
use App\Services\QuoteBuilder;
use App\Services\QuotePdf;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * PDF devis sur 2 pages (lot B, 2026-11-19).
 *
 * Le devis est réellement rendu par DomPDF, par le service commun
 * (App\Services\QuotePdf), puis relu avec smalot/pdfparser — pdfinfo
 * n'existe pas sur les postes Windows.
 *
 * Les deux cas de référence du cahier des charges : ILUX (1 emplacement,
 * DEV-2026-001) et Havas (2 lignes Cocody, modèle Excel). Modèles en
 * mémoire, sans base de données.
 */
class DevisPdfPagesTest extends TestCase
{
    private function devis(array $lignes, array $services, int $validite, float $remise = 0): Quote
    {
        $calc = app(InvoiceCalculator::class);
        $calculees = [];
        $modeles = [];
        foreach ($lignes as $l) {
            $c = $calc->calculateLine($l);
            $calculees[] = $l + $c;
            $modeles[] = (new QuoteLine())->forceFill($l + $c + ['panel_id' => null]);
        }
        $totaux = app(QuoteBuilder::class)->totaux($calculees, ['remise_pct' => $remise, 'services' => $services ?: null]);
        unset($totaux['services_impression'], $totaux['services_pose_depose'], $totaux['tsp_rate_override']);

        $q = (new Quote())->forceFill([
            'reference' => 'DEV-TEST', 'title' => 'Essai', 'status' => 'brouillon', 'valid_days' => $validite,
            'created_at' => '2026-11-16 10:00:00', 'remise_pct' => $remise,
        ] + $totaux);
        // Coordonnées fictives : jamais de données réelles de client dans le dépôt.
        $q->setRelation('client', (new Client())->forceFill([
            'name' => 'CLIENT ESSAI', 'contact_name' => 'Contact Essai', 'company' => 'CLIENT ESSAI SA',
            'phone' => '+225 00 00 00 00 00', 'email' => 'contact@client-essai.test',
        ]));
        $q->setRelation('lines', collect($modeles));
        $q->setRelation('services', collect(array_map(fn ($s) => (new QuoteService())->forceFill($s), $services)));

        return $q;
    }

    private function pages(Quote $quote): int
    {
        $pdf = app(QuotePdf::class)->pdf($quote)->output();

        return count((new \Smalot\PdfParser\Parser())->parseContent($pdf)->getPages());
    }

    public function test_le_devis_ilux_tient_sur_deux_pages(): void
    {
        $q = $this->devis([[
            'designation' => 'PBTCAIS-01B — Port-Bouet - Route-Aéroport-sens-Aéroport-face-Akwaba',
            'snapshot_commune_name' => 'PORT-BOUET', 'dimension_m2' => 18, 'quantite' => 1, 'duree_mois' => 6,
            'pu_ht_mensuel' => 800000, 'odp_rate_applique' => 1000, 'tm_rate_applique' => 1000,
        ]], [['label' => 'Impression', 'quantite' => 1, 'prix_ht' => 180000, 'tva_applicable' => true]], 7);

        $this->assertSame(6236400, $q->total_a_payer, 'Le cas ILUX doit être celui du cahier des charges.');
        $this->assertSame(2, $this->pages($q), 'Le devis ILUX doit tenir sur 2 pages, conditions générales comprises.');
    }

    public function test_le_devis_havas_tient_sur_deux_pages(): void
    {
        $ligne = ['designation' => 'Cocody', 'snapshot_commune_name' => 'Cocody', 'dimension_m2' => 12, 'duree_mois' => 1,
            'odp_rate_applique' => 5000, 'tm_rate_applique' => 1000];
        $q = $this->devis(
            [$ligne + ['quantite' => 4, 'pu_ht_mensuel' => 97000], $ligne + ['quantite' => 1, 'pu_ht_mensuel' => 80000]],
            [['label' => "Frais d'impression dos bleu format 12m²", 'quantite' => 30, 'prix_ht' => 20000, 'tva_applicable' => true]],
            15
        );

        $this->assertSame(1634280, $q->total_a_payer, 'Le cas Havas doit être celui du modèle Excel.');
        $this->assertSame(2, $this->pages($q), 'Le devis Havas doit tenir sur 2 pages, conditions générales comprises.');
    }

    /**
     * Option 1 validée le 2026-11-19 : on garde la charte et on resserre les
     * CG (7pt) pour qu'un devis long tienne sur 2 pages. Cas de contrôle :
     * 10 emplacements sur 5 communes (10 lignes de taxes), 2 services dont
     * un hors TVA.
     */
    public function test_un_devis_long_tient_sur_deux_pages(): void
    {
        $communes = ['Cocody', 'Plateau', 'Marcory', 'Yopougon', 'Treichville'];
        $lignes = [];
        for ($i = 1; $i <= 10; $i++) {
            $lignes[] = ['designation' => "Boulevard de la République, carrefour n° {$i}, face station",
                'snapshot_commune_name' => $communes[$i % 5], 'dimension_m2' => 12, 'quantite' => 1, 'duree_mois' => 3,
                'pu_ht_mensuel' => 150000, 'odp_rate_applique' => 2000, 'tm_rate_applique' => 1000];
        }
        $q = $this->devis($lignes, [
            ['label' => "Frais d'impression bâches 12 m²", 'quantite' => 10, 'prix_ht' => 45000, 'tva_applicable' => true],
            ['label' => 'Pose et dépose', 'quantite' => 10, 'prix_ht' => 25000, 'tva_applicable' => false],
        ], 30);

        $this->assertSame(7306000, $q->total_a_payer);
        // Avec le cachet signé dans les deux cadres (2026-11-19) : une image
        // de remplacement sur un disque privé simulé.
        $this->poserCachet();
        $this->assertStringContainsString('class="cachet"', view('pdf.quote', ['quote' => $q])->render());
        $this->assertSame(2, $this->pages($q), 'Un devis de 10 emplacements doit tenir sur 2 pages avec la charte et le cachet.');
    }

    /**
     * Cachet et signature de la Directrice Commerciale (demande écrite du
     * 2026-11-19) : sur CHAQUE devis généré, dans les deux cadres « Pour
     * CIBLE SARL », lu sur le disque privé et intégré en base64.
     */
    public function test_le_cachet_signe_est_sur_chaque_devis(): void
    {
        $this->poserCachet();
        $html = view('pdf.quote', ['quote' => $this->devisSimple()])->render();

        $this->assertSame(2, substr_count($html, '<div class="cachet"><img src="data:image/png;base64,'),
            'Le cachet doit figurer dans les deux cadres « Pour CIBLE SARL ».');
        $this->assertSame(2, substr_count($html, 'trait trait-cachet'));
    }

    /** Sans le fichier (poste neuf, serveur pas encore servi) : cadres vides, PDF normal. */
    public function test_sans_cachet_le_devis_se_genere_comme_avant(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $q = $this->devisSimple();

        $this->assertStringNotContainsString('class="cachet"', view('pdf.quote', ['quote' => $q])->render());
        $this->assertSame(2, $this->pages($q));
    }

    /** Le cachet vient du disque privé, jamais d'un dossier public ni de Git. */
    public function test_le_cachet_est_lu_sur_le_disque_prive(): void
    {
        $src = File::get(resource_path('views/pdf/quote.blade.php'));
        $this->assertStringContainsString("config('devis.cachet_signature', 'signatures/directrice.png')", $src);
        $this->assertStringContainsString("\\Illuminate\\Support\\Facades\\Storage::disk('local')", $src);
        $this->assertSame('signatures/directrice.png', config('devis.cachet_signature'));
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
        $this->assertStringContainsString('*', File::get(storage_path('app/private/.gitignore')),
            'storage/app/private reste hors de Git.');
    }

    private function poserCachet(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        // Image de remplacement : le logo public, pour ne jamais dépendre du
        // vrai cachet (absent des postes de test et hors de Git).
        \Illuminate\Support\Facades\Storage::disk('local')
            ->put('signatures/directrice.png', File::get(public_path('images/logol.png')));
    }

    private function devisSimple(): Quote
    {
        return $this->devis([[
            'designation' => 'Cocody', 'snapshot_commune_name' => 'Cocody', 'dimension_m2' => 12, 'quantite' => 1,
            'duree_mois' => 1, 'pu_ht_mensuel' => 80000, 'odp_rate_applique' => 5000, 'tm_rate_applique' => 1000,
        ]], [], 15);
    }

    /**
     * Remise (2026-11-19) : la barre des lignes donne le HT brut, puis
     * « Montant HT − Remise = Montant Total HT », comme la fiche admin et
     * l'espace client. Avant, la barre affichait le net sous des lignes
     * brutes, sans ligne de remise : le PDF ne se recoupait pas.
     */
    public function test_la_remise_a_sa_ligne_et_le_pdf_se_recoupe(): void
    {
        $ligne = ['designation' => 'Cocody', 'snapshot_commune_name' => 'Cocody', 'dimension_m2' => 12, 'duree_mois' => 1,
            'odp_rate_applique' => 5000, 'tm_rate_applique' => 1000];
        $q = $this->devis(
            [$ligne + ['quantite' => 4, 'pu_ht_mensuel' => 97000], $ligne + ['quantite' => 1, 'pu_ht_mensuel' => 80000]],
            [], 15, 10
        );
        $html = view('pdf.quote', ['quote' => $q])->render();

        $this->assertSame(468000, (int) $q->amount);
        $this->assertSame(421200, (int) $q->net_ht);
        $this->assertStringContainsString('Remise (10 %)', $html);
        $this->assertStringContainsString('− 46 800 FCFA', $html);
        $this->assertMatchesRegularExpression('/<tr class="bar">.*?468 000/s', $html, 'La barre des lignes donne le HT brut.');
        $this->assertStringContainsString('421 200 FCFA', $html, 'Montant Total HT = net après remise.');
        $this->assertSame(2, $this->pages($q));

        // Sans remise : pas de ligne de remise, la barre donne le net (= le brut).
        $sans = view('pdf.quote', ['quote' => $this->devis([$ligne + ['quantite' => 1, 'pu_ht_mensuel' => 80000]], [], 15)])->render();
        $this->assertStringNotContainsString('Remise (', $sans);
    }

    /**
     * fontHeightRatio 0,85 : posé sur le rendu du devis seulement. Dans
     * config/dompdf.php, il changerait aussi la facture FNE et les rapports.
     */
    public function test_le_ratio_de_hauteur_de_ligne_reste_propre_au_devis(): void
    {
        $this->assertSame(0.85, QuotePdf::FONT_HEIGHT_RATIO);
        $this->assertStringContainsString(
            "->setOption('fontHeightRatio', self::FONT_HEIGHT_RATIO)",
            File::get(app_path('Services/QuotePdf.php'))
        );
        $this->assertStringNotContainsString('height_ratio', File::get(config_path('dompdf.php')),
            'Le ratio ne doit pas être réglé pour tous les PDF.');
        $this->assertStringNotContainsString('HeightRatio', File::get(config_path('dompdf.php')),
            'Le ratio ne doit pas être réglé pour tous les PDF.');
    }

    /** Les quatre chemins du PDF devis passent par le même service. */
    public function test_les_quatre_chemins_passent_par_le_service(): void
    {
        foreach ([
            'app/Http/Controllers/Admin/QuoteController.php',
            'app/Http/Controllers/Client/ClientQuoteController.php',
            'app/Http/Controllers/PublicQuoteController.php',
            'app/Mail/QuoteSentMail.php',
        ] as $fichier) {
            $src = File::get(base_path($fichier));
            $this->assertStringContainsString('QuotePdf::class', $src, "{$fichier} doit passer par QuotePdf.");
            $this->assertStringNotContainsString("loadView('pdf.quote'", $src,
                "{$fichier} : plus d'appel direct à DomPDF pour le devis.");
        }
    }

    /** Les arbitrages écrits du 2026-11-19, qui priment sur le cahier. */
    public function test_les_arbitrages_du_19_novembre_sont_tenus(): void
    {
        $src = File::get(resource_path('views/pdf/quote.blade.php'));

        // a. Fond jaune de l'en-tête gardé, filet or dessous.
        $this->assertMatchesRegularExpression('/\.filet \{[^}]*background: \{\{ \$dv\[\x27or\x27\] \}\}/s', $src);
        $this->assertMatchesRegularExpression('/<\/table>\s*<div class="filet"><\/div>/', $src,
            'Le filet or se pose sous le bandeau jaune.');
        // b. Aucun saut de page forcé ; articles et signatures d'un seul tenant.
        $this->assertStringNotContainsString('page-break-before', $src);
        $this->assertStringNotContainsString('page-break-after', $src);
        $this->assertSame(9, substr_count($src, '<table class="g cg keep">'),
            "L'en-tête des CG et chacun des 8 articles restent d'un seul tenant.");
        $this->assertSame(2, substr_count($src, "<tr class=\"sig\">"));
        // c. Pied fixe, sur une seule ligne.
        // (la règle contient des {{ … }} Blade : on ne s'arrête pas aux accolades)
        $this->assertMatchesRegularExpression('/\.dv-pied \{\s*position: fixed;.*?white-space: nowrap;/s', $src);
        // e. Montant net : texte jaune sur barre bleu moyen.
        $this->assertMatchesRegularExpression('/table\.g tr\.net td \{[^}]*background: \{\{ \$dv\[\x27bleu_moyen\x27\] \}\}/s', $src);
        $this->assertStringContainsString("table.g tr.net td.v { color: {{ \$dv['or_vif'] }}; }", $src);
    }

    /**
     * Polices de la charte en Regular et Bold seulement (arbitrage du
     * 2026-11-19) : jamais le poids 300, qui rendait le texte pâle.
     */
    public function test_le_devis_ne_declare_que_regular_et_bold(): void
    {
        $src = File::get(resource_path('views/pdf/quote.blade.php'));

        $this->assertStringNotContainsString("@include('pdf.partials.charte-styles')", $src,
            'Le devis déclare ses propres polices : celles de la charte vont du poids 300 au 900.');
        $this->assertDoesNotMatchRegularExpression('/font-weight:\s*(100|200|300|500|600|800|900)\b/', $src,
            'Seuls les poids 400 et 700 sont permis dans le devis.');
        $this->assertStringContainsString("'Nunito-Regular.ttf'", $src);
        $this->assertStringContainsString("'Nunito-Bold.ttf'", $src);
    }
}
