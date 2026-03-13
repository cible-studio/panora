<x-admin-layout title="Nouvelle réservation">

<x-slot:topbarActions>
  <a href="{{ route('admin.reservations.index') }}" class="btn btn-ghost">← Retour</a>
</x-slot:topbarActions>

<div style="max-width:960px;">

  @if($errors->any())
    <div class="flash flash-error" style="margin-bottom:16px;">
      @foreach($errors->all() as $e)
        <div>✕ {{ $e }}</div>
      @endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('admin.reservations.store') }}"
        x-data="reservationForm()">
    @csrf

    {{-- ── Bloc 1 : Infos générales ── --}}
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <span class="card-title">Informations générales</span>
      </div>
      <div class="card-body">

        <div class="mfg">
          <label>Client *</label>
          <select name="client_id"
                  class="{{ $errors->has('client_id') ? 'error' : '' }}"
                  style="background:var(--surface2);border:1px solid var(--border2);
                         border-radius:8px;padding:9px 12px;color:var(--text);
                         font-family:var(--font-body);font-size:13px;
                         outline:none;width:100%;" required>
            <option value="">— Sélectionner un client —</option>
            @foreach($clients as $client)
              <option value="{{ $client->id }}"
                      {{ old('client_id') == $client->id ? 'selected' : '' }}>
                {{ $client->name }}
              </option>
            @endforeach
          </select>
          @error('client_id')
            <div class="field-error">{{ $message }}</div>
          @enderror
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Date de début *</label>
            <input type="date" name="start_date"
                   x-model="startDate"
                   value="{{ old('start_date', $startDate) }}"
                   class="{{ $errors->has('start_date') ? 'error' : '' }}"
                   @change="loadPanels" required/>
            @error('start_date')
              <div class="field-error">{{ $message }}</div>
            @enderror
          </div>
          <div class="mfg">
            <label>Date de fin *</label>
            <input type="date" name="end_date"
                   x-model="endDate"
                   value="{{ old('end_date', $endDate) }}"
                   class="{{ $errors->has('end_date') ? 'error' : '' }}"
                   @change="loadPanels" required/>
            @error('end_date')
              <div class="field-error">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <div class="mfg">
          <label>Notes</label>
          <textarea name="notes"
                    placeholder="Remarques, conditions particulières…">{{ old('notes') }}</textarea>
        </div>

      </div>
    </div>

    {{-- ── Bloc 2 : Sélection panneaux ── --}}
    <div class="card" style="margin-bottom:16px;">

      <div class="card-header">
        <span class="card-title">Panneaux disponibles</span>
        <span style="font-size:12px;color:var(--text2);"
              x-text="panels.length
                ? panels.length + ' panneau(x) disponible(s)'
                : 'Sélectionnez une période'">
        </span>
      </div>

      {{-- Filtres panneaux --}}
      <div style="padding:12px 17px;border-bottom:1px solid var(--border);
                  display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;
                  background:var(--surface2);">
        <div class="filter-group">
          <label class="filter-label">Zone</label>
          <select class="filter-select" x-model="filters.zone_id" @change="loadPanels">
            <option value="">Toutes</option>
            @foreach($zones as $zone)
              <option value="{{ $zone->id }}">{{ $zone->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">Commune</label>
          <select class="filter-select" x-model="filters.commune_id" @change="loadPanels">
            <option value="">Toutes</option>
            @foreach($communes as $commune)
              <option value="{{ $commune->id }}">{{ $commune->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">Format</label>
          <select class="filter-select" x-model="filters.format_id" @change="loadPanels">
            <option value="">Tous</option>
            @foreach($formats as $format)
              <option value="{{ $format->id }}">{{ $format->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-group">
          <button type="button" class="btn btn-ghost btn-sm"
                  @click="filters = { zone_id:'', commune_id:'', format_id:'' }; loadPanels()">
            ✕ Réinitialiser
          </button>
        </div>
      </div>

      {{-- Loader --}}
      <div x-show="loading"
           style="padding:40px;text-align:center;color:var(--text2);">
        ⏳ Chargement des disponibilités…
      </div>

      {{-- Message initial --}}
      <div x-show="!loading && !startDate && !endDate"
           style="padding:40px;text-align:center;color:var(--text3);">
        📅 Sélectionnez une période pour voir les panneaux disponibles.
      </div>

      {{-- Aucun panneau --}}
      <div x-show="!loading && startDate && endDate && panels.length === 0"
           style="padding:40px;text-align:center;color:var(--text3);">
        😔 Aucun panneau disponible sur cette période.
      </div>

      {{-- Tableau --}}
      <div x-show="!loading && panels.length > 0" class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:40px;">
                <input type="checkbox" @change="toggleAll($event)"
                       style="accent-color:var(--accent);width:15px;height:15px;">
              </th>
              <th>Référence</th>
              <th>Nom</th>
              <th>Commune</th>
              <th>Format</th>
              <th>Éclairé</th>
              <th>Tarif / mois</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="panel in panels" :key="panel.id">
              <tr :style="selectedIds.includes(panel.id)
                    ? 'background:var(--accent-dim);cursor:pointer;'
                    : 'cursor:pointer;'"
                  @click="togglePanel(panel.id)">
                <td @click.stop>
                  <input type="checkbox"
                         :value="panel.id"
                         :checked="selectedIds.includes(panel.id)"
                         @change="togglePanel(panel.id)"
                         style="accent-color:var(--accent);width:15px;height:15px;">
                </td>
                <td>
                  <span style="font-family:monospace;font-size:12px;
                               background:var(--surface3);padding:2px 7px;
                               border-radius:5px;color:var(--accent);"
                        x-text="panel.reference"></span>
                </td>
                <td style="font-weight:500;" x-text="panel.name"></td>
                <td style="color:var(--text2);" x-text="panel.commune ?? '—'"></td>
                <td style="color:var(--text2);" x-text="panel.format ?? '—'"></td>
                <td>
                  <span x-text="panel.is_lit ? '💡 Oui' : 'Non'"
                        :style="panel.is_lit
                          ? 'color:var(--accent)'
                          : 'color:var(--text3)'"></span>
                </td>
                <td style="font-weight:600;color:var(--accent);"
                    x-text="panel.monthly_rate
                      ? Number(panel.monthly_rate).toLocaleString('fr-FR') + ' FCFA'
                      : '—'"></td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      {{-- Inputs hidden --}}
      <template x-for="id in selectedIds" :key="id">
        <input type="hidden" name="panel_ids[]" :value="id">
      </template>

      {{-- Récap sélection --}}
      <div x-show="selectedIds.length > 0"
           style="padding:14px 17px;border-top:1px solid var(--border);
                  display:flex;justify-content:space-between;align-items:center;">
        <span style="color:var(--text2);font-size:13px;">
          <span x-text="selectedIds.length"></span> panneau(x) sélectionné(s)
        </span>
        <span style="font-weight:700;color:var(--accent);font-size:15px;">
          Total estimé : <span x-text="formatTotal()"></span> FCFA
        </span>
      </div>

      @error('panel_ids')
        <div class="field-error" style="padding:8px 17px;">{{ $message }}</div>
      @enderror

    </div>

    {{-- ── Actions ── --}}
    <div style="display:flex;justify-content:flex-end;gap:10px;">
      <a href="{{ route('admin.reservations.index') }}" class="btn btn-ghost">Annuler</a>
      <button type="submit" class="btn btn-primary btn-lg"
              :disabled="selectedIds.length === 0"
              :style="selectedIds.length === 0
                ? 'opacity:0.5;cursor:not-allowed;' : ''">
        ✓ Créer la réservation
      </button>
    </div>

  </form>
</div>

@push('scripts')
<script>
function reservationForm() {
  return {
    startDate:   '{{ old('start_date', $startDate ?? '') }}',
    endDate:     '{{ old('end_date',   $endDate   ?? '') }}',
    panels:      [],
    selectedIds: @json(old('panel_ids', [])),
    loading:     false,
    filters:     { zone_id: '', commune_id: '', format_id: '' },

    init() {
      if (this.startDate && this.endDate) this.loadPanels();
    },

    async loadPanels() {
      if (!this.startDate || !this.endDate) return;
      this.loading     = true;
      this.panels      = [];
      this.selectedIds = [];
      try {
        const params = new URLSearchParams({
          start_date: this.startDate,
          end_date:   this.endDate,
        });
        if (this.filters.zone_id)    params.append('zone_id',    this.filters.zone_id);
        if (this.filters.commune_id) params.append('commune_id', this.filters.commune_id);
        if (this.filters.format_id)  params.append('format_id',  this.filters.format_id);

        const res = await fetch(
          `/admin/reservations/available-panels?${params}`,
          { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } }
        );
        this.panels = await res.json();
      } catch (e) {
        console.error('Erreur chargement panneaux :', e);
      } finally {
        this.loading = false;
      }
    },

    togglePanel(id) {
      const idx = this.selectedIds.indexOf(id);
      if (idx === -1) this.selectedIds.push(id);
      else            this.selectedIds.splice(idx, 1);
    },

    toggleAll(e) {
      this.selectedIds = e.target.checked ? this.panels.map(p => p.id) : [];
    },

    formatTotal() {
      const months = this.monthsBetween(this.startDate, this.endDate);
      const total  = this.selectedIds.reduce((sum, id) => {
        const panel = this.panels.find(p => p.id === id);
        return sum + (panel ? (parseFloat(panel.monthly_rate) || 0) * months : 0);
      }, 0);
      return Math.round(total).toLocaleString('fr-FR');
    },

    monthsBetween(start, end) {
      if (!start || !end) return 1;
      const diff = (new Date(end) - new Date(start)) / (1000 * 60 * 60 * 24);
      return Math.max(Math.round((diff / 30) * 100) / 100, 1);
    }
  }
}
</script>
@endpush

</x-admin-layout>