<x-admin-layout title="Régies Externes">

{{-- ── Topbar ── --}}
<x-slot:topbarActions>
  <button class="btn btn-primary"
          @click="$dispatch('open-modal', 'create-agency')">
    + Nouvelle régie
  </button>
</x-slot:topbarActions>

{{-- ── KPI cliquables (filtre par status) ── --}}
@php
    $currentStatus = request('status');
    $kpis = [
        ['key' => null,        'label' => 'Total',     'icon' => '🏢', 'value' => $stats['total']    ?? 0, 'color' => 'var(--text)'],
        ['key' => 'active',    'label' => 'Actives',   'icon' => '✅', 'value' => $stats['active']   ?? 0, 'color' => '#22c55e'],
        ['key' => 'inactive',  'label' => 'Inactives', 'icon' => '⏸',  'value' => $stats['inactive'] ?? 0, 'color' => '#ef4444'],
    ];
@endphp
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
    @foreach($kpis as $k)
        @php $isActive = ($currentStatus ?? '') === ($k['key'] ?? ''); @endphp
        <a href="{{ route('admin.external-agencies.index', $k['key'] ? ['status' => $k['key']] : []) }}"
           class="stat-card {{ $isActive ? 'is-active' : '' }}"
           style="text-decoration:none;border:1px solid {{ $isActive ? $k['color'] : 'var(--border)' }};background:{{ $isActive ? 'var(--accent-dim)' : 'var(--surface)' }};border-radius:14px;padding:16px;display:flex;align-items:center;gap:14px;transition:all .15s">
            <div style="font-size:24px">{{ $k['icon'] }}</div>
            <div>
                <div style="font-size:22px;font-weight:700;color:{{ $k['color'] }};line-height:1.1">{{ $k['value'] }}</div>
                <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;font-weight:600">{{ $k['label'] }}</div>
            </div>
        </a>
    @endforeach
</div>

{{-- ── Filtres recherche ── --}}
<div class="filter-bar" style="margin-bottom:16px;border-radius:12px 12px 0 0;">
  <form method="GET" action="{{ route('admin.external-agencies.index') }}"
        style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;width:100%;">
    {{-- Conserve le filtre status courant via hidden --}}
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="filter-group" style="flex:1;min-width:200px;">
      <label class="filter-label">Recherche</label>
      <input type="text" name="search" value="{{ request('search') }}"
             class="filter-input" style="width:100%;"
             placeholder="Nom, responsable, commercial, ville…"/>
    </div>
    <div class="filter-group">
      <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
    </div>
    @if(request('search') || request('status'))
      <div class="filter-group">
        <a href="{{ route('admin.external-agencies.index') }}"
           class="btn btn-ghost btn-sm">Réinitialiser</a>
      </div>
    @endif
  </form>
</div>

