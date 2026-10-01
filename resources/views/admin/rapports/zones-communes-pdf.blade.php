<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Zones & Communes — CIBLE CI</title>
{{-- 2026-10-01 — charte graphique : styles communs, en-tête charte-header,
     pied charte-footer (remplace l'ancien pied « Page N / 0 » : le compteur
     counter(pages) n'est pas résolu par DomPDF). --}}
@include('pdf.partials.charte-styles')
<style>
    /* margin-bottom 22mm + body padding-bottom = double garde-fou
       contre le débordement du tableau sur le footer (bug DomPDF). */
    @page { size: A4 landscape; margin: 12mm 10mm 22mm 10mm !important; }
    body { font-size: 9px; color: {{ $charte['noir'] }}; line-height: 1.2; padding-bottom: 4mm; }
    .meta { font-size: 9px; color: {{ $charte['noir'] }}; margin-top: 8px; padding: 6px 10px; background: {{ $charte['gris_clair'] }}; border-left: 3px solid {{ $charte['rouge'] }}; }
    .meta strong { color: {{ $charte['noir'] }}; }
    .kpis { display: table; width: 100%; margin: 8px 0 12px; border-collapse: separate; border-spacing: 5px 0; }
    .kpis .cell { display: table-cell; background: {{ $charte['gris_clair'] }}; border-top: 3px solid {{ $charte['rouge'] }}; padding: 6px 10px; text-align: center; }
    .kpis .cell .n { font-family: {!! $charte['ff_titres'] !!}; font-size: 15px; font-weight: 700; color: {{ $charte['noir'] }}; display: block; }
    .kpis .cell .l { font-size: 7.5px; font-weight: 700; text-transform: uppercase; color: {{ $charte['texte_doux'] }}; letter-spacing: .4px; }

    /* ── Section commune ── */
    .commune-block { margin-top: 14px; page-break-inside: avoid; }
    .commune-head {
        background: {{ $charte['gris_clair'] }}; color: {{ $charte['noir'] }};
        border-left: 3px solid {{ $charte['rouge'] }};
        padding: 7px 12px;
        display: table; width: 100%;
    }
    .commune-head .name { display: table-cell; font-family: {!! $charte['ff_titres'] !!}; font-size: 12px; font-weight: 700; letter-spacing: .5px; }
    .commune-head .stats {
        display: table-cell; text-align: right; font-size: 9px; color: {{ $charte['texte_doux'] }};
    }
    .commune-head .stats strong { color: {{ $charte['noir'] }}; }
    .commune-head .badge-zone {
        display: inline-block; padding: 1px 8px; font-family: {!! $charte['ff_texte'] !!}; font-size: 8px; border-radius: 3px;
        margin-left: 8px; font-weight: 700; letter-spacing: 0;
    }
    .commune-head .badge-abj { background: {{ $charte['bleu'] }}; color: {{ $charte['blanc'] }}; }
    .commune-head .badge-int { background: {{ $charte['vert'] }}; color: {{ $charte['blanc'] }}; }
    .commune-head .city { font-family: {!! $charte['ff_texte'] !!}; font-weight: 400; margin-left: 6px; letter-spacing: 0; color: {{ $charte['texte_pale'] }}; }

    .commune-empty {
        background: {{ $charte['blanc'] }}; border: 1px solid {{ $charte['gris'] }}; border-top: 0;
        padding: 8px 14px; font-size: 8.5px; color: {{ $charte['texte_doux'] }}; font-style: italic;
    }

    table.details {
        width: 100%; border-collapse: collapse;
    }
    table.details th {
        background: {{ $charte['noir'] }}; color: {{ $charte['blanc'] }};
        font-family: {!! $charte['ff_titres'] !!};
        padding: 5px 8px; text-align: left; font-size: 7.5px;
        font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;
    }
    table.details th.r, table.details td.r { text-align: right; }
    table.details th.c, table.details td.c { text-align: center; }
    table.details td { padding: 4px 8px; font-size: 8px; border-bottom: 1px solid {{ $charte['gris'] }}; vertical-align: top; }
    table.details tr:nth-child(even) td { background: {{ $charte['gris_clair'] }}; }
    .ref { font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['rouge'] }}; font-weight: 700; }
    .muted { color: {{ $charte['texte_doux'] }}; font-size: 7.5px; }
    .badge { display: inline-block; padding: 1px 5px; font-size: 7px; border-radius: 3px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
    .badge-actif    { background: {{ $charte['vert'] }};   color: {{ $charte['blanc'] }}; }
    .badge-planifie { background: {{ $charte['bleu'] }};   color: {{ $charte['blanc'] }}; }
    .badge-termine  { background: {{ $charte['gris'] }};   color: {{ $charte['noir'] }}; }
    .badge-pause    { background: {{ $charte['jaune'] }};  color: {{ $charte['noir'] }}; }
    .badge-ext      { background: {{ $charte['gris'] }};   color: {{ $charte['noir'] }}; margin-top: 2px; display: inline-block; }
    .badge-decap    { background: {{ $charte['rouge'] }};  color: {{ $charte['blanc'] }}; margin-top: 2px; display: inline-block; }
    /* Taux d'occupation : pastille pleine (pas de texte jaune sur fond clair). */
    .pct { font-weight: 700; padding: 0 5px; border-radius: 3px; }
    .pct-hi  { background: {{ $charte['vert'] }};  color: {{ $charte['blanc'] }}; }
    .pct-mid { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .pct-lo  { background: {{ $charte['rouge'] }}; color: {{ $charte['blanc'] }}; }
    .commune-head .stats strong.pct-hi, .commune-head .stats strong.pct-lo { color: {{ $charte['blanc'] }}; }
</style>
</head>
<body>

@include('pdf.partials.charte-footer', [
    'footerHint' => "CIBLE SARL — Régie OOH Côte d'Ivoire · Document généré automatiquement par Panora",
])

@include('pdf.partials.charte-header', [
    'docKicker'   => 'Rapport',
    'docTitle'    => 'ZONES & COMMUNES — DÉTAIL PAR PANNEAU',
    'docSubtitle' => 'Panneaux occupés × campagnes, groupés par commune · ' . ($operatorName ?? 'CIBLE CI'),
    'docMeta'     => [
        'Édité le ' . now()->format('d/m/Y H:i'),
        'Par ' . ($user->name ?? '—'),
        'Réf. ' . strtoupper(substr(md5(now()), 0, 8)),
    ],
])

@include('admin.rapports.partials._filter_recap_pdf')

<div class="kpis">
    <div class="cell"><span class="n">{{ number_format($summary['nb_communes'] ?? 0, 0, ',', ' ') }}</span><span class="l">Communes</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_panels'] ?? 0, 0, ',', ' ') }}</span><span class="l">Panneaux total</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_occupes'] ?? 0, 0, ',', ' ') }}</span><span class="l">Occupés</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_libres'] ?? 0, 0, ',', ' ') }}</span><span class="l">Libres</span></div>
    <div class="cell"><span class="n">{{ ($summary['taux_moyen'] ?? 0) }} %</span><span class="l">Taux moyen</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['ca_total'] ?? 0, 0, ',', ' ') }}</span><span class="l">CA FCFA</span></div>
