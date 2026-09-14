<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Occupation des panneaux — CIBLE CI</title>
<style>
/* ═══════════════════════════════════════════════════════
   RESET + BASE — DomPDF compatible
   Pas de flexbox, pas de grid. Uniquement float + table.
═══════════════════════════════════════════════════════ */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
    font-size: 9px;
    color: #1E293B;
    background: #ffffff;
}

/* ── @page + zones réservées header/footer ─────── */
@page {
    size: A4 landscape;
    margin: 1.4cm 1.2cm 1.2cm 1.2cm;
}

/* ── PAGE DE GARDE ─────────────────────────────── */
.cover-page {
    width: 100%;
    height: 550px;
    background-color: #0F172A;
    page-break-after: always;
    position: relative;
}
.cover-header {
    background-color: #0F172A;
    padding: 28px 32px 20px 32px;
    border-bottom: 4px solid #E8A020;
}
.cover-logo-row { width: 100%; border-collapse: collapse; }
.cover-logo-row td { vertical-align: middle; }
.cover-logo { height: 56px; width: auto; }
.cover-brand-name { font-size: 16px; font-weight: bold; color: #ffffff; padding-left: 14px; }
.cover-brand-sub { font-size: 9px; color: #94A3B8; padding-left: 14px; display: block; margin-top: 3px; }
.cover-body { padding: 30px 32px 10px 32px; }
.cover-title-1 {
    font-size: 30px; font-weight: bold; color: #ffffff;
    line-height: 1.1; margin-bottom: 4px;
}
.cover-title-2 {
    font-size: 30px; font-weight: bold; color: #E8A020;
    line-height: 1.1; margin-bottom: 14px;
}
.cover-subtitle { font-size: 11px; color: #94A3B8; margin-bottom: 22px; }
.cover-meta-table { width: 520px; border-collapse: collapse; }
.cover-meta-table td { padding: 6px 0; border-bottom: 1px solid #1E293B; font-size: 9px; }
.cover-meta-label {
    color: #64748B; width: 150px; text-transform: uppercase;
    font-size: 8px; letter-spacing: 0.5px;
}
.cover-meta-value { color: #E2E8F0; font-weight: bold; }
.cover-footer {
    background-color: #E8A020; padding: 8px 32px;
    position: absolute; bottom: 0; left: 0; right: 0;
}
.cover-footer-text { font-size: 8px; font-weight: bold; color: #0F172A; }

/* ── EN-TÊTE fixe (répétée à chaque page) ──────── */
.page-header {
    position: fixed;
    top: -1.35cm;
    left: -1.2cm;
    right: -1.2cm;
    height: 1.05cm;
    background-color: #0F172A;
    border-bottom: 3px solid #E8A020;
}
.page-header table { width: 100%; height: 100%; border-collapse: collapse; }
.page-header td { vertical-align: middle; padding: 0 14px; }
.header-logo-mini { height: 20px; width: auto; }
.header-brand { font-size: 9px; font-weight: bold; color: #E8A020; padding-left: 6px; }
.header-sub { font-size: 8px; color: #94A3B8; padding-left: 4px; }
.header-right-text { font-size: 7.5px; color: #94A3B8; text-align: right; }

/* ── PIED DE PAGE fixe ─────────────────────────── */
.page-footer {
    position: fixed;
    bottom: -1.1cm;
    left: 0;
    right: 0;
    height: 0.9cm;
    border-top: 1px solid #E2E8F0;
    padding: 4px 4px 0 4px;
}
.page-footer table { width: 100%; border-collapse: collapse; }
.page-footer td { font-size: 7px; color: #94A3B8; vertical-align: top; }
.page-footer .footer-right { text-align: right; }

/* ── KPI BANNER ────────────────────────────────── */
.kpi-table {
    width: 100%; border-collapse: collapse;
    margin-bottom: 8px; background-color: #0F172A;
}
.kpi-table td {
    text-align: center; padding: 10px 6px 8px 6px;
    border-right: 1px solid #1E293B;
}
.kpi-table td:last-child { border-right: none; }
.kpi-value { font-size: 20px; font-weight: bold; line-height: 1; display: block; }
.kpi-label {
    font-size: 6.5px; color: #94A3B8;
    text-transform: uppercase; letter-spacing: 0.4px;
    display: block; margin-top: 4px;
}
.kpi-orange { color: #E8A020; }
.kpi-blue   { color: #60A5FA; }
.kpi-yellow { color: #F59E0B; }
.kpi-green  { color: #22C55E; }
.kpi-purple { color: #A78BFA; }

/* ── LÉGENDE ───────────────────────────────────── */
.legend-table {
    width: 100%; border-collapse: collapse;
    background-color: #F1F5F9; margin-bottom: 6px;
}
.legend-table td {
    text-align: center; font-size: 7.5px;
    padding: 5px 6px; border-right: 1px solid #E2E8F0;
}
.legend-table td:last-child { border-right: none; }
.leg-green  { color: #16A34A; font-weight: bold; }
.leg-yellow { color: #D97706; font-weight: bold; }
.leg-red    { color: #DC2626; font-weight: bold; }
.leg-gray   { color: #94A3B8; font-weight: bold; }
.leg-purple { color: #7C3AED; font-weight: bold; }

/* ── TABLEAU PRINCIPAL ─────────────────────────── */
.main-table { width: 100%; border-collapse: collapse; font-size: 7.5px; }
.main-table thead tr { background-color: #1E3A5F; }
.main-table thead th {
    color: #ffffff; font-weight: bold; font-size: 7px;
    padding: 6px 4px; text-align: center;
    border-right: 1px solid #334155;
}
.main-table thead th:first-child,
.main-table thead th.left { text-align: left; }
.main-table thead th:last-child { border-right: none; }
.main-table tbody td {
    padding: 4px 5px; border-bottom: 0.3px solid #E2E8F0;
    vertical-align: middle;
}
.col-num   { width: 24px;  text-align: center; color: #94A3B8; font-size: 7px; }
.col-ref   { width: 78px;  font-weight: bold;  color: #B45309; font-size: 7.5px; }
.col-empl  { font-size: 7px; color: #334155; }
.col-comm  { width: 80px;  text-align: center; font-size: 7px; }
.col-zone  { width: 56px;  text-align: center; font-size: 7px; font-weight: bold; }
.col-fmt   { width: 46px;  text-align: center; font-size: 7px; }
.col-camp  { width: 34px;  text-align: center; }
.col-jours { width: 60px;  text-align: center; font-weight: bold; }
.col-taux  { width: 62px;  text-align: center; font-weight: bold; font-size: 8px; }

.zone-abj { color: #1D4ED8; }
.zone-int { color: #047857; }
.taux-high  { color: #16A34A; background-color: #F0FDF4; }
.taux-mid   { color: #D97706; background-color: #FFFBEB; }
.taux-low   { color: #DC2626; background-color: #FEF2F2; }
.taux-zero  { color: #94A3B8; background-color: #F8FAFC; }
.taux-maint { color: #7C3AED; background-color: #F5F3FF; }
.maint-tag  { color: #7C3AED; font-weight: bold; font-size: 7px; }

/* Ligne total */
.row-total { background-color: #0F172A; }
.row-total td {
    color: #E8A020; font-weight: bold; font-size: 9px;
    padding: 8px 4px; border-top: 2px solid #E8A020;
}

/* ── NOTE FINALE ───────────────────────────────── */
.note-box {
    border-left: 4px solid #E8A020;
    background-color: #F8FAFC;
    padding: 8px 12px;
    font-size: 7.5px;
    color: #475569;
    margin-top: 14px;
    line-height: 1.5;
}
</style>
</head>
<body>

{{-- ══════════════════════════════════════
     PAGE DE GARDE
══════════════════════════════════════ --}}
<div class="cover-page">
    <div class="cover-header">
        <table class="cover-logo-row">
            <tr>
                <td style="width:80px;">
                    @if(!empty($logoCibleDark))
                        <img src="{{ $logoCibleDark }}" class="cover-logo" alt="CIBLE CI">
                    @else
                        <div style="width:56px;height:56px;background:#E8A020;
                                    text-align:center;line-height:56px;color:#0F172A;
                                    font-weight:bold;font-size:11px;">CIBLE</div>
                    @endif
                </td>
                <td>
                    <span class="cover-brand-name">CIBLE SARL</span>
                    <span class="cover-brand-sub">Régie Publicitaire OOH · Côte d'Ivoire</span>
                </td>
            </tr>
        </table>
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
     HEADER + FOOTER RÉPÉTÉS SUR CHAQUE PAGE
══════════════════════════════════════ --}}
<div class="page-header">
    <table>
        <tr>
            <td style="width:60%;">
                @if(!empty($logoCibleDark))
                    <img src="{{ $logoCibleDark }}" class="header-logo-mini" alt="">
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

<div class="page-footer">
    <table>
        <tr>
            <td>Panora &middot; CIBLE SARL &middot; Régie OOH Côte d'Ivoire &middot; Document généré automatiquement</td>
            <td class="footer-right">
                {{ $stats['total_panneaux'] }} panneaux &middot;
                {{ $stats['total_campagnes'] }} campagnes &middot;
                Taux moyen {{ $stats['taux_moyen'] }} %
            </td>
        </tr>
    </table>
</div>

{{-- ── KPI BANNER ── --}}
<table class="kpi-table">
    <tr>
        <td>
            <span class="kpi-value kpi-orange">{{ $stats['total_panneaux'] }}</span>
            <span class="kpi-label">PANNEAUX ANALYSÉS</span>
        </td>
        <td>
            <span class="kpi-value kpi-blue">{{ number_format($stats['total_jours'], 0, ',', ' ') }}</span>
            <span class="kpi-label">JOURS OCCUPÉS</span>
        </td>
        <td>
            <span class="kpi-value kpi-yellow">{{ $stats['taux_moyen'] }} %</span>
            <span class="kpi-label">TAUX MOYEN</span>
        </td>
        <td>
            <span class="kpi-value kpi-green">{{ $stats['total_campagnes'] }}</span>
            <span class="kpi-label">CAMPAGNES</span>
        </td>
        @if($stats['en_maintenance'] > 0)
        <td>
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
        <td><span class="leg-yellow">&#9632;</span> Orange 40&ndash;79 % &mdash; Bon</td>
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
            <tr style="background-color: {{ $loop->even ? '#F8FAFC' : '#ffffff' }}">
                <td class="col-num">{{ $loop->iteration }}</td>
                <td class="col-ref">
                    {{ $p->reference }}@if($isMaint) <span class="maint-tag">🔧</span>@endif
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
            <td colspan="6" style="text-align:right;padding-right:8px;">
                TOTAL ({{ $stats['total_panneaux'] }} panneaux@if($stats['en_maintenance'] > 0) · dont {{ $stats['en_maintenance'] }} en maintenance@endif)
            </td>
            <td style="text-align:center;color:#E8A020;">{{ $stats['total_campagnes'] }}</td>
            <td style="text-align:center;color:#ffffff;">
                {{ number_format($stats['total_jours'], 0, ',', ' ') }} j
            </td>
            <td style="text-align:center;color:#E8A020;">{{ $stats['taux_moyen'] }} %</td>
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