{{-- ── Tableau ── --}}
<div class="card">
  <div class="card-header">
    <span class="card-title">Régies partenaires</span>
    <span style="font-size:12px;color:var(--text2);">
      {{ $agencies->total() }} régie(s)
    </span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Régie</th>
          <th>Contact</th>
          <th>Email</th>
          <th>Panneaux</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($agencies as $agency)
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="avatar-circle">
                  {{ strtoupper(substr($agency->name, 0, 1)) }}
                </div>
                <div>
                  <div style="font-weight:600;">{{ $agency->name }}</div>
                  <div style="font-size:11px;color:var(--text2);">
                    Depuis {{ $agency->created_at->format('d/m/Y') }}
                  </div>
                </div>
              </div>
            </td>
            <td style="color:var(--text2);">{{ $agency->contact ?? '—' }}</td>
            <td style="color:var(--text2);">{{ $agency->email ?? '—' }}</td>
            <td>
              <span class="badge badge-blue">
                {{ $agency->external_panels_count }} panneau(x)
              </span>
            </td>
            <td>
              @php
                $allCampaigns = $agency->externalPanels->flatMap(fn($p) => $p->campaigns)->unique('id');
                $allClients   = $allCampaigns->pluck('client')->filter()->unique('id');
              @endphp
              @if($allClients->isEmpty())
                <span style="color:var(--text3);font-size:12px;">—</span>
              @else
                <div style="display:flex;flex-direction:column;gap:4px;">
                  @foreach($allClients->take(2) as $client)
                    <span style="font-size:11px;padding:2px 8px;border-radius:20px;
                                 background:rgba(63,127,192,0.12);color:#3f7fc0;
                                 border:1px solid rgba(63,127,192,0.3);font-weight:600;
                                 white-space:nowrap;display:inline-block;">
                      👥 {{ $client->name }}
                    </span>
                  @endforeach
                  @if($allClients->count() > 2)
                    <span style="font-size:11px;color:var(--text3);">+{{ $allClients->count() - 2 }} autre(s)</span>
                  @endif
                </div>
              @endif
            </td>
            <td>
              <div style="display:flex;gap:5px;">
                <a href="{{ route('admin.external-agencies.show', $agency) }}"
                   class="btn btn-ghost btn-sm">Voir</a>
                <button class="btn btn-ghost btn-sm"
                        @click="$dispatch('open-modal', {
                          name: 'edit-agency',
                          data: {{ $agency->toJson() }}
                        })">✏️</button>
                <button class="btn btn-danger btn-sm"
                        @click="$dispatch('open-modal', {
                          name: 'delete-agency',
                          data: { id: {{ $agency->id }}, name: '{{ addslashes($agency->name) }}' }
                        })">🗑️</button>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6"
                style="text-align:center;padding:40px;color:var(--text3);">
              Aucune régie trouvée.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($agencies->hasPages())
    <div style="padding:14px 17px;border-top:1px solid var(--border);
                display:flex;justify-content:flex-end;">
      {{ $agencies->links() }}
    </div>
  @endif
</div>

{{-- ══════════════════════════════════════
     MODAL — CRÉER RÉGIE
══════════════════════════════════════ --}}
<div x-data="{ open: false }"
     x-on:open-modal.window="if($event.detail === 'create-agency') open = true"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal" @click.stop>
    <div class="modal-header">
      <span class="modal-title">Nouvelle régie</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <form method="POST" action="{{ route('admin.external-agencies.store') }}">
      @csrf
      <div class="modal-body">

        <div class="mfg">
          <label>Nom de la régie *</label>
          <input type="text" name="name" value="{{ old('name') }}"
                 class="{{ $errors->has('name') ? 'error' : '' }}"
                 placeholder="Ex : Affichage Plus CI" required/>
          @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Email général</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="{{ $errors->has('email') ? 'error' : '' }}"
                   placeholder="contact@regie.ci"/>
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
          </div>
          <div class="mfg">
            <label>Téléphone général</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   placeholder="+225 ..."/>
          </div>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Ville</label>
            <input type="text" name="city" value="{{ old('city') }}" placeholder="Abidjan"/>
          </div>
          <div class="mfg">
            <label>Statut</label>
            <select name="is_active">
                <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>✅ Active</option>
                <option value="0" {{ old('is_active', 1) == 0 ? 'selected' : '' }}>⏸ Inactive</option>
            </select>
          </div>
        </div>

        <div class="mfg">
          <label>Adresse</label>
          <textarea name="address" placeholder="Ex : Zone 4, Abidjan">{{ old('address') }}</textarea>
        </div>

        {{-- ── Section Contacts (T11) ───────────────────────── --}}
        <div class="section-label" style="margin-top:16px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--accent);font-weight:700;border-top:1px solid var(--border);padding-top:14px;">
            👥 Contacts détaillés
        </div>

        <div class="mfg">
          <label>Responsable général</label>
          <input type="text" name="manager_name" value="{{ old('manager_name') }}"
                 placeholder="Nom & prénom du responsable"/>
        </div>

        <div class="mfg">
          <label>Commercial dédié</label>
          <input type="text" name="commercial_name" value="{{ old('commercial_name') }}"
                 placeholder="Nom & prénom du commercial"/>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Email commercial</label>
            <input type="email" name="commercial_email" value="{{ old('commercial_email') }}"
                   class="{{ $errors->has('commercial_email') ? 'error' : '' }}"
                   placeholder="commercial@regie.ci"/>
            @error('commercial_email')<div class="field-error">{{ $message }}</div>@enderror
          </div>
          <div class="mfg">
            <label>Téléphone commercial</label>
            <input type="text" name="commercial_phone" value="{{ old('commercial_phone') }}"
                   placeholder="+225 ..."/>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer la régie</button>
      </div>
    </form>
  </div>
