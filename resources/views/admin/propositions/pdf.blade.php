<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Proposition {{ $reservation->reference }}</title>
@include('pdf.partials.charte-styles')
{{-- 2026-10-01 — charte graphique : l'ancien bandeau foncé est remplacé
     par l'en-tête clair commun (liseré + logo CIBLE + titre + méta), le
     pied de fin de document par le pied fixe commun (même texte reporté
     en 2e ligne). Palette $charte, polices Poppins / Nunito, marges @page
     avec !important. --}}
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    @page { size: A4 portrait; margin: 12mm 13mm 18mm 13mm !important; }

    body {
        font-size: 11px;
        line-height: 1.25;
        color: {{ $charte['noir'] }};
        background: {{ $charte['blanc'] }};
    }

    /* CONTENT ────────────────────────────────────── */
    .content { padding: 0; }

    .grid-2 {
        display: table; width: 100%; border-collapse: separate; border-spacing: 12px 0;
        margin-bottom: 16px;
    }
    .grid-2 .col { display: table-cell; width: 50%; vertical-align: top; }

    .section {
        background: {{ $charte['gris_clair'] }}; border: 1px solid {{ $charte['gris'] }};
        border-top: 3px solid {{ $charte['rouge'] }};
        border-radius: 4px; padding: 12px 16px; margin-bottom: 14px;
    }
    .section-title {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 9px; font-weight: 700; color: {{ $charte['rouge'] }};
        text-transform: uppercase; letter-spacing: 1.2px;
        margin-bottom: 9px; padding-bottom: 5px;
        border-bottom: 1px solid {{ $charte['gris'] }};
    }
    .row { margin-bottom: 6px; }
    .row .lbl { font-size: 8px; color: {{ $charte['texte_doux'] }}; text-transform: uppercase; letter-spacing: .8px; }
    .row .val { font-size: 11px; font-weight: 600; color: {{ $charte['noir'] }}; }

    /* PANELS TABLE ───────────────────────────────── */
    .panels {
        width: 100%; border-collapse: collapse; margin-top: 4px;
        font-size: 10px;
    }
    .panels thead th {
        background: {{ $charte['noir'] }}; color: {{ $charte['blanc'] }};
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 7.5px; font-weight: 600;
        text-transform: uppercase; letter-spacing: .6px;
        padding: 7px 8px; text-align: left;
    }
    .panels tbody td {
        padding: 7px 8px; border-bottom: 1px solid {{ $charte['gris'] }};
        vertical-align: top; line-height: 1.15; background: {{ $charte['blanc'] }};
    }
    .panels tbody tr:nth-child(even) td { background: {{ $charte['gris_clair'] }}; }
    .panels .ref {
        font-family: {!! $charte['ff_mono'] !!}; font-weight: 700;
        color: {{ $charte['rouge'] }}; font-size: 9px;
    }
    .panels .panel-name { font-weight: 600; color: {{ $charte['noir'] }}; }
    .panels .panel-sub { font-size: 8.5px; color: {{ $charte['texte_doux'] }}; margin-top: 2px; }
    .panels .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .panels thead th.num { text-align: right; white-space: normal; }
    .panels .badge-ext {
        display: inline-block; padding: 1px 5px;
        border-radius: 3px; font-size: 7.5px; font-weight: 700;
        background: {{ $charte['bleu'] }}; color: {{ $charte['blanc'] }}; margin-left: 4px;
    }
    .panels .vide { text-align:center; color: {{ $charte['texte_pale'] }}; padding:18px; }

    /* TOTALS ─────────────────────────────────────── */
    .totals {
        margin-top: 12px; width: 60%; margin-left: 40%;
        border-collapse: collapse;
    }
    .totals td { padding: 7px 12px; font-size: 11px; }
    .totals .lbl { color: {{ $charte['texte_doux'] }}; text-align: right; }
    .totals .val { font-weight: 700; text-align: right; min-width: 110px; }
    .totals .grand-total td {
        background: {{ $charte['noir'] }}; color: {{ $charte['jaune'] }};
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 13px; font-weight: 800;
    }
    .totals .grand-total td.lbl { color: {{ $charte['gris'] }}; }
    .badge-offert {
        display: inline-block; font-size: 9px; font-weight: 700; padding: 2px 7px;
        border-radius: 9px; background: {{ $charte['vert'] }}; color: {{ $charte['blanc'] }};
        letter-spacing: .4px; margin-left: 6px;
    }

    /* CONDITIONS ─────────────────────────────────── */
    .conditions {
        background: {{ $charte['jaune_clair'] }};
        border: 1px solid {{ $charte['jaune'] }};
        border-left: 4px solid {{ $charte['jaune'] }};
        border-radius: 4px; padding: 11px 16px;
        font-size: 11px; color: {{ $charte['noir'] }}; line-height: 1.35;
        margin-top: 14px;
    }
    .conditions strong { color: {{ $charte['noir'] }}; }

    .notes-texte { font-size: 10.5px; color: {{ $charte['texte_doux'] }}; line-height: 1.4; white-space: pre-line; }
