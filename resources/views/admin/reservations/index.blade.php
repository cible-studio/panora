<x-admin-layout title="Réservations">

<x-slot:topbarActions>
    <a href="{{ route('admin.reservations.disponibilites') }}" class="btn btn-primary">
        + Nouvelle réservation
    </a>
</x-slot:topbarActions>

{{-- ══ STATS ══ --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;">
    @php
    $statCards = [
        ['key'=>'total',      'label'=>'Total',         'icon'=>'📋', 'color'=>'var(--text)',   'bg'=>'var(--surface)',               'border'=>'var(--border)'],
        ['key'=>'en_attente', 'label'=>'En attente',    'icon'=>'⏳', 'color'=>'#e8a020',       'bg'=>'rgba(232,160,32,0.08)',         'border'=>'rgba(232,160,32,0.3)'],
        ['key'=>'confirme',   'label'=>'Confirmées',    'icon'=>'✅', 'color'=>'#22c55e',       'bg'=>'rgba(34,197,94,0.08)',          'border'=>'rgba(34,197,94,0.3)'],
        ['key'=>'refuse',     'label'=>'Refusées',      'icon'=>'❌', 'color'=>'#ef4444',       'bg'=>'rgba(239,68,68,0.08)',          'border'=>'rgba(239,68,68,0.3)'],
        ['key'=>'annule',     'label'=>'Annulées',      'icon'=>'🚫', 'color'=>'#6b7280',       'bg'=>'rgba(107,114,128,0.08)',        'border'=>'rgba(107,114,128,0.3)'],
    ];
    @endphp
    @foreach($statCards as $sc)
    <a href="{{ route('admin.reservations.index',
        $sc['key'] !== 'total'
            ? array_merge(request()->except(['status','page']), ['status' => $sc['key']])
            : request()->except(['status','page'])
        ) }}"
       style="text-decoration:none;">
        <div style="background:{{ $sc['bg'] }};border:1px solid {{ $sc['border'] }};
                    border-radius:12px;padding:14px 16px;cursor:pointer;
                    transition:transform 0.15s,box-shadow 0.15s;
                    {{ (request('status') === $sc['key'] || ($sc['key']==='total' && !request('status')))
                        ? 'box-shadow:0 0 0 2px '.($sc['key']==='total'?'var(--accent)':$sc['color']).';'
                        : '' }}"
             onmouseover="this.style.transform='translateY(-2px)'"
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size:20px;margin-bottom:6px;">{{ $sc['icon'] }}</div>
            <div style="font-size:24px;font-weight:800;color:{{ $sc['color'] }};line-height:1;">
                {{ $counts[$sc['key']] ?? 0 }}
            </div>
            <div style="font-size:11px;color:var(--text3);font-weight:600;
                        letter-spacing:.4px;margin-top:4px;">
                {{ strtoupper($sc['label']) }}
            </div>
            @if($sc['key'] === 'en_attente' && ($newCount ?? 0) > 0)
            <div style="font-size:10px;color:var(--accent);font-weight:700;margin-top:3px;">
                ✦ {{ $newCount }} nouvelle(s)
            </div>
            @endif
        </div>
    </a>
    @endforeach
</div>

{{-- ══ RÉSERVATIONS RÉCENTES (highlight) ══ --}}
@if(($newCount ?? 0) > 0)
<div style="background:rgba(232,160,32,0.06);border:1px solid rgba(232,160,32,0.2);
            border-radius:12px;padding:12px 16px;margin-bottom:16px;
            display:flex;align-items:center;gap:10px;">
    <span style="font-size:18px;">✦</span>
    <span style="font-size:13px;font-weight:600;color:var(--accent);">
        {{ $newCount }} nouvelle(s) réservation(s)
    </span>
    <span style="font-size:12px;color:var(--text2);">
        depuis votre dernière visite
        @if($lastSeenAt)
            ({{ $lastSeenAt->diffForHumans() }})
        @endif
    </span>
</div>
@endif

