<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Occupation des panneaux — CIBLE CI</title>
@include('pdf.partials.charte-styles')
<style>
/* ═══════════════════════════════════════════════════════
   RESET + BASE — DomPDF compatible
   Pas de flexbox, pas de grid. Uniquement float + table.
   2026-10-01 — charte graphique CIBLE : palette $charte, polices
   Poppins/Nunito (charte-styles), garde claire, pied charte-footer.
═══════════════════════════════════════════════════════ */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-size: 9px;
    color: {{ $charte['noir'] }};
    background: {{ $charte['blanc'] }};
}

/* ── @page + zones réservées header/footer ─────── */
/* 2026-10-01 — !important : sans lui « * { margin:0 } » annule la marge
   de page dans DomPDF (en-tête et pied fixes se retrouvaient hors page).
   16 mm en haut pour l'en-tête répété, 18 mm en bas pour charte-footer. */
@page {
    size: A4 landscape;
    margin: 16mm 12mm 18mm 12mm !important;
}

/* ── PAGE DE GARDE ─────────────────────────────── */
/* Fix 2026-09-14 : page blanche entre cover et contenu.
   Cause : cover-page height fixe + cover-footer position:absolute
   → DomPDF crée une page fantôme. Solution : flow normal, pas de
   height explicite, pas d'absolute.
   2026-10-01 — charte graphique : garde sur fond clair (liseré + logo clair). */
.cover-page {
    width: 100%;
    page-break-after: always;
}
.cover-header {
    padding: 22px 32px 18px 32px;
    border-bottom: 3px solid {{ $charte['rouge'] }};
    text-align: center;
}
.cover-logo { height: 72px; width: auto; }
.cover-logo-fallback {
    width: 72px; height: 72px; background: {{ $charte['rouge'] }};
    text-align: center; line-height: 72px; color: {{ $charte['blanc'] }};
    font-weight: 700; font-size: 13px; display: inline-block;
}
.cover-body { padding: 32px 32px 26px 32px; }
.cover-title-1 {
    font-family: {!! $charte['ff_titres'] !!};
    font-size: 30px; font-weight: 700; color: {{ $charte['noir'] }};
    line-height: 1.1; margin-bottom: 4px;
}
.cover-title-2 {
    font-family: {!! $charte['ff_titres'] !!};
    font-size: 30px; font-weight: 700; color: {{ $charte['rouge'] }};
    line-height: 1.1; margin-bottom: 14px;
}
.cover-subtitle { font-size: 11px; color: {{ $charte['texte_doux'] }}; margin-bottom: 22px; }
.cover-meta-table { width: 520px; border-collapse: collapse; }
.cover-meta-table td { padding: 6px 0; border-bottom: 1px solid {{ $charte['gris'] }}; font-size: 9px; }
.cover-meta-label {
    color: {{ $charte['texte_doux'] }}; width: 150px; text-transform: uppercase;
    font-size: 8px; letter-spacing: 0.5px;
}
.cover-meta-value { color: {{ $charte['noir'] }}; font-weight: 700; }
.cover-footer {
    background-color: {{ $charte['gris_clair'] }};
    border-left: 3px solid {{ $charte['rouge'] }};
    padding: 10px 32px;
}
.cover-footer-text { font-size: 8px; font-weight: 700; color: {{ $charte['noir'] }}; }

/* ── EN-TÊTE fixe (répétée à chaque page après la garde) ──
   2026-10-01 — fond clair, logo clair, filet rouge ; placée dans la
   marge haute (16 mm). */
.page-header {
    position: fixed;
    top: -12mm;
    left: 0;
    right: 0;
    height: 9mm;
    border-bottom: 2px solid {{ $charte['rouge'] }};
}
.page-header table { width: 100%; height: 100%; border-collapse: collapse; }
.page-header td { vertical-align: middle; padding: 0 0 3px 0; }
.header-logo-mini { height: 20px; width: auto; vertical-align: middle; }
.header-brand { font-family: {!! $charte['ff_titres'] !!}; font-size: 9px; font-weight: 700; color: {{ $charte['noir'] }}; padding-left: 6px; }
.header-sub { font-size: 8px; color: {{ $charte['texte_doux'] }}; padding-left: 4px; }
.header-right-text { font-size: 7.5px; color: {{ $charte['texte_doux'] }}; text-align: right; }

