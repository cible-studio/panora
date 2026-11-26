@extends('public.layout')
@section('title', 'Facture '.($invoice->reference ?? '').' — CIBLE CI')
@section('content')
<div class="card">
    @php
        // 2026-11-26 — Libellés officiels (Invoice::STATUS_LABELS, « Soldée »
        // et non « Payée ») et les 9 statuts, pas seulement 4.
        $couleurs = [
            'payee' => '#16a34a', 'partiellement_payee' => '#d97706', 'en_retard' => '#dc2626',
            'litige' => '#b45309', 'annulee' => '#dc2626', 'envoyee' => '#3b82f6',
            'validee' => '#3b82f6', 'generee' => '#6b7280', 'brouillon' => '#6b7280',
        ];
        $col   = $couleurs[$invoice->status] ?? '#6b7280';
        $label = \App\Models\Invoice::statusLabel($invoice->status);
        $tx    = $invoice->totauxAffiches();
        $f     = fn ($n) => number_format((int) $n, 0, ',', ' ');
    @endphp
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <h1>Facture {{ $invoice->reference }}</h1>
        <span class="badge" style="background:{{ $col }}22;color:{{ $col }}">{{ strtoupper($label) }}</span>
    </div>
    <p class="muted">Émise le {{ $invoice->issued_at?->format('d/m/Y') ?? $invoice->created_at->format('d/m/Y') }}
        @if($invoice->paid_at) · Soldée le {{ $invoice->paid_at->format('d/m/Y') }}@endif
    </p>

    <h2>Destinataire</h2>
    <div style="background:var(--bg);border-radius:10px;padding:14px;font-size:13px;line-height:1.7">
        {{-- Identité figée à l'émission (2026-10-30). --}}
        @php $identite = $invoice->identiteClient(); @endphp
        <strong>{{ $identite['nom'] ?? '—' }}</strong><br>
        @if($identite['ncc'])NCC : {{ $identite['ncc'] }}<br>@endif
        @if($identite['adresse']){{ $identite['adresse'] }}<br>@endif
        @if($invoice->client?->email){{ $invoice->client->email }}@endif
    </div>

    @if($invoice->campaign)
    <h2>Campagne associée</h2>
    <div style="background:var(--bg);border-radius:10px;padding:14px;font-size:13px">
        {{ $invoice->campaign->name ?? '—' }}
        @if($invoice->campaign->start_date && $invoice->campaign->end_date)
            <br><span class="small">{{ \Carbon\Carbon::parse($invoice->campaign->start_date)->format('d/m/Y') }}
            → {{ \Carbon\Carbon::parse($invoice->campaign->end_date)->format('d/m/Y') }}</span>
        @endif
    </div>
    @endif

    <h2>Détail</h2>
    <table>
        <tr><th>Description</th><th style="text-align:right">Montant</th></tr>
        <tr><td>Total HT{{ $tx['remise'] > 0 ? ' après remise' : '' }}</td><td style="text-align:right">{{ $f($tx['net_ht'] + $tx['services_ht']) }} FCFA</td></tr>
        <tr><td>TVA ({{ rtrim(rtrim(number_format($tx['taux_tva'], 2, ',', ''), '0'), ',') }} %)</td><td style="text-align:right">{{ $f($tx['tva']) }} FCFA</td></tr>
        <tr><td>Total TTC</td><td style="text-align:right">{{ $f($tx['ttc']) }} FCFA</td></tr>
        @if($tx['autres_taxes'] > 0)
        <tr><td>Autres taxes (ODP, TM, TSP)</td><td style="text-align:right">{{ $f($tx['autres_taxes']) }} FCFA</td></tr>
        @endif
        <tr><td style="font-weight:700;font-size:14px">Total à payer</td>
            <td style="text-align:right;font-weight:700;color:var(--green);font-size:16px">{{ $f($tx['total_a_payer']) }} FCFA</td></tr>
    </table>

    @if($invoice->status !== 'payee' && $invoice->status !== 'annulee')
    <div style="margin-top:22px;padding:14px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:10px;font-size:13px;line-height:1.6">
        💳 <strong>Modalités de règlement</strong><br>
        Virement bancaire ou Mobile Money (Orange / Moov / MTN). Pour les détails de paiement, contactez-nous à
        <a href="mailto:contact@cible-ci.com" style="color:var(--accent-dark);font-weight:700">contact@cible-ci.com</a>.
    </div>
    @endif
</div>
@endsection
