<x-admin-layout>
<x-slot name="title">Propositions</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.propositions.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouvelle proposition
    </a>
</x-slot>

{{-- STATS --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-label">En attente</div>
        <div class="stat-value" style="color:var(--accent);">{{ $totalEnAttente }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Acceptées</div>
        <div class="stat-value" style="color:var(--green);">{{ $totalAcceptees }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Refusées</div>
        <div class="stat-value" style="color:var(--red);">{{ $totalRefusees }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Expirées</div>
        <div class="stat-value" style="color:var(--text3);">{{ $totalExpirees }}</div>
    </div>
</div>

{{-- FILTRES --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.propositions.index') }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Recherche</label>
                <input type="text" name="search" class="filter-input"
                       value="{{ request('search') }}"
                       placeholder="Numéro, client...">
            </div>
            <div class="filter-group">
                <label class="filter-label">Client</label>
                <select name="client_id" class="filter-select">
                    <option value="">Tous</option>
                    @foreach($clients as $client)
                    <option value="{{ $client->id }}"
                        {{ request('client_id') == $client->id ? 'selected' : '' }}>
                        {{ $client->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Statut</label>
                <select name="statut" class="filter-select">
                    <option value="">Tous</option>
                    <option value="en_attente" {{ request('statut') === 'en_attente' ? 'selected' : '' }}>En attente</option>
                    <option value="acceptee"   {{ request('statut') === 'acceptee'   ? 'selected' : '' }}>Acceptée</option>
                    <option value="refusee"    {{ request('statut') === 'refusee'    ? 'selected' : '' }}>Refusée</option>
                    <option value="expiree"    {{ request('statut') === 'expiree'    ? 'selected' : '' }}>Expirée</option>
                </select>
            </div>
            <div class="filter-group" style="justify-content:flex-end;">
                <label class="filter-label">&nbsp;</label>
                <div style="display:flex; gap:6px;">
                    <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrer</button>
                    <a href="{{ route('admin.propositions.index') }}" class="btn btn-ghost btn-sm">✕ Reset</a>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- TABLEAU --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">📄 Propositions ({{ $propositions->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Numéro</th>
                    <th>Client</th>
                    <th>Panneaux</th>
                    <th>Période</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Créée par</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($propositions as $proposition)
                <tr>
                    <td>
                        <span style="font-family:monospace; color:var(--accent); font-weight:700;">
                            {{ $proposition->numero }}
                        </span>
                    </td>
                    <td><strong>{{ $proposition->client->name }}</strong></td>
                    <td style="text-align:center;">{{ $proposition->nb_panneaux }}</td>
                    <td style="font-size:12px;">
                        {{ $proposition->date_debut->format('d/m/Y') }}
                        →
                        {{ $proposition->date_fin->format('d/m/Y') }}
                    </td>
                    <td style="color:var(--accent); font-weight:600;">
                        {{ number_format($proposition->montant, 0, ',', ' ') }} FCFA
                    </td>
                    <td>
                        @if($proposition->statut === 'en_attente')
                            <span class="badge badge-orange">En attente</span>
                        @elseif($proposition->statut === 'acceptee')
                            <span class="badge badge-green">Acceptée ✓</span>
                        @elseif($proposition->statut === 'refusee')
                            <span class="badge badge-red">Refusée</span>
                        @else
                            <span class="badge badge-gray">Expirée</span>
                        @endif
                    </td>
                    <td style="font-size:12px; color:var(--text3);">
                        {{ $proposition->creator->name }}
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.propositions.show', $proposition) }}"
                               class="btn btn-ghost btn-sm">👁️</a>
                            <a href="{{ route('admin.propositions.pdf', $proposition) }}"
                               class="btn btn-ghost btn-sm">📄</a>
                            <a href="{{ route('admin.propositions.edit', $proposition) }}"
                               class="btn btn-ghost btn-sm">✏️</a>
                            <form method="POST"
                                  action="{{ route('admin.propositions.destroy', $proposition) }}"
                                  onsubmit="return confirm('Supprimer ?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:var(--text3); padding:32px;">
                        Aucune proposition
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $propositions->links() }}
    </div>
</div>

</x-admin-layout>
