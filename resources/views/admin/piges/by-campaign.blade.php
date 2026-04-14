@extends('admin.layouts.admin')

@section('title', 'Piges - ' . $campaign->name)

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-white">Piges de la campagne</h1>
                <p class="text-gray-400 mt-1">{{ $campaign->name }}</p>
            </div>
            <div class="flex gap-3">
                @if(!$campaign->status->isTerminal())
                <button onclick="PG.openUploadModal({{ $campaign->id }})" 
                        class="px-4 py-2 bg-[#e8a020] text-black rounded-lg font-semibold hover:bg-[#c47a00] transition">
                    📷 + Nouvelle pige
                </button>
                @endif
                <a href="{{ route('admin.piges.index', ['campaign_id' => $campaign->id]) }}" 
                   class="px-4 py-2 bg-[#1a1d2e] border border-white/5 rounded-lg text-gray-300 hover:text-white transition">
                    ← Retour
                </a>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-[#11131f] border border-white/5 rounded-xl p-4">
            <div class="text-2xl mb-1">📸</div>
            <div class="text-2xl font-bold text-[#e8a020]">{{ $stats->total }}</div>
            <div class="text-xs text-gray-500">Total piges</div>
        </div>
        <div class="bg-[#11131f] border border-white/5 rounded-xl p-4">
            <div class="text-2xl mb-1">⏳</div>
            <div class="text-2xl font-bold text-[#f97316]">{{ $stats->en_attente }}</div>
            <div class="text-xs text-gray-500">En attente</div>
        </div>
        <div class="bg-[#11131f] border border-white/5 rounded-xl p-4">
            <div class="text-2xl mb-1">✅</div>
            <div class="text-2xl font-bold text-[#22c55e]">{{ $stats->verifie }}</div>
            <div class="text-xs text-gray-500">Vérifiées</div>
        </div>
        <div class="bg-[#11131f] border border-white/5 rounded-xl p-4">
            <div class="text-2xl mb-1">❌</div>
            <div class="text-2xl font-bold text-[#ef4444]">{{ $stats->rejete }}</div>
            <div class="text-xs text-gray-500">Rejetées</div>
        </div>
    </div>

    {{-- Grille des piges --}}
    @if($piges->isEmpty())
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-12 text-center">
        <div class="text-6xl mb-4 opacity-50">📸</div>
        <div class="text-lg font-semibold text-white mb-2">Aucune pige pour cette campagne</div>
        <div class="text-sm text-gray-500 mb-6">Ajoutez la première photo terrain</div>
        @if(!$campaign->status->isTerminal())
        <button onclick="PG.openUploadModal({{ $campaign->id }})" class="px-5 py-2 bg-[#e8a020] text-black rounded-lg font-semibold">
            + Ajouter une pige
        </button>
        @endif
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($piges as $pige)
        @php
            $statusColor = match($pige->status) {
                'verifie' => ['bg' => 'rgba(34,197,94,0.1)', 'text' => '#22c55e', 'icon' => '✅'],
                'rejete'  => ['bg' => 'rgba(239,68,68,0.1)', 'text' => '#ef4444', 'icon' => '❌'],
                default   => ['bg' => 'rgba(249,115,22,0.1)', 'text' => '#f97316', 'icon' => '⏳'],
            };
        @endphp
        <div class="bg-[#11131f] border border-white/5 rounded-xl overflow-hidden hover:border-[#e8a020]/30 transition-all cursor-pointer"
             onclick="PG.openLightbox({{ $pige->id }})">
            <div class="h-40 bg-[#1a1d2e] relative">
                <img src="{{ asset('storage/' . $pige->photo_path) }}" 
                     class="w-full h-full object-cover"
                     alt="Pige {{ $pige->panel?->reference }}">
                <div class="absolute top-2 right-2 px-2 py-1 rounded-lg text-xs font-semibold"
                     style="background: {{ $statusColor['bg'] }}; color: {{ $statusColor['text'] }}">
                    {{ $statusColor['icon'] }} {{ $pige->status_label }}
                </div>
            </div>
            <div class="p-4">
                <div class="font-mono text-sm font-bold text-[#e8a020]">{{ $pige->panel?->reference ?? '—' }}</div>
                <div class="text-sm text-white mt-1 truncate">{{ $pige->panel?->name ?? '—' }}</div>
                <div class="text-xs text-gray-500 mt-2">
                    📅 {{ $pige->taken_at?->format('d/m/Y') }} · 
                    👤 {{ $pige->takenBy?->name ?? '—' }}
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $piges->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof PG !== 'undefined' && PG.openUploadModal) {
        window.PG = PG;
    }
});
</script>
@endpush