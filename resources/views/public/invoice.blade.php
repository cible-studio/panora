@extends('public.layout')
@section('title', 'Facture '.($invoice->reference ?? '').' — CIBLE CI')
@section('content')
<div class="card">
    @php
        $statusColors = [
            'brouillon' => ['#6b7280', 'Brouillon'],
            'envoyee'   => ['#3b82f6', 'Envoyée'],
            'payee'     => ['#16a34a', 'Payée'],
            'annulee'   => ['#dc2626', 'Annulée'],
        ];
        [$col, $label] = $statusColors[$invoice->status] ?? ['#6b7280', $invoice->status];
    @endphp
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <h1>Facture {{ $invoice->reference }}</h1>
        <span class="badge" style="background:{{ $col }}22;color:{{ $col }}">{{ strtoupper($label) }}</span>
    </div>
    <p class="muted">Émise le {{ $invoice->issued_at?->format('d/m/Y') ?? $invoice->created_at->format('d/m/Y') }}
        @if($invoice->paid_at) · Payée le {{ $invoice->paid_at->format('d/m/Y') }}@endif
    </p>

    <h2>Destinataire</h2>
    <div style="background:var(--bg);border-radius:10px;padding:14px;font-size:13px;line-height:1.7">
        <strong>{{ $invoice->client?->name ?? '—' }}</strong><br>
        @if($invoice->client?->ncc)NCC : {{ $invoice->client->ncc }}<br>@endif
        @if($invoice->client?->address){{ $invoice->client->address }}<br>@endif
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
        <tr><td>Montant HT</td><td style="text-align:right">{{ number_format($invoice->amount, 0, ',', ' ') }} FCFA</td></tr>
        <tr><td>TVA</td><td style="text-align:right">{{ number_format($invoice->tva, 0, ',', ' ') }} FCFA</td></tr>
        <tr><td style="font-weight:700;font-size:14px">Total TTC</td>
            <td style="text-align:right;font-weight:700;color:var(--green);font-size:16px">{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA</td></tr>
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