/* ── KPI BANNER ────────────────────────────────── */
.kpi-table {
    width: 100%; border-collapse: separate; border-spacing: 6px 0;
    margin-bottom: 8px;
}
.kpi-table td {
    text-align: center; padding: 9px 6px 7px 6px;
    background-color: {{ $charte['gris_clair'] }};
    border-top: 3px solid {{ $charte['rouge'] }};
}
.kpi-table td.k-bleu   { border-top-color: {{ $charte['bleu'] }}; }
.kpi-table td.k-jaune  { border-top-color: {{ $charte['jaune'] }}; }
.kpi-table td.k-vert   { border-top-color: {{ $charte['vert'] }}; }
.kpi-table td.k-violet { border-top-color: {{ $charte['violet'] }}; }
.kpi-value { font-family: {!! $charte['ff_titres'] !!}; font-size: 20px; font-weight: 700; line-height: 1; display: block; }
.kpi-label {
    font-size: 6.5px; color: {{ $charte['texte_doux'] }}; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.4px;
    display: block; margin-top: 4px;
}
.kpi-orange { color: {{ $charte['rouge'] }}; }
.kpi-blue   { color: {{ $charte['bleu'] }}; }
.kpi-yellow { color: {{ $charte['noir'] }}; }
.kpi-green  { color: {{ $charte['vert'] }}; }
.kpi-purple { color: {{ $charte['violet'] }}; }

/* ── LÉGENDE ───────────────────────────────────── */
.legend-table {
    width: 100%; border-collapse: collapse;
    background-color: {{ $charte['gris_clair'] }}; margin-bottom: 6px;
}
.legend-table td {
    text-align: center; font-size: 7.5px;
    padding: 5px 6px; border-right: 1px solid {{ $charte['gris'] }};
}
.legend-table td:last-child { border-right: none; }
.leg-green  { color: {{ $charte['vert'] }}; font-weight: 700; }
.leg-yellow { color: {{ $charte['jaune'] }}; font-weight: 700; }
.leg-red    { color: {{ $charte['rouge'] }}; font-weight: 700; }
.leg-gray   { color: {{ $charte['texte_pale'] }}; font-weight: 700; }
.leg-purple { color: {{ $charte['violet'] }}; font-weight: 700; }

/* ── TABLEAU PRINCIPAL ─────────────────────────── */
.main-table { width: 100%; border-collapse: collapse; font-size: 7.5px; }
.main-table thead tr { background-color: {{ $charte['noir'] }}; }
.main-table thead th {
    background-color: {{ $charte['noir'] }};
    color: {{ $charte['blanc'] }};
    font-family: {!! $charte['ff_titres'] !!}; font-weight: 600; font-size: 7px;
    padding: 6px 4px; text-align: center;
    border-right: 1px solid {{ $charte['texte_doux'] }};
}
.main-table thead th:first-child,
.main-table thead th.left { text-align: left; }
.main-table thead th:last-child { border-right: none; }
.main-table tbody td {
    padding: 3px 5px; line-height: 1.1; border-bottom: 0.3px solid {{ $charte['gris'] }};
    vertical-align: middle;
}
.col-num   { width: 24px;  text-align: center; color: {{ $charte['texte_pale'] }}; font-size: 7px; }
.col-ref   { width: 78px;  font-weight: 700;  color: {{ $charte['rouge'] }}; font-size: 7.5px; }
.col-empl  { font-size: 7px; color: {{ $charte['texte_doux'] }}; }
.col-comm  { width: 80px;  text-align: center; font-size: 7px; }
.col-zone  { width: 56px;  text-align: center; font-size: 7px; font-weight: 700; }
.col-fmt   { width: 46px;  text-align: center; font-size: 7px; }
.col-camp  { width: 34px;  text-align: center; }
.col-jours { width: 60px;  text-align: center; font-weight: 700; }
.col-taux  { width: 62px;  text-align: center; font-weight: 700; font-size: 8px; }

