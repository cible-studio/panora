<x-admin-layout title="{{ $agency->name }}">

<x-slot:topbarActions>
  <button class="btn btn-primary"
          @click="$dispatch('open-modal', 'create-panel')">
    + Ajouter un panneau
  </button>
</x-slot:topbarActions>


{{-- ── Retour ── --}}
<div style="margin-bottom:16px;">
  <a href="{{ route('admin.external-agencies.index') }}"
     style="color:var(--text2);font-size:13px;text-decoration:none;">
    ← Retour aux régies
  </a>
</div>

{{-- ── Fiche régie ── --}}
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div style="display:flex;align-items:center;gap:14px;">
      <div class="avatar-circle" style="width:46px;height:46px;font-size:18px;">
        {{ strtoupper(substr($agency->name, 0, 1)) }}
      </div>
      <div>
        <div style="font-size:17px;font-weight:700;">{{ $agency->name }}</div>
        <div style="font-size:12px;color:var(--text2);">
          Régie externe · {{ $agency->externalPanels->count() }} panneau(x)
        </div>
      </div>
    </div>
    <button class="btn btn-ghost btn-sm"
            @click="$dispatch('open-modal', {
              name: 'edit-agency-show',
              data: {{ $agency->toJson() }}
            })">✏️ Modifier</button>
  </div>
  <div class="card-body">
    <div class="form-3col" style="gap:12px;">
      <div>
        <div style="font-size:11px;color:var(--text3);margin-bottom:3px;">CONTACT</div>
        <div style="font-weight:500;">{{ $agency->contact ?? '—' }}</div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--text3);margin-bottom:3px;">EMAIL</div>
        <div style="font-weight:500;">{{ $agency->email ?? '—' }}</div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--text3);margin-bottom:3px;">ADRESSE</div>
        <div style="font-weight:500;">{{ $agency->address ?? '—' }}</div>
      </div>
    </div>
  </div>
</div>

{{-- ── Tableau panneaux ── --}}
<div class="card">
  <div class="card-header">
    <span class="card-title">Panneaux de la régie</span>
    <span style="font-size:12px;color:var(--text2);">
      {{ $agency->externalPanels->count() }} panneau(x)
    </span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Désignation</th>
          <th>Type</th>
          <th>Commune</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($agency->externalPanels as $panel)
          <tr>
            <td>
              <span style="font-family:monospace;font-size:12px;
                           background:var(--surface3);padding:2px 7px;
                           border-radius:5px;color:var(--accent);">
                {{ $panel->code_panneau }}
              </span>
            </td>
            <td style="font-weight:500;">{{ $panel->designation }}</td>
            <td>
              @if($panel->type)
                <span class="badge badge-gray">{{ $panel->type }}</span>
              @else
                <span style="color:var(--text3);">—</span>
              @endif
            </td>
            <td style="color:var(--text2);">
              {{ $panel->commune->name ?? '—' }}
            </td>
            <td>
              <div style="display:flex;gap:5px;">
                <button class="btn btn-ghost btn-sm"
                        @click="$dispatch('open-modal', {
                          name: 'edit-panel',
                          data: {{ $panel->toJson() }}
                        })">✏️</button>
                <button class="btn btn-danger btn-sm"
                        @click="$dispatch('open-modal', {
                          name: 'delete-panel',
                          data: {
                            id: {{ $panel->id }},
                            label: '{{ addslashes($panel->code_panneau) }}'
                          }
                        })">🗑️</button>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5"
                style="text-align:center;padding:40px;color:var(--text3);">
              Aucun panneau pour cette régie.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ══════════════════════════════════════
     MODAL — AJOUTER PANNEAU