</style>
</head>
<body>

@php
    // total_amount === null → ancienne réservation, on recompose depuis
    // la projection unifiée. total_amount === 0 → choix explicite
    // (campagne offerte) → on respecte 0, pas de fallback.
    $totalAmount = $reservation->total_amount === null
        ? $panels->sum(fn($p) => (float) ($p['total'] ?? 0))
        : (float) $reservation->total_amount;
    $isOffert = $reservation->total_amount !== null && (float) $reservation->total_amount === 0.0;

    // En-tête (charte) : toutes les informations de l'ancien bandeau.
    $metaEntete = ["Date d'émission : " . ($reservation->proposition_sent_at ?? $reservation->created_at)->format('d/m/Y')];
    if ($reservation->proposition_expires_at) {
        $metaEntete[] = "Valable jusqu'au : " . $reservation->proposition_expires_at->format('d/m/Y');
    }
@endphp

{{-- Pied fixe commun : le texte de l'ancien pied de fin de document est
     reporté en 2e ligne. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE CI — Régie publicitaire OOH · Abidjan, Côte d\'Ivoire · Document émis le '
        . now()->format('d/m/Y \à H:i') . ' · Référence : ' . $reservation->reference,
])

{{-- 2026-06-18 (feedback patronne : logo CIBLE sur TOUS les PDF).
     Variable injectée par AppServiceProvider::boot() View::composer.
     2026-10-01 — charte : en-tête clair commun, logo $logoCibleLight. --}}
@include('pdf.partials.charte-header', [
    'docKicker'   => "Régie OOH — Côte d'Ivoire",
    'docTitle'    => 'Proposition commerciale',
    'docSubtitle' => $reservation->reference . ' · Client : ' . ($reservation->client?->name ?? '—'),
    'docMeta'     => $metaEntete,
])

