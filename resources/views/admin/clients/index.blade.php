<x-admin-layout title="Clients">

<x-slot:topbarActions>
    <a href="{{ route('admin.clients.create') }}" class="btn btn-primary">
        + Nouveau client
    </a>
</x-slot:topbarActions>

{{-- Stats --}}
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;
                padding:12px 20px;display:flex;flex-direction:column;gap:2px;min-width:120px;">
        <span style="font-size:24px;font-weight:800;color:var(--text);">{{ $stats['total'] }}</span>
        <span style="font-size:11px;color:var(--text2);font-weight:600;">CLIENTS TOTAL</span>
    </div>
    <div style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);
                border-radius:10px;padding:12px 20px;display:flex;flex-direction:column;
                gap:2px;min-width:120px;">
        <span style="font-size:24px;font-weight:800;color:#22c55e;">{{ $stats['actifs'] }}</span>
        <span style="font-size:11px;color:var(--text2);font-weight:600;">AVEC CAMPAGNE ACTIVE</span>
    </div>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
        <button class="btn btn-ghost btn-sm">📊 Excel</button>
        <button class="btn btn-ghost btn-sm">📄 PDF</button>
    </div>
</div>

{{-- Filtres --}}
<form method="GET" action="{{ route('admin.clients.index') }}"
      style="background:var(--surface);border:1px solid var(--border);border-radius:12px;
             padding:14px 16px;margin-bottom:16px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">

        <div style="flex:2;min-width:200px;">
            <label style="font-size:11px;color:var(--text3);font-weight:600;
                          display:block;margin-bottom:4px;">RECHERCHE</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Nom, NCC, email, contact, téléphone…"
                   style="width:100%;background:var(--surface2);border:1px solid var(--border2);
                          border-radius:8px;padding:8px 12px;color:var(--text);
                          font-size:13px;outline:none;box-sizing:border-box;">
        </div>

        <div style="min-width:180px;">
            <label style="font-size:11px;color:var(--text3);font-weight:600;
                          display:block;margin-bottom:4px;">SECTEUR</label>
            <select name="sector"
                    style="width:100%;background:var(--surface2);border:1px solid var(--border2);
                           border-radius:8px;padding:8px 12px;color:var(--text);
                           font-size:13px;outline:none;">
                <option value="">Tous les secteurs</option>
                @foreach($sectors as $sector)
                <option value="{{ $sector }}"
                    {{ request('sector') === $sector ? 'selected' : '' }}>
                    {{ $sector }}
                </option>
                @endforeach
            </select>
        </div>

        <div style="min-width:150px;">
            <label style="font-size:11px;color:var(--text3);font-weight:600;
                          display:block;margin-bottom:4px;">TRIER PAR</label>
            <select name="sort"
                    style="background:var(--surface2);border:1px solid var(--border2);
                           border-radius:8px;padding:8px 12px;color:var(--text);
                           font-size:13px;outline:none;width:100%;">
                <option value="name"            {{ request('sort','name') === 'name'            ? 'selected' : '' }}>Nom A-Z</option>
                <option value="created_at"      {{ request('sort') === 'created_at'             ? 'selected' : '' }}>Plus récents</option>
                <option value="campaigns_count" {{ request('sort') === 'campaigns_count'        ? 'selected' : '' }}>Nb campagnes</option>
            </select>
        </div>

        <div style="display:flex;gap:6px;">
            <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
            @if(request()->hasAny(['search','sector','sort']))
            <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost btn-sm">✕</a>
            @endif
        </div>
    </div>
</form>

