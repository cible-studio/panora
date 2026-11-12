{{-- Lot de piges (lien « pige_bundle », 2026-11-12) : les photos validées
     d'un axe, une par pied — le lien de l'e-mail envoyé au client quand le
     dernier pied de l'axe est validé (App\Services\Piges\AvisPigeValidee).
     Même mise en page que public/pige. Paramètres : $campaign, $piges, $axe, $link. --}}
@extends('public.layout')
@php
    $nomAxe = $axe ? app(\App\Services\Axes\AxeService::class)->nomCourt($axe->name) : null;
    $titre = $axe ? 'Axe ' . $axe->reference : 'Piges photo';
@endphp
@section('title', $titre . ' — ' . ($campaign->name ?? 'CIBLE CI'))
@section('content')
<div class="card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
        <span class="badge badge-green">✅ Validées</span>
        <span class="small">{{ $piges->count() }} photo{{ $piges->count() > 1 ? 's' : '' }}</span>
    </div>
    <h1>{{ $titre }}@if($nomAxe) — {{ $nomAxe }}@endif</h1>
    <p class="muted">Campagne « {{ $campaign->name ?? '—' }} » · {{ $campaign->client?->name ?? '' }}</p>

    @forelse($piges as $pige)
        <div style="margin:22px 0 0">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:14px;margin-bottom:8px">
                <strong>
                    @if($axe && $pige->panel?->pied_numero) Pied {{ $pige->panel->pied_numero }} · @endif
                    <span style="font-family:ui-monospace,monospace">{{ $pige->panel?->reference }}</span>
                </strong>
                <span class="small">
                    {{ $pige->panel?->commune?->name }}{{ $pige->taken_at ? ' · ' . $pige->taken_at->format('d/m/Y') : '' }}
                    @if($pige->gps_lat && $pige->gps_lng)
                        · <a href="https://www.google.com/maps?q={{ $pige->gps_lat }},{{ $pige->gps_lng }}" target="_blank" rel="noopener" style="color:var(--accent-dark);text-decoration:none;font-weight:700">📍 carte</a>
                    @endif
                </span>
            </div>
            @if($pige->photo_path)
                <img src="{{ asset('storage/'.$pige->photo_path) }}" alt="Photo {{ $pige->panel?->reference }}"
                     style="max-width:100%;border-radius:10px;border:1px solid var(--border)">
            @endif
        </div>
    @empty
        <p class="muted" style="margin-top:20px">Aucune photo validée à afficher.</p>
    @endforelse
</div>
@endsection