<div class="content">

    {{-- ─── Bloc client + période ─── --}}
    <div class="grid-2">
        <div class="col">
            <div class="section">
                <div class="section-title">Client</div>
                <div class="row">
                    <div class="val">{{ $reservation->client?->name ?? '—' }}</div>
                </div>
                @if($reservation->client?->contact_person)
                <div class="row">
                    <span class="lbl">Contact :</span>
                    <span class="val" style="font-weight:500">{{ $reservation->client->contact_person }}</span>
                </div>
                @endif
                @if($reservation->client?->email)
                <div class="row">
                    <span class="lbl">Email :</span>
                    <span class="val" style="font-weight:500">{{ $reservation->client->email }}</span>
                </div>
                @endif
                @if($reservation->client?->phone)
                <div class="row">
                    <span class="lbl">Tél :</span>
                    <span class="val" style="font-weight:500">{{ $reservation->client->phone }}</span>
                </div>
                @endif
            </div>
        </div>
        <div class="col">
            <div class="section">
                <div class="section-title">Période d'affichage</div>
                <div class="row">
                    <div class="lbl">Du</div>
                    <div class="val">{{ $reservation->start_date->format('d/m/Y') }}</div>
                </div>
                <div class="row">
                    <div class="lbl">Au</div>
                    <div class="val">{{ $reservation->end_date->format('d/m/Y') }}</div>
                </div>
                <div class="row">
                    <div class="lbl">Durée facturée</div>
                    <div class="val">
                        {{ rtrim(rtrim(number_format($months, 2, ',', ''), '0'), ',') }}
                        mois{{ $months > 1 ? '' : '' }}
                    </div>
                </div>
                <div class="row">
                    <div class="lbl">Emplacements</div>
                    <div class="val">{{ count($panels) }} panneau{{ count($panels) > 1 ? 'x' : '' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Tableau des panneaux ─── --}}
    <div class="section" style="padding:0;">
        <div style="padding:12px 16px 8px;">
            <div class="section-title" style="border-bottom:0;margin-bottom:0;padding-bottom:0;">
                Détail des emplacements
            </div>
        </div>

        <table class="panels">
            <thead>
                <tr>
                    <th style="width:14%;">Réf.</th>
                    <th style="width:30%;">Emplacement</th>
                    <th style="width:18%;">Commune / Zone</th>
                    <th style="width:18%;">Format</th>
                    <th style="width:10%;" class="num">PU mensuel (estimatif)</th>
                    <th style="width:10%;" class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($panels as $p)
                <tr>
                    <td class="ref">
                        {{ $p['reference'] ?? '—' }}
                        @if(($p['source'] ?? 'interne') === 'externe')
                            <span class="badge-ext">EXT</span>
                        @endif
                    </td>
                    <td>
                        <div class="panel-name">{{ \Illuminate\Support\Str::limit($p['name'] ?? '—', 60) }}</div>
                        @if(!empty($p['category']) && $p['category'] !== '—')
                            <div class="panel-sub">{{ $p['category'] }}{{ $p['is_lit'] ?? false ? ' · Éclairé' : '' }}</div>
                        @elseif($p['is_lit'] ?? false)
                            <div class="panel-sub">Éclairé</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $p['commune'] ?? '—' }}</div>
                        @if(!empty($p['zone']) && $p['zone'] !== '—')
                            <div class="panel-sub">{{ $p['zone'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $p['format'] ?? '—' }}</div>
                        @if(!empty($p['dimensions']))
                            <div class="panel-sub">{{ $p['dimensions'] }}@if(!empty($p['surface'])) · {{ $p['surface'] }}@endif</div>
                        @endif
                    </td>
                    <td class="num">{{ number_format((float)($p['monthly_rate'] ?? 0), 0, ',', ' ') }}</td>
                    <td class="num"><strong>{{ number_format((float)($p['total'] ?? 0), 0, ',', ' ') }}</strong></td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="vide">
                        Aucun panneau associé à cette proposition.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ─── Totaux ─── --}}
    <table class="totals">
        <tr>
            <td class="lbl">Sous-total HT</td>
            <td class="val">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr class="grand-total">
            <td class="lbl">TOTAL HT</td>
            <td class="val">
                {{ number_format($totalAmount, 0, ',', ' ') }} FCFA
                @if($isOffert)
                    <span class="badge-offert">OFFERT</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- ─── Conditions ─── --}}
    <div class="conditions">
        <strong>Conditions :</strong>
        Proposition valable
        @if($reservation->proposition_expires_at)
            jusqu'au {{ $reservation->proposition_expires_at->format('d/m/Y') }}
        @else
            7 jours à compter de la date d'émission
        @endif.
        Tarifs hors taxes communales et coûts de production.
        Confirmation requise avant le démarrage de la campagne.
    </div>

    {{-- ─── Notes ─── --}}
    @if(!empty($reservation->notes))
    <div class="section" style="margin-top:14px;">
        <div class="section-title">Notes</div>
        <div class="notes-texte">{{ $reservation->notes }}</div>
    </div>
    @endif

</div>

</body>
</html>
