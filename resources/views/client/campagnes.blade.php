@extends('client.layout')
@section('title', 'Mes campagnes')
@section('page-title', 'Mes campagnes')

@section('content')
<div class="mb-6">
    <form method="GET" action="{{ route('client.campagnes') }}" class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <input type="text" name="search" placeholder="Rechercher une campagne..." value="{{ request('search') }}" 
                   class="w-full bg-[#11131f] border border-white/5 rounded-xl px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-[#e8a020] transition-colors">
        </div>
        <div class="w-full sm:w-48">
            <select name="status" class="w-full bg-[#11131f] border border-white/5 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-[#e8a020] transition-colors">
                <option value="">Tous les statuts</option>
                <option value="actif" {{ request('status') == 'actif' ? 'selected' : '' }}>Actif</option>
                <option value="pose" {{ request('status') == 'pose' ? 'selected' : '' }}>En pose</option>
                <option value="termine" {{ request('status') == 'termine' ? 'selected' : '' }}>Terminé</option>
            </select>
        </div>
        <button type="submit" class="bg-[#e8a020] text-[#0a0c15] font-semibold rounded-xl px-6 py-2.5 hover:bg-[#c47a00] transition-colors cursor-pointer">
            Filtrer
        </button>
        @if(request('search') || request('status'))
            <a href="{{ route('client.campagnes') }}" class="bg-[#1a1d2e] text-gray-400 font-semibold rounded-xl px-6 py-2.5 hover:bg-[#252a3f] transition-colors text-center">
                Réinitialiser
            </a>
        @endif
    </form>
</div>

<div class="overflow-x-auto">
    <table class="w-full">
        <thead>
            <tr class="border-b border-white/10">
                <th class="text-left py-4 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nom</th>
                <th class="text-left py-4 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Période</th>
                <th class="text-left py-4 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Panneaux</th>
                <th class="text-left py-4 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Montant</th>
                <th class="text-left py-4 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                <th class="text-left py-4 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($campagnes as $camp)
            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                <td class="py-4 px-3">
                    <strong class="text-white font-semibold">{{ $camp->name }}</strong>
                </td>
                <td class="py-4 px-3 text-gray-400 text-sm">
                    {{ $camp->start_date->format('d/m/Y') }} → {{ $camp->end_date->format('d/m/Y') }}
                </td>
                <td class="py-4 px-3 text-gray-400 text-sm">
                    {{ $camp->panels->count() }}
                </td>
                <td class="py-4 px-3 text-[#e8a020] font-semibold text-sm">
                    {{ number_format($camp->total_amount ?? 0, 0, ',', ' ') }} FCFA
                </td>
                <td class="py-4 px-3">
                    @php
                        $badgeClass = match($camp->status->value) {
                            'actif' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
                            'pose' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                            'termine' => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
                            default => 'bg-gray-500/20 text-gray-400 border-gray-500/30',
                        };
                    @endphp
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium border {{ $badgeClass }}">
                        {{ ucfirst($camp->status->value) }}
                    </span>
                </td>
                <td class="py-4 px-3">
                    <a href="{{ route('client.campagne.detail', $camp) }}" class="text-[#e8a020] hover:text-[#fbbf24] transition-colors text-sm font-medium">
                        Voir détails →
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center py-12 text-gray-500">
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-4xl">📭</span>
                        <p>Aucune campagne trouvée</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($campagnes->hasPages())
<div class="mt-6">
    {{ $campagnes->appends(request()->query())->links() }}
</div>
@endif

<style>
    /* Pagination styles */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .pagination .page-item {
        display: inline-block;
    }
    .pagination .page-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 2.5rem;
        height: 2.5rem;
        padding: 0 0.75rem;
        background: #11131f;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 0.75rem;
        color: #9ca3af;
        font-size: 0.875rem;
        transition: all 0.2s;
    }
    .pagination .page-link:hover {
        background: rgba(232, 160, 32, 0.1);
        border-color: rgba(232, 160, 32, 0.3);
        color: #e8a020;
    }
    .pagination .active .page-link {
        background: #e8a020;
        border-color: #e8a020;
        color: #0a0c15;
        font-weight: 600;
    }
    .pagination .disabled .page-link {
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>
@endsection