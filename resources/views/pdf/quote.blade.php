@php
    /**
     * PDF Devis — modèle CIBLE SARL officiel.
     *
     * ═══ MISE EN PAGE (lot B, 2026-11-19) ═══
     * Reprise du gabarit validé (docs/reference/…/gabarit_devis_reference.php)
     * et du modèle Excel (devis_modele.pdf), avec les arbitrages écrits du
     * 2026-11-19, qui priment sur le cahier des charges :
     *   a. en-tête sur fond jaune (17/11), filet orange #FAAF00 DESSOUS ;
     *   b. aucun saut de page forcé avant les conditions générales (17/11) :
     *      elles commencent en page 1 et continuent en page 2, comme dans le
     *      modèle Excel. Chaque article et chaque bloc de signature reste
     *      d'un seul tenant (page-break-inside: avoid) ;
     *   c. pied de page fixe (05/11) gardé, sur UNE seule ligne ;
     *   d. polices de la charte (16/11), en Regular et Bold seulement —
     *      jamais le poids 300 ni ExtraLight ;
     *   e. montant net : texte #FFC000 sur la barre bleue #327DAF.
     *
     * Une seule grille de 7 colonnes (29 / 11 / 11.5 / 8.5 / 8.5 / 14.75 /
     * 16.75 %) pour tous les blocs de la page 1, posée par une ligne de
     * dimensionnement invisible en tête de chaque table : DomPDF respecte
     * mal <col>. Les colonnes s'alignent donc d'un bloc à l'autre.
     *
     * Rendu : App\Services\QuotePdf (fontHeightRatio 0,85, propre au devis).
     *
     * Ce template ne fait AUCUN calcul. Tous les montants viennent du
     * modèle Quote (calculés par QuoteBuilder → InvoiceCalculator :
     * source unique). Modifier ici ne change JAMAIS le montant du devis.
     */
    // ═══ PALETTE DU MODÈLE OFFICIEL ═══
    //
    // Décision du 2026-11-16 : ce PDF doit être IDENTIQUE au modèle de
    // devis que la direction envoie aux clients. Il reprend donc sa
    // palette (bleu nuit + or) et non la charte du 2026-10-01
    // (rouge / noir / jaune), qui reste en vigueur sur les autres PDF —
    // facture FNE, rapports.
    //
    // Les POLICES, elles, restent celles de la charte ($charte['ff_titres'],
    // $charte['ff_texte']) : seules les couleurs changent.
    $dv = [
        // Rôle de chaque couleur (cahier lot B, B4 ; gabarit validé).
        'bleu'       => '#1C2E3D', // texte, barres de section
        'bleu_moyen' => '#327DAF', // barres Émetteur/Client, sous-totaux, titres des CG, net
        'bleu_table' => '#4D8EB9', // en-têtes de tableau
        'bleu_pale'  => '#EEF5FB', // lignes claires
        'bordure'    => '#BDD0E0', // filets de cellule (0,5 pt)
        'or'         => '#FAAF00', // filet sous l'en-tête et sous le titre des CG
        'or_vif'     => '#FFC000', // bandeau d'en-tête, montant net
        'or_doux'    => '#FFE082', // fond de la ligne MONTANT TOTAL TTC
        'or_pale'    => '#FFF4CC', // fond de l'article 3 des CG
        'gris_clair' => '#F8F9FA', // fond des modalités
        'noir'       => '#000000', // cadre de la ligne TTC
        'blanc'      => '#FFFFFF',
    ];

    $company = config('billing.company');
    $bank    = config('billing.bank');
    $societe = $company['name'] ?? 'CIBLE SARL';

    // Formatage entier FCFA (espaces normes locales)
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');

    // Grille commune : une ligne invisible qui fixe la largeur des 7
    // colonnes, en tête de chaque table (gabarit validé).
    $grille = '<tr class="sz"><td style="width:29%"></td><td style="width:11%"></td>'
        . '<td style="width:11.5%"></td><td style="width:8.5%"></td><td style="width:8.5%"></td>'
        . '<td style="width:14.75%"></td><td style="width:16.75%"></td></tr>';

    // ═══ POLICES DU DEVIS (2026-11-19) ═══
    // Déclarées ici, pour ce document seul : Regular et Bold de la charte,
    // et rien d'autre. Les autres PDF gardent les déclarations de
    // App\Support\PdfCharte. Les fichiers Nunito portent dans leurs
    // métadonnées le nom « Nunito ExtraLight » (instances tirées de la
    // police variable), mais leur dessin est bien Regular et Bold : épaisseur
    // du fût mesurée le 2026-11-19, identique à celle de Carlito Regular et
    // Bold. Poppins ne sert qu'aux titres, toujours en gras.
    $policesDevis = '';
    if ($charte['polices_ok'] ?? false) {
        foreach ([
            ['Nunito',  'normal', 400, 'Nunito-Regular.ttf'],
            ['Nunito',  'normal', 700, 'Nunito-Bold.ttf'],
            ['Nunito',  'italic', 400, 'Nunito-Italic.ttf'],
            ['Nunito',  'italic', 700, 'Nunito-BoldItalic.ttf'],
            ['Poppins', 'normal', 700, 'Poppins-Bold.ttf'],
        ] as [$famille, $style, $poids, $fichier]) {
            $policesDevis .= "@font-face{font-family:'{$famille}';font-style:{$style};font-weight:{$poids};"
                . "src:url('file://" . str_replace('\\', '/', resource_path('fonts/' . $fichier)) . "') format('truetype');}\n";
        }
    }

    // Logo émetteur en base64 (DomPDF ne charge PAS les URLs distantes,
    // isRemoteEnabled: false par défaut, base64 obligatoire).
    $logoPath = public_path($company['logo_path'] ?? 'images/logol.png');
    $logoSrc  = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    // Coordonnées de l'émetteur, ligne à ligne en face des champs du client
    // (disposition du modèle). Chaque élément reste optionnel : un .env
    // incomplet ne doit pas laisser « Tél. : » ou « | » orphelins.
    $telephones = implode(' &nbsp;|&nbsp; ', array_filter([
        !empty($company['phone'])  ? 'Tél. : ' . e($company['phone'])  : null,
        !empty($company['mobile']) ? 'Mob. : ' . e($company['mobile']) : null,
    ]));
    $courriels = implode(' &nbsp;|&nbsp; ', array_filter([
        !empty($company['email_finance']) ? e($company['email_finance']) : null,
        !empty($company['email'])         ? e($company['email'])         : null,
    ]));
    $emetteur = [
        '<b>' . e($societe) . '</b>',
        !empty($company['address']) ? e($company['address']) : '',
        $telephones,
        !empty($company['ncc']) ? 'CC : N°' . e($company['ncc']) : '',
        $courriels,
    ];
    $clientLignes = [
        'Nom :'        => $quote->client?->contact_name ?? $quote->client?->name ?? '—',
        'Entreprise :' => $quote->client?->company ?? $quote->client?->name ?? '—',
        'N°CC :'       => $quote->client?->ncc ?? '',
        'Tél. :'       => $quote->client?->phone ?? '',
        'Email :'      => $quote->client?->email ?? '',
    ];

    // Taxes publicitaires agrégées par commune (TM + ODP)
    // Reconstruites depuis odp_ligne / tm_ligne des lignes du devis.
    $taxesPub = [];
    foreach ($quote->lines as $line) {
        $commune = $line->snapshot_commune_name ?: '—';
        if ((int) $line->tm_ligne > 0) {
            $key = 'TM|' . $commune;
            $taxesPub[$key] = [
                'type'    => 'Taxe Municipale',
                'commune' => $commune,
                'rate'    => (float) $line->tm_rate_applique,
                'montant' => (int) (($taxesPub[$key]['montant'] ?? 0) + $line->tm_ligne),
            ];
        }
        if ((int) $line->odp_ligne > 0) {
            $key = 'ODP|' . $commune;
            $taxesPub[$key] = [
                'type'    => 'ODP',
                'commune' => $commune,
                'rate'    => (float) $line->odp_rate_applique,
                'montant' => (int) (($taxesPub[$key]['montant'] ?? 0) + $line->odp_ligne),
            ];
        }
    }
    uasort($taxesPub, fn($a, $b) => [$a['type'], $a['commune']] <=> [$b['type'], $b['commune']]);
    $totalTaxesPub = array_sum(array_column($taxesPub, 'montant'));

    // Totaux (sources : QuoteBuilder → InvoiceCalculator)
    $netHt         = (int) $quote->net_ht;
    $tvaAmount     = (int) $quote->tva_amount;
    $tspAmount     = (int) $quote->tsp_amount;
    // TTC du devis = net HT + TVA + TSP (règle du 2026-11-19, écrite par
    // QuoteBuilder::totaux). Valeur STOCKÉE, lue telle quelle : un devis
    // enregistré avant cette règle garde son TTC d'origine (HT + TVA)
    // jusqu'à son prochain enregistrement — rien n'est recalculé ici.
    $totalTtc      = (int) $quote->amount_ttc;
    $servicesHtTot = (int) $quote->services_ht_total;
    // Net = TTC + Taxes pub (TM + ODP) + Services TTC. La TSP est dans le
    // TTC : elle n'est pas rajoutée une seconde fois.
    $totalAPayer   = (int) $quote->total_a_payer;

    // TVA et TTC des services (2026-11-16).
    // Seuls les services soumis à TVA la portent (interrupteur
    // `tva_applicable`, 2026-10-30) ; montantHt() tient compte de la
    // quantité. La TVA vient de Quote::tvaServices() (2026-11-19), la
    // source unique que lisent aussi la fiche admin, l'espace client et
    // le lien public.
    $servicesTvaAmount = $quote->tvaServices();
    $servicesTtcTot    = $servicesHtTot + $servicesTvaAmount;

    // Somme en toutes lettres (français, jusqu'aux millions)
    $montantEnLettres = function (int $n): string {
        if ($n === 0) return 'zéro';
        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
                  'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
                  'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens  = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante',
                  'soixante', 'quatre-vingt', 'quatre-vingt'];
        $sub999 = function (int $n) use (&$sub999, $units, $tens): string {
            if ($n < 20) return $units[$n];
            if ($n < 100) {
                $t = intdiv($n, 10); $u = $n % 10;
                if ($t === 7 || $t === 9) return $tens[$t] . '-' . $units[10 + $u];
                if ($t === 8 && $u === 0) return 'quatre-vingts';
                $sep = ($u === 1 && $t !== 8) ? ' et ' : '-';
                return $tens[$t] . ($u ? $sep . $units[$u] : '');
            }
            $c = intdiv($n, 100); $r = $n % 100;
            $prefix = $c === 1 ? 'cent' : $units[$c] . ' cent' . ($r === 0 ? 's' : '');
            return $prefix . ($r ? ' ' . $sub999($r) : '');
        };
        $out = '';
        if ($n >= 1_000_000) {
            $m = intdiv($n, 1_000_000);
            $out .= ($m === 1 ? 'un million' : $sub999($m) . ' millions') . ' ';
            $n %= 1_000_000;
        }
        if ($n >= 1000) {
            $k = intdiv($n, 1000);
            $out .= ($k === 1 ? 'mille' : $sub999($k) . ' mille') . ' ';
            $n %= 1000;
        }
        if ($n > 0) $out .= $sub999($n);
        return trim($out);
    };

    $dateEmission = ($quote->sent_at ?: $quote->created_at)?->format('d/m/Y');
    // Remise (2026-11-19) : HT brut des lignes (`amount`) et taux, affichés
    // seulement s'il y a une remise. « 10 % », « 7,5 % ».
    $htBrut   = (int) $quote->amount;
    $aRemise  = (float) $quote->remise_pct > 0 && $htBrut > $netHt;
    $remisePct = rtrim(rtrim(number_format((float) $quote->remise_pct, 2, ',', ''), '0'), ',');

    $tvaRate = (int) $quote->tva;
    $tspRate = (int) config('billing.tsp_rate', 3);

    // Fonds des articles des CG, dans l'ordre 1 → 8 (cahier lot B, B5) :
    // l'article 3 (annulation, non-remboursement) ressort en jaune.
    $fondsCg = [
        $dv['bleu_pale'], $dv['blanc'], $dv['or_pale'], $dv['bleu_pale'],
        $dv['blanc'], $dv['bleu_pale'], $dv['blanc'], $dv['bleu_pale'],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis N° {{ $quote->reference }}</title>
    <style>
{!! $policesDevis !!}
        /* Marges du gabarit (10 / 10 / 9 mm), la marge basse portée à 13 mm
           pour loger le pied fixe d'une ligne (arbitrage c) : il se pose
           dans cette marge et ne chevauche jamais le contenu.
           ⚠ Aucune règle « margin: 0 » sur html ni sur * : DomPDF
           l'applique à la boîte de page et annule les marges @page. */
        @page { margin: 10mm 10mm 13mm 10mm; size: A4 portrait; }
        body {
            margin: 0;
            padding: 0;
            font-family: {!! $charte['ff_texte'] !!};
            font-size: 8pt;
            font-weight: 400;
            color: {{ $dv['bleu'] }};
            background: {{ $dv['blanc'] }};
        }
        ul, li { margin-top: 0; margin-bottom: 0; }
        b, strong { font-weight: 700; }

        /* ═══ GRILLE COMMUNE ═══ */
        table.g { width: 100%; table-layout: fixed; border-collapse: collapse; }
        table.g td, table.g th {
            border: 0.5pt solid {{ $dv['bordure'] }};
            padding: 1pt 4pt;
            vertical-align: middle;
            font-weight: 400;
            text-align: left;
        }
        table.g tr.sz td { border: 0; padding: 0; height: 0; line-height: 0; font-size: 0; }
        .r { text-align: right !important; }
        .c { text-align: center !important; }
        .b { font-weight: 700 !important; }
        .i { font-style: italic; }
        .gap { height: 4pt; }
        .keep { page-break-inside: avoid; }
        /* Titres : police de titre de la charte, toujours en gras (seul le
           Bold de Poppins est déclaré). */
        .t { font-family: {!! $charte['ff_titres'] !!}; font-weight: 700; }
        /* Cases ☐ / ☑ et flèches : DejaVu Sans, qui porte ces glyphes. */
        .box { font-family: 'DejaVu Sans', sans-serif; }

        /* ═══ EN-TÊTE ═══
           Fond jaune #FFC000 sur toute la largeur (17/11) : logo sur les
           colonnes 1 à 3, « DEVIS », « N° : » et « Date : » sur les
           colonnes 4-5, les valeurs sur les colonnes 6-7. Trois lignes de
           même hauteur. Le filet or se pose dessous (arbitrage a). */
        .header-table {
            width: 100%;
            background: {{ $dv['or_vif'] }};
        }
        table.g.header-table td {
            border: 0;
            padding: 0 4pt;
            height: 13pt;
            font-weight: 700;
            vertical-align: middle;
        }
        table.g.header-table td.logo { padding: 3pt 4pt; }
        .header-table .logo img { height: 36pt; }
        .header-table .titre-doc { font-family: {!! $charte['ff_titres'] !!}; font-size: 11pt; font-weight: 700; letter-spacing: 1pt; }
        .filet { height: 2pt; background: {{ $dv['or'] }}; font-size: 0; line-height: 0; }

        /* ═══ BARRES ═══
           bleu moyen : Émetteur / Client, sous-totaux, net ;
           bleu nuit  : sections (taxes, services, modalités, accord, CG). */
        table.g tr.section-header.moyen td,
        table.g tr.bar td {
            background: {{ $dv['bleu_moyen'] }};
            color: {{ $dv['blanc'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-weight: 700;
        }
        table.g tr.sec td {
            background: {{ $dv['bleu'] }};
            color: {{ $dv['blanc'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-weight: 700;
            border-color: {{ $dv['bleu'] }};
        }
        table.g tr.lt td, table.g tr.alt td { background: {{ $dv['bleu_pale'] }}; }
        table.g tr.gr td { background: {{ $dv['gris_clair'] }}; }

        /* ═══ TABLEAU DES EMPLACEMENTS ═══ */
        .panneaux-table th {
            background: {{ $dv['bleu_table'] }};
            color: {{ $dv['blanc'] }};
            font-weight: 700 !important;
            text-align: center !important;
        }
        .taxes-table th {
            background: {{ $dv['bleu_table'] }};
            color: {{ $dv['blanc'] }};
            font-weight: 700 !important;
            text-align: center !important;
        }

        /* ═══ SOUS-TOTAUX ═══ */
        table.g.subtotals td.lbl { border: 0; text-align: right; }
        /* MONTANT TOTAL TTC : fond ambre clair, cadre noir de 1 pt. */
        .subtotals tr.ttc td {
            background: {{ $dv['or_doux'] }};
            font-weight: 700;
            border: 1pt solid {{ $dv['noir'] }};
        }

        /* ═══ MONTANT NET ═══ (arbitrage e)
           Barre bleu moyen, libellé blanc, montant en jaune #FFC000. */
        table.g tr.net td {
            background: {{ $dv['bleu_moyen'] }};
            color: {{ $dv['blanc'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-weight: 700;
            font-size: 9pt;
            padding-top: 4pt;
            padding-bottom: 4pt;
        }
        table.g tr.net td.v { color: {{ $dv['or_vif'] }}; }

        /* Service refacturé sans TVA : le dire sur la ligne. */
        .svc-ht-flag { font-size: 7pt; font-style: italic; }

        /* ═══ SIGNATURES ═══ */
        table.g tr.sig td {
            background: {{ $dv['bleu_pale'] }};
            text-align: center;
            vertical-align: top;
            height: 52pt;
            padding-top: 5pt;
            border-color: {{ $dv['bleu'] }};
        }
        .trait { margin-top: 26pt; }
        .bandeau-bas { height: 5pt; background: {{ $dv['or_vif'] }}; margin-top: 3pt; font-size: 0; line-height: 0; }

        /* ═══ CONDITIONS GÉNÉRALES ═══ */
        /* 2026-11-19 — 7pt (au lieu de 7.5pt) : un devis long (10 emplacements,
           5 communes) tient sur 2 pages avec les polices de la charte. */
        table.g.cg td { font-size: 7pt; padding: 1.1pt 5pt; line-height: 8.2pt; }
        table.g.cg tr.art td {
            background: {{ $dv['bleu_moyen'] }};
            color: {{ $dv['blanc'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-weight: 700;
            padding: 1.3pt 5pt;
        }
        table.g.cg tr.filet-cg td { height: 2pt; padding: 0; background: {{ $dv['or'] }}; border: 0; font-size: 1pt; line-height: 2pt; }
        /* Police du texte en gras italique : Poppins n'est déclaré qu'en gras
           droit, son italique retomberait sur DejaVu. */
        .cg-site { float: right; color: {{ $dv['or_vif'] }}; font-family: {!! $charte['ff_texte'] !!}; font-style: italic; font-weight: 700; }
        .cg ul { margin: 1pt 0 1pt 0; padding-left: 12pt; }
        /* Article 3 : retenues alignées en colonnes, sans bordure ni gras. */
        table.ret { width: 100%; table-layout: fixed; border-collapse: collapse; margin: 1pt 0; }
        table.g.cg table.ret td { border: 0; padding: 0 2pt 0 0; background: transparent; }

        /* ═══ PIED FIXE (une ligne) ═══ — cf. pdf.partials.charte-footer-devis */
        .dv-pied {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -8mm;
            border-top: 1pt solid {{ $dv['or_vif'] }};
            padding-top: 1.5pt;
            font-size: 6.5pt;
            font-style: italic;
            text-align: center;
            white-space: nowrap;
            color: {{ $dv['bleu'] }};
        }
    </style>
</head>
<body>

    {{-- ═══ EN-TÊTE ═══ --}}
    <table class="g header-table">
        {!! $grille !!}
        <tr>
            <td class="logo" colspan="3" rowspan="3">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="{{ $societe }}">
                @else
                    <span class="titre-doc">{{ $societe }}</span>
                @endif
            </td>
            <td colspan="2"><span class="titre-doc">DEVIS</span></td>
            <td colspan="2"></td>
        </tr>
        <tr><td colspan="2">N° :</td><td colspan="2">{{ $quote->reference }}</td></tr>
        <tr><td colspan="2">Date :</td><td colspan="2">{{ $dateEmission }}</td></tr>
    </table>
    <div class="filet"></div>

    {{-- ═══ ÉMETTEUR / CLIENT ═══
         Chaque coordonnée de l'émetteur fait face à un champ du client,
         comme dans le modèle. --}}
    <table class="g">
        {!! $grille !!}
        <tr class="section-header moyen"><td colspan="3">ÉMETTEUR</td><td colspan="4">CLIENT / MANDATAIRE</td></tr>
        @foreach($clientLignes as $libelle => $valeur)
            <tr class="lt">
                <td colspan="3">{!! $emetteur[$loop->index] ?? '' !!}</td>
                <td class="b" style="white-space:nowrap;padding-right:1pt;font-size:7.5pt">{{ $libelle }}</td>
                <td colspan="3" style="background:{{ $dv['blanc'] }}">{{ $valeur }}</td>
            </tr>
        @endforeach
    </table>
    <div class="gap"></div>

    {{-- ═══ EMPLACEMENTS ═══ --}}
    <table class="g panneaux-table">
        {!! $grille !!}
        <tr>
            <th>DÉSIGNATION / EMPLACEMENT</th>
            <th>Format<br>m²</th>
            <th>Commune</th>
            <th>Qté</th>
            <th>Durée<br>(mois)</th>
            <th>PU HT<br>(FCFA)</th>
            <th>Montant HT<br>(FCFA)</th>
        </tr>
        @foreach($quote->lines as $line)
            @php
                // `Panel::find()` par ligne (2026-11-16) : conservé pour
                // retrouver l'adresse réelle quand la ligne n'en porte
                // pas. withTrashed() — un panneau démantelé depuis
                // l'émission du devis ne doit pas vider sa désignation.
                $panel  = $line->panel_id
                    ? \App\Models\Panel::withTrashed()->find($line->panel_id)
                    : null;
                $addr   = $panel?->adresse ?: $panel?->quartier ?: $line->designation;
            @endphp
            {{-- Emplacement seul (2026-11-16), comme le modèle officiel. --}}
            <tr class="{{ $loop->odd ? '' : 'alt' }}">
                <td class="i">Emplacement : {{ $addr ?: ($line->snapshot_commune_name ?? '—') }}</td>
                <td class="c">{{ (int) $line->dimension_m2 }}</td>
                <td class="c">{{ $line->snapshot_commune_name ?: '—' }}</td>
                <td class="c">{{ (int) $line->quantite }}</td>
                <td class="c">{{ rtrim(rtrim(number_format((float) $line->duree_mois, 1, ',', ''), '0'), ',') }}</td>
                <td class="r">{{ $fmt($line->pu_ht_mensuel) }}</td>
                <td class="r">{{ $fmt($line->montant_ht_ligne) }}</td>
            </tr>
        @endforeach
        {{-- Barre de sous-total : le cumul des lignes avant taxes.
             2026-11-19 — avec une remise, le cumul des lignes est le HT
             BRUT (`amount`) : la barre affichait le net, et le PDF ne se
             recoupait plus avec les lignes. --}}
        <tr class="bar"><td colspan="5"></td><td colspan="2" class="r">{{ $fmt($aRemise ? $htBrut : $netHt) }}</td></tr>
    </table>

    {{-- ═══ SOUS-TOTAUX ═══ — HT, TVA, TSP, puis le TTC qui les additionne.
         2026-11-19 — Remise : même lecture que la fiche admin et l'espace
         client (HT brut − net HT, deux montants enregistrés), pour que
         HT brut − remise = Montant Total HT. --}}
    <table class="g subtotals">
        {!! $grille !!}
        @if($aRemise)
            <tr><td colspan="5" class="lbl">Montant HT</td><td colspan="2" class="r">{{ $fmt($htBrut) }} FCFA</td></tr>
            <tr><td colspan="5" class="lbl">Remise ({{ $remisePct }} %)</td><td colspan="2" class="r">− {{ $fmt($htBrut - $netHt) }} FCFA</td></tr>
        @endif
        <tr><td colspan="5" class="lbl">Montant Total HT</td><td colspan="2" class="r">{{ $fmt($netHt) }} FCFA</td></tr>
        <tr><td colspan="5" class="lbl">TVA ({{ $tvaRate }}%)</td><td colspan="2" class="r">{{ $fmt($tvaAmount) }} FCFA</td></tr>
        <tr><td colspan="5" class="lbl">TSP ({{ $tspRate }}%)</td><td colspan="2" class="r">{{ $fmt($tspAmount) }} FCFA</td></tr>
        <tr class="ttc"><td colspan="5" class="r">MONTANT TOTAL TTC</td><td colspan="2" class="r">{{ $fmt($totalTtc) }} FCFA</td></tr>
    </table>
    <div class="gap"></div>

    {{-- ═══ TAXES PUBLICITAIRES ═══ --}}
    <table class="g taxes-table keep">
        {!! $grille !!}
        <tr class="sec"><td colspan="7">TAXES PUBLICITAIRES</td></tr>
        {{-- Commune sur deux colonnes de la grille : « PORT-BOUET » ne tenait
             pas dans une seule en police de la charte. --}}
        <tr><th>Type</th><th colspan="2">Commune</th><th colspan="2"></th><th>PU/m²/mois</th><th>Montant</th></tr>
        @forelse($taxesPub as $tax)
            <tr>
                <td>{{ $tax['type'] }}</td>
                <td colspan="2" class="c">{{ $tax['commune'] }}</td>
                <td colspan="2"></td>
                <td class="r">{{ $fmt($tax['rate']) }}</td>
                <td class="r">{{ $fmt($tax['montant']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="c i">Aucune taxe publicitaire applicable</td></tr>
        @endforelse
        {{-- Le libellé est gardé (2026-11-17) : un nombre seul sur la barre
             ne dirait pas de quoi il est le total. --}}
        <tr class="bar"><td colspan="5" class="r">Total Taxes Publicitaires</td><td colspan="2" class="r">{{ $fmt($totalTaxesPub) }} FCFA</td></tr>
    </table>
    <div class="gap"></div>

    {{-- ═══ SERVICES ADDITIONNELS ═══
         2026-11-16 : quantité, TVA des services et total TTC (le net les
         additionne en TTC). 2026-11-17 : la section disparaît quand le devis
         n'a aucun service. --}}
    @if($quote->services->isNotEmpty())
    <table class="g keep">
        {!! $grille !!}
        <tr class="sec"><td colspan="7">SERVICES ADDITIONNELS</td></tr>
        @foreach($quote->services as $svc)
            @php
                $svcQte = max(1, (int) ($svc->quantite ?: 1));
                $svcHt  = $svc->montantHt();
            @endphp
            <tr>
                <td>{{ $svc->label }}@unless($svc->tva_applicable) <span class="svc-ht-flag">(HT strict)</span>@endunless</td>
                <td colspan="4" class="c">{{ $svcQte }}</td>
                <td class="r">{{ $svc->prix_ht > 0 ? $fmt($svc->prix_ht) . ' FCFA' : 'OFFERT' }}</td>
                <td class="r">{{ $svcHt > 0 ? $fmt($svcHt) . ' FCFA' : 'OFFERT' }}</td>
            </tr>
        @endforeach
        {{-- TVA des services : seuls ceux qui y sont soumis comptent. --}}
        @if($servicesTvaAmount > 0)
            <tr><td></td><td colspan="4" class="c">TVA ({{ $tvaRate }}%)</td><td></td><td class="r">{{ $fmt($servicesTvaAmount) }} FCFA</td></tr>
        @endif
        <tr class="lt"><td colspan="5" class="r b">Total Services Additionnels</td><td colspan="2" class="r b">{{ $fmt($servicesTtcTot) }} FCFA</td></tr>
    </table>
    <div class="gap"></div>
    @endif

    {{-- ═══ MONTANT NET TOTAL À PAYER ═══ --}}
    <table class="g keep">
        {!! $grille !!}
        <tr class="net"><td colspan="5" class="r">MONTANT NET TOTAL À PAYER</td><td colspan="2" class="r v">{{ $fmt($totalAPayer) }} FCFA</td></tr>
        <tr><td colspan="7" class="i">Arrêté le présent devis à la somme de : <b>{{ ucfirst($montantEnLettres($totalAPayer)) }} francs</b></td></tr>
    </table>
    <div class="gap"></div>

    {{-- ═══ MODALITÉS DE RÈGLEMENT ═══ — d'un seul tenant. --}}
    <table class="g keep">
        {!! $grille !!}
        <tr class="sec"><td colspan="7">MODALITÉS DE RÈGLEMENT</td></tr>
        <tr class="gr"><td colspan="3" class="b">Moyens de paiement :</td><td colspan="4">Espèces · Chèque · Virement bancaire</td></tr>
        <tr class="gr">
            <td colspan="3" class="b">Conditions de paiement :</td>
            <td colspan="4"><span class="box">☐</span> 100% à la commande &nbsp;&nbsp; <span class="box">☐</span> 70% à la commande + 30% à J-15 avant fin de campagne</td>
        </tr>
        <tr class="gr"><td colspan="3" class="b">Libellé du chèque :</td><td colspan="4">{{ $societe }}</td></tr>
        <tr class="gr"><td colspan="3" class="b">Banque / BIC :</td><td colspan="4">{{ $bank['name'] ?? '' }}@if(!empty($bank['swift'])) &nbsp;·&nbsp; BIC : {{ $bank['swift'] }}@endif</td></tr>
        <tr class="gr"><td colspan="3" class="b">IBAN :</td><td colspan="4">{{ $bank['iban'] ?? '' }}</td></tr>
    </table>
    <div class="gap"></div>

    {{-- ═══ BON POUR ACCORD (page 1) ═══ — d'un seul tenant. --}}
    <table class="g keep">
        {!! $grille !!}
        <tr class="sec"><td colspan="7">BON POUR ACCORD — Lu et approuvé</td></tr>
        <tr class="sig">
            <td colspan="3"><b>Pour {{ $societe }}</b><br><b>Directrice Commerciale</b><div class="trait">_____________________________</div></td>
            <td colspan="4"><b>Pour l'Annonceur / Mandataire</b><br><b>Nom &amp; Fonction :</b><div class="trait">_____________________________</div></td>
        </tr>
    </table>
    <div class="bandeau-bas"></div>
    <div class="gap"></div>

    {{-- ═══ CONDITIONS GÉNÉRALES ═══
         ⚠ PAS de saut de page forcé (17/11, confirmé le 2026-11-19) : comme
         dans le modèle Excel, les conditions commencent en page 1 et
         continuent en page 2. Chaque article est une table d'un seul tenant :
         il passe en entier sur la page suivante plutôt que d'être coupé. --}}
    <table class="g cg keep">
        <tr class="sec"><td>{{ mb_strtoupper($societe) }} — CONDITIONS GÉNÉRALES <span class="cg-site">www.cible-ci.com</span></td></tr>
        <tr class="filet-cg"><td>&nbsp;</td></tr>
    </table>

    <table class="g cg keep">
        <tr class="art"><td>1. VALIDITÉ DU DEVIS</td></tr>
        <tr><td style="background:{{ $fondsCg[0] }}">
            Le présent devis est valable <b>{{ $quote->valid_days ?? 30 }} jours calendaires</b>
            à compter de sa date d'émission, sous réserve de la disponibilité des emplacements à la date
            de signature des deux parties. Passé ce délai, {{ $societe }} se réserve le droit de modifier
            les tarifs et/ou les disponibilités sans préavis.
            <b>La commande n'est confirmée qu'après réception du présent devis signé, cacheté par
            l'annonceur/mandataire, et accompagné du règlement correspondant aux conditions définies
            ci-après.</b>
        </td></tr>
    </table>

    <table class="g cg keep">
        <tr class="art"><td>2. CONDITIONS DE PAIEMENT</td></tr>
        <tr><td style="background:{{ $fondsCg[1] }}">
            Sauf conditions particulières expressément mentionnées sur ce devis, le règlement s'effectue
            selon l'une des modalités suivantes :
            <ul>
                <li><b>Option A</b> — 100 % du montant TTC à la signature du devis / bon de commande (paiement intégral à la commande) ;</li>
                <li><b>Option B</b> — 70 % du montant TTC à la signature, solde de 30 % au plus tard quinze (15) jours avant la date de fin de campagne.</li>
            </ul>
            La modalité applicable est cochée sur la page 1. {{ $societe }} se réserve le droit de
            suspendre toute prestation en cas de non-respect des délais de paiement, sans que cela ne
            constitue un manquement contractuel de sa part.
        </td></tr>
    </table>

    {{-- Article 3 : fond jaune, sans gras ; les trois retenues alignées en
         colonnes dans une petite table sans bordure (cahier lot B, B5). --}}
    <table class="g cg keep">
        <tr class="art"><td>3. ANNULATION, REPORT ET NON-REMBOURSEMENT</td></tr>
        <tr><td style="background:{{ $fondsCg[2] }}">
            Tout acompte ou paiement versé est NON REMBOURSABLE en cas d'annulation ou de report de
            campagne, quelle qu'en soit la cause, à l'exception des cas de force majeure dûment reconnus
            (voir article 4).<br>
            En cas d'annulation confirmée par écrit, les retenues suivantes s'appliquent sur le montant total TTC :
            <table class="ret">
                <tr><td style="width:3%" class="c">•</td><td style="width:44%">Annulation avant J-30 du démarrage</td><td style="width:4%" class="c"><span class="box">→</span></td><td style="width:9%">retenue de</td><td style="width:6%" class="r">30 %</td><td style="width:34%"></td></tr>
                <tr><td class="c">•</td><td>Annulation entre J-30 et J-15 du démarrage</td><td class="c"><span class="box">→</span></td><td>retenue de</td><td class="r">50 %</td><td></td></tr>
                <tr><td class="c">•</td><td>Annulation à moins de J-15 ou en cours</td><td class="c"><span class="box">→</span></td><td>retenue de</td><td class="r">100 %</td><td></td></tr>
            </table>
            Un AVOIR sera émis pour le montant versé diminué de la retenue applicable. Cet avoir est
            valable à compter de sa date d'émission sur l'exercice comptable en cours, utilisable
            exclusivement pour toute prestation {{ $societe }}, non remboursable et non cessible.
        </td></tr>
    </table>

    <table class="g cg keep">
        <tr class="art"><td>4. CAS DE FORCE MAJEURE</td></tr>
        <tr><td style="background:{{ $fondsCg[3] }}">
            Sont considérés comme cas de force majeure tout événement imprévisible, irrésistible et
            extérieur aux parties : catastrophe naturelle, acte de guerre, émeute, décision
            gouvernementale rendant impossible l'exécution des prestations. En cas de force majeure dûment
            prouvée et notifiée par écrit dans les 72 heures, {{ $societe }} émettra un avoir sans retenue
            dans les conditions de l'article 3.
        </td></tr>
    </table>

    <table class="g cg keep">
        <tr class="art"><td>5. PRODUCTION ET LIVRAISON DES VISUELS</td></tr>
        <tr><td style="background:{{ $fondsCg[4] }}">
            Les frais d'impression, de pose et de dépose des visuels sont à la charge de
            l'annonceur/mandataire, sauf mention contraire expressément indiquée sur ce devis. Les visuels
            doivent être livrés en haute définition au moins cinq (5) jours ouvrables avant le démarrage
            de la campagne. Tout retard de livraison imputable à l'annonceur ne donnera droit à aucun
            report, prolongation, ni remboursement.
        </td></tr>
    </table>

    <table class="g cg keep">
        <tr class="art"><td>6. TAXES, IMPÔTS ET REDEVANCES</td></tr>
        <tr><td style="background:{{ $fondsCg[5] }}">
            Tous les montants sont exprimés en FCFA Hors Taxes. La <b>TVA ({{ $tvaRate }} %)</b>
            et la <b>TSP ({{ $tspRate }} %)</b> sont appliquées conformément à la législation
            fiscale de la République de Côte d'Ivoire. Les taxes publicitaires municipales
            (<b>ODP</b> et <b>Taxe Municipale</b>) sont facturées séparément selon la
            commune d'implantation du panneau. Toute modification législative entraînant une variation
            de ces taux sera répercutée de plein droit sur les montants facturés.
        </td></tr>
    </table>

    <table class="g cg keep">
        <tr class="art"><td>7. DROITS DE TIMBRE</td></tr>
        <tr><td style="background:{{ $fondsCg[6] }}">
            Pour tout règlement en espèces, des droits de timbre s'appliquent conformément à la
            réglementation fiscale ivoirienne :
            De 0 à 5 000 FCFA : 0 FCFA · De 5 001 à 100 000 FCFA : 100 FCFA ·
            De 100 001 à 500 000 FCFA : 500 FCFA · De 500 001 à 1 000 000 FCFA : 1 000 FCFA ·
            De 1 000 001 à 5 000 000 FCFA : 2 000 FCFA · Au-delà de 5 000 000 FCFA : 5 000 FCFA.
        </td></tr>
    </table>

    <table class="g cg keep">
        <tr class="art"><td>8. DROIT APPLICABLE ET RÈGLEMENT DES LITIGES</td></tr>
        <tr><td style="background:{{ $fondsCg[7] }}">
            Le présent devis/contrat est régi par le <b>droit ivoirien</b>. En cas de litige
            relatif à son interprétation, son exécution ou sa résiliation, les parties s'engagent à
            rechercher une solution amiable dans les trente (30) jours suivant la notification écrite du
            différend. À défaut d'accord amiable dans ce délai, tout litige sera soumis à la
            <b>compétence exclusive du Tribunal de Commerce d'Abidjan</b>.
        </td></tr>
    </table>
    <div class="gap"></div>

    {{-- ═══ BON POUR ACCORD (fin des conditions) ═══ — d'un seul tenant. --}}
    <table class="g keep">
        {!! $grille !!}
        <tr class="sec"><td colspan="7">BON POUR ACCORD — Lu et approuvé, bon pour accord</td></tr>
        <tr class="sig">
            <td colspan="3"><b>Pour {{ $societe }}</b><br><b>Directrice Commerciale</b><div class="trait">_____________________________</div></td>
            <td colspan="4"><b>Pour l'Annonceur / Mandataire</b><br><b>Nom &amp; Fonction :</b><div class="trait">_____________________________</div></td>
        </tr>
    </table>

    {{-- ═══ PIED FIXE ═══ (05/11 : le devis garde son pied ; une seule
         ligne depuis le 2026-11-19). Mention légale du modèle officiel. --}}
    @include('pdf.partials.charte-footer-devis', [
        'footerHint' => implode('  |  ', array_filter([
            $societe . ' — Capital : 10 000 000 FCFA',
            $company['address'] ?? null,
            !empty($company['phone'])  ? 'Tél. ' . $company['phone']  : null,
            !empty($company['mobile']) ? 'Mob. ' . $company['mobile'] : null,
            $company['email'] ?? null,
        ])),
    ])
</body>
</html>
