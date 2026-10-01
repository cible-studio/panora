{{-- ═══════════════════════════════════════════════════════════════════
     Identité visuelle des PDF de TAXES COMMUNALES — source unique.
     Partagée par : pdf/taxes-details.blade.php et pdf/taxes-report.blade.php

     Refonte 2026-09-02 (maquette validée par le user) : barre de marque
     claire + bloc méta horizontal + tableau à en-tête foncé.
     2026-10-01 — passage à la charte graphique (config/charte.php) : toutes
     les couleurs viennent de $charte (cf. pdf.partials.charte-styles, inclus
     ci-dessous) ; liseré 5 couleurs et pied de page communs à tous les PDF.

     ⚠️ Toute modification ici impacte LES DEUX PDF — c'est voulu
     (règle n°1 CLAUDE.md : une seule source de vérité pour le style).

     Les deux vues déclarent les MÊMES marges @page (avec !important : la
     remise à zéro « * { margin:0 } » ci-dessous écrase sinon les marges de
     page sous DomPDF), seule l'orientation change.
     ═══════════════════════════════════════════════════════════════════ --}}
@include('pdf.partials.charte-styles')
@php($chT = $charte ?? \App\Support\PdfCharte::data())
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:{!! $chT['ff_texte'] !!}; font-size:9px; color:{{ $chT['noir'] }}; }

    /* ── Barre de marque (fond CLAIR) ────────────────────────────────
       Le logo doit être la version « light » (logol.png, texte noir) :
       le logoCibleDark est en texte blanc et serait invisible ici. */
    .tdoc-top { width:100%; border-collapse:collapse; margin-top:8px; }
    .tdoc-top td { vertical-align:bottom; padding:0 0 7px; }
    .tdoc-logo { height:30px; display:block; margin-bottom:6px; }
    .tdoc-title { font-family:{!! $chT['ff_titres'] !!}; font-size:13px; font-weight:700; color:{{ $chT['noir'] }}; letter-spacing:.2px; }
    .tdoc-top-right { text-align:right; font-size:8.5px; color:{{ $chT['texte_doux'] }}; }
    .tdoc-rule { height:2px; background:{{ $chT['noir'] }}; margin-bottom:15px; }

    /* ── Bloc méta horizontal ───────────────────────────────────────── */
    .tdoc-meta { width:100%; border-collapse:collapse; margin-bottom:17px; border:1px solid {{ $chT['gris'] }}; }
    .tdoc-meta th {
        background:{{ $chT['gris_clair'] }}; color:{{ $chT['texte_doux'] }};
        font-size:7.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
        text-align:left; padding:7px 12px;
        border-bottom:1px solid {{ $chT['gris'] }}; border-right:1px solid {{ $chT['gris'] }};
    }
    .tdoc-meta td {
        padding:10px 12px; font-size:11px; font-weight:700; color:{{ $chT['noir'] }};
        border-right:1px solid {{ $chT['gris'] }}; vertical-align:middle;
    }
    .tdoc-meta th:last-child, .tdoc-meta td:last-child { border-right:none; }
    .tdoc-meta td.accent { color:{{ $chT['rouge'] }}; }
    .tdoc-meta td.small  { font-size:8.5px; font-weight:700; color:{{ $chT['texte_doux'] }}; }

    /* ── Tableau principal ────────────────────────────────────────── */
    /* Légèrement encadré (94 %) pour reprendre le rythme de la maquette validée. */
    .tdoc-table { width:94%; margin:0 auto; border-collapse:collapse; font-size:8.5px; }
    .tdoc-table thead th {
        background:{{ $chT['noir'] }}; color:{{ $chT['blanc'] }};
        font-family:{!! $chT['ff_titres'] !!};
        padding:8px 8px; text-align:left;
        font-size:7.5px; font-weight:600; text-transform:uppercase; letter-spacing:.4px;
    }
    /* line-height réduit : Nunito est plus haute que DejaVu sous DomPDF. */
    .tdoc-table tbody td { padding:6px 8px; line-height:1.1; border-bottom:1px solid {{ $chT['gris'] }}; vertical-align:middle; }
    .tdoc-table tbody tr:nth-child(even) td { background:{{ $chT['gris_clair'] }}; }
    .tdoc-table .right { text-align:right; }
    .tdoc-table .mono  { font-family:{!! $chT['ff_mono'] !!}; }
    .tdoc-ref { color:{{ $chT['rouge'] }}; font-weight:700; }
    .tdoc-muted { color:{{ $chT['texte_doux'] }}; }
    .tdoc-empty { text-align:center; padding:22px; color:{{ $chT['texte_pale'] }}; }

    /* Séparateur de groupe commune (multi-communes uniquement) */
    .tdoc-group td {
        background:{{ $chT['gris'] }} !important; color:{{ $chT['noir'] }};
        font-family:{!! $chT['ff_titres'] !!};
        font-weight:700; font-size:8.5px; letter-spacing:.5px;
        padding:6px 8px !important; border-left:3px solid {{ $chT['rouge'] }};
    }

    /* ── Badges nature / statut (couleurs de la charte) ────────────── */
    .tdoc-badge {
        display:inline-block; padding:2px 7px; border-radius:3px;
        font-size:7.5px; font-weight:700; letter-spacing:.3px;
    }
    .tdoc-badge-tm     { background:{{ $chT['bleu'] }};   color:{{ $chT['blanc'] }}; }
    .tdoc-badge-odp    { background:{{ $chT['violet'] }}; color:{{ $chT['blanc'] }}; }
    .tdoc-badge-db     { background:{{ $chT['gris'] }};   color:{{ $chT['noir'] }}; }
    .tdoc-badge-green  { background:{{ $chT['vert'] }};   color:{{ $chT['blanc'] }}; }
    .tdoc-badge-orange { background:{{ $chT['jaune'] }};  color:{{ $chT['noir'] }}; }
    .tdoc-badge-red    { background:{{ $chT['rouge'] }};  color:{{ $chT['blanc'] }}; }

    /* ── Ligne de total ─────────────────────────────────────────────
       Montant mis en avant en jaune sur deux lignes (valeur / devise). */
    .tdoc-total td {
        background:{{ $chT['noir'] }} !important; color:{{ $chT['blanc'] }};
        font-family:{!! $chT['ff_titres'] !!};
        font-weight:700; font-size:11px; padding:12px 8px; border-bottom:none;
    }
    .tdoc-total .tdoc-amount {
        color:{{ $chT['jaune'] }}; font-size:12px; line-height:1.15; text-align:right; white-space:nowrap;
    }
    .tdoc-total .tdoc-amount span { display:block; font-size:11px; }

    /* ── Note explicative de bas de document ────────────────────────── */
    .tdoc-note {
        margin-top:16px; padding:11px 14px;
        background:{{ $chT['gris_clair'] }}; border-left:3px solid {{ $chT['rouge'] }};
        font-size:8px; color:{{ $chT['noir'] }}; line-height:1.55;
    }
    .tdoc-note b { color:{{ $chT['noir'] }}; }
</style>
