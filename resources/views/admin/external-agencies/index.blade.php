<x-admin-layout title="Régies Externes">

{{-- ── Topbar ── --}}
<x-slot:topbarActions>
  <button class="btn btn-primary"
          @click="$dispatch('open-modal', 'create-agency')">
    + Nouvelle régie
  </button>
</x-slot:topbarActions>

{{-- ── Filtres ── --}}
<div class="filter-bar" style="margin-bottom:16px;border-radius:12px 12px 0 0;">
  <form method="GET" action="{{ route('admin.external-agencies.index') }}"
        style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;width:100%;">
    <div class="filter-group" style="flex:1;min-width:200px;">
      <label class="filter-label">Recherche</label>
      <input type="text" name="search" value="{{ request('search') }}"
             class="filter-input" style="width:100%;"
             placeholder="Nom, email, contact…"/>
    </div>
    <div class="filter-group">
      <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
    </div>
    @if(request('search'))
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
            <td colspan="5"
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
            <label>Contact</label>
            <input type="text" name="contact" value="{{ old('contact') }}"
                   placeholder="Nom du responsable"/>
          </div>
          <div class="mfg">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="{{ $errors->has('email') ? 'error' : '' }}"
                   placeholder="contact@regie.ci"/>
            @error('email')<div class="field-error">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="mfg">
          <label>Adresse</label>
          <textarea name="address"
                    placeholder="Ex : Zone 4, Abidjan">{{ old('address') }}</textarea>
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
            <label>Contact</label>
            <input type="text" name="contact" :value="agency.contact"/>
          </div>
          <div class="mfg">
            <label>Email</label>
            <input type="email" name="email" :value="agency.email"/>
          </div>
        </div>

        <div class="mfg">
          <label>Adresse</label>
          <textarea name="address" x-text="agency.address"></textarea>
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