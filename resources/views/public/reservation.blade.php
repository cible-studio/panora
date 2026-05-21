@extends('public.layout')
@section('title', 'Réservation '.$reservation->reference.' — CIBLE CI')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <h1>Réservation {{ $reservation->reference }}</h1>
        <span class="badge badge-green">✅ CONFIRMÉE</span>
    </div>
    <p class="muted">
        Période : {{ \Carbon\Carbon::parse($reservation->start_date)->format('d/m/Y') }}
        → {{ \Carbon\Carbon::parse($reservation->end_date)->format('d/m/Y') }}
    </p>

    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-label">Panneaux</div>
            <div class="kpi-value">{{ $reservation->reservationPanels?->count() ?? 0 }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Durée</div>
            <div class="kpi-value">{{ (int) \Carbon\Carbon::parse($reservation->start_date)->diffInDays(\Carbon\Carbon::parse($reservation->end_date)) + 1 }}j</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Montant total</div>
            <div class="kpi-value" style="color:var(--green)">{{ number_format($reservation->total_amount ?? 0, 0, ',', ' ') }} <span style="font-size:11px;color:var(--text3);font-weight:400">FCFA</span></div>
        </div>
    </div>

    <h2>Panneaux réservés</h2>
    <table>
        <thead>
            <tr><th>Référence</th><th>Commune</th><th style="text-align:right">Prix</th></tr>
        </thead>
        <tbody>
            @forelse($reservation->reservationPanels ?? [] as $rp)
                <tr>
                    <td><strong style="font-family:ui-monospace,monospace">{{ $rp->panel?->reference ?? '—' }}</strong></td>
                    <td>{{ $rp->panel?->commune?->name ?? '—' }}</td>
                    <td style="text-align:right;font-weight:600">{{ number_format($rp->total_price ?? 0, 0, ',', ' ') }} FCFA</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;color:var(--text3)">Aucun panneau</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:22px;padding:14px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);border-radius:10px;font-size:13px;line-height:1.6">
        ✅ <strong>Prochaines étapes</strong><br>
        Votre commercial vous contactera pour finaliser la pose. Vous recevrez les piges photo une fois les panneaux installés.
    </div>
</div>
@endsection
