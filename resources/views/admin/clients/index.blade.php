<x-admin-layout title="Clients">

{{-- ── Actions topbar ── --}}
<x-slot:topbarActions>
  <button class="btn btn-primary" @click="$dispatch('open-modal', 'create-client')">
    + Nouveau client
  </button>
</x-slot:topbarActions>

{{-- ── Barre export ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <button class="btn btn-ghost btn-sm">📊 Réseau clients Excel</button>
    <button class="btn btn-ghost btn-sm">📄 PDF avec images</button>
    <button class="btn btn-ghost btn-sm">📋 PDF liste clients</button>
  </div>
  <button class="btn btn-primary" @click="$dispatch('open-modal', 'create-client')">
    + Nouveau client
  </button>
</div>

{{-- ── Filtres ── --}}
<div class="filter-bar" style="margin-bottom:16px;border-radius:12px 12px 0 0;">
  <form method="GET" action="{{ route('admin.clients.index') }}"
        style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;width:100%;">
    <div class="filter-group" style="flex:1;min-width:200px;">
      <label class="filter-label">Recherche</label>
      <input type="text" name="search" value="{{ request('search') }}"
             class="filter-input" style="width:100%;" placeholder="Nom, email, contact…"/>
    </div>
    <div class="filter-group">
      <label class="filter-label">Secteur</label>
      <select name="sector" class="filter-select">
        <option value="">Tous</option>
        @foreach($sectors as $sector)
          <option value="{{ $sector }}" {{ request('sector') === $sector ? 'selected' : '' }}>
            {{ $sector }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="filter-group">
      <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
    </div>
    @if(request('search') || request('sector'))
      <div class="filter-group">
        <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
      </div>
    @endif
  </form>
</div>

{{-- ── Tableau ── --}}
<div class="card">
  <div class="card-header">
    <span class="card-title">Portefeuille clients</span>
    <span style="font-size:12px;color:var(--text2);">{{ $clients->total() }} client(s)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Client</th>
          <th>Secteur</th>
          <th>Campagnes</th>
          <th>CA Total</th>
          <th>Panneaux actifs</th>
          <th>Contact</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($clients as $client)
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="avatar-circle">{{ strtoupper(substr($client->name, 0, 1)) }}</div>
                <div>
                  <div style="font-weight:600;">{{ $client->name }}</div>
                  <div style="font-size:11px;color:var(--text2);">
                    Depuis {{ $client->created_at->format('d/m/Y') }}
                  </div>
                </div>
              </div>
            </td>
            <td>
              @if($client->sector)
                @php
                  $sectorColors = [
                    'Télécommunications' => 'badge-blue',
                    'Banque'             => 'badge-teal',
                    'Agroalimentaire'    => 'badge-green',
                    'Distribution'       => 'badge-orange',
                    'Énergie'            => 'badge-purple',
                    'Assurance'          => 'badge-orange',
                  ];
                  $badgeClass = $sectorColors[$client->sector] ?? 'badge-gray';
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $client->sector }}</span>
              @else
                <span style="color:var(--text3);">—</span>
              @endif
            </td>
            <td style="color:var(--text2);">{{ $client->campaigns_count ?? 0 }}</td>
            <td style="font-weight:600;color:var(--accent);">
              {{ number_format($client->invoices_sum_amount_ttc ?? 0, 0, ',', ' ') }} FCFA
            </td>
            <td style="color:var(--text2);">{{ $client->active_panels_count ?? 0 }}</td>
            <td style="color:var(--text2);">{{ $client->email ?? '—' }}</td>
            <td>
              <div style="display:flex;gap:5px;">
                <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-ghost btn-sm">Voir</a>
                <button class="btn btn-ghost btn-sm"
                        @click="$dispatch('open-modal', {name: 'edit-client', data: {{ $client->toJson() }} })">
                  ✏️
                </button>
                <button class="btn btn-danger btn-sm"
                        @click="$dispatch('open-modal', {name: 'delete-client', data: {id: {{ $client->id }}, name: '{{ addslashes($client->name) }}'}})">
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="text-align:center;padding:40px;color:var(--text3);">
              Aucun client trouvé.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
  @if($clients->hasPages())
    <div style="padding:14px 17px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;">
      {{ $clients->links() }}
    </div>
  @endif
</div>

{{-- ══════════════════════════════════════
     MODAL — CRÉER CLIENT
══════════════════════════════════════ --}}
<div x-data="{ open: false, errors: {} }"
     x-on:open-modal.window="if($event.detail === 'create-client') open = true"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal" @click.stop>
    <div class="modal-header">
      <span class="modal-title">Nouveau client</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <form method="POST" action="{{ route('admin.clients.store') }}">
      @csrf
      <div class="modal-body">

        <div class="mfg">
          <label>Nom de l'entreprise *</label>
          <input type="text" name="name" value="{{ old('name') }}"
                 class="{{ $errors->has('name') ? 'error' : '' }}"
                 placeholder="Ex : Orange Côte d'Ivoire" required/>
          @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Secteur d'activité</label>
            <input type="text" name="sector" value="{{ old('sector') }}"
                   list="sectors-create" placeholder="Ex : Télécommunications"/>
            <datalist id="sectors-create">
              @foreach($sectors as $s)<option value="{{ $s }}">@endforeach
            </datalist>
          </div>
          <div class="mfg">
            <label>Nom du contact</label>
            <input type="text" name="contact_name" value="{{ old('contact_name') }}"
                   placeholder="Ex : Koné Ibrahim"/>
          </div>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="{{ $errors->has('email') ? 'error' : '' }}"
                   placeholder="contact@entreprise.ci"/>
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
          </div>
          <div class="mfg">
            <label>Téléphone</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   placeholder="+225 07 00 00 00 00"/>
          </div>
        </div>

        <div class="mfg">
          <label>Adresse</label>
          <textarea name="address" placeholder="Ex : Plateau, Abidjan">{{ old('address') }}</textarea>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer le client</button>
      </div>
    </form>
  </div>
</div>

{{-- ══════════════════════════════════════
     MODAL — ÉDITER CLIENT
══════════════════════════════════════ --}}
<div x-data="{ open: false, client: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'edit-client') { client = $event.detail.data; open = true; }"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal" @click.stop>
    <div class="modal-header">
      <span class="modal-title">Modifier le client</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <form method="POST" :action="`/admin/clients/${client.id}`">
      @csrf @method('PUT')
      <div class="modal-body">

        <div class="mfg">
          <label>Nom de l'entreprise *</label>
          <input type="text" name="name" :value="client.name" required/>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Secteur d'activité</label>
            <input type="text" name="sector" :value="client.sector"
                   list="sectors-edit"/>
            <datalist id="sectors-edit">
              @foreach($sectors as $s)<option value="{{ $s }}">@endforeach
            </datalist>
          </div>
          <div class="mfg">
            <label>Nom du contact</label>
            <input type="text" name="contact_name" :value="client.contact_name"/>
          </div>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Email</label>
            <input type="email" name="email" :value="client.email"/>
          </div>
          <div class="mfg">
            <label>Téléphone</label>
            <input type="text" name="phone" :value="client.phone"/>
          </div>
        </div>

        <div class="mfg">
          <label>Adresse</label>
          <textarea name="address" x-text="client.address"></textarea>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

{{-- ══════════════════════════════════════
     MODAL — SUPPRIMER CLIENT
══════════════════════════════════════ --}}
<div x-data="{ open: false, client: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'delete-client') { client = $event.detail.data; open = true; }"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal" style="width:420px;" @click.stop>
    <div class="modal-header">
      <span class="modal-title" style="color:var(--red);">Supprimer le client</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <div class="modal-body" style="text-align:center;padding:32px 22px;">
      <div style="font-size:40px;margin-bottom:12px;">🗑️</div>
      <p style="font-size:15px;font-weight:600;margin-bottom:8px;">
        Supprimer <span x-text="client.name" style="color:var(--accent);"></span> ?
      </p>
      <p style="font-size:13px;color:var(--text2);">
        Cette action est irréversible. Toutes les données liées seront supprimées.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
      <form method="POST" :action="`/admin/clients/${client.id}`" style="display:inline;">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger">Oui, supprimer</button>
      </form>
    </div>
  </div>
</div>

</x-admin-layout>