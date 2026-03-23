<x-admin-layout>
<x-slot name="title">Taxes Communes</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.taxes.export.pdf') }}" class="btn btn-ghost btn-sm">
        📄 Export PDF
    </a>
    <a href="{{ route('admin.taxes.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouvelle taxe
    </a>
</x-slot>

{{-- STATS --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-label">En attente</div>
        <div class="stat-value" style="color:var(--accent);">{{ $totalEnAttente }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Payées</div>
        <div class="stat-value" style="color:var(--green);">{{ $totalPayees }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">En retard</div>
        <div class="stat-value" style="color:var(--red);">{{ $totalEnRetard }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Montant dû</div>
        <div class="stat-value" style="font-size:18px; color:var(--accent);">
            {{ number_format($montantTotal, 0, ',', ' ') }}
        </div>
    </div>
</div>

{{-- FILTRES --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.taxes.index') }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Commune</label>
                <select name="commune_id" class="filter-select">
                    <option value="">Toutes</option>
                    @foreach($communes as $commune)
                    <option value="{{ $commune->id }}"
                        {{ request('commune_id') == $commune->id ? 'selected' : '' }}>
                        {{ $commune->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Type</label>
                <select name="type" class="filter-select">
                    <option value="">Tous</option>
                    <option value="odp" {{ request('type') === 'odp' ? 'selected' : '' }}>ODP</option>
                    <option value="tm"  {{ request('type') === 'tm'  ? 'selected' : '' }}>TM</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Année</label>
                <select name="year" class="filter-select">
                    <option value="">Toutes</option>
                    @for($y = date('Y'); $y >= 2020; $y--)
                    <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>
                        {{ $y }}
                    </option>
                    @endfor
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Statut</label>
                <select name="status" class="filter-select">
                    <option value="">Tous</option>
                    <option value="en_attente" {{ request('status') === 'en_attente' ? 'selected' : '' }}>En attente</option>
                    <option value="payee"      {{ request('status') === 'payee'      ? 'selected' : '' }}>Payée</option>
                    <option value="en_retard"  {{ request('status') === 'en_retard'  ? 'selected' : '' }}>En retard</option>
                </select>
            </div>
            <div class="filter-group" style="justify-content:flex-end;">
                <label class="filter-label">&nbsp;</label>
                <div style="display:flex; gap:6px;">
                    <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrer</button>
                    <a href="{{ route('admin.taxes.index') }}" class="btn btn-ghost btn-sm">✕ Reset</a>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- TABLEAU --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🏛️ Taxes ({{ $taxes->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Commune</th>
                    <th>Type</th>
                    <th>Année</th>
                    <th>Montant</th>
                    <th>Échéance</th>
                    <th>Payée le</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($taxes as $tax)
                <tr>
                    <td><strong>{{ $tax->commune->name }}</strong></td>
                    <td>
                        @if($tax->type === 'odp')
                            <span class="badge badge-blue">ODP</span>
                        @else
                            <span class="badge badge-purple">TM</span>
                        @endif
                    </td>
                    <td>{{ $tax->year }}</td>
                    <td style="color:var(--accent); font-weight:600;">
                        {{ number_format($tax->amount, 0, ',', ' ') }} FCFA
                    </td>
                    <td style="font-size:12px;">
                        {{ $tax->due_date ? $tax->due_date->format('d/m/Y') : '—' }}
                    </td>
                    <td style="font-size:12px; color:var(--text3);">
                        {{ $tax->paid_at ? $tax->paid_at->format('d/m/Y') : '—' }}
                    </td>
                    <td>
                        @if($tax->status === 'en_attente')
                            <span class="badge badge-orange">En attente</span>
                        @elseif($tax->status === 'payee')
                            <span class="badge badge-green">Payée ✓</span>
                        @else
                            <span class="badge badge-red">En retard ⚠️</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            @if($tax->status !== 'payee')
                            <form method="POST"
                                  action="{{ route('admin.taxes.pay', $tax) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-success btn-sm" title="Marquer payée">
                                    ✓ Payée
                                </button>
                            </form>
                            @endif
                            <a href="{{ route('admin.taxes.edit', $tax) }}"
                               class="btn btn-ghost btn-sm">✏️</a>
                            <form method="POST"
                                  action="{{ route('admin.taxes.destroy', $tax) }}"
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
                        Aucune taxe
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $taxes->links() }}
    </div>
</div>

</x-admin-layout>