══════════════════════════════════════ --}}
<div x-data="{ open: false }"
     x-on:open-modal.window="if($event.detail === 'create-panel') open = true"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal" @click.stop>
    <div class="modal-header">
      <span class="modal-title">Ajouter un panneau</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <form method="POST"
          action="{{ route('admin.external-agencies.panels.store', $agency) }}">
      @csrf
      <input type="hidden" name="agency_id" value="{{ $agency->id }}"/>
      <div class="modal-body">

        <div class="form-2col">
          <div class="mfg">
            <label>Code panneau *</label>
            <input type="text" name="code_panneau"
                   value="{{ old('code_panneau') }}"
                   class="{{ $errors->has('code_panneau') ? 'error' : '' }}"
                   placeholder="Ex : AP-0042" required/>
            @error('code_panneau')
              <div class="field-error">{{ $message }}</div>
            @enderror
          </div>
          <div class="mfg">
            <label>Type</label>
            <input type="text" name="type" value="{{ old('type') }}"
                   placeholder="Ex : 4x3, Mupi…"/>
          </div>
        </div>

        <div class="mfg">
          <label>Désignation *</label>
          <input type="text" name="designation"
                 value="{{ old('designation') }}"
                 class="{{ $errors->has('designation') ? 'error' : '' }}"
                 placeholder="Ex : Boulevard Latrille face mer" required/>
          @error('designation')
            <div class="field-error">{{ $message }}</div>
          @enderror
        </div>

        <div class="mfg">
          <label>Commune *</label>
          <select name="commune_id"
                  class="filter-select {{ $errors->has('commune_id') ? 'error' : '' }}"
                  style="width:100%;" required>
            <option value="">— Sélectionner —</option>
            @foreach($communes as $commune)
              <option value="{{ $commune->id }}"
                {{ old('commune_id') == $commune->id ? 'selected' : '' }}>
                {{ $commune->name }}
                @if($commune->city) · {{ $commune->city }}@endif
              </option>
            @endforeach
          </select>
          @error('commune_id')
            <div class="field-error">{{ $message }}</div>
          @enderror
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
        <button type="submit" class="btn btn-primary">Ajouter</button>
      </div>
    </form>
  </div>
</div>

{{-- ══════════════════════════════════════
     MODAL — ÉDITER PANNEAU
══════════════════════════════════════ --}}
<div x-data="{ open: false, panel: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'edit-panel') {
       panel = $event.detail.data; open = true;
     }"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal" @click.stop>
    <div class="modal-header">
      <span class="modal-title">Modifier le panneau</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <form method="POST"
          :action="`/admin/external-agencies/{{ $agency->id }}/panels/${panel.id}`">
      @csrf @method('PUT')
      <div class="modal-body">

        <div class="form-2col">
          <div class="mfg">
            <label>Code panneau *</label>
            <input type="text" name="code_panneau"
                   :value="panel.code_panneau" required/>
          </div>
          <div class="mfg">
            <label>Type</label>
            <input type="text" name="type" :value="panel.type"/>
          </div>
        </div>

        <div class="mfg">
          <label>Désignation *</label>
          <input type="text" name="designation"
                 :value="panel.designation" required/>
        </div>

        <div class="mfg">
          <label>Commune *</label>
          <select name="commune_id" class="filter-select"
                  style="width:100%;" required>
            <option value="">— Sélectionner —</option>
            @foreach($communes as $commune)
              <option value="{{ $commune->id }}"
                      :selected="panel.commune_id == {{ $commune->id }}">
                {{ $commune->name }}
                @if($commune->city) · {{ $commune->city }}@endif
              </option>
            @endforeach
          </select>
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
     MODAL — SUPPRIMER PANNEAU
══════════════════════════════════════ --}}
<div x-data="{ open: false, panel: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'delete-panel') {
       panel = $event.detail.data; open = true;
     }"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal" style="width:420px;" @click.stop>
    <div class="modal-header">
      <span class="modal-title" style="color:var(--red);">
        Supprimer le panneau
      </span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <div class="modal-body" style="text-align:center;padding:32px 22px;">
      <div style="font-size:40px;margin-bottom:12px;">🗑️</div>
      <p style="font-size:15px;font-weight:600;margin-bottom:8px;">
        Supprimer le panneau
        <span x-text="panel.label" style="color:var(--accent);"></span> ?
      </p>
      <p style="font-size:13px;color:var(--text2);">
        Cette action est irréversible.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
      <form method="POST"
            :action="`/admin/external-agencies/{{ $agency->id }}/panels/${panel.id}`"
            style="display:inline;">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger">Oui, supprimer</button>
      </form>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════════
     MODAL — ÉDITER RÉGIE (depuis show)
══════════════════════════════════════ --}}
<div x-data="{ open: false, agency: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'edit-agency-show') {
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
    <form method="POST"
          :action="`/admin/external-agencies/${agency.id}`">
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

</x-admin-layout>