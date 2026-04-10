<x-admin-layout title="{{ $agency->name }}">

<x-slot:topbarActions>
  <button class="btn btn-primary"
          @click="$dispatch('open-modal', 'create-panel')">
    + Ajouter un panneau
  </button>
</x-slot:topbarActions>

{{-- Retour --}}
<div style="margin-bottom:16px;">
  <a href="{{ route('admin.external-agencies.index') }}"
     style="color:var(--text2);font-size:13px;text-decoration:none;">
    ← Retour aux régies
  </a>
</div>

{{-- Fiche régie --}}
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
    <div class="form-3col">
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

{{-- Tableau panneaux --}}
<div class="card">
  <div class="card-header">
    <span class="card-title">🪧 Panneaux de la régie</span>
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
          <th>Format / Catégorie</th>
          <th>Commune</th>
          <th>Faces</th>
          <th>Tarif/mois</th>
          <th>Client</th>
          <th>Campagne</th>
          <th>Éclairé</th>
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
            <td>
              <div style="font-weight:500;">{{ $panel->designation }}</div>
              @if($panel->quartier)
                <div style="font-size:11px;color:var(--text3);">{{ $panel->quartier }}</div>
              @endif
            </td>
            <td>
              <div>{{ $panel->format?->name ?? '—' }}</div>
              @if($panel->category)
                <div style="font-size:11px;color:var(--text3);">{{ $panel->category->name }}</div>
              @endif
            </td>
            <td style="color:var(--text2);">{{ $panel->commune->name ?? '—' }}</td>
            <td style="text-align:center;font-weight:700;color:var(--text2);">
              {{ $panel->nombre_faces ?? 1 }}
            </td>
            <td style="color:var(--accent);font-weight:600;">
              @if($panel->monthly_rate > 0)
                {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
              @else
                <span style="color:var(--text3);">—</span>
              @endif
            </td>
            <td>
              @if($panel->client)
                <span style="font-size:12px;padding:2px 8px;border-radius:20px;
                             background:rgba(63,127,192,0.12);color:#3f7fc0;
                             border:1px solid rgba(63,127,192,0.3);font-weight:600;">
                  {{ $panel->client->name }}
                </span>
              @else
                <span style="color:var(--text3);">—</span>
              @endif
            </td>
            <td>
              @if($panel->campaign)
                <span style="font-size:12px;padding:2px 8px;border-radius:20px;
                             background:rgba(129,53,138,0.12);color:#81358a;
                             border:1px solid rgba(129,53,138,0.3);font-weight:600;">
                  {{ Str::limit($panel->campaign->name, 20) }}
                </span>
              @else
                <span style="color:var(--text3);">—</span>
              @endif
            </td>
            <td style="text-align:center;">
              {{ $panel->is_lit ? '💡' : '—' }}
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
            <td colspan="10"
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
     MACRO : champs communs panneau
══════════════════════════════════════ --}}
@php
$orientations = ['nord','sud','est','ouest','nord-est','nord-ouest','sud-est','sud-ouest'];
@endphp

