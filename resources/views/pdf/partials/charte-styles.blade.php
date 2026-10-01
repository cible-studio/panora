{{-- ═══════════════════════════════════════════════════════════════════
     CHARTE GRAPHIQUE DES PDF — source unique de l'habillage (2026-10-01)

     À inclure dans le <head> de CHAQUE vue PDF, AVANT ses styles propres :
         @include('pdf.partials.charte-styles')

     Lit $charte (injecté par le view composer d'AppServiceProvider, cf.
     App\Support\PdfCharte — lui-même alimenté par config/charte.php).
     Repli sur PdfCharte::data() si la vue n'est pas couverte par le composer.

     Palette verrouillée (aucune autre couleur) :
       rouge  — accent principal, titres de section, en retard / occupé / échec
       jaune  — mises en avant, option / en attente
       vert   — libre, payé, réussi
       bleu   — information
       violet — détails
       gris   — fonds de lignes alternées, bordures
       noir   — texte, en-têtes de tableaux
       blanc
     + teintes dérivées (couleur fondue sur blanc) : *_clair, gris_clair,
       texte_doux, texte_pale.

     Dans les vues : utiliser les classes ch-* ci-dessous, ou
     {{ $charte['rouge'] }} dans un style propre — JAMAIS un hex en dur.

     Contraintes DomPDF : pas de flex/grid ; tables et blocs uniquement.
     ⚠ Marges de page : DomPDF applique la règle « * { margin:0 } » à la
       boîte de page et annule @page { margin } — déclarer les marges avec
       !important :  @page { margin: 12mm 12mm 20mm 12mm !important; }
     ⚠ Poids de police : DomPDF associe un fichier à un poids EXACT ; les
       poids 300 à 900 sont déclarés (Poppins, Nunito, DejaVu Sans / Mono).
       Un poids hors liste retombe sur Times.
     ═══════════════════════════════════════════════════════════════════ --}}