{{-- ══ FILTRES ══ --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:14px 16px;">
        <form method="GET" action="{{ route('admin.reservations.index') }}"
              style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">

            <div style="flex:2;min-width:180px;">
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Recherche
                </label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="filter-input" style="width:100%;"
                       placeholder="Référence, client…"/>
            </div>

            <div>
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Statut
                </label>
                <select name="status" class="filter-select">
                    <option value="">Tous</option>
                    @foreach($statuses as $s)
                    <option value="{{ $s->value }}"
                            {{ request('status') === $s->value ? 'selected' : '' }}>
                        {{ $s->label() }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Type
                </label>
                <select name="type" class="filter-select">
                    <option value="">Tous</option>
                    <option value="option" {{ request('type') === 'option' ? 'selected' : '' }}>
                        ⏳ Option
                    </option>
                    <option value="ferme"  {{ request('type') === 'ferme'  ? 'selected' : '' }}>
                        🔒 Ferme
                    </option>
                </select>
            </div>

            <div>
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Client
                </label>
                <select name="client_id" class="filter-select">
                    <option value="">Tous</option>
                    @foreach($clients as $c)
                    <option value="{{ $c->id }}"
                            {{ request('client_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtres rapides période --}}
            <div>
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Période
                </label>
                <select name="periode" class="filter-select">
                    <option value="">Toutes</option>
                    <option value="this_month"  {{ request('periode') === 'this_month'  ? 'selected' : '' }}>Ce mois</option>
                    <option value="last_month"  {{ request('periode') === 'last_month'  ? 'selected' : '' }}>Mois dernier</option>
                    <option value="this_quarter"{{ request('periode') === 'this_quarter'? 'selected' : '' }}>Ce trimestre</option>
                    <option value="this_year"   {{ request('periode') === 'this_year'   ? 'selected' : '' }}>Cette année</option>
                </select>
            </div>

            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary">🔍 Filtrer</button>
                @if(request()->hasAny(['search','status','type','client_id','periode']))
                <a href="{{ route('admin.reservations.index') }}" class="btn btn-ghost">↺ Reset</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- ══ TABLEAU ══ --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Réservations</span>
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:12px;color:var(--text2);">
                {{ $reservations->total() }} résultat(s)
            </span>
            @if(($newCount ?? 0) > 0)
            <span style="font-size:12px;font-weight:600;color:var(--accent);
                         background:rgba(232,160,32,0.1);padding:3px 10px;
                         border-radius:20px;border:1px solid rgba(232,160,32,0.3);">
                ✦ {{ $newCount }} nouvelle(s)
            </span>
            @endif
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:14px;padding:0;"></th>
                    <th>Référence</th>
                    <th>Client</th>
                    <th>Période</th>
                    <th>Panneaux</th>
                    <th>Montant</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Campagne</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reservations as $res)
                @php
                    $isNew         = isset($lastSeenAt) && $lastSeenAt && $res->created_at > $lastSeenAt;
                    $clientDeleted = $res->client?->trashed();
                    $canEdit       = $res->isEditable()    && auth()->user()->can('update',  $res);
                    $canAnnuler    = $res->isCancellable() && auth()->user()->can('annuler', $res);
                    $canDelete     = $res->isDeletable()   && auth()->user()->can('delete',  $res);
                    $rc            = $res->status->uiConfig();
                @endphp
                <tr style="{{ $isNew ? 'background:rgba(232,160,32,.04);' : '' }}
                            transition:background 0.12s;"
                    onmouseover="this.style.background='var(--surface2)'"
                    onmouseout="this.style.background='{{ $isNew ? 'rgba(232,160,32,.04)' : '' }}'">

                    {{-- Point "nouveau" --}}
                    <td style="padding:0 4px 0 10px;">
                        @if($isNew)
                        <span style="display:inline-block;width:7px;height:7px;
                                     border-radius:50%;background:var(--accent);
                                     box-shadow:0 0 5px var(--accent);">
                        </span>
                        @endif
                    </td>

                    {{-- Référence --}}
                    <td>
                        <a href="{{ route('admin.reservations.show', $res) }}"
                           style="font-family:monospace;font-size:12px;font-weight:700;
                                  color:var(--accent);text-decoration:none;">
                            {{ $res->reference }}
                        </a>
                        <div style="font-size:10px;color:var(--text3);margin-top:1px;">
                            {{ $res->created_at->diffForHumans() }}
                        </div>
                    </td>

                    {{-- Client --}}
                    <td>
                        @if($clientDeleted)
                        <span style="color:var(--text2);">{{ $res->client?->name ?? '—' }}</span>
                        <span style="font-size:10px;margin-left:4px;padding:1px 5px;
                                     background:rgba(239,68,68,.1);color:var(--red);
                                     border-radius:4px;">Supprimé</span>
                        @else
                        <a href="{{ route('admin.clients.show', $res->client) }}"
                           style="font-weight:600;color:var(--text);text-decoration:none;">
                            {{ $res->client?->name ?? '—' }}
                        </a>
                        @endif
                    </td>

                    {{-- Période --}}
                    <td style="font-size:12px;white-space:nowrap;color:var(--text2);">
                        {{ $res->start_date->format('d/m/Y') }}
                        <span style="color:var(--text3);margin:0 2px;">→</span>
                        {{ $res->end_date->format('d/m/Y') }}
                    </td>

                    {{-- Panneaux --}}
                    <td>
                        <span class="badge badge-blue">
                            {{ $res->panels_count }} 🪧
                        </span>
                    </td>

                    {{-- Montant --}}
                    <td style="font-weight:600;color:var(--accent);font-size:13px;
                               white-space:nowrap;">
                        {{ number_format($res->total_amount, 0, ',', ' ') }}
                        <span style="font-size:10px;font-weight:400;color:var(--text3);">FCFA</span>
                    </td>

                    {{-- Type --}}
                    <td>
                        <span style="font-size:11px;padding:2px 7px;border-radius:5px;
                                     background:var(--surface3);color:var(--text2);">
                            {{ $res->type === 'ferme' ? '🔒 Ferme' : '⏳ Option' }}
                        </span>
                    </td>

                    {{-- Statut --}}
                    <td>
                        <span style="padding:3px 9px;border-radius:20px;font-size:11px;
                                     font-weight:600;background:{{ $rc['bg'] }};
                                     color:{{ $rc['color'] }};border:1px solid {{ $rc['border'] }};">
                            {{ $rc['icon'] }} {{ $res->status->label() }}
                        </span>
                        @if($clientDeleted)
                        <div style="font-size:10px;color:var(--text3);margin-top:2px;">
                            lecture seule
                        </div>
                        @endif
                    </td>

                    {{-- Campagne --}}
                    <td>
                        @if($res->campaign)
                        <a href="{{ route('admin.campaigns.show', $res->campaign) }}"
                           style="font-size:12px;color:var(--accent);text-decoration:none;
                                  font-weight:600;">
                            📁 {{ $res->campaign->name }}
                        </a>
                        @elseif($res->status->value === 'confirme')
                        <a href="{{ route('admin.campaigns.create', ['reservation_id' => $res->id]) }}"
                           style="font-size:11px;color:var(--green);text-decoration:none;
                                  padding:2px 7px;border-radius:5px;
                                  border:1px solid rgba(34,197,94,0.3);">
                            + Créer
                        </a>
                        @else
                        <span style="color:var(--text3);font-size:12px;">—</span>
                        @endif
                    </td>

                    {{-- Actions --}}
                    <td>
                        <div style="display:flex;gap:4px;align-items:center;">
                            <a href="{{ route('admin.reservations.show', $res) }}"
                               class="btn btn-ghost btn-sm">👁 Voir</a>

                            @if($canEdit)
                            <a href="{{ route('admin.reservations.edit', $res) }}"
                               class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
                            @endif

                            @if($canAnnuler)
                            <button class="btn btn-ghost btn-sm"
                                    style="color:var(--orange);border-color:rgba(249,115,22,.3);"
                                    onclick="openAnnulerModal(
                                        {{ $res->id }},
                                        '{{ $res->reference }}',
                                        '{{ addslashes($res->client?->name ?? '') }}',
                                        {{ $res->panels_count }}
                                    )"
                                    title="Annuler">🚫</button>
                            @endif

                            @if($canDelete)
                            <button class="btn btn-danger btn-sm"
                                    onclick="openDeleteModal({{ $res->id }}, '{{ $res->reference }}')"
                                    title="Supprimer">🗑️</button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10"
                        style="text-align:center;padding:60px;color:var(--text3);">
                        <div style="font-size:32px;margin-bottom:8px;">📋</div>
                        Aucune réservation trouvée.
                        <div style="margin-top:8px;">
                            <a href="{{ route('admin.reservations.disponibilites') }}"
                               style="color:var(--accent);text-decoration:none;font-size:13px;">
                                + Créer une réservation depuis les disponibilités
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($reservations->hasPages())
    <div style="padding:14px 17px;border-top:1px solid var(--border);
                display:flex;justify-content:flex-end;">
        {{ $reservations->links() }}
    </div>
    @endif
</div>

{{-- ══ MODAL — ANNULER ══ --}}
<div id="modal-annuler" class="modal-overlay" style="display:none;"
     onclick="if(event.target===this) closeAnnulerModal()">
    <div class="modal" style="width:460px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" style="color:var(--orange);">🚫 Annuler la réservation</span>
            <button class="modal-close" onclick="closeAnnulerModal()">✕</button>
        </div>
        <div class="modal-body">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="font-size:44px;margin-bottom:10px;">🚫</div>
                <div style="font-weight:700;font-size:15px;margin-bottom:4px;">
                    Annuler <span id="annuler-ref"
                                  style="color:var(--accent);font-family:monospace;"></span> ?
                </div>
                <div style="font-size:13px;color:var(--text2);">
                    Réservation de <strong id="annuler-client"
                                           style="color:var(--text);"></strong>
                </div>
            </div>
            <div style="background:var(--surface2);border:1px solid var(--border2);
                        border-radius:10px;padding:14px;margin-bottom:14px;font-size:13px;">
                <div style="font-weight:600;margin-bottom:10px;">Ce qui va se passer :</div>
                <div style="display:flex;flex-direction:column;gap:8px;color:var(--text2);">
                    <div style="display:flex;gap:8px;">
                        <span style="color:var(--green);flex-shrink:0;">✓</span>
                        <span>Les <strong id="annuler-panels"
                                          style="color:var(--text);"></strong>
                              panneau(x) seront <strong>libérés immédiatement</strong>.</span>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <span style="color:var(--green);flex-shrink:0;">✓</span>
                        <span>L'historique sera <strong>conservé</strong>.</span>
                    </div>
                </div>
            </div>
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
                        border-radius:8px;padding:11px 13px;
                        display:flex;gap:8px;font-size:12px;color:var(--red);">
                <span>⚠️</span>
                <span>Cette action est <strong>irréversible</strong>.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeAnnulerModal()">
                Conserver la réservation
            </button>
            <form id="annuler-form" method="POST" style="display:inline;">
                @csrf @method('PATCH')
                <button type="submit"
                        style="background:var(--orange);color:#000;border:none;
                               padding:8px 18px;border-radius:8px;font-weight:700;
                               cursor:pointer;font-size:13px;">
                    🚫 Confirmer l'annulation
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ══ MODAL — SUPPRIMER ══ --}}
<div id="modal-delete" class="modal-overlay" style="display:none;"
     onclick="if(event.target===this) closeDeleteModal()">
    <div class="modal" style="width:420px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" style="color:var(--red);">🗑️ Supprimer</span>
            <button class="modal-close" onclick="closeDeleteModal()">✕</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:28px 22px;">
            <div style="font-size:44px;margin-bottom:12px;">🗑️</div>
            <div style="font-weight:700;font-size:15px;margin-bottom:8px;">
                Supprimer <span id="delete-ref"
                                style="color:var(--accent);font-family:monospace;"></span> ?
            </div>
            <div style="font-size:13px;color:var(--text2);margin-bottom:14px;">
                Suppression définitive et irréversible.
            </div>
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
                        border-radius:8px;padding:10px;font-size:12px;color:var(--red);">
                ⚠️ Seules les réservations annulées ou refusées peuvent être supprimées.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeDeleteModal()">Annuler</button>
            <form id="delete-form" method="POST" style="display:inline;">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">🗑️ Supprimer</button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openAnnulerModal(id, ref, client, panels) {
    document.getElementById('annuler-ref').textContent    = ref;
    document.getElementById('annuler-client').textContent = client;
    document.getElementById('annuler-panels').textContent = panels;
    document.getElementById('annuler-form').action        = `/admin/reservations/${id}/annuler`;
    document.getElementById('modal-annuler').style.display = 'flex';
}
function closeAnnulerModal() {
    document.getElementById('modal-annuler').style.display = 'none';
}
function openDeleteModal(id, ref) {
    document.getElementById('delete-ref').textContent  = ref;
    document.getElementById('delete-form').action      = `/admin/reservations/${id}`;
    document.getElementById('modal-delete').style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('modal-delete').style.display = 'none';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeAnnulerModal(); closeDeleteModal(); }
});

// Marquer comme vu après 2s
setTimeout(() => {
    fetch('{{ route("admin.reservations.mark-seen") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
}, 2000);
</script>
@endpush

</x-admin-layout>