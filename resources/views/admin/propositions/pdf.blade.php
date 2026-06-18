<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Proposition {{ $reservation->reference }}</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    @page { margin: 0; size: A4 portrait; }

    body {
        font-family: 'DejaVu Sans', 'Helvetica', sans-serif;
        font-size: 11px;
        color: #1e293b;
        background: #ffffff;
    }

    /* HEADER ─────────────────────────────────────── */
    .header {
        background: #0a0c10;
        color: #fff;
        padding: 22px 30px;
        border-bottom: 4px solid #e8a020;
    }
    .header table { width: 100%; }
    .header td { vertical-align: top; }
    .logo { font-size: 22px; font-weight: 800; color: #e8a020; letter-spacing: -.5px; }
    .logo-sub {
        font-size: 9px; color: #8a90a2;
        text-transform: uppercase; letter-spacing: 2px; margin-top: 4px;
    }
    .doc-title { font-size: 13px; font-weight: 700; color: #fff; margin-top: 14px; }
    .doc-meta {
        margin-top: 8px;
        font-size: 11px;
        color: #cbd5e1;
        line-height: 1.5;
    }
    .doc-meta .ref-badge {
        display: inline-block;
        background: #e8a020; color: #0a0c10;
        padding: 3px 9px; border-radius: 4px;
        font-family: monospace, 'DejaVu Sans Mono', sans-serif;
        font-weight: 800; font-size: 11px;
        margin-right: 8px;
    }
    .doc-meta .client-name { color: #fff; font-weight: 700; }

    .header-right { text-align: right; font-size: 10px; color: #cbd5e1; line-height: 1.7; }
    .header-right strong { color: #fff; }

    /* CONTENT ────────────────────────────────────── */
    .content { padding: 22px 30px 30px; }

    .grid-2 {
        display: table; width: 100%; border-collapse: separate; border-spacing: 12px 0;
        margin-bottom: 18px;
    }
    .grid-2 .col { display: table-cell; width: 50%; vertical-align: top; }

    .section {
        background: #f8fafc; border: 1px solid #e2e8f0;
        border-radius: 6px; padding: 14px 16px; margin-bottom: 14px;
    }
    .section-title {
        font-size: 9px; font-weight: 700; color: #e8a020;
        text-transform: uppercase; letter-spacing: 1.2px;
        margin-bottom: 10px; padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    .row { margin-bottom: 6px; }
    .row .lbl { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: .8px; }
    .row .val { font-size: 11px; font-weight: 600; color: #0a0c10; }

    /* PANELS TABLE ───────────────────────────────── */
    .panels {
        width: 100%; border-collapse: collapse; margin-top: 4px;
        font-size: 10px;
    }
    .panels thead th {
        background: #0a0c10; color: #e8a020;
        font-size: 8.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 1px;
        padding: 7px 8px; text-align: left;
    }
    .panels tbody td {
        padding: 7px 8px; border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }
    .panels tbody tr:nth-child(even) td { background: #fafbfc; }
    .panels .ref {
        font-family: monospace; font-weight: 700;
        color: #e8a020; font-size: 9px;
    }
    .panels .panel-name { font-weight: 600; color: #0a0c10; }
    .panels .panel-sub { font-size: 8.5px; color: #64748b; margin-top: 2px; }
    .panels .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .panels .badge-ext {
        display: inline-block; padding: 1px 5px;
        border-radius: 3px; font-size: 7.5px; font-weight: 700;
        background: #dbeafe; color: #2563eb; margin-left: 4px;
    }

    /* TOTALS ─────────────────────────────────────── */
    .totals {
        margin-top: 12px; width: 60%; margin-left: 40%;
        border-collapse: collapse;
    }
    .totals td { padding: 7px 12px; font-size: 11px; }
    .totals .lbl { color: #64748b; text-align: right; }
    .totals .val { font-weight: 700; text-align: right; min-width: 110px; }
    .totals .grand-total td {
        background: #0a0c10; color: #e8a020;
        font-size: 13px; font-weight: 800;
    }

    /* CONDITIONS ─────────────────────────────────── */
    .conditions {
        background: #fff8e6; border: 1px solid #f3d488;
        border-radius: 6px; padding: 18px 20px;
        font-size: 11px; color: #6b4a13; line-height: 1.8;
        margin-top: 20px;
    }
    .conditions strong { color: #a06010; }

    /* FOOTER ─────────────────────────────────────── */
    .footer {
        margin-top: 18px;
        padding-top: 12px; border-top: 1px solid #e2e8f0;
        font-size: 8.5px; color: #94a3b8; text-align: center; line-height: 1.6;
    }
    .footer strong { color: #475569; }
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
@endphp

<div class="header">
    <table>
        <tr>
            <td>
                {{-- 2026-06-18 (feedback patronne : logo CIBLE sur TOUS les PDF).
                     Variable injectée par AppServiceProvider::boot() View::composer. --}}
                @if(!empty($logoCibleLight))
                    <img src="{{ $logoCibleLight }}" alt="CIBLE CI" style="height:38px;margin-bottom:6px;">
                @else
                    <div class="logo">CIBLE CI</div>
                @endif
                <div class="logo-sub">Régie OOH — Côte d'Ivoire</div>
                <div class="doc-title">Proposition commerciale</div>
                <div class="doc-meta">
                    <span class="ref-badge">{{ $reservation->reference }}</span>
                    Client&nbsp;: <span class="client-name">{{ $reservation->client?->name ?? '—' }}</span>
                </div>
            </td>
            <td class="header-right">
                <strong>Date d'émission</strong><br>
                {{ ($reservation->proposition_sent_at ?? $reservation->created_at)->format('d/m/Y') }}
                @if($reservation->proposition_expires_at)
                    <br><br>
                    <strong>Valable jusqu'au</strong><br>
                    {{ $reservation->proposition_expires_at->format('d/m/Y') }}
                @endif
            </td>
        </tr>
    </table>
</div>

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
        <div style="padding:14px 16px 8px;">
            <div class="section-title" style="border-bottom:0;margin-bottom:0;">
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
                    <td colspan="6" style="text-align:center;color:#94a3b8;padding:18px;">
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
            <td class="lbl" style="color:#cbd5e1;">TOTAL HT</td>
            <td class="val">
                {{ number_format($totalAmount, 0, ',', ' ') }} FCFA
                @if($isOffert)
                    <span style="display:inline-block;font-size:9px;font-weight:700;padding:2px 7px;border-radius:9px;background:rgba(34,197,94,0.2);color:#bbf7d0;letter-spacing:.4px;margin-left:6px;">OFFERT</span>
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
        <div style="font-size:10.5px;color:#475569;line-height:1.6;white-space:pre-line;">{{ $reservation->notes }}</div>
    </div>
    @endif

    <div class="footer">
        <strong>CIBLE CI</strong> — Régie publicitaire OOH · Abidjan, Côte d'Ivoire<br>
        Document émis le {{ now()->format('d/m/Y \à H:i') }} · Référence : {{ $reservation->reference }}
    </div>

</div>

</body>
</html>
