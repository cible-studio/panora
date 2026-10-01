<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Historique des relances — CIBLE CI</title>
{{-- 2026-10-01 — charte graphique : socle commun (polices, palette, classes ch-*). --}}
@include('pdf.partials.charte-styles')
<style>
    /* Marge basse ≥ 18 mm : réserve la place du pied fixe charte-footer. */
    @page { size: A4 landscape; margin: 12mm 10mm 18mm 10mm !important; }
    body { font-size: 9px; color: {{ $charte['noir'] }}; line-height: 1.2; }
    .meta { font-size: 9px; color: {{ $charte['texte_doux'] }}; margin: 0 0 12px; }
    .meta strong { color: {{ $charte['noir'] }}; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    th { background: {{ $charte['noir'] }}; padding: 5px 7px; text-align: left; font-family: {!! $charte['ff_titres'] !!}; font-size: 7.5px; font-weight: 700; color: {{ $charte['blanc'] }}; text-transform: uppercase; letter-spacing: 0.4px; }
    td { padding: 4px 7px; font-size: 8.5px; border-bottom: 1px solid {{ $charte['gris'] }}; vertical-align: top; }
    tr:nth-child(even) td { background: {{ $charte['gris_clair'] }}; }
    .ref { font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['rouge'] }}; }
    .client-block { margin-bottom: 14px; page-break-inside: avoid; }
    /* Bandeau client : table (pas de flex) — fond clair, accent rouge à gauche. */
    table.client-head { margin-bottom: 4px; }
    table.client-head td { padding: 6px 10px; background: {{ $charte['gris_clair'] }}; border-bottom: none; vertical-align: middle; }
    table.client-head td.cn { font-family: {!! $charte['ff_titres'] !!}; font-weight: 700; font-size: 11px; color: {{ $charte['noir'] }}; border-left: 3px solid {{ $charte['rouge'] }}; }
    table.client-head td.cm { text-align: right; font-size: 9px; color: {{ $charte['texte_doux'] }}; white-space: nowrap; }
    .empty { text-align: center; padding: 30px; color: {{ $charte['texte_doux'] }}; font-style: italic; font-size: 11px; }
</style>
</head>
<body>

{{-- 2026-10-01 — charte graphique : en-tête commun (liseré + logo + titre). --}}
@include('pdf.partials.charte-header', [
    'docTitle'    => 'HISTORIQUE DES RELANCES',
    'docSubtitle' => 'Suivi recouvrement · ' . ($operatorName ?? 'CIBLE CI'),
    'docMeta'     => [
        'Édité le ' . now()->format('d/m/Y H:i'),
        'Par ' . ($user->name ?? '—'),
        'Réf. ' . strtoupper(substr(md5(now()), 0, 8)),
    ],
])

<div class="ch-info meta">
    @if(!empty($filterRecapLine))
        {{ $filterRecapLine }} ·
    @endif
    <strong>{{ $byClient->count() }}</strong> client(s) ·
    <strong>{{ $totalRelances }}</strong> relance(s) au total
</div>

@php
    // 2026-10-01 — charte graphique : badges ch-badge-* (couleurs pleines de la charte).
    $outcomeCfg = [
        'promesse_paiement' => ['l' => 'Promesse paiement', 'cls' => 'ch-badge-vert'],
        'paiement_recu'     => ['l' => 'Paiement reçu',     'cls' => 'ch-badge-vert'],
        'a_relancer'        => ['l' => 'À relancer',        'cls' => 'ch-badge-jaune'],
        'sans_reponse'      => ['l' => 'Sans réponse',      'cls' => 'ch-badge-gris'],
        'desaccord'         => ['l' => 'Désaccord',         'cls' => 'ch-badge-rouge'],
        'autre'             => ['l' => 'Autre',             'cls' => 'ch-badge-violet'],
    ];
@endphp

@forelse($byClient as $clientId => $relances)
    @php $clt = $relances->first()->client; @endphp
    <div class="client-block">
        <table class="client-head">
            <tr>
                <td class="cn">{{ $clt?->name ?? '— Client supprimé' }}{{ $clt?->phone ? ' · ' . $clt->phone : '' }}</td>
                <td class="cm">{{ $relances->count() }} relance(s)</td>
            </tr>
        </table>
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
                        {{-- 2026-10-01 — charte graphique : le pictogramme emoji en tête du
                             libellé (Relance::CANAUX) s'affiche en carré vide dans le PDF → retiré
                             à l'affichage seulement (le libellé texte est conservé). --}}
                        <td>{{ trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', \App\Models\Relance::CANAUX[$r->canal] ?? $r->canal)) }}</td>
                        <td>
                            @if($oc)
                                <span class="ch-badge {{ $oc['cls'] }}">{{ $oc['l'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="ref">
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
    <div class="empty">
        Aucune relance ne correspond aux filtres.
    </div>
@endforelse

{{-- 2026-10-01 — charte graphique : pied fixe commun ; ancienne mention conservée en 2e ligne. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE SARL — Régie OOH Côte d\'Ivoire · Document généré automatiquement par Panora.',
])

</body>
</html>
