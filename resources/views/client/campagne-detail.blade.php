@extends('client.layout')
@section('title', $campaign->name)
@section('page-title', $campaign->name)

@section('content')
<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
    <a href="{{ route('client.dashboard') }}" class="hover:text-[#e8a020] transition-colors">Accueil</a>
    <span>›</span>
    <a href="{{ route('client.campagnes') }}" class="hover:text-[#e8a020] transition-colors">Campagnes</a>
    <span>›</span>
    <span class="text-gray-400 truncate">{{ $campaign->name }}</span>
</div>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white mb-1">{{ $campaign->name }}</h1>
        <p class="text-sm text-gray-500">Campagne #{{ $campaign->id }}</p>
    </div>
    @php 
        $badgeClass = match($campaign->status->value) {
            'actif' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
            'pose' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
            'termine' => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
            'annule' => 'bg-red-500/20 text-red-400 border-red-500/30',
            default => 'bg-gray-500/20 text-gray-400 border-gray-500/30',
        };
    @endphp
    <span class="inline-block px-4 py-2 rounded-full text-sm font-medium border {{ $badgeClass }} w-fit">
        {{ ucfirst($campaign->status->value) }}
    </span>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <div class="bg-[#11131f] rounded-xl border border-white/5 p-4">
        <div class="text-2xl font-bold text-white">{{ $campaign->start_date->format('d/m/Y') }}</div>
        <div class="text-xs text-gray-500 mt-1">Date de début</div>
    </div>
    <div class="bg-[#11131f] rounded-xl border border-white/5 p-4">
        <div class="text-2xl font-bold text-white">{{ $campaign->end_date->format('d/m/Y') }}</div>
        <div class="text-xs text-gray-500 mt-1">Date de fin</div>
    </div>
    <div class="bg-[#11131f] rounded-xl border border-white/5 p-4">
        <div class="text-2xl font-bold text-white">{{ $campaign->panels->count() }}</div>
        <div class="text-xs text-gray-500 mt-1">Panneaux</div>
    </div>
    @if($campaign->total_amount > 0)
    <div class="bg-[#11131f] rounded-xl border border-[#e8a020]/30 p-4">
        <div class="text-xl font-bold text-[#e8a020]">{{ number_format($campaign->total_amount, 0, ',', ' ') }} FCFA</div>
        <div class="text-xs text-gray-500 mt-1">Montant total</div>
    </div>
    @endif
</div>

<!-- Panels Section -->
<div class="mb-8">
    <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
        <span>📍</span> Emplacements
        <span class="text-sm text-gray-500 font-normal">({{ $campaign->panels->count() }})</span>
    </h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($campaign->panels as $panel)
        @php $photo = $panel->photos->sortBy('ordre')->first(); @endphp
        <div class="bg-[#11131f] rounded-xl border border-white/5 overflow-hidden hover:border-[#e8a020]/30 transition-all group">
            @if($photo)
                <img src="{{ asset('storage/' . ltrim($photo->path, '/')) }}" class="w-full h-40 object-cover" alt="{{ $panel->reference }}" loading="lazy">
            @else
                <div class="w-full h-40 bg-gradient-to-br from-[#1a1d2e] to-[#11131f] flex items-center justify-center text-4xl text-gray-600">
                    🪧
                </div>
            @endif
            <div class="p-4">
                <div class="text-[#e8a020] font-mono text-xs mb-1">{{ $panel->reference }}</div>
                <div class="text-white font-medium text-sm mb-2 truncate" title="{{ $panel->name }}">{{ $panel->name }}</div>
                <div class="text-gray-500 text-xs space-y-0.5">
                    <div>📍 {{ $panel->commune?->name ?? '—' }}</div>
                    <div>📐 {{ $panel->format?->name ?? '—' }}</div>
                    @if($panel->is_lit)
                        <div>💡 Éclairé</div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<!-- Invoices Section -->
@if($campaign->invoices->isNotEmpty())
<div>
    <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
        <span>💰</span> Factures
    </h2>
    
    <div class="space-y-3">
        @foreach($campaign->invoices as $inv)
        <div class="bg-[#11131f] rounded-xl border border-white/5 p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="font-mono text-sm text-[#e8a020]">{{ $inv->reference ?? '#' . $inv->id }}</div>
                    <div class="text-xs text-gray-500 mt-1">Émise le {{ $inv->created_at->format('d/m/Y') }}</div>
                </div>
                <div class="text-lg font-bold text-white">{{ number_format($inv->amount ?? 0, 0, ',', ' ') }} FCFA</div>
                @if(!empty($inv->paid_at))
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        ✓ Payée
                    </span>
                @else
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-[#e8a020]/20 text-[#e8a020] border border-[#e8a020]/30">
                        ⏳ En attente
                    </span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($campaign->panels->isEmpty())
<div class="text-center py-12">
    <div class="text-6xl mb-4">🪧</div>
    <h3 class="text-xl font-semibold text-white mb-2">Aucun panneau associé</h3>
    <p class="text-gray-500">Cette campagne n'a pas encore de panneaux assignés.</p>
</div>
@endif
@endsection