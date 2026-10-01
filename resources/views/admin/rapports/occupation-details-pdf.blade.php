<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Occupation détaillée — CIBLE CI</title>
@include('pdf.partials.charte-styles')
<style>
    /* margin-bottom 26mm + body padding-bottom = double garde-fou
       contre le débordement du tableau sur le footer (bug DomPDF).
       2026-10-01 — charte graphique : palette $charte, polices de la
       charte, en-tête charte-header, pied charte-footer. */
    @page { size: A4 landscape; margin: 12mm 10mm 26mm 10mm !important; }
    body { font-size: 9px; color: {{ $charte['noir'] }}; line-height: 1.2; padding-bottom: 4mm; }
    .meta { font-size: 9px; color: {{ $charte['texte_doux'] }}; margin-top: 8px; padding: 6px 10px; background: {{ $charte['gris_clair'] }}; border-left: 3px solid {{ $charte['rouge'] }}; }
    .meta strong { color: {{ $charte['noir'] }}; }
    .kpis { display: table; width: 100%; margin: 8px 0 12px; border-collapse: separate; border-spacing: 5px 0; }
    .kpis .cell { display: table-cell; background: {{ $charte['gris_clair'] }}; border-top: 3px solid {{ $charte['rouge'] }}; padding: 6px 10px; text-align: center; }
    .kpis .cell .n { font-family: {!! $charte['ff_titres'] !!}; font-size: 15px; font-weight: 700; color: {{ $charte['noir'] }}; display: block; }
    .kpis .cell .l { font-size: 7.5px; font-weight: 700; text-transform: uppercase; color: {{ $charte['texte_doux'] }}; letter-spacing: .4px; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th { background: {{ $charte['noir'] }}; padding: 6px 6px; text-align: left; font-family: {!! $charte['ff_titres'] !!}; font-size: 8px; font-weight: 600; color: {{ $charte['blanc'] }}; text-transform: uppercase; letter-spacing: 0.4px; }
    table.grid th.r, table.grid td.r { text-align: right; }
    table.grid th.c, table.grid td.c { text-align: center; }
    table.grid td { padding: 4px 6px; font-size: 8px; border-bottom: 1px solid {{ $charte['gris'] }}; vertical-align: top; }
    table.grid tr:nth-child(even) td { background: {{ $charte['gris_clair'] }}; }
    .ref { font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['rouge'] }}; font-weight: 700; }
    .muted { color: {{ $charte['texte_doux'] }}; font-size: 7.5px; }
    .badge { display: inline-block; padding: 1px 6px; font-size: 7.5px; border-radius: 3px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
    .badge-actif    { background: {{ $charte['vert'] }};   color: {{ $charte['blanc'] }}; }
    .badge-planifie { background: {{ $charte['bleu'] }};   color: {{ $charte['blanc'] }}; }
    .badge-termine  { background: {{ $charte['gris'] }};   color: {{ $charte['noir'] }}; }
    .badge-pause    { background: {{ $charte['jaune'] }};  color: {{ $charte['noir'] }}; }
    .badge-ext      { background: {{ $charte['gris'] }};   color: {{ $charte['noir'] }}; margin-top: 2px; display: inline-block; }
    .badge-decap    { background: {{ $charte['rouge'] }};  color: {{ $charte['blanc'] }}; margin-top: 2px; display: inline-block; }
    .empty-row { text-align: center; color: {{ $charte['texte_doux'] }}; font-style: italic; padding: 24px; }
</style>
</head>
<body>

{{-- 2026-10-01 — charte graphique : pied commun (texte d'origine en
     footerHint ; « Page N » fourni par le partiel). --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => "CIBLE SARL — Régie OOH Côte d'Ivoire · Document généré automatiquement par Panora",
])

{{-- 2026-10-01 — charte graphique : en-tête commun (liseré + logo clair). --}}
@include('pdf.partials.charte-header', [
    'docKicker'   => 'Rapport',
    'docTitle'    => 'OCCUPATION DÉTAILLÉE',
    'docSubtitle' => 'Panneaux occupés × campagnes · ' . ($operatorName ?? 'CIBLE CI'),
    'docMeta'     => [
        'Édité le ' . now()->format('d/m/Y H:i'),
        'Par ' . ($user->name ?? '—'),
        'Réf. ' . strtoupper(substr(md5(now()), 0, 8)),
    ],
])

@include('admin.rapports.partials._filter_recap_pdf')

<div class="kpis">
    <div class="cell"><span class="n">{{ number_format($summary['total_rows'] ?? 0, 0, ',', ' ') }}</span><span class="l">Lignes</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_panels'] ?? 0, 0, ',', ' ') }}</span><span class="l">Panneaux</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_campaigns'] ?? 0, 0, ',', ' ') }}</span><span class="l">Campagnes</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_clients'] ?? 0, 0, ',', ' ') }}</span><span class="l">Clients</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_communes'] ?? 0, 0, ',', ' ') }}</span><span class="l">Communes</span></div>
    @if(($summary['nb_externals'] ?? 0) > 0)
    <div class="cell"><span class="n">{{ number_format($summary['nb_externals'], 0, ',', ' ') }}</span><span class="l">Externes</span></div>
    @endif