@php($ch = $charte ?? \App\Support\PdfCharte::data())
<style>
{!! $ch['font_face_css'] !!}
    body { font-family: {!! $ch['ff_texte'] !!}; color: {{ $ch['noir'] }}; background: {{ $ch['blanc'] }}; }

    /* ── Typographie ─────────────────────────────────────────────── */
    .ch-titres, .ch-title, .ch-section, .ch-kicker, .ch-kpi-value { font-family: {!! $ch['ff_titres'] !!}; }
    .ch-mono   { font-family: {!! $ch['ff_mono'] !!}; }
    .ch-muted  { color: {{ $ch['texte_doux'] }}; }
    .ch-pale   { color: {{ $ch['texte_pale'] }}; }
    .ch-strong { color: {{ $ch['noir'] }}; font-weight: 700; }
    .ch-right  { text-align: right; }
    .ch-center { text-align: center; }

    /* Couleurs utilitaires (texte) */
    .c-rouge  { color: {{ $ch['rouge'] }}; }
    .c-jaune  { color: {{ $ch['jaune'] }}; }
    .c-vert   { color: {{ $ch['vert'] }}; }
    .c-bleu   { color: {{ $ch['bleu'] }}; }
    .c-violet { color: {{ $ch['violet'] }}; }
    .c-noir   { color: {{ $ch['noir'] }}; }
    .c-doux   { color: {{ $ch['texte_doux'] }}; }

    /* ── Liseré 5 couleurs (signature de la charte) ──────────────── */
    .ch-lisere { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; }
    .ch-lisere td { height: 4px; padding: 0; font-size: 0; line-height: 0; border: none; }

    /* ── En-tête de document ─────────────────────────────────────── */
    .ch-head { width: 100%; border-collapse: collapse; margin: 0 0 12px; }
    .ch-head td { vertical-align: middle; padding: 10px 0 8px; border: none; }
    .ch-head .ch-head-logo { width: 1%; padding-right: 14px; white-space: nowrap; }
    .ch-head .ch-head-logo img { height: 34px; display: block; }
    .ch-head .ch-head-meta { width: 1%; padding-left: 14px; text-align: right; font-size: 8px; color: {{ $ch['texte_doux'] }}; line-height: 1.55; white-space: nowrap; }
    .ch-head .ch-head-meta strong { color: {{ $ch['noir'] }}; }
    .ch-kicker { font-size: 7.5px; font-weight: 700; color: {{ $ch['rouge'] }}; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 2px; }
    .ch-title  { font-size: 17px; font-weight: 700; color: {{ $ch['noir'] }}; line-height: 1.2; }
    .ch-subtitle { font-size: 8.5px; color: {{ $ch['texte_doux'] }}; margin-top: 2px; }
    .ch-head-rule { height: 2px; background: {{ $ch['noir'] }}; margin: 0 0 14px; font-size: 0; line-height: 0; }

    /* ── Titres de section ───────────────────────────────────────── */
    .ch-section {
        font-size: 11px; font-weight: 700; color: {{ $ch['noir'] }};
        padding: 0 0 4px 8px; margin: 16px 0 8px;
        border-left: 3px solid {{ $ch['rouge'] }};
        border-bottom: 1px solid {{ $ch['gris'] }};
    }

    /* ── Bandeau d'informations (filtres, période…) ──────────────── */
    .ch-info { background: {{ $ch['gris_clair'] }}; border-left: 3px solid {{ $ch['rouge'] }}; padding: 7px 11px; font-size: 8.5px; color: {{ $ch['noir'] }}; margin-bottom: 12px; }
    .ch-info strong { color: {{ $ch['noir'] }}; }

    /* ── Tableaux ────────────────────────────────────────────────── */
    .ch-table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
    .ch-table thead th, .ch-table th.ch-th {
        background: {{ $ch['noir'] }}; color: {{ $ch['blanc'] }};
        font-family: {!! $ch['ff_titres'] !!}; font-weight: 600;
        font-size: 7.5px; text-transform: uppercase; letter-spacing: .4px;
        padding: 7px 8px; text-align: left; border: none;
    }
    .ch-table tbody td { padding: 6px 8px; border-bottom: 1px solid {{ $ch['gris'] }}; vertical-align: middle; }
    .ch-table tbody tr:nth-child(even) td { background: {{ $ch['gris_clair'] }}; }
    .ch-table .ch-right, .ch-table .right, .ch-table .num { text-align: right; }
    .ch-table tr.ch-total td {
        background: {{ $ch['noir'] }}; color: {{ $ch['blanc'] }};
        font-weight: 700; border-bottom: none;
    }
    .ch-table tr.ch-total td .ch-total-amount, .ch-table tr.ch-total td.ch-total-amount { color: {{ $ch['jaune'] }}; }
    .ch-table tr.ch-group td {
        background: {{ $ch['gris'] }}; color: {{ $ch['noir'] }};
        font-weight: 700; border-left: 3px solid {{ $ch['rouge'] }};
    }
    .ch-ref { color: {{ $ch['rouge'] }}; font-weight: 700; }

    /* ── Badges de statut ────────────────────────────────────────── */
    .ch-badge {
        display: inline-block; padding: 2px 7px; border-radius: 3px;
        font-size: 7.5px; font-weight: 700; letter-spacing: .3px; line-height: 1.3;
        white-space: nowrap;
    }
    .ch-badge-rouge  { background: {{ $ch['rouge'] }};  color: {{ $ch['blanc'] }}; }
    .ch-badge-jaune  { background: {{ $ch['jaune'] }};  color: {{ $ch['noir'] }}; }
    .ch-badge-vert   { background: {{ $ch['vert'] }};   color: {{ $ch['blanc'] }}; }
    .ch-badge-bleu   { background: {{ $ch['bleu'] }};   color: {{ $ch['blanc'] }}; }
    .ch-badge-violet { background: {{ $ch['violet'] }}; color: {{ $ch['blanc'] }}; }
    .ch-badge-gris   { background: {{ $ch['gris'] }};   color: {{ $ch['noir'] }}; }
    .ch-badge-noir   { background: {{ $ch['noir'] }};   color: {{ $ch['blanc'] }}; }

    /* ── Cartes KPI ──────────────────────────────────────────────── */
    .ch-kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 0 12px; }
    .ch-kpi { background: {{ $ch['gris_clair'] }}; border-top: 3px solid {{ $ch['rouge'] }}; padding: 8px 10px; vertical-align: top; }
    .ch-kpi-label { font-size: 7px; font-weight: 700; color: {{ $ch['texte_doux'] }}; text-transform: uppercase; letter-spacing: .5px; }
    .ch-kpi-value { font-size: 15px; font-weight: 700; color: {{ $ch['noir'] }}; margin-top: 3px; }
    .ch-kpi-sub   { font-size: 7.5px; color: {{ $ch['texte_doux'] }}; margin-top: 2px; }
    .ch-kpi.k-rouge  { border-top-color: {{ $ch['rouge'] }}; }
    .ch-kpi.k-jaune  { border-top-color: {{ $ch['jaune'] }}; }
    .ch-kpi.k-vert   { border-top-color: {{ $ch['vert'] }}; }
    .ch-kpi.k-bleu   { border-top-color: {{ $ch['bleu'] }}; }
    .ch-kpi.k-violet { border-top-color: {{ $ch['violet'] }}; }
    .ch-kpi.k-noir   { border-top-color: {{ $ch['noir'] }}; }

    /* ── Encadrés (notes, alertes) ───────────────────────────────── */
    .ch-note { padding: 8px 11px; font-size: 8px; line-height: 1.55; border-left: 3px solid {{ $ch['bleu'] }}; background: {{ $ch['bleu_clair'] }}; color: {{ $ch['noir'] }}; margin: 8px 0; }
    .ch-note-rouge  { border-left-color: {{ $ch['rouge'] }};  background: {{ $ch['rouge_clair'] }}; }
    .ch-note-jaune  { border-left-color: {{ $ch['jaune'] }};  background: {{ $ch['jaune_clair'] }}; }
    .ch-note-vert   { border-left-color: {{ $ch['vert'] }};   background: {{ $ch['vert_clair'] }}; }
    .ch-note-violet { border-left-color: {{ $ch['violet'] }}; background: {{ $ch['violet_clair'] }}; }
    .ch-note-gris   { border-left-color: {{ $ch['noir'] }};   background: {{ $ch['gris_clair'] }}; }

    /* ── Pied de page fixe (cf. pdf.partials.charte-footer) ──────── */
    .ch-footer { position: fixed; left: 0; right: 0; bottom: -9mm; font-size: 7px; color: {{ $ch['texte_doux'] }}; }
    .ch-footer table { width: 100%; border-collapse: collapse; }
    .ch-footer td { padding: 4px 0 0; border: none; vertical-align: top; border-top: 1px solid {{ $ch['gris'] }}; }
    .ch-footer .ch-footer-brand { color: {{ $ch['noir'] }}; font-weight: 700; }
    .ch-footer .ch-footer-slogan { color: {{ $ch['rouge'] }}; font-weight: 700; }
    .ch-footer .ch-footer-page { text-align: right; white-space: nowrap; width: 18%; }
    .ch-pagenum:before { content: counter(page); }
</style>
