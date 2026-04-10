@extends('client.layout')
@section('title', 'Mes propositions')
@section('page-title', '📋 Propositions commerciales')

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-4">
        <div class="text-2xl mb-2">📬</div>
        <div class="text-2xl font-bold text-[#e8a020]">{{ $propositions->total() }}</div>
        <div class="text-xs text-gray-500">Total propositions</div>
    </div>
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-4">
        <div class="text-2xl mb-2">🆕</div>
        <div class="text-2xl font-bold text-[#e8a020]">{{ $propositions->where('proposition_viewed_at', null)->where('end_date', '>=', now())->count() }}</div>
        <div class="text-xs text-gray-500">Nouvelles</div>
    </div>
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-4">
        <div class="text-2xl mb-2">✅</div>
        <div class="text-2xl font-bold text-[#e8a020]">{{ $propositions->where('status', 'confirme')->count() }}</div>
        <div class="text-xs text-gray-500">Acceptées</div>
    </div>
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-4">
        <div class="text-2xl mb-2">⏰</div>
        <div class="text-2xl font-bold text-[#e8a020]">{{ $propositions->where('end_date', '<', now())->count() }}</div>
        <div class="text-xs text-gray-500">Expirées</div>
    </div>
</div>

<div class="flex flex-wrap gap-3 mb-6">
    <form method="GET" action="{{ route('client.propositions') }}" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Recherche</label>
            <input type="text" name="search" class="bg-[#1a1d2e] border border-white/5 rounded-lg px-4 py-2 text-sm text-white w-64 focus:border-[#e8a020] focus:outline-none" placeholder="Référence..." value="{{ request('search') }}">
        </div>
        <div>
            <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Statut</label>
            <select name="status" class="bg-[#1a1d2e] border border-white/5 rounded-lg px-4 py-2 text-sm text-white cursor-pointer focus:border-[#e8a020] focus:outline-none">
                <option value="">Tous</option>
                <option value="en_attente" {{ request('status') == 'en_attente' ? 'selected' : '' }}>En attente</option>
                <option value="confirme" {{ request('status') == 'confirme' ? 'selected' : '' }}>Confirmée</option>
                <option value="refuse" {{ request('status') == 'refuse' ? 'selected' : '' }}>Refusée</option>
            </select>
        </div>
        <button type="submit" class="px-5 py-2 bg-[#e8a020] text-[#0a0c15] font-semibold rounded-lg text-sm hover:bg-[#c47a00] transition-all">🔍 Filtrer</button>
        @if(request('search') || request('status'))
        <a href="{{ route('client.propositions') }}" class="px-5 py-2 bg-[#1a1d2e] border border-white/5 rounded-lg text-sm text-gray-400 hover:text-white transition-all">↺ Réinitialiser</a>
        @endif
    </form>
</div>

@forelse($propositions as $res)
@php
    $total = $res->panels->sum(fn($p) => (float)($p->monthly_rate ?? 0));
    $expired = $res->end_date < now();
    $viewed = !is_null($res->proposition_viewed_at);
    $status = $res->status->value;
    $daysLeft = now()->startOfDay()->diffInDays($res->end_date->startOfDay(), false);
@endphp

<div class="bg-[#11131f] border border-white/5 rounded-xl mb-4 overflow-hidden transition-all hover:border-[#e8a020]/30 {{ !$viewed && !$expired && $status === 'en_attente' ? 'border-l-4 border-l-[#e8a020]' : '' }} {{ $expired ? 'opacity-60' : '' }}">
    <div class="p-5">
        <div class="flex flex-wrap justify-between items-start gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap mb-2">
                    <span class="font-mono text-sm font-bold text-[#e8a020] bg-[#e8a020]/10 px-3 py-1 rounded-lg">{{ $res->reference }}</span>
                    @if(!$viewed && !$expired && $status === 'en_attente')
                        <span class="text-[10px] font-bold bg-yellow-500/10 text-yellow-400 px-2 py-0.5 rounded-full">🆕 Nouvelle</span>
                    @elseif($expired)
                        <span class="text-[10px] font-bold bg-red-500/10 text-red-400 px-2 py-0.5 rounded-full">⏰ Expirée</span>
                    @elseif($status === 'confirme')
                        <span class="text-[10px] font-bold bg-green-500/10 text-green-400 px-2 py-0.5 rounded-full">✓ Confirmée</span>
                    @elseif(in_array($status, ['annule','refuse']))
                        <span class="text-[10px] font-bold bg-red-500/10 text-red-400 px-2 py-0.5 rounded-full">✗ Refusée</span>
                    @elseif($viewed)
                        <span class="text-[10px] font-bold bg-blue-500/10 text-blue-400 px-2 py-0.5 rounded-full">👁️ Consultée</span>
                    @endif
                </div>
                <div class="flex items-center gap-4 flex-wrap text-sm mb-2">
                    <span class="text-gray-300">📅 {{ $res->start_date->format('d/m/Y') }} → {{ $res->end_date->format('d/m/Y') }}</span>
                    @if(!$expired && $status === 'en_attente')
                        <span class="text-xs {{ $daysLeft <= 3 ? 'text-red-400' : 'text-yellow-400' }}">⏳ {{ $daysLeft }} jour(s) restant(s)</span>
                    @endif
                </div>
                <div class="flex gap-4 text-xs text-gray-500">
                    <span>📦 {{ $res->panels->count() }} panneau(x)</span>
                    @if($total > 0)
                        <span>💰 {{ number_format($total, 0, ',', ' ') }} FCFA/mois</span>
                    @endif
                    @if($res->proposition_sent_at)
                        <span>📨 Reçue {{ $res->proposition_sent_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                @if(!$expired && $status === 'en_attente')
                    <a href="{{ route('client.proposition.detail', $res->proposition_token) }}" class="px-4 py-2 bg-[#e8a020] text-[#0a0c15] font-semibold rounded-lg text-sm hover:bg-[#c47a00] transition-all">✅ Voir et répondre</a>
                @else
                    <a href="{{ route('client.proposition.detail', $res->proposition_token) }}" class="px-4 py-2 bg-[#1a1d2e] border border-white/5 rounded-lg text-sm text-gray-300 hover:text-white transition-all">👁️ Consulter</a>
                @endif
                <a href="{{ route('proposition.show', $res->proposition_token) }}" target="_blank" class="px-4 py-2 bg-[#1a1d2e] border border-white/5 rounded-lg text-sm text-gray-400 hover:text-white transition-all">🔗 Lien public</a>
            </div>
        </div>
    </div>
</div>
@empty
<div class="bg-[#11131f] border border-white/5 rounded-xl p-12 text-center">
    <div class="text-6xl mb-4 opacity-50">📭</div>
    <div class="text-lg font-semibold text-white mb-2">Aucune proposition reçue</div>
    <div class="text-sm text-gray-500">Vos propositions commerciales apparaîtront ici dès qu'elles vous seront envoyées.</div>
</div>
@endforelse

<div class="mt-6">
    {{ $propositions->appends(request()->query())->links() }}
</div>
@endsection