</div>

<div class="meta">
    Période analysée : <strong>{{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</strong>
    · Une ligne = un panneau occupé par une campagne dont la période chevauche l'intervalle demandé
    · Statuts inclus : planifié, actif, en pause, terminé (annulés exclus).
</div>

<table class="grid">
    <thead>
        <tr>
            <th>Commune</th>
            <th>Panneau</th>
            <th>Type / Dim.</th>
            <th>Campagne</th>
            <th>Client</th>
            <th class="c">Début</th>
            <th class="c">Fin</th>
            <th class="r">Durée</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            @php
                $st = (string) $r['campaign_status'];
                $badgeClass = match($st) {
                    'actif'    => 'badge-actif',
                    'planifie' => 'badge-planifie',
                    'termine'  => 'badge-termine',
                    'pause'    => 'badge-pause',
                    default    => 'badge-termine',
                };
                $statusLabel = match($st) {
                    'actif'    => 'Actif',
                    'planifie' => 'Planifié',
                    'termine'  => 'Terminé',
                    'pause'    => 'En pause',
                    default    => ucfirst($st),
                };
            @endphp
            <tr>
                <td>
                    <strong>{{ $r['commune'] }}</strong>
                    @if($r['city'] !== $r['commune'])
                        <div class="muted">{{ $r['city'] }}</div>
                    @endif
                </td>
                <td>
                    <span class="ref">{{ $r['panel_ref'] }}</span>
                    <div class="muted">{{ \Illuminate\Support\Str::limit($r['panel_name'] ?? '', 40) }}</div>
                    @if($r['is_external'])
                        <span class="badge badge-ext">Externe</span>
                    @endif
                </td>
                <td>
                    <div>{{ $r['panel_dims'] }}</div>
                    @if($r['panel_type'] && $r['panel_type'] !== '—')
                        <div class="muted">{{ $r['panel_type'] }}</div>
                    @endif
                </td>
                <td>
                    <div>{{ \Illuminate\Support\Str::limit($r['campaign_name'] ?? '', 34) }}</div>
                    <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                </td>
                <td>
                    <div>{{ \Illuminate\Support\Str::limit($r['client_name'] ?? '', 24) }}</div>
                    @if($r['client_sector'] && $r['client_sector'] !== '—')
                        <div class="muted">{{ $r['client_sector'] }}</div>
                    @endif
                </td>
                <td class="c">{{ $r['campaign_start'] ? \Carbon\Carbon::parse($r['campaign_start'])->format('d/m/Y') : '—' }}</td>
                <td class="c">{{ $r['campaign_end']   ? \Carbon\Carbon::parse($r['campaign_end'])->format('d/m/Y')   : '—' }}</td>
                <td class="r">
                    <strong>{{ $r['duration_label'] }}</strong>
                    @if($r['decapped_at'])
                        <div><span class="badge badge-decap">Décapé {{ \Carbon\Carbon::parse($r['decapped_at'])->format('d/m/y') }}</span></div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty-row">Aucune occupation sur la période et les filtres choisis.</td></tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
