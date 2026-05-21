<x-admin-layout>
<x-slot name="title">Propositions</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.propositions.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouvelle proposition
    </a>
</x-slot>

{{-- STATS --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px">
    <div class="kpi-card" style="--kpi-color:#f97316">
        <div class="kpi-card__top-bar" style="background:#f97316"></div>
        <div class="kpi-card__icon" style="color:#f97316"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div class="kpi-card__value" style="color:#f97316">{{ $totalEnAttente }}</div>
        <div class="kpi-card__label">En attente</div>
        <div class="kpi-card__sub">à valider</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#22c55e">
        <div class="kpi-card__top-bar" style="background:#22c55e"></div>
        <div class="kpi-card__icon" style="color:#22c55e"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div class="kpi-card__value" style="color:#22c55e">{{ $totalAcceptees }}</div>
        <div class="kpi-card__label">Acceptées</div>
        <div class="kpi-card__sub">propositions signées</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#ef4444">
        <div class="kpi-card__top-bar" style="background:#ef4444"></div>
        <div class="kpi-card__icon" style="color:#ef4444"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
        <div class="kpi-card__value" style="color:#ef4444">{{ $totalRefusees }}</div>
        <div class="kpi-card__label">Refusées</div>
        <div class="kpi-card__sub">non retenues</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#6b7280">
        <div class="kpi-card__top-bar" style="background:#6b7280"></div>
        <div class="kpi-card__icon" style="color:#6b7280"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="kpi-card__value" style="color:#6b7280">{{ $totalExpirees }}</div>
        <div class="kpi-card__label">Expirées</div>
        <div class="kpi-card__sub">délai dépassé</div>
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
                               onclick="return downloadPropositionPdf(event, this, '{{ $proposition->reference }}', @js($proposition->client?->name ?? ''))"
                               class="btn btn-ghost btn-sm"
                               title="Télécharger le PDF (renommer possible)">📄</a>
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

<script>
// Téléchargement PDF proposition avec nom personnalisable.
// On propose un nom par défaut "proposition-{client}-{ref}" (slugifié),
// l'admin peut l'éditer dans le prompt avant le DL. Annuler → rien.
function downloadPropositionPdf(e, link, ref, clientName) {
    e.preventDefault();
    const slug = (s) => String(s || '')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')   // retire accents
        .replace(/[^A-Za-z0-9]+/g, '-')                     // espaces/spéciaux → tiret
        .replace(/^-+|-+$/g, '')
        .toLowerCase();
    const defaultName = clientName
        ? `proposition-${slug(clientName)}-${ref}`
        : `proposition-${ref}`;
    const name = prompt('Nom du fichier PDF (sans extension) :', defaultName);
    if (name === null) return false;           // Annuler
    const clean = name.trim();
    if (!clean) {                              // Vide → on garde le défaut
        window.location.href = link.href;
        return false;
    }
    const url = new URL(link.href, window.location.origin);
    url.searchParams.set('filename', clean);
    window.location.href = url.toString();
    return false;
}
</script>

</x-admin-layout>