.zone-abj { color: {{ $charte['bleu'] }}; }
.zone-int { color: {{ $charte['vert'] }}; }
.taux-high  { color: {{ $charte['vert'] }};       background-color: {{ $charte['vert_clair'] }}; }
.taux-mid   { color: {{ $charte['noir'] }};       background-color: {{ $charte['jaune_clair'] }}; }
.taux-low   { color: {{ $charte['rouge'] }};      background-color: {{ $charte['rouge_clair'] }}; }
.taux-zero  { color: {{ $charte['texte_pale'] }}; background-color: {{ $charte['gris_clair'] }}; }
.taux-maint { color: {{ $charte['violet'] }};     background-color: {{ $charte['violet_clair'] }}; }
.maint-tag  { color: {{ $charte['violet'] }}; font-weight: 700; font-size: 7px; }

/* Ligne total */
.row-total { background-color: {{ $charte['noir'] }}; }
.row-total td {
    background-color: {{ $charte['noir'] }};
    color: {{ $charte['blanc'] }}; font-weight: 700; font-size: 9px;
    padding: 8px 4px; border-top: 2px solid {{ $charte['rouge'] }};
}
.row-total td.tot-hl { color: {{ $charte['jaune'] }}; }

/* ── NOTE FINALE ───────────────────────────────── */
.note-box {
    border-left: 4px solid {{ $charte['rouge'] }};
    background-color: {{ $charte['gris_clair'] }};
    padding: 8px 12px;
    font-size: 7.5px;
    color: {{ $charte['texte_doux'] }};
    margin-top: 14px;
    line-height: 1.3;
}
.note-box strong { color: {{ $charte['noir'] }}; }
</style>
</head>
<body>

