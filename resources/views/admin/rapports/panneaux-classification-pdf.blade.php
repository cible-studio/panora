<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Classification panneaux — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 12mm 10mm 26mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; line-height: 1.4; padding-bottom: 4mm; }
    h1 { font-size: 16px; color: #e8a020; margin: 0 0 4px; }
    h2 { font-size: 12px; margin: 14px 0 6px; padding: 5px 8px; color: #fff; border-radius: 3px; }
    .header { display: table; width: 100%; margin-bottom: 12px; }
    .header .left { display: table-cell; vertical-align: middle; }
    .header .right { display: table-cell; vertical-align: middle; text-align: right; font-size: 8.5px; color: #6b7280; }
    .period { font-size: 10px; color: #6b7280; margin-top: 2px; }
    .meta { font-size: 9px; color: #374151; margin-top: 8px; padding: 6px 10px; background: #fafafa; border-left: 3px solid #e8a020; border-radius: 3px; }
    .synth { display: table; width: 100%; margin: 10px 0 4px; border-collapse: separate; border-spacing: 6px 0; }
    .synth .cell { display: table-cell; padding: 8px 10px; border-radius: 4px; text-align: center; vertical-align: middle; }
    .synth .value { font-size: 20px; font-weight: bold; color: #fff; line-height: 1; }
    .synth .label { font-size: 8px; color: rgba(255,255,255,.85); margin-top: 3px; text-transform: uppercase; letter-spacing: .3px; }
    table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
    th { padding: 6px 8px; text-align: left; font-size: 8px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 0.4px; }
    th.r, td.r { text-align: right; }
    td { padding: 5px 8px; font-size: 8.5px; border-bottom: 1px solid #f3f4f6; }
    tr:nth-child(even) td { background: #fafafa; }
    .ref { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; }
    .badge-zone { display: inline-block; padding: 1px 6px; font-size: 8px; border-radius: 999px; }
    .badge-abj { background: #dbeafe; color: #1d4ed8; }
    .badge-int { background: #d1fae5; color: #047857; }
    .footer { position: fixed; bottom: 6mm; left: 10mm; right: 10mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; background: #fff; }
    .footer .pagenum:before { content: counter(page) " / " counter(pages); }
    .empty { padding: 12px; text-align: center; color: #9ca3af; font-style: italic; background: #fafafa; border-radius: 3px; }
</style>
</head>
<body>

<div class="header">
    <div class="left">
        <h1>CLASSIFICATION DES PANNEAUX PAR OCCUPATION</h1>
        <div class="period">
            Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
            · CIBLE CI
        </div>
    </div>
    <div class="right">
        Édité le {{ now()->format('d/m/Y H:i') }}<br>
        Par {{ $user?->name ?? '—' }}
    </div>
</div>

<div class="meta">
    <strong>Périmètre :</strong> {{ $classification['total'] }} panneaux analysés
    · <strong>Exclus :</strong> {{ $classification['excluded_count'] }} (chevalets + murales)
    · <strong>Règle :</strong> à l'année = ≥ 365 j occupés, intermédiaire = 1-364 j, jamais = 0 j
</div>

{{-- Synthèse 3 buckets --}}
<div class="synth">
    <div class="cell" style="background:#22c55e">
        <div class="value">{{ $classification['a_lannee']->count() }}</div>
        <div class="label">À l'année (≥ 365 j)</div>
    </div>
    <div class="cell" style="background:#f97316">
        <div class="value">{{ $classification['intermediaire']->count() }}</div>
        <div class="label">Intermédiaire (1-364 j)</div>
    </div>
    <div class="cell" style="background:#ef4444">
        <div class="value">{{ $classification['jamais']->count() }}</div>
        <div class="label">Jamais occupés (0 j)</div>
    </div>
</div>

@php
    $sections = [
        ['key' => 'a_lannee',      'label' => "🟢 Panneaux occupés à l'année",           'color' => '#16a34a'],
        ['key' => 'intermediaire', 'label' => '🟡 Panneaux occupation intermédiaire',    'color' => '#ea580c'],
        ['key' => 'jamais',        'label' => '🔴 Panneaux jamais occupés sur la période','color' => '#dc2626'],
    ];
@endphp

@foreach($sections as $section)
    @php $list = $classification[$section['key']]; @endphp
    <h2 style="background:{{ $section['color'] }}">
        {{ $section['label'] }} ({{ $list->count() }})
    </h2>

    @if($list->isEmpty())
        <div class="empty">Aucun panneau dans cette catégorie.</div>
    @else
        <table>
            <thead>
                <tr style="background:#0a0c10">
                    <th style="width:70px">Référence</th>
                    <th>Emplacement</th>
                    <th style="width:100px">Commune</th>
                    <th style="width:60px">Zone</th>
                    <th class="r" style="width:70px">Jours</th>
                    <th class="r" style="width:60px">Taux</th>
                    <th class="r" style="width:60px">Camp.</th>
                    <th class="r" style="width:90px">CA (FCFA)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($list as $p)
                    <tr>
                        <td class="ref">{{ $p->reference }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($p->name ?? '—', 55) }}</td>
                        <td>{{ $p->commune_name ?? '—' }}</td>
                        <td>
                            @if($p->zone === 'Abidjan')
                                <span class="badge-zone badge-abj">Abidjan</span>
                            @else
                                <span class="badge-zone badge-int">Intérieur</span>
                            @endif
                        </td>
                        <td class="r"><strong>{{ (int) $p->days_occupied }}</strong></td>
                        <td class="r">{{ number_format((float) $p->occupation_rate, 1, ',', ' ') }} %</td>
                        <td class="r">{{ (int) $p->campaigns_count }}</td>
                        <td class="r">{{ number_format((float) $p->estimated_revenue, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endforeach

<div class="footer">
    CIBLE SARL — Régie OOH Côte d'Ivoire · Document généré automatiquement par Panora · Page <span class="pagenum"></span>
</div>

</body>
</html>