{{-- ══════════════════════════════════════
     MODAL — AJOUTER PANNEAU (formulaire complet)
══════════════════════════════════════ --}}
<div x-data="{ open: false }"
     x-on:open-modal.window="if($event.detail === 'create-panel') open = true"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal modal-wide" @click.stop>
    <div class="modal-header">
      <span class="modal-title">➕ Ajouter un panneau</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <form method="POST"
          action="{{ route('admin.external-agencies.panels.store', $agency) }}">
      @csrf
      <input type="hidden" name="agency_id" value="{{ $agency->id }}"/>
      <div class="modal-body">

        {{-- INFORMATIONS GÉNÉRALES --}}
        <div class="section-label">Informations générales</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Code panneau *</label>
            <input type="text" name="code_panneau"
                   value="{{ old('code_panneau') }}"
                   placeholder="Ex : AP-0042" required/>
            @error('code_panneau')<div class="field-error">{{ $message }}</div>@enderror
          </div>
          <div class="mfg">
            <label>Type de support</label>
            <input type="text" name="type" value="{{ old('type') }}"
                   placeholder="Ex : 4x3, Mupi, LED…"/>
          </div>
        </div>

        <div class="mfg">
          <label>Désignation *</label>
          <input type="text" name="designation"
                 value="{{ old('designation') }}"
                 placeholder="Ex : Boulevard Latrille face mer" required/>
          @error('designation')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Commune *</label>
            <select name="commune_id" required>
              <option value="">— Sélectionner —</option>
              @foreach($communes as $commune)
                <option value="{{ $commune->id }}"
                  {{ old('commune_id') == $commune->id ? 'selected' : '' }}>
                  {{ $commune->name }}
                </option>
              @endforeach
            </select>
            @error('commune_id')<div class="field-error">{{ $message }}</div>@enderror
          </div>
          <div class="mfg">
            <label>Zone</label>
            <select name="zone_id">
              <option value="">— Aucune —</option>
              @foreach($zones as $zone)
                <option value="{{ $zone->id }}"
                  {{ old('zone_id') == $zone->id ? 'selected' : '' }}>
                  {{ $zone->name }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Format</label>
            <select name="format_id">
              <option value="">— Aucun —</option>
              @foreach($formats as $format)
                <option value="{{ $format->id }}"
                  {{ old('format_id') == $format->id ? 'selected' : '' }}>
                  {{ $format->name }}@if($format->surface) ({{ $format->surface }}m²)@endif
                </option>
              @endforeach
            </select>
          </div>
          <div class="mfg">
            <label>Catégorie</label>
            <select name="category_id">
              <option value="">— Aucune —</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}"
                  {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                  {{ $cat->name }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- CARACTÉRISTIQUES TECHNIQUES --}}
        <div class="section-label">Caractéristiques techniques</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Nombre de faces</label>
            <input type="number" name="nombre_faces"
                   value="{{ old('nombre_faces', 1) }}" min="1" max="6"/>
          </div>
          <div class="mfg">
            <input type="checkbox" name="is_lit" value="1"
                   id="is_lit_toggle_create"
                   {{ old('is_lit') ? 'checked' : '' }}
                   style="display:none;">
            <div style="display:flex;align-items:center;gap:12px;cursor:pointer;margin-top:18px;" onclick="toggleLitCreate()">
              <div id="toggle-track-create"
                   style="position:relative;width:52px;height:28px;border-radius:14px;
                          background:{{ old('is_lit') ? '#e8a020' : '#d1d5db' }};
                          transition:background .3s ease;flex-shrink:0;">
                <div id="toggle-thumb-create"
                     style="position:absolute;top:3px;left:{{ old('is_lit') ? '25px' : '3px' }};
                            width:22px;height:22px;border-radius:50%;background:white;
                            box-shadow:0 1px 4px rgba(0,0,0,.25);transition:left .3s ease;">
                </div>
              </div>
              <div>
                <div id="toggle-label-create"
                     style="font-size:14px;font-weight:600;color:{{ old('is_lit') ? '#e8a020' : 'var(--text2)' }};">
                  {{ old('is_lit') ? '💡 Éclairé (rétroéclairé)' : '🌑 Non éclairé' }}
                </div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px;">Cliquez pour basculer</div>
              </div>
            </div>
          </div>
        </div>
        <script>
        function toggleLitCreate() {
            const cb = document.getElementById('is_lit_toggle_create');
            const track = document.getElementById('toggle-track-create');
            const thumb = document.getElementById('toggle-thumb-create');
            const label = document.getElementById('toggle-label-create');
            cb.checked = !cb.checked;
            if (cb.checked) {
                track.style.background = '#e8a020'; thumb.style.left = '25px';
                label.textContent = '💡 Éclairé (rétroéclairé)'; label.style.color = '#e8a020';
            } else {
                track.style.background = '#d1d5db'; thumb.style.left = '3px';
                label.textContent = '🌑 Non éclairé'; label.style.color = 'var(--text2)';
            }
        }
        </script>

        {{-- TARIFICATION --}}
        <div class="section-label">Tarification</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Tarif mensuel (FCFA)</label>
            <input type="number" name="monthly_rate"
                   value="{{ old('monthly_rate', 0) }}" step="1000" min="0"/>
          </div>
          <div class="mfg">
            <label>Trafic journalier</label>
            <input type="number" name="daily_traffic"
                   value="{{ old('daily_traffic') }}"
                   min="0" placeholder="Nb véhicules/jour"/>
          </div>
        </div>

        {{-- LOCALISATION --}}
        <div class="section-label">Localisation</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Adresse</label>
            <input type="text" name="adresse" value="{{ old('adresse') }}"
                   placeholder="Ex : Rue des Jardins, N°12"/>
          </div>
          <div class="mfg">
            <label>Quartier</label>
            <input type="text" name="quartier" value="{{ old('quartier') }}"
                   placeholder="Ex : Deux Plateaux"/>
          </div>
        </div>

        <div class="mfg">
          <label>Description emplacement</label>
          <textarea name="zone_description"
                    placeholder="Ex : Face au carrefour, côté droit en venant du Plateau…">{{ old('zone_description') }}</textarea>
        </div>

        {{-- GPS --}}
        <div class="section-label">Coordonnées GPS</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Latitude</label>
            <input type="number" name="latitude"
                   value="{{ old('latitude') }}"
                   step="0.0000001" placeholder="Ex : 5.3600"/>
          </div>
          <div class="mfg">
            <label>Longitude</label>
            <input type="number" name="longitude"
                   value="{{ old('longitude') }}"
                   step="0.0000001" placeholder="Ex : -4.0083"/>
          </div>
        </div>

        {{-- LIAISON COMMERCIALE --}}
        <div class="section-label" style="color:var(--accent);">🔗 Liaison commerciale (optionnel)</div>
        <div style="background:rgba(226,6,19,0.05);border:1px solid rgba(226,6,19,0.2);border-radius:10px;padding:14px;margin-bottom:16px;">
          <div style="font-size:12px;color:var(--text2);margin-bottom:12px;">
            Associez ce panneau à un client et une campagne existante.
          </div>

          <div class="form-2col">
            <div class="mfg">
              <label>Client concerné</label>
              <select name="client_id" id="ext-client-select"
                      onchange="window.updateExtCampaigns(this.value)">
                <option value="">— Aucun client —</option>
                @foreach($clients as $client)
                  <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="mfg">
              <label>Campagne liée</label>
              <select name="campaign_id" id="ext-campaign-select">
                <option value="">— Choisir un client d'abord —</option>
              </select>
            </div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
        <button type="submit" class="btn btn-primary">✅ Ajouter le panneau</button>
      </div>
    </form>
  </div>
