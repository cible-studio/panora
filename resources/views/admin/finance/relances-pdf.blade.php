<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Historique des relances — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 12mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; line-height: 1.4; }
    h1 { font-size: 16px; color: #e8a020; margin: 0 0 4px; }
    h2 { font-size: 11px; color: #111827; margin: 12px 0 6px; padding: 4px 8px; background: linear-gradient(90deg, rgba(232,160,32,.12), transparent); border-left: 3px solid #e8a020; }
    .header { display: table; width: 100%; margin-bottom: 12px; }
    .header .left  { display: table-cell; vertical-align: top; }
    .header .right { display: table-cell; vertical-align: top; text-align: right; font-size: 8.5px; color: #6b7280; }
    .period { font-size: 10px; color: #6b7280; margin-top: 2px; }
    .meta { font-size: 9px; color: #374151; margin: 6px 0 12px; padding: 6px 10px; background: #fafafa; border-left: 3px solid #e8a020; border-radius: 3px; }
    .meta strong { color: #111827; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    th { background: #0a0c10; padding: 5px 7px; text-align: left; font-size: 7.5px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 0.4px; }
    td { padding: 4px 7px; font-size: 8.5px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    tr:nth-child(even) td { background: #fafafa; }
    .client-block { margin-bottom: 14px; page-break-inside: avoid; }
    .client-head { display: table; width: 100%; padding: 6px 10px; background: #fff7e6; border: 1px solid #fed7aa; border-radius: 4px; margin-bottom: 4px; }
    .client-head .cn { display: table-cell; font-weight: bold; font-size: 11px; color: #9a3412; }
    .client-head .cm { display: table-cell; text-align: right; font-size: 9px; color: #6b7280; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 7.5px; font-weight: bold; }
    .b-promesse { background: rgba(34,197,94,.18); color: #15803d; }
    .b-recu     { background: rgba(34,197,94,.25); color: #15803d; }
    .b-relancer { background: rgba(245,158,11,.18); color: #b45309; }
    .b-sans     { background: rgba(107,114,128,.18); color: #4b5563; }
    .b-desaccord{ background: rgba(239,68,68,.18); color: #b91c1c; }
    .b-autre    { background: rgba(99,102,241,.18); color: #4338ca; }
    .footer { position: fixed; bottom: 4mm; left: 10mm; right: 10mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
</style>
</head>
<body>

<div class="header">
    <div class="left">
        <h1>HISTORIQUE DES RELANCES</h1>
        <div class="period">Suivi recouvrement · {{ $operatorName ?? 'CIBLE CI' }}</div>
    </div>
    <div class="right">
        Édité le {{ now()->format('d/m/Y H:i') }}<br>
        Par {{ $user->name ?? '—' }}<br>
        Réf. {{ strtoupper(substr(md5(now()), 0, 8)) }}
    </div>
</div>

<div class="meta">
    @if(!empty($filterRecapLine))
        {{ $filterRecapLine }} ·
    @endif
    <strong>{{ $byClient->count() }}</strong> client(s) ·
    <strong>{{ $totalRelances }}</strong> relance(s) au total
</div>

@php
    $outcomeCfg = [
        'promesse_paiement' => ['l' => 'Promesse paiement', 'cls' => 'b-promesse'],
        'paiement_recu'     => ['l' => 'Paiement reçu',     'cls' => 'b-recu'],
        'a_relancer'        => ['l' => 'À relancer',        'cls' => 'b-relancer'],
        'sans_reponse'      => ['l' => 'Sans réponse',      'cls' => 'b-sans'],
        'desaccord'         => ['l' => 'Désaccord',         'cls' => 'b-desaccord'],
        'autre'             => ['l' => 'Autre',             'cls' => 'b-autre'],
    ];
@endphp

@forelse($byClient as $clientId => $relances)
    @php $clt = $relances->first()->client; @endphp
    <div class="client-block">
        <div class="client-head">
            <div class="cn">{{ $clt?->name ?? '— Client supprimé' }}{{ $clt?->phone ? ' · ' . $clt->phone : '' }}</div>
            <div class="cm">{{ $relances->count() }} relance(s)</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:8%">Date</th>
                    <th style="width:11%">Canal</th>
                    <th style="width:12%">Résultat</th>
                    <th style="width:11%">Facture</th>
                    <th>Motif / Note</th>
                    <th style="width:15%">Suite à donner</th>
                    <th style="width:9%">Auteur</th>
                </tr>
            </thead>
            <tbody>
                @foreach($relances as $r)
                    @php $oc = $outcomeCfg[$r->outcome] ?? null; @endphp
                    <tr>
                        <td style="white-space:nowrap">{{ $r->relance_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ \App\Models\Relance::CANAUX[$r->canal] ?? $r->canal }}</td>
                        <td>
                            @if($oc)
                                <span class="badge {{ $oc['cls'] }}">{{ $oc['l'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td style="font-family:'Courier New',monospace;color:#b45309">
                            {{ $r->invoice?->reference ?? '— globale' }}
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($r->note ?? '—', 220) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($r->suite_donnee ?? '—', 110) }}</td>
                        <td>{{ $r->user?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div style="text-align:center;padding:30px;color:#6b7280;font-style:italic;font-size:11px">
        Aucune relance ne correspond aux filtres.
    </div>
@endforelse

<div class="footer">
    CIBLE SARL — Régie OOH Côte d'Ivoire · Document généré automatiquement par Panora.
</div>

</body>
</html>
