@extends('public.layout')
@section('title', 'Pige photo — '. ($pige->campaign?->name ?? 'CIBLE CI'))
@section('content')
<div class="card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
        <span class="badge badge-green">✅ Validée</span>
        <span class="small">le {{ $pige->verified_at?->format('d/m/Y') ?? '—' }}</span>
    </div>
    <h1>Pige photo — {{ $pige->panel?->reference ?? '—' }}</h1>
    <p class="muted">Campagne « {{ $pige->campaign?->name ?? '—' }} » · {{ $pige->campaign?->client?->name ?? '' }}</p>

    @if($pige->photo_path)
    <div style="margin:20px 0">
        <img src="{{ asset('storage/'.$pige->photo_path) }}" alt="Pige photo"
             style="max-width:100%;border-radius:10px;border:1px solid var(--border)">
    </div>
    @endif

    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-label">Panneau</div>
            <div class="kpi-value" style="font-family:ui-monospace,monospace;font-size:14px">{{ $pige->panel?->reference }}</div>
            <div class="small" style="margin-top:2px">{{ $pige->panel?->commune?->name ?? '' }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Date pose</div>
            <div class="kpi-value" style="font-size:14px">{{ $pige->taken_at?->format('d/m/Y') ?? '—' }}</div>
        </div>
        @if($pige->gps_lat && $pige->gps_lng)
        <div class="kpi">
            <div class="kpi-label">Géolocalisation</div>
            <div style="font-size:12px;margin-top:6px">
                <a href="https://www.google.com/maps?q={{ $pige->gps_lat }},{{ $pige->gps_lng }}" target="_blank" rel="noopener" style="color:var(--accent-dark);text-decoration:none;font-weight:700">
                    📍 Voir sur carte
                </a>
            </div>
        </div>
        @endif
    </div>

    @if($pige->notes)
    <div style="margin-top:14px;padding:12px;background:var(--bg);border-radius:8px;font-size:13px">
        <strong>Note du technicien :</strong> {{ $pige->notes }}
    </div>
    @endif
</div>
@endsection