</div>

{{-- ══════════════════════════════════════
     MODAL — ÉDITER PANNEAU (formulaire complet)
══════════════════════════════════════ --}}
<div x-data="{ open: false, panel: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'edit-panel') {
       panel = $event.detail.data; open = true;
     }"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
  <div class="modal modal-wide" @click.stop>
    <div class="modal-header">
      <span class="modal-title">✏️ Modifier le panneau</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <form method="POST"
          :action="`/admin/external-agencies/{{ $agency->id }}/panels/${panel.id}`">
      @csrf @method('PUT')
      <div class="modal-body">

        <div class="section-label">Informations générales</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Code panneau *</label>
            <input type="text" name="code_panneau"
                   :value="panel.code_panneau" required/>
          </div>
          <div class="mfg">
            <label>Type de support</label>
            <input type="text" name="type" :value="panel.type"
                   placeholder="Ex : 4x3, Mupi, LED…"/>
          </div>
        </div>

        <div class="mfg">
          <label>Désignation *</label>
          <input type="text" name="designation"
                 :value="panel.designation" required/>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Commune *</label>
            <select name="commune_id" required>
              <option value="">— Sélectionner —</option>
              @foreach($communes as $commune)
                <option value="{{ $commune->id }}"
                        :selected="panel.commune_id == {{ $commune->id }}">
                  {{ $commune->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="mfg">
            <label>Zone</label>
            <select name="zone_id">
              <option value="">— Aucune —</option>
              @foreach($zones as $zone)
                <option value="{{ $zone->id }}"
                        :selected="panel.zone_id == {{ $zone->id }}">
                  {{ $zone->name }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Format</label>
            <select name="format_id">
              <option value="">— Aucun —</option>
              @foreach($formats as $format)
                <option value="{{ $format->id }}"
                        :selected="panel.format_id == {{ $format->id }}">
                  {{ $format->name }}@if($format->surface) ({{ $format->surface }}m²)@endif
                </option>
              @endforeach
            </select>
          </div>
          <div class="mfg">
            <label>Catégorie</label>
            <select name="category_id">
              <option value="">— Aucune —</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}"
                        :selected="panel.category_id == {{ $cat->id }}">
                  {{ $cat->name }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="section-label">Caractéristiques techniques</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Nombre de faces</label>
            <input type="number" name="nombre_faces"
                   :value="panel.nombre_faces ?? 1" min="1" max="6"/>
          </div>
          <div class="mfg">
            <input type="checkbox" name="is_lit" value="1"
                   id="is_lit_toggle_edit"
                   :checked="panel.is_lit"
                   style="display:none;">
            <div style="display:flex;align-items:center;gap:12px;cursor:pointer;margin-top:18px;" onclick="toggleLitEdit()">
              <div id="toggle-track-edit"
                   style="position:relative;width:52px;height:28px;border-radius:14px;
                          background:#d1d5db;transition:background .3s ease;flex-shrink:0;"
                   x-init="$el.style.background = panel.is_lit ? '#e8a020' : '#d1d5db'">
                <div id="toggle-thumb-edit"
                     style="position:absolute;top:3px;left:3px;width:22px;height:22px;
                            border-radius:50%;background:white;box-shadow:0 1px 4px rgba(0,0,0,.25);
                            transition:left .3s ease;"
                     x-init="$el.style.left = panel.is_lit ? '25px' : '3px'">
                </div>
              </div>
              <div>
                <div id="toggle-label-edit"
                     style="font-size:14px;font-weight:600;color:var(--text2);"
                     x-init="$el.textContent = panel.is_lit ? '💡 Éclairé (rétroéclairé)' : '🌑 Non éclairé'; $el.style.color = panel.is_lit ? '#e8a020' : 'var(--text2)'">
                  🌑 Non éclairé
                </div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px;">Cliquez pour basculer</div>
              </div>
            </div>
          </div>
        </div>
        <script>
        function toggleLitEdit() {
            const cb = document.getElementById('is_lit_toggle_edit');
            const track = document.getElementById('toggle-track-edit');
            const thumb = document.getElementById('toggle-thumb-edit');
            const label = document.getElementById('toggle-label-edit');
            cb.checked = !cb.checked;
            if (cb.checked) {
                track.style.background = '#e8a020'; thumb.style.left = '25px';
                label.textContent = '💡 Éclairé (rétroéclairé)'; label.style.color = '#e8a020';
            } else {
                track.style.background = '#d1d5db'; thumb.style.left = '3px';
                label.textContent = '🌑 Non éclairé'; label.style.color = 'var(--text2)';
            }
        }
        </script>

        <div class="section-label">Tarification</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Tarif mensuel (FCFA)</label>
            <input type="number" name="monthly_rate"
                   :value="panel.monthly_rate ?? 0" step="1000" min="0"/>
          </div>
          <div class="mfg">
            <label>Trafic journalier</label>
            <input type="number" name="daily_traffic"
                   :value="panel.daily_traffic" min="0"/>
          </div>
        </div>

        <div class="section-label">Localisation</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Adresse</label>
            <input type="text" name="adresse" :value="panel.adresse"/>
          </div>
          <div class="mfg">
            <label>Quartier</label>
            <input type="text" name="quartier" :value="panel.quartier"/>
          </div>
        </div>

        <div class="mfg">
          <label>Axe routier</label>
          <input type="text" name="axe_routier" :value="panel.axe_routier"/>
        </div>

        <div class="mfg">
          <label>Description emplacement</label>
          <textarea name="zone_description" x-text="panel.zone_description"></textarea>
        </div>

        <div class="section-label">Coordonnées GPS</div>

        <div class="form-2col">
          <div class="mfg">
            <label>Latitude</label>
            <input type="number" name="latitude"
                   :value="panel.latitude" step="0.0000001"/>
          </div>
          <div class="mfg">
            <label>Longitude</label>
            <input type="number" name="longitude"
                   :value="panel.longitude" step="0.0000001"/>
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
      <span class="modal-title" style="color:var(--red);">🗑️ Supprimer le panneau</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <div class="modal-body" style="text-align:center;padding:32px 22px;">
      <div style="font-size:40px;margin-bottom:12px;">🗑️</div>
      <p style="font-size:15px;font-weight:600;margin-bottom:8px;">
        Supprimer le panneau
        <span x-text="panel.label" style="color:var(--accent);"></span> ?
      </p>
      <p style="font-size:13px;color:var(--text2);">Cette action est irréversible.</p>
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
     MODAL — ÉDITER RÉGIE
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


{{-- Script global liaison commerciale --}}
<script>
window.CLIENTS_CAMPAIGNS = {!! json_encode($clients->map(function($c) {
    return [
        'id'        => $c->id,
        'campaigns' => $c->campaigns->map(fn($camp) => [
            'id'   => $camp->id,
            'name' => $camp->name,
        ])->values(),
    ];
})->keyBy('id')) !!};

window.updateExtCampaigns = function(clientId) {
    const campSel = document.getElementById('ext-campaign-select');
    if (!campSel) return;

    if (!clientId || !window.CLIENTS_CAMPAIGNS[clientId]) {
        campSel.innerHTML = '<option value="">— Choisir un client d\'abord —</option>';
        return;
    }

    const campaigns = window.CLIENTS_CAMPAIGNS[clientId].campaigns;
    campSel.innerHTML = '<option value="">— Aucune campagne —</option>' +
        campaigns.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
};
</script>
</x-admin-layout>