</div>

<div class="meta">
    Période : <strong>{{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</strong>
    · Pour chaque commune : détail des panneaux occupés avec la (les) campagne(s) qui les ont utilisés sur la période.
    · Statuts campagne inclus : planifié, actif, en pause, terminé (annulés exclus).
    · Communes triées par taux d'occupation décroissant.
</div>

{{-- Boucle par commune ─────────────────────────────────────────── --}}
@forelse($rows as $c)
    @php
        $rate = $c['taux'] ?? 0;
        $rateClass = $rate >= 60 ? 'pct-hi' : ($rate >= 25 ? 'pct-mid' : 'pct-lo');
        $zoneBadge = $c['zone'] === 'Abidjan' ? 'badge-abj' : 'badge-int';
        $panelsList = $detailsByCommune[$c['commune']] ?? collect();
    @endphp

    <div class="commune-block">
        <div class="commune-head">
            <div class="name">
                {{ $c['commune'] }}
                <span class="badge-zone {{ $zoneBadge }}">{{ $c['zone'] }}</span>
                <span class="muted city">— {{ $c['city'] }}</span>
            </div>
            <div class="stats">
                <strong>{{ (int) $c['total'] }}</strong> pann. ·
                <strong>{{ (int) $c['occupes'] }}</strong> occ. ·
                <strong>{{ (int) $c['libres'] }}</strong> libres ·
                Taux <strong class="pct {{ $rateClass }}">{{ $rate }} %</strong> ·
                CA <strong>{{ number_format((float) $c['ca_annee'], 0, ',', ' ') }}</strong> FCFA
            </div>
        </div>

        @if($panelsList->isEmpty())
            <div class="commune-empty">
                Aucun panneau de cette commune n'a été occupé sur la période.
                @if(($c['occupes'] ?? 0) > 0)
                    <br><em>Note : {{ $c['occupes'] }} panneau(x) affiché(s) en "occupé" mais liés à des campagnes annulées ou hors périmètre RBAC.</em>
                @endif
            </div>
        @else
            <table class="details">
                <thead>
                    <tr>
                        <th style="width:14%">Réf. Panneau</th>
                        <th style="width:20%">Nom / Type</th>
                        <th style="width:22%">Campagne</th>
                        <th style="width:18%">Client</th>
                        <th class="c" style="width:9%">Début</th>
                        <th class="c" style="width:9%">Fin</th>
                        <th class="r" style="width:8%">Durée</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($panelsList as $p)
                        @php
                            $st = (string) $p['campaign_status'];
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
                                <span class="ref">{{ $p['panel_ref'] }}</span>
                                @if($p['is_external'])
                                    <div><span class="badge badge-ext">Externe</span></div>
                                @endif
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::limit($p['panel_name'] ?? '', 30) }}
                                <div class="muted">{{ $p['panel_dims'] }}{{ $p['panel_type'] && $p['panel_type'] !== '—' ? ' · '.$p['panel_type'] : '' }}</div>
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::limit($p['campaign_name'] ?? '', 26) }}
                                <div><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></div>
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::limit($p['client_name'] ?? '', 22) }}
                                @if($p['client_sector'] && $p['client_sector'] !== '—')
                                    <div class="muted">{{ \Illuminate\Support\Str::limit($p['client_sector'], 22) }}</div>
                                @endif
                            </td>
                            <td class="c">{{ $p['campaign_start'] ? \Carbon\Carbon::parse($p['campaign_start'])->format('d/m/y') : '—' }}</td>
                            <td class="c">{{ $p['campaign_end']   ? \Carbon\Carbon::parse($p['campaign_end'])->format('d/m/y')   : '—' }}</td>
                            <td class="r">
                                <strong>{{ $p['duration_label'] }}</strong>
                                @if($p['decapped_at'])
                                    <div><span class="badge badge-decap">Décapé {{ \Carbon\Carbon::parse($p['decapped_at'])->format('d/m/y') }}</span></div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@empty
    <div class="ch-muted" style="text-align:center;font-style:italic;padding:40px">
        Aucune commune ne correspond aux filtres sélectionnés.
    </div>
@endforelse

</body>
</html>
