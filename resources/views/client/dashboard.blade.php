{{-- resources/views/client/dashboard.blade.php --}}
@extends('client.layout')
@section('title', 'Tableau de bord')
@section('page-title', 'Tableau de bord')

@section('content')

{{-- ══ STATS 4 KPI PRINCIPAUX ══ --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    @php
    $kpis = [
        ['v' => $stats['propositions_en_attente'], 'l' => 'Propositions', 'c' => '#e20613', 'i' => '📋'],
        ['v' => $stats['campagnes_actives'], 'l' => 'Campagnes actives', 'c' => '#fab80b', 'i' => '📢'],
        ['v' => $stats['poses_realisees'], 'l' => 'Poses réalisées', 'c' => '#8b5cf6', 'i' => '✅'],
        ['v' => $stats['piges_verifiees'], 'l' => 'Preuves validées', 'c' => '#22c55e', 'i' => '📸'],
    ];
    @endphp
    @foreach($kpis as $k)
    <div class="bg-[var(--surface)] border border-[var(--border)] rounded-xl p-4 transition-all hover:border-[{{ $k['c'] }}]/30">
        <div class="text-2xl mb-1">{{ $k['i'] }}</div>
        <div class="text-2xl font-bold" style="color:{{ $k['c'] }}">{{ number_format($k['v']) }}</div>
        <div class="text-[10px] text-[var(--text3)] font-medium mt-1">{{ $k['l'] }}</div>
    </div>
    @endforeach
</div>

{{-- ══ STATS SECONDAIRES (2 colonnes) ══ --}}
<div class="grid grid-cols-2 gap-3 mb-6">
    <div class="bg-[var(--surface)] border border-[var(--border)] rounded-xl p-4">
        <div class="text-[var(--text2)] text-xs font-semibold uppercase tracking-wide mb-2">📊 Total campagnes</div>
        <div class="text-2xl font-bold text-[#22c55e]">{{ number_format($stats['campagnes_total']) }}</div>
    </div>
    <div class="bg-[var(--surface)] border border-[var(--border)] rounded-xl p-4">
        <div class="text-[var(--text2)] text-xs font-semibold uppercase tracking-wide mb-2">🪧 Panneaux couverts</div>
        <div class="text-2xl font-bold text-[#0ea5e9]">{{ number_format($stats['panneaux_couverts']) }}</div>
        <div class="text-[10px] text-[var(--text3)] mt-1">sur {{ number_format($stats['panneaux_actifs']) }} actifs</div>
    </div>
</div>

{{-- ══ ACTIVITÉ RÉCENTE (2 colonnes sur mobile, 3 sur desktop) ══ --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">

    {{-- Propositions --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="text-red-500 text-sm">📋</span>
                <h3 class="text-sm font-semibold text-[var(--text)]">Propositions</h3>
            </div>
            <a href="{{ route('client.propositions') }}" class="text-[10px] font-semibold text-red-500 hover:underline">Voir →</a>
        </div>
        <div class="space-y-2">
            @forelse($propositions->take(4) as $prop)
            <a href="{{ route('client.proposition.detail', $prop->proposition_token) }}"
               class="block bg-[var(--surface)] border border-[var(--border)] rounded-lg p-3 transition-all hover:border-red-500/30">
                <div class="font-mono text-[10px] font-bold text-red-500">{{ $prop->reference }}</div>
                <div class="text-xs text-[var(--text2)] mt-1">{{ $prop->panels->count() }} panneau(x)</div>
                <div class="text-[10px] text-[var(--text3)] mt-1">📅 {{ $prop->end_date->format('d/m/Y') }}</div>
            </a>
            @empty
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-lg p-6 text-center text-[var(--text3)] text-xs">Aucune proposition</div>
            @endforelse
        </div>
    </div>

    {{-- Poses réalisées --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="text-purple-500 text-sm">🔧</span>
                <h3 class="text-sm font-semibold text-[var(--text)]">Poses réalisées</h3>
            </div>
            <a href="{{ route('client.poses') }}" class="text-[10px] font-semibold text-purple-500 hover:underline">Voir →</a>
        </div>
        <div class="space-y-2">
            @forelse($recentPoses as $pose)
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-lg p-3">
                <div class="font-mono text-xs font-bold text-purple-500">{{ $pose->panel?->reference ?? '—' }}</div>
                <div class="text-xs text-[var(--text2)] truncate">{{ $pose->campaign?->name ?? 'Sans campagne' }}</div>
                <div class="text-[10px] text-[var(--text3)] mt-1">✅ {{ $pose->done_at?->format('d/m/Y') }}</div>
            </div>
            @empty
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-lg p-6 text-center text-[var(--text3)] text-xs">Aucune pose récente</div>
            @endforelse
        </div>
    </div>

    {{-- Preuves d'affichage --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="text-green-500 text-sm">📸</span>
                <h3 class="text-sm font-semibold text-[var(--text)]">Preuves validées</h3>
            </div>
            <a href="{{ route('client.piges') }}" class="text-[10px] font-semibold text-green-500 hover:underline">Voir →</a>
        </div>
        <div class="space-y-2">
            @forelse($recentPiges as $pige)
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-lg p-3">
                <div class="font-mono text-xs font-bold text-green-500">{{ $pige->panel?->reference ?? '—' }}</div>
                <div class="text-xs text-[var(--text2)] truncate">{{ $pige->campaign?->name ?? 'Sans campagne' }}</div>
                <div class="text-[10px] text-[var(--text3)] mt-1">✅ Vérifié {{ $pige->verified_at?->format('d/m/Y') }}</div>
            </div>
            @empty
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-lg p-6 text-center text-[var(--text3)] text-xs">Aucune preuve disponible</div>
            @endforelse
        </div>
    </div>
</div>

{{-- ══ CAMPAGNES EN COURS ══ --}}
<div>
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="text-yellow-500 text-sm">📢</span>
            <h3 class="text-sm font-semibold text-[var(--text)]">Campagnes en cours</h3>
        </div>
        <a href="{{ route('client.campagnes') }}" class="text-[10px] font-semibold text-yellow-500 hover:underline">Voir toutes →</a>
    </div>

    <div class="space-y-2">
        @forelse($campagnesActives as $camp)
        @php
            $totalDays = $camp->start_date->diffInDays($camp->end_date);
            $elapsedDays = $camp->start_date->diffInDays(now());
            $progress = $totalDays > 0 ? min(100, round(($elapsedDays / $totalDays) * 100)) : 0;
            $daysLeft = now()->startOfDay()->diffInDays($camp->end_date->startOfDay(), false);
            $statusColor = $daysLeft <= 7 ? '#ef4444' : ($daysLeft <= 14 ? '#f97316' : '#22c55e');
        @endphp
        <a href="{{ route('client.campagne.detail', $camp) }}"
           class="block bg-[var(--surface)] border border-[var(--border)] rounded-xl p-4 transition-all hover:border-red-500/20">
            <div class="flex justify-between items-start gap-3 mb-3">
                <div>
                    <div class="font-semibold text-[var(--text)] text-sm">{{ $camp->name }}</div>
                    <div class="text-[10px] text-[var(--text3)] mt-1">
                        {{ $camp->panels_count }} panneau(x) · {{ $camp->start_date->format('d/m/Y') }} → {{ $camp->end_date->format('d/m/Y') }}
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="text-xs font-bold" style="color: {{ $statusColor }}">
                        {{ $daysLeft >= 0 ? $daysLeft . 'j restants' : 'Terminée' }}
                    </div>
                </div>
            </div>
            <div class="bg-[var(--surface2)] rounded-full h-1.5 overflow-hidden">
                <div class="h-full rounded-full transition-all" style="width: {{ $progress }}%; background: {{ $daysLeft <= 7 ? '#ef4444' : '#e20613' }}"></div>
            </div>
            <div class="text-[9px] text-[var(--text3)] mt-2">{{ $progress }}% de la durée écoulée</div>
        </a>
        @empty
        <div class="bg-[var(--surface)] border border-[var(--border)] rounded-xl p-8 text-center text-[var(--text3)] text-sm">Aucune campagne active</div>
        @endforelse
    </div>
</div>

@endsection