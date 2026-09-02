{{-- ═══════════════════════════════════════════════════════════════════
     Identité visuelle des PDF de TAXES COMMUNALES — source unique.
     Partagée par : pdf/taxes-details.blade.php et pdf/taxes-report.blade.php

     Refonte 2026-09-02 (maquette validée par le user) : on abandonne le
     gros bandeau noir au profit d'une barre de marque claire + un bloc
     méta horizontal + un tableau bleu nuit. Plus sobre, plus « document
     administratif », meilleure lisibilité à l'impression N&B.

     ⚠️ Toute modification ici impacte LES DEUX PDF — c'est voulu
     (règle n°1 CLAUDE.md : une seule source de vérité pour le style).

     Les deux vues doivent déclarer les MÊMES marges @page
     (14mm 14mm 18mm 14mm), seule l'orientation change : les offsets du
     footer fixe ci-dessous en dépendent.
     ═══════════════════════════════════════════════════════════════════ --}}
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'DejaVu Sans', sans-serif; font-size:9px; color:#1f2937; }

    /* ── Barre de marque (fond CLAIR) ────────────────────────────────
       Le logo doit être la version « light » (logol.png, texte noir) :
       le logoCibleDark est en texte blanc et serait invisible ici. */
    .tdoc-top { width:100%; border-collapse:collapse; }
    .tdoc-top td { vertical-align:bottom; padding:0 0 7px; }
    .tdoc-logo { height:26px; display:block; margin-bottom:4px; }
    .tdoc-title { font-size:11px; font-weight:800; color:#e8a020; letter-spacing:.2px; }
    .tdoc-top-right { text-align:right; font-size:8.5px; color:#6b7280; }
    .tdoc-rule { height:3px; background:#1e2a44; margin-bottom:15px; }

    /* ── Bloc méta horizontal (remplace la fiche verticale) ────────── */
    .tdoc-meta { width:100%; border-collapse:collapse; margin-bottom:17px; border:1px solid #e5e7eb; }
    .tdoc-meta th {
        background:#f8fafc; color:#8b94a5;
        font-size:7.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
        text-align:left; padding:7px 12px;
        border-bottom:1px solid #e5e7eb; border-right:1px solid #eef1f5;
    }
    .tdoc-meta td {
        padding:10px 12px; font-size:11px; font-weight:700; color:#111827;
        border-right:1px solid #eef1f5; vertical-align:middle;
    }
    .tdoc-meta th:last-child, .tdoc-meta td:last-child { border-right:none; }
    .tdoc-meta td.accent { color:#e8a020; }
    .tdoc-meta td.small  { font-size:8.5px; font-weight:600; color:#4b5563; }

    /* ── Tableau principal ──────────────────────────────────────────
       Légèrement encadré (94%) pour reprendre le rythme de la maquette. */
    .tdoc-table { width:94%; margin:0 auto; border-collapse:collapse; font-size:8.5px; }
    .tdoc-table thead th {
        background:#1e2a44; color:#ffffff;
        padding:9px 8px; text-align:left;
        font-size:7.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
    }
    .tdoc-table tbody td { padding:9px 8px; border-bottom:1px solid #eef1f5; vertical-align:middle; }
    .tdoc-table tbody tr:nth-child(even) td { background:#fafbfc; }
    .tdoc-table .right { text-align:right; }
    .tdoc-table .mono  { font-family:'DejaVu Sans Mono', monospace; }
    .tdoc-ref { color:#e8a020; font-weight:800; }
    .tdoc-muted { color:#6b7280; }
    .tdoc-empty { text-align:center; padding:22px; color:#9ca3af; }

    /* Séparateur de groupe commune (multi-communes uniquement) */
    .tdoc-group td {
        background:#fdf6e8 !important; color:#92400e;
        font-weight:800; font-size:9px; letter-spacing:.4px;
        padding:8px 8px !important; border-left:3px solid #e8a020;
    }

    /* ── Badges nature / statut — aplatis pour rester sobres ───────── */
    .tdoc-badge {
        display:inline-block; padding:2px 7px; border-radius:3px;
        font-size:7.5px; font-weight:700; letter-spacing:.3px;
    }
    .tdoc-badge-tm     { background:#eef2ff; color:#4338ca; }
    .tdoc-badge-odp    { background:#fff7ed; color:#c2410c; }
    .tdoc-badge-db     { background:#eff6ff; color:#1d4ed8; }
    .tdoc-badge-green  { background:#ecfdf5; color:#047857; }
    .tdoc-badge-orange { background:#fffbeb; color:#b45309; }
    .tdoc-badge-red    { background:#fef2f2; color:#b91c1c; }

    /* ── Ligne de total ─────────────────────────────────────────────
       Montant en or sur deux lignes (valeur / devise), comme la maquette. */
    .tdoc-total td {
        background:#1e2a44 !important; color:#ffffff;
        font-weight:800; font-size:11px; padding:12px 8px; border-bottom:none;
    }
    .tdoc-total .tdoc-amount {
        color:#e8a020; font-size:13px; line-height:1.15; text-align:right;
    }
    .tdoc-total .tdoc-amount span { display:block; font-size:11px; }

    /* ── Note explicative de bas de document ────────────────────────── */
    .tdoc-note {
        margin-top:16px; padding:11px 14px;
        background:#f1f5f9; border-left:4px solid #e8a020;
        font-size:8px; color:#475569; line-height:1.55;
    }
    .tdoc-note b { color:#334155; }

    /* ── Footer fixe avec pagination ────────────────────────────────
       Offsets alignés sur les marges @page communes aux deux vues.
       counter(page) : idiome DomPDF pour numéroter les pages. */
    .tdoc-footer {
        position:fixed; bottom:7mm; left:14mm; right:14mm;
        font-size:7.5px; color:#9ca3af;
        padding-top:6px; border-top:1px solid #e5e7eb;
    }
    .tdoc-footer table { width:100%; border-collapse:collapse; }
    .tdoc-footer td { padding:0; }
    .tdoc-footer .right { text-align:right; }
    .tdoc-pagenum:before { content: counter(page); }
</style>
