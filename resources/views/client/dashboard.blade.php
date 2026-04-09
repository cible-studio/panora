@extends('client.layout')
@section('title', 'Tableau de bord')
@section('page-title', 'Tableau de bord')

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-[#11131f] border border-white/5 rounded-2xl p-5 hover:border-[#e8a020]/30 transition-all">
        <div class="text-3xl mb-3">📋</div>
        <div class="text-3xl font-extrabold text-[#e8a020]">{{ $stats['propositions_en_attente'] ?? 0 }}</div>
        <div class="text-xs text-gray-500 mt-1">Propositions en attente</div>
    </div>
    <div class="bg-[#11131f] border border-white/5 rounded-2xl p-5 hover:border-[#e8a020]/30 transition-all">
        <div class="text-3xl mb-3">📢</div>
        <div class="text-3xl font-extrabold text-[#e8a020]">{{ $stats['campagnes_actives'] ?? 0 }}</div>
        <div class="text-xs text-gray-500 mt-1">Campagnes actives</div>
    </div>
    <div class="bg-[#11131f] border border-white/5 rounded-2xl p-5 hover:border-[#e8a020]/30 transition-all">
        <div class="text-3xl mb-3">🪧</div>
        <div class="text-3xl font-extrabold text-[#e8a020]">{{ $stats['panneaux_actifs'] ?? 0 }}</div>
        <div class="text-xs text-gray-500 mt-1">Panneaux actifs</div>
    </div>
    <div class="bg-[#11131f] border border-white/5 rounded-2xl p-5 hover:border-[#e8a020]/30 transition-all">
        <div class="text-3xl mb-3">📊</div>
        <div class="text-3xl font-extrabold text-[#e8a020]">{{ $stats['campagnes_total'] ?? 0 }}</div>
        <div class="text-xs text-gray-500 mt-1">Total campagnes</div>
    </div>
</div>

<div class="mb-8">
    <h2 class="text-lg font-semibold text-white mb-4">📋 Propositions récentes</h2>
    @forelse($propositions->take(5) as $prop)
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-4 mb-3 hover:border-[#e8a020]/30 transition-all">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <div class="font-mono text-xs text-[#e8a020] font-bold">{{ $prop->reference }}</div>
                <div class="text-sm text-gray-300 mt-1">{{ $prop->start_date->format('d/m/Y') }} → {{ $prop->end_date->format('d/m/Y') }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ $prop->panels->count() }} panneau(x)</div>
            </div>
            <a href="{{ route('client.proposition.detail', $prop->proposition_token) }}" class="px-4 py-2 bg-[#e8a020]/10 border border-[#e8a020]/20 rounded-lg text-xs font-semibold text-[#e8a020] hover:bg-[#e8a020]/20 transition-all">Voir →</a>
        </div>
    </div>
    @empty
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-8 text-center text-gray-500">Aucune proposition récente</div>
    @endforelse
</div>

<div>
    <h2 class="text-lg font-semibold text-white mb-4">🎯 Campagnes actives</h2>
    @forelse($campagnesActives as $camp)
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-4 mb-3 hover:border-[#e8a020]/30 transition-all">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <div class="font-semibold text-white">{{ $camp->name }}</div>
                <div class="text-xs text-gray-500 mt-1">Du {{ $camp->start_date->format('d/m/Y') }} au {{ $camp->end_date->format('d/m/Y') }}</div>
            </div>
            <a href="{{ route('client.campagne.detail', $camp) }}" class="px-4 py-2 bg-green-500/10 border border-green-500/20 rounded-lg text-xs font-semibold text-green-400 hover:bg-green-500/20 transition-all">Détails →</a>
        </div>
    </div>
    @empty
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-8 text-center text-gray-500">Aucune campagne active</div>
    @endforelse
</div>
@endsection