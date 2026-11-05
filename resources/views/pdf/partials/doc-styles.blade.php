{{-- ═══════════════════════════════════════════════════════════════════
     MISE EN PAGE ÉDITORIALE des documents internes (2026-11-05)

     Retour utilisateur : « ces cases font trop IA », logo et titre mal
     alignés — « donne le résultat d'un expert ». À inclure APRÈS
     pdf.partials.charte-styles, avec l'en-tête pdf.partials.doc-entete :

         @include('pdf.partials.charte-styles')
         @include('pdf.partials.doc-styles')

     Surcharge, dans le document qui l'inclut seulement, les classes
     communes de la charte :
       · cartes de chiffres (.ch-kpi)  → bande de chiffres : filets, pas de fond ;
       · tableaux (.ch-table)          → filets fins, plus de bandeau noir ni de zébrures ;
       · badges (.ch-badge)            → pastille de couleur + texte ;
       · intertitres, bandeaux d'info, encadrés → sobres.
     Les documents clients (proposition, disponibilités, catalogue) et les
     documents fiscaux (devis, facture) ne l'incluent pas : inchangés.
     Couleurs et polices : la charte ($charte), jamais un hex en dur.
     ═══════════════════════════════════════════════════════════════════ --}}
@php($chD = $charte ?? \App\Support\PdfCharte::data())
<style>
    /* ── En-tête (cf. pdf.partials.doc-entete) ───────────────────── */
    .ch-lisere td { height: 2px; }
    .doc-tete { width: 100%; border-collapse: collapse; margin: 5mm 0 0; }
    .doc-tete td { padding: 0; border: none; vertical-align: middle; }
    .doc-tete .doc-logo img { height: 12mm; display: block; }
    .doc-tete .doc-societe { text-align: right; font-size: 7.5pt; line-height: 1.55; color: {{ $chD['texte_doux'] }}; }
    .doc-tete .doc-societe strong { color: {{ $chD['noir'] }}; font-family: {!! $chD['ff_titres'] !!}; font-weight: 700; }
    .doc-filet { height: 0; border-top: 0.8pt solid {{ $chD['noir'] }}; margin: 4mm 0 6mm; font-size: 0; line-height: 0; }
    .doc-type  { font-family: {!! $chD['ff_titres'] !!}; font-size: 7.5pt; font-weight: 700; letter-spacing: 1.8pt; text-transform: uppercase; color: {{ $chD['rouge'] }}; }
    .doc-titre { font-family: {!! $chD['ff_titres'] !!}; font-size: 18pt; font-weight: 700; line-height: 1.2; color: {{ $chD['noir'] }}; margin: 1.5mm 0 1.5mm; }
    .doc-soustitre, .doc-resume { font-size: 9.5pt; line-height: 1.4; color: {{ $chD['texte_doux'] }}; }
    .doc-soustitre strong, .doc-resume strong { color: {{ $chD['noir'] }}; font-weight: 700; }
    .doc-meta { font-size: 8pt; color: {{ $chD['texte_doux'] }}; margin-top: 1mm; }
    .doc-fin-entete { height: 6mm; font-size: 0; line-height: 0; }

    /* ── Intertitres ─────────────────────────────────────────────── */
    .ch-section {
        font-family: {!! $chD['ff_titres'] !!}; font-size: 8.5pt; font-weight: 700; letter-spacing: 1.2pt; text-transform: uppercase;
        color: {{ $chD['noir'] }}; border-left: none; border-bottom: 0.8pt solid {{ $chD['noir'] }};
        padding: 0 0 1.5mm; margin: 7mm 0 3mm;
    }

    /* ── Bandeau d'informations : une ligne de texte, sans fond ──── */
    .ch-info { background: none; border-left: none; padding: 0; margin: 0 0 5mm; font-size: 8.5pt; color: {{ $chD['texte_doux'] }}; }
    .ch-info strong { color: {{ $chD['noir'] }}; }

    /* ── Tableaux : filets fins ──────────────────────────────────── */
    .ch-table thead th, .ch-table th.ch-th {
        background: none; color: {{ $chD['texte_doux'] }};
        font-family: {!! $chD['ff_titres'] !!}; font-weight: 700; font-size: 6.8pt; letter-spacing: .8pt;
        padding: 0 7px 5px; border-bottom: 0.9pt solid {{ $chD['noir'] }};
    }
    .ch-table tbody td { border-bottom: 0.4pt solid {{ $chD['gris'] }}; }
    .ch-table tbody tr:nth-child(even) td { background: none; }
    .ch-table tr.ch-total td {
        background: none; color: {{ $chD['noir'] }}; font-weight: 700;
        border-top: 0.9pt solid {{ $chD['noir'] }}; border-bottom: none;
    }
    .ch-table tr.ch-total td .ch-total-amount, .ch-table tr.ch-total td.ch-total-amount { color: {{ $chD['noir'] }}; }
    .ch-table tr.ch-group td {
        background: none; color: {{ $chD['noir'] }}; border-left: none;
        font-family: {!! $chD['ff_titres'] !!}; font-size: 7.5pt; letter-spacing: 1pt; text-transform: uppercase;
        padding-top: 9px; border-bottom: 0.6pt solid {{ $chD['noir'] }};
    }
    .ch-ref { color: {{ $chD['noir'] }}; }

    /* ── Statuts : pastille de couleur + texte (plus de badges pleins) ── */
    .ch-badge { background: none !important; color: {{ $chD['noir'] }} !important; padding: 0; border-radius: 0; font-weight: 400; letter-spacing: 0; font-size: inherit; line-height: inherit; }
    .ch-badge:before { content: "\25CF\00A0"; font-family: 'DejaVu Sans', sans-serif; font-size: 7pt; }
    .ch-badge-rouge:before  { color: {{ $chD['rouge'] }}; }
    .ch-badge-jaune:before  { color: {{ $chD['jaune'] }}; }
    .ch-badge-vert:before   { color: {{ $chD['vert'] }}; }
    .ch-badge-bleu:before   { color: {{ $chD['bleu'] }}; }
    .ch-badge-violet:before { color: {{ $chD['violet'] }}; }
    .ch-badge-gris:before   { color: {{ $chD['texte_pale'] }}; }
    .ch-badge-noir:before   { color: {{ $chD['noir'] }}; }

    /* ── Chiffres clés : une bande entre deux filets, sans cases ──── */
    .ch-kpis { border-collapse: collapse; border-spacing: 0; margin: 0 0 6mm; border-top: 0.8pt solid {{ $chD['noir'] }}; border-bottom: 0.4pt solid {{ $chD['gris'] }}; }
    .ch-kpi, .ch-kpi.k-rouge, .ch-kpi.k-jaune, .ch-kpi.k-vert, .ch-kpi.k-bleu, .ch-kpi.k-violet, .ch-kpi.k-noir {
        background: none; border-top: none; border-left: 0.4pt solid {{ $chD['gris'] }}; padding: 3mm 4mm;
    }
    .ch-kpis td.ch-kpi:first-child { border-left: none; padding-left: 0; }
    .ch-kpi-label { font-family: {!! $chD['ff_titres'] !!}; font-size: 6.5pt; letter-spacing: .8pt; color: {{ $chD['texte_doux'] }}; }
    .ch-kpi-value { font-family: {!! $chD['ff_titres'] !!}; font-size: 14pt; color: {{ $chD['noir'] }}; margin-top: 1.5mm; }
    .ch-kpi-sub   { font-size: 7.5pt; color: {{ $chD['texte_doux'] }}; margin-top: 1mm; }

    /* ── Encadrés : un filet à gauche, sans fond ─────────────────── */
    .ch-note, .ch-note-rouge, .ch-note-jaune, .ch-note-vert, .ch-note-violet, .ch-note-gris {
        background: none; border-left-width: 0.8pt; padding: 1mm 0 1mm 3mm;
    }

    /* ── Un intertitre ne reste jamais seul en bas de page ────────── */
    h2, h3, .ch-section { page-break-after: avoid; }
</style>