{{-- 2026-10-01 — charte graphique : pied commun (régie · slogan ·
     coordonnées + texte de pied d'origine + n° de page). Déclaré en tête
     du body pour figurer aussi sur la page de garde. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => "Panora · CIBLE SARL · Régie OOH Côte d'Ivoire · Document généré automatiquement"
        . ' — ' . $stats['total_panneaux'] . ' panneaux · '
        . $stats['total_campagnes'] . ' campagnes · Taux moyen ' . $stats['taux_moyen'] . ' %',
])

{{-- ══════════════════════════════════════
     PAGE DE GARDE
══════════════════════════════════════ --}}
<div class="cover-page">
    @include('pdf.partials.charte-lisere')
    <div class="cover-header">
        @if(!empty($logoCibleLight))
            <img src="{{ $logoCibleLight }}" class="cover-logo" alt="CIBLE CI">
        @else
            <div class="cover-logo-fallback">CIBLE</div>
        @endif
    </div>

    <div class="cover-body">
        <div class="cover-title-1">RAPPORT D'OCCUPATION</div>
        <div class="cover-title-2">DES PANNEAUX</div>
        <div class="cover-subtitle">Rapport détaillé par panneau &mdash; Synthèse globale</div>

        <table class="cover-meta-table">
            <tr>
                <td class="cover-meta-label">Référence</td>
                <td class="cover-meta-value">{{ $stats['reference'] }}</td>
            </tr>
            <tr>
                <td class="cover-meta-label">Période</td>
                <td class="cover-meta-value">
                    {{ \Carbon\Carbon::parse($stats['date_debut'])->format('d/m/Y') }}
                    &rarr;
                    {{ \Carbon\Carbon::parse($stats['date_fin'])->format('d/m/Y') }}
                </td>
            </tr>
            <tr>
                <td class="cover-meta-label">Panneaux analysés</td>
                <td class="cover-meta-value">
                    {{ $stats['total_panneaux'] }} panneaux
                    @if($stats['en_maintenance'] > 0)
                        &mdash; dont {{ $stats['en_maintenance'] }} en maintenance
                    @endif
                </td>
            </tr>
            <tr>
                <td class="cover-meta-label">Jours occupés</td>
                <td class="cover-meta-value">
                    {{ number_format($stats['total_jours'], 0, ',', ' ') }} jours
                    &middot; Taux moyen : {{ $stats['taux_moyen'] }} %
                </td>
            </tr>
            <tr>
                <td class="cover-meta-label">Campagnes cumulées</td>
                <td class="cover-meta-value">{{ $stats['total_campagnes'] }} campagnes</td>
            </tr>
            <tr>
                <td class="cover-meta-label">Édité par</td>
                <td class="cover-meta-value">{{ $user->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="cover-meta-label">Édité le</td>
                <td class="cover-meta-value">{{ $stats['edite_le'] }}</td>
            </tr>
        </table>
    </div>

    <div class="cover-footer">
        <span class="cover-footer-text">
            Plateforme Panora &middot; opérée par CIBLE CI
            &mdash; Document généré automatiquement &middot; Usage interne
        </span>
    </div>
</div>

{{-- ══════════════════════════════════════
     HEADER RÉPÉTÉ SUR CHAQUE PAGE
     2026-10-01 — charte graphique : bandeau clair dans la marge haute
     (DomPDF le répète sur toutes les pages, garde comprise). Le pied
     d'origine (.page-footer) est remplacé par pdf.partials.charte-footer
     (texte repris dans footerHint, ci-dessus).
══════════════════════════════════════ --}}
<div class="page-header">
    <table>
        <tr>
            <td style="width:60%;">
                @if(!empty($logoCibleLight))
                    <img src="{{ $logoCibleLight }}" class="header-logo-mini" alt="">
                @endif
                <span class="header-brand">CIBLE CI</span>
                <span class="header-sub">| Occupation des panneaux</span>
            </td>
            <td>
                <div class="header-right-text">
                    {{ \Carbon\Carbon::parse($stats['date_debut'])->format('d/m/Y') }}
                    &rarr;
                    {{ \Carbon\Carbon::parse($stats['date_fin'])->format('d/m/Y') }}
                    &nbsp;&middot;&nbsp; Réf. {{ $stats['reference'] }}
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- ── KPI BANNER ── --}}
<table class="kpi-table">
    <tr>
        <td class="k-rouge">
            <span class="kpi-value kpi-orange">{{ $stats['total_panneaux'] }}</span>
            <span class="kpi-label">PANNEAUX ANALYSÉS</span>
        </td>
        <td class="k-bleu">
            <span class="kpi-value kpi-blue">{{ number_format($stats['total_jours'], 0, ',', ' ') }}</span>
            <span class="kpi-label">JOURS OCCUPÉS</span>
        </td>
        <td class="k-jaune">
            <span class="kpi-value kpi-yellow">{{ $stats['taux_moyen'] }} %</span>
            <span class="kpi-label">TAUX MOYEN</span>
        </td>
        <td class="k-vert">
            <span class="kpi-value kpi-green">{{ $stats['total_campagnes'] }}</span>
            <span class="kpi-label">CAMPAGNES</span>
        </td>
        @if($stats['en_maintenance'] > 0)
        <td class="k-violet">
            <span class="kpi-value kpi-purple">{{ $stats['en_maintenance'] }}</span>
            <span class="kpi-label">EN MAINTENANCE</span>
        </td>
        @endif
    </tr>
</table>

{{-- ── LÉGENDE ── --}}
<table class="legend-table">
    <tr>
        <td><span class="leg-green">&#9632;</span> Vert &ge; 80 % &mdash; Très occupé</td>
        <td><span class="leg-yellow">&#9632;</span> Jaune 40&ndash;79 % &mdash; Bon</td>
        <td><span class="leg-red">&#9632;</span> Rouge 1&ndash;39 % &mdash; Faible</td>
        <td><span class="leg-gray">&#9632;</span> Gris 0 % &mdash; Non occupé</td>
        <td><span class="leg-purple">&#9632;</span> Violet &mdash; Maintenance</td>
    </tr>
</table>

{{-- ── TABLEAU PRINCIPAL ── --}}
<table class="main-table">
    <thead>
        <tr>
            <th class="col-num">N°</th>
            <th class="col-ref left">RÉFÉRENCE</th>
            <th class="col-empl left">EMPLACEMENT</th>
            <th class="col-comm">COMMUNE</th>
            <th class="col-zone">ZONE</th>
            <th class="col-fmt">FORMAT</th>
            <th class="col-camp">CAMP.</th>
            <th class="col-jours">JOURS OCC.</th>
            <th class="col-taux">TAUX OCC.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($panels as $p)
            @php
                $taux = (float) ($p->occupation_rate ?? 0);
                $isMaint = $p->status === 'maintenance';
                $isAbj = ($p->zone ?? '') === 'Abidjan';
                if ($isMaint) {
                    $tauxClass = 'taux-maint';
                } elseif ($taux >= 80) {
                    $tauxClass = 'taux-high';
                } elseif ($taux >= 40) {
                    $tauxClass = 'taux-mid';
                } elseif ($taux > 0) {
                    $tauxClass = 'taux-low';
                } else {
                    $tauxClass = 'taux-zero';
                }
                $tauxStr = $isMaint ? 'Maint.' : number_format($taux, 1, ',', '') . ' %';
                $joursStr = $isMaint ? '—' : number_format((int) $p->days_occupied, 0, ',', ' ') . ' j';
            @endphp
            <tr style="background-color: {{ $loop->even ? $charte['gris_clair'] : $charte['blanc'] }}">
                <td class="col-num">{{ $loop->iteration }}</td>
                <td class="col-ref">
                    {{-- 2026-10-01 — pictogramme 🔧 (carré vide en PDF) remplacé par ■ violet --}}
                    {{ $p->reference }}@if($isMaint) <span class="maint-tag">&#9632;</span>@endif
                </td>
                <td class="col-empl">{{ \Illuminate\Support\Str::limit($p->name ?? '—', 58) }}</td>
                <td class="col-comm">{{ $p->commune_name ?? '—' }}</td>
                <td class="col-zone {{ $isAbj ? 'zone-abj' : 'zone-int' }}">
                    {{ $isAbj ? 'Abidjan' : 'Intérieur' }}
                </td>
                <td class="col-fmt">{{ $p->format_name ?? '—' }}</td>
                <td class="col-camp">{{ (int) $p->campaigns_count }}</td>
                <td class="col-jours">{{ $joursStr }}</td>
                <td class="col-taux {{ $tauxClass }}">{{ $tauxStr }}</td>
            </tr>
        @endforeach

        {{-- Ligne total --}}
        @if($panels->isNotEmpty())
        <tr class="row-total">
            <td colspan="6" class="tot-hl" style="text-align:right;padding-right:8px;">
                TOTAL ({{ $stats['total_panneaux'] }} panneaux{{ $stats['en_maintenance'] > 0 ? ' · dont ' . $stats['en_maintenance'] . ' en maintenance' : '' }})
            </td>
            <td class="tot-hl" style="text-align:center;">{{ $stats['total_campagnes'] }}</td>
            <td style="text-align:center;">
                {{ number_format($stats['total_jours'], 0, ',', ' ') }} j
            </td>
            <td class="tot-hl" style="text-align:center;">{{ $stats['taux_moyen'] }} %</td>
        </tr>
        @endif
    </tbody>
</table>

{{-- ── NOTE FINALE ── --}}
<div class="note-box">
    <strong>Note :</strong>
    Les taux d'occupation sont calculés sur la base des jours de réservations
    confirmées par rapport à la durée totale de la période analysée.
    Un panneau en maintenance est signalé par un badge violet.
    Document généré automatiquement par la plateforme Panora &mdash; CIBLE SARL.
</div>

</body>
</html>
