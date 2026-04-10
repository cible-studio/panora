<x-admin-layout>
<x-slot name="title">Facturation</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.invoices.create') }}" class="btn btn-primary btn-sm">＋ Nouvelle facture</a>
</x-slot>

{{-- STATS CLIQUABLES --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <a href="{{ route('admin.invoices.index', array_merge(request()->except('status'), ['status' => 'brouillon'])) }}"
       class="stat-card" style="text-decoration:none;cursor:pointer;transition:all .15s;
              {{ request('status') === 'brouillon' ? 'border-color:var(--text3);' : 'border:2px solid transparent;' }}">
        <div class="stat-label">Brouillons</div>
        <div class="stat-value" style="color:var(--text3);">{{ $totalBrouillons }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </a>
    <a href="{{ route('admin.invoices.index', array_merge(request()->except('status'), ['status' => 'envoyee'])) }}"
       class="stat-card" style="text-decoration:none;cursor:pointer;transition:all .15s;
              {{ request('status') === 'envoyee' ? 'border-color:var(--blue);' : 'border:2px solid transparent;' }}">
        <div class="stat-label">Envoyées</div>
        <div class="stat-value" style="color:var(--blue);">{{ $totalEnvoyees }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </a>
    <a href="{{ route('admin.invoices.index', array_merge(request()->except('status'), ['status' => 'payee'])) }}"
       class="stat-card" style="text-decoration:none;cursor:pointer;transition:all .15s;
              {{ request('status') === 'payee' ? 'border-color:var(--green);' : 'border:2px solid transparent;' }}">
        <div class="stat-label">Payées</div>
        <div class="stat-value" style="color:var(--green);">{{ $totalPayees }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </a>
    <a href="{{ route('admin.invoices.index') }}"
       class="stat-card" style="text-decoration:none;cursor:pointer;transition:all .15s;
              {{ !request('status') ? 'border-color:var(--accent);' : 'border:2px solid transparent;' }}">
        <div class="stat-label">CA Encaissé</div>
        <div class="stat-value" style="font-size:16px; color:var(--accent);">
            {{ number_format($montantTotal, 0, ',', ' ') }}
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Voir tout →</div>
    </a>
</div>

{{-- FILTRES AUTO --}}
<div class="card" style="margin-bottom:16px;">
    <form id="filter-form" method="GET" action="{{ route('admin.invoices.index') }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Client</label>
                <select name="client_id" class="filter-select" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                        {{ $client->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Statut</label>
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="brouillon" {{ request('status') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                    <option value="envoyee"   {{ request('status') === 'envoyee'   ? 'selected' : '' }}>Envoyée</option>
                    <option value="payee"     {{ request('status') === 'payee'     ? 'selected' : '' }}>Payée</option>
                    <option value="annulee"   {{ request('status') === 'annulee'   ? 'selected' : '' }}>Annulée</option>
                </select>
            </div>
            @if(request()->hasAny(['client_id', 'status']))
            <div class="filter-group" style="justify-content:flex-end;">
                <label class="filter-label">&nbsp;</label>
                <a href="{{ route('admin.invoices.index') }}" class="btn btn-ghost btn-sm">✕ Reset</a>
            </div>
            @endif
        </div>
    </form>
</div>

{{-- TABLEAU --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">💰 Factures ({{ $invoices->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Client</th>
                    <th>Campagne</th>
                    <th>Montant HT</th>
                    <th>TVA</th>
                    <th>Montant TTC</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                <tr>
                    <td>
                        <span style="font-family:monospace; color:var(--accent); font-weight:700;">{{ $invoice->reference }}</span>
                    </td>
                    <td><strong>{{ $invoice->client->name }}</strong></td>
                    <td style="font-size:12px; color:var(--text3);">{{ $invoice->campaign?->name ?? '—' }}</td>
                    <td>{{ number_format($invoice->amount, 0, ',', ' ') }} FCFA</td>
                    <td>{{ $invoice->tva }}%</td>
                    <td style="color:var(--accent); font-weight:700;">{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA</td>
                    <td style="font-size:12px;">{{ $invoice->issued_at->format('d/m/Y') }}</td>
                    <td>
                        @if($invoice->status === 'brouillon')
                            <span class="badge badge-gray">Brouillon</span>
                        @elseif($invoice->status === 'envoyee')
                            <span class="badge badge-blue">Envoyée</span>
                        @elseif($invoice->status === 'payee')
                            <span class="badge badge-green">Payée ✓</span>
                        @else
                            <span class="badge badge-red">Annulée</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">👁️</a>
                            <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn btn-ghost btn-sm">📄</a>
                            @if($invoice->status === 'brouillon')
                            <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-blue btn-sm" title="Envoyer">📤</button>
                            </form>
                            @endif
                            @if($invoice->status === 'envoyee')
                            <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-success btn-sm" title="Marquer payée">✓</button>
                            </form>
                            @endif
                            <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-ghost btn-sm">✏️</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center; color:var(--text3); padding:32px;">Aucune facture</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">{{ $invoices->links() }}</div>
</div>

</x-admin-layout>