{{-- Tableau --}}
<div style="background:var(--surface);border:1px solid var(--border);
            border-radius:12px;overflow:hidden;">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);
                display:flex;align-items:center;justify-content:space-between;">
        <span style="font-weight:700;font-size:14px;color:var(--text);">
            Portefeuille clients
        </span>
        <span style="font-size:12px;color:var(--text2);">
            {{ $clients->total() }} client(s)
        </span>
    </div>

    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:1px solid var(--border);">
                @foreach(['Client / NCC','Secteur','Campagnes','Réservations','Contact','Actions'] as $h)
                <th style="padding:11px 16px;text-align:left;font-size:10px;font-weight:700;
                           color:var(--text3);letter-spacing:.5px;text-transform:uppercase;">
                    {{ $h }}
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($clients as $client)
            @php
                $hasActive = ($client->active_campaigns_count ?? 0) > 0;
            @endphp
            <tr style="border-bottom:1px solid var(--border);transition:background .15s;"
                onmouseover="this.style.background='var(--surface2)'"
                onmouseout="this.style.background=''">

                {{-- Client / NCC --}}
                <td style="padding:14px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;
                                    background:var(--accent);color:#000;
                                    display:flex;align-items:center;justify-content:center;
                                    font-weight:800;font-size:14px;flex-shrink:0;">
                            {{ strtoupper(substr($client->name, 0, 1)) }}
                        </div>
                        <div>
                            <a href="{{ route('admin.clients.show', $client) }}"
                               style="font-weight:700;color:var(--text);text-decoration:none;
                                      font-size:14px;display:block;">
                                {{ $client->name }}
                            </a>
                            <div style="font-size:11px;color:var(--text3);margin-top:1px;">
                                @if($client->ncc)
                                    <span style="font-family:monospace;background:var(--surface3);
                                                 padding:1px 5px;border-radius:3px;">
                                        {{ $client->ncc }}
                                    </span>
                                @endif
                                · Depuis {{ $client->created_at->format('d/m/Y') }}
                            </div>
                        </div>
                    </div>
                </td>

                {{-- Secteur --}}
                <td style="padding:14px 16px;">
                    @if($client->sector)
                    <span style="padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;
                                 background:var(--surface3);color:var(--text2);">
                        {{ $client->sector }}
                    </span>
                    @else
                    <span style="color:var(--text3);font-size:13px;">—</span>
                    @endif
                </td>

                {{-- Campagnes --}}
                <td style="padding:14px 16px;">
                    <div style="font-weight:600;color:var(--text);font-size:14px;">
                        {{ $client->campaigns_count ?? 0 }}
                    </div>
                    @if($hasActive)
                    <div style="font-size:10px;color:#22c55e;font-weight:600;">
                        {{ $client->active_campaigns_count }} active(s)
                    </div>
                    @endif
                </td>

                {{-- Réservations --}}
                <td style="padding:14px 16px;color:var(--text2);font-size:13px;">
                    {{ $client->reservations_count ?? 0 }}
                </td>

                {{-- Contact --}}
                <td style="padding:14px 16px;">
                    @if($client->contact_name)
                    <div style="font-size:13px;color:var(--text);">{{ $client->contact_name }}</div>
                    @endif
                    @if($client->email)
                    <div style="font-size:11px;color:var(--text3);">{{ $client->email }}</div>
                    @endif
                    @if($client->phone)
                    <div style="font-size:11px;color:var(--text3);">{{ $client->phone }}</div>
                    @endif
                    @if(!$client->contact_name && !$client->email && !$client->phone)
                    <span style="color:var(--text3);font-size:13px;">—</span>
                    @endif
                </td>

                {{-- Actions --}}
                <td style="padding:14px 16px;">
                    <div style="display:flex;gap:5px;">
                        <a href="{{ route('admin.clients.show', $client) }}"
                           class="btn btn-ghost btn-sm" title="Voir">👁</a>
                        <a href="{{ route('admin.clients.edit', $client) }}"
                           class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
                        <button type="button"
                                onclick="openDeleteClient({{ $client->id }}, '{{ addslashes($client->name) }}', {{ $client->active_campaigns_count ?? 0 }})"
                                class="btn btn-ghost btn-sm"
                                style="color:var(--red);" title="Supprimer">🗑</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6"
                    style="padding:60px;text-align:center;color:var(--text3);font-size:14px;">
                    <div style="font-size:32px;margin-bottom:8px;">👥</div>
                    Aucun client trouvé.
                    <div style="margin-top:8px;">
                        <a href="{{ route('admin.clients.create') }}"
                           style="color:var(--accent);text-decoration:none;font-size:13px;">
                            + Créer le premier client
                        </a>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($clients->hasPages())
    <div style="padding:14px 16px;border-top:1px solid var(--border);
                display:flex;justify-content:flex-end;">
        {{ $clients->links() }}
    </div>
    @endif
</div>

{{-- Modal suppression --}}
<div id="modal-delete-client" class="modal-overlay" style="display:none"
     onclick="if(event.target===this) closeDeleteClient()">
    <div class="modal" style="max-width:420px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">🗑 Supprimer le client</div>
            <button class="modal-close" onclick="closeDeleteClient()">✕</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:28px 22px;">
            <div style="font-size:44px;margin-bottom:12px;">👥</div>
            <div style="font-weight:700;font-size:15px;margin-bottom:8px;">
                Supprimer <span id="del-client-name" style="color:var(--accent);"></span> ?
            </div>
            <div id="del-client-warning"
                 style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
                        border-radius:8px;padding:10px;font-size:12px;color:var(--red);
                        margin-bottom:12px;">
                ⚠️ Ce client a des campagnes actives. La suppression sera bloquée.
            </div>
            <div style="font-size:13px;color:var(--text2);margin-bottom:14px;">
                Le client sera archivé (soft delete). Ses données historiques seront conservées.
            </div>
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
                        border-radius:8px;padding:10px;font-size:12px;color:var(--red);">
                ⚠️ Ses réservations seront en lecture seule uniquement.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeDeleteClient()">Annuler</button>
            <form id="del-client-form" method="POST" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">🗑 Supprimer</button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openDeleteClient(id, name, activeCampaigns) {
    document.getElementById('del-client-name').textContent = name;
    document.getElementById('del-client-form').action = `/admin/clients/${id}`;
    document.getElementById('del-client-warning').style.display =
        activeCampaigns > 0 ? 'block' : 'none';
    document.getElementById('modal-delete-client').style.display = 'flex';
}
function closeDeleteClient() {
    document.getElementById('modal-delete-client').style.display = 'none';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDeleteClient();
});
</script>
@endpush

</x-admin-layout>