</div>

{{-- ══════════════════════════════════════
     MODAL — ÉDITER RÉGIE
══════════════════════════════════════ --}}
<div x-data="{ open: false, agency: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'edit-agency') {
       agency = $event.detail.data; open = true;
     }"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal" @click.stop>
    <div class="modal-header">
      <span class="modal-title">Modifier la régie</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <form method="POST" :action="`/admin/external-agencies/${agency.id}`">
      @csrf @method('PUT')
      <div class="modal-body">

        <div class="mfg">
          <label>Nom de la régie *</label>
          <input type="text" name="name" :value="agency.name" required/>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Email général</label>
            <input type="email" name="email" :value="agency.email"/>
          </div>
          <div class="mfg">
            <label>Téléphone général</label>
            <input type="text" name="phone" :value="agency.phone"/>
          </div>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Ville</label>
            <input type="text" name="city" :value="agency.city"/>
          </div>
          <div class="mfg">
            <label>Statut</label>
            <select name="is_active">
                <option value="1" :selected="agency.is_active == 1 || agency.is_active === true">✅ Active</option>
                <option value="0" :selected="agency.is_active == 0 || agency.is_active === false">⏸ Inactive</option>
            </select>
          </div>
        </div>

        <div class="mfg">
          <label>Adresse</label>
          <textarea name="address" x-text="agency.address"></textarea>
        </div>

        {{-- ── Section Contacts (T11) ───────────────────────── --}}
        <div class="section-label" style="margin-top:16px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--accent);font-weight:700;border-top:1px solid var(--border);padding-top:14px;">
            👥 Contacts détaillés
        </div>

        <div class="mfg">
          <label>Responsable général</label>
          <input type="text" name="manager_name" :value="agency.manager_name"/>
        </div>

        <div class="mfg">
          <label>Commercial dédié</label>
          <input type="text" name="commercial_name" :value="agency.commercial_name"/>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Email commercial</label>
            <input type="email" name="commercial_email" :value="agency.commercial_email"/>
          </div>
          <div class="mfg">
            <label>Téléphone commercial</label>
            <input type="text" name="commercial_phone" :value="agency.commercial_phone"/>
          </div>
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
     MODAL — SUPPRIMER RÉGIE
══════════════════════════════════════ --}}
<div x-data="{ open: false, agency: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'delete-agency') {
       agency = $event.detail.data; open = true;
     }"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal" style="width:420px;" @click.stop>
    <div class="modal-header">
      <span class="modal-title" style="color:var(--red);">
        Supprimer la régie
      </span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <div class="modal-body" style="text-align:center;padding:32px 22px;">
      <div style="font-size:40px;margin-bottom:12px;">🗑️</div>
      <p style="font-size:15px;font-weight:600;margin-bottom:8px;">
        Supprimer <span x-text="agency.name"
                        style="color:var(--accent);"></span> ?
      </p>
      <p style="font-size:13px;color:var(--text2);">
        Tous les panneaux rattachés seront également supprimés.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
      <form method="POST"
            :action="`/admin/external-agencies/${agency.id}`"
            style="display:inline;">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger">Oui, supprimer</button>
      </form>
    </div>
  </div>
</div>

</x-admin-layout>
