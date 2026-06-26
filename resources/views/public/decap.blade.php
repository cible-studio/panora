@extends('public.layout')
@section('title', 'Décapage — '. $campaign->name)
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <h1>Campagne « {{ $campaign->name }} »</h1>
        <span class="badge badge-green">✅ DÉCAPÉE</span>
    </div>
    <p class="muted">{{ $campaign->client?->name ?? '' }}
        @if($campaign->end_date) · Terminée le {{ \Carbon\Carbon::parse($campaign->end_date)->format('d/m/Y') }}@endif
    </p>

    @php
        $totalPanels    = $campaign->panels?->count() ?? 0;
        $decappedPanels = $campaign->panels?->filter(fn($p) => $p->pivot?->decapped_at)->count() ?? 0;
        $durationDays   = $campaign->start_date && $campaign->end_date
            ? (int) \Carbon\Carbon::parse($campaign->start_date)->diffInDays(\Carbon\Carbon::parse($campaign->end_date)) + 1
            : 0;
    @endphp

    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-label">Panneaux décapés</div>
            <div class="kpi-value" style="color:var(--green)">{{ $decappedPanels }}/{{ $totalPanels }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Durée</div>
            <div class="kpi-value">{{ $durationDays }}j</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Communes</div>
            <div class="kpi-value">{{ $campaign->panels?->pluck('commune_id')->filter()->unique()->count() ?? 0 }}</div>
        </div>
    </div>

    <h2>Panneaux et statut</h2>
    <table>
        <thead>
            <tr><th>Référence</th><th>Commune</th><th>Décapé</th></tr>
        </thead>
        <tbody>
            @forelse($campaign->panels ?? [] as $panel)
                @php $decAt = $panel->pivot?->decapped_at ?? null; @endphp
                <tr>
                    <td><strong style="font-family:ui-monospace,monospace">{{ $panel->reference }}</strong></td>
                    <td>{{ $panel->commune?->name ?? '—' }}</td>
                    <td>
                        @if($decAt)
                            <span style="color:var(--green);font-weight:700;font-size:11px">✓ {{ \Carbon\Carbon::parse($decAt)->format('d/m/Y') }}</span>
                        @else
                            <span style="color:var(--text3);font-size:11px">En attente</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;color:var(--text3)">Aucun panneau</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:22px;padding:14px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);border-radius:10px;font-size:13px;line-height:1.6">
        🙏 <strong>Merci de votre confiance.</strong><br>
        Pour vos prochaines campagnes, contactez-nous à <a href="mailto:contact@cible-ci.com" style="color:var(--accent-dark);font-weight:700">contact@cible-ci.com</a>.
    </div>
</div>
@endsection
