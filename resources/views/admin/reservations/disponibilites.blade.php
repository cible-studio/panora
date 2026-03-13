<x-admin-layout title="Disponibilités & Panneaux">

<x-slot:topbarActions>
  <button class="btn btn-primary"
          x-data
          @click="$dispatch('open-modal', 'confirm-selection')"
          x-show="false"
          id="btn-confirm-top">
    + Nouvelle réservation
  </button>
</x-slot:topbarActions>

<div x-data="disponibilites()" x-init="init()">

  {{-- ── Filtres ── --}}
  <form method="GET" action="{{ route('admin.reservations.disponibilites') }}"
        style="background:var(--surface);border:1px solid var(--border);
               border-radius:12px;padding:14px 16px;margin-bottom:16px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">

      <div class="filter-group">
        <label class="filter-label">Commune</label>
        <select name="commune_id" class="filter-select">
          <option value="">Toutes</option>
          @foreach($communes as $c)
            <option value="{{ $c->id }}" {{ request('commune_id') == $c->id ? 'selected' : '' }}>
              {{ $c->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label">Zone</label>
        <select name="zone_id" class="filter-select">
          <option value="">Toutes</option>
          @foreach($zones as $z)
            <option value="{{ $z->id }}" {{ request('zone_id') == $z->id ? 'selected' : '' }}>
              {{ $z->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label">Format</label>
        <select name="format_id" class="filter-select">
          <option value="">Tous</option>
          @foreach($formats as $f)
            <option value="{{ $f->id }}" {{ request('format_id') == $f->id ? 'selected' : '' }}>
              {{ $f->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label">Dispo du</label>
        <input type="date" name="dispo_du"
               value="{{ request('dispo_du') }}"
               class="filter-input"/>
      </div>

      <div class="filter-group">
        <label class="filter-label">Au</label>
        <input type="date" name="dispo_au"
               value="{{ request('dispo_au') }}"
               class="filter-input"/>
      </div>

      <div class="filter-group">
        <label class="filter-label">Statut</label>
        <select name="statut" class="filter-select">
          <option value="tous" {{ request('statut','tous') === 'tous' ? 'selected' : '' }}>Tous</option>
          <option value="libre"      {{ request('statut') === 'libre'      ? 'selected' : '' }}>Disponible</option>
          <option value="occupe"     {{ request('statut') === 'occupe'     ? 'selected' : '' }}>Occupé</option>
          <option value="confirme"   {{ request('statut') === 'confirme'   ? 'selected' : '' }}>Confirmé</option>
          <option value="option"     {{ request('statut') === 'option'     ? 'selected' : '' }}>Option</option>
          <option value="maintenance"{{ request('statut') === 'maintenance'? 'selected' : '' }}>Maintenance</option>
        </select>
      </div>

      <div>
        <label>Dimensions</label>
        <select name="dimensions"
                style="background:var(--surface2);border:1px solid var(--border2);
                       border-radius:8px;padding:7px 12px;color:var(--text);font-size:13px;outline:none;">
            <option value="">Toutes</option>
            @foreach($dimensions as $dim)
                <option value="{{ $dim }}"
                        {{ request('dimensions') === $dim ? 'selected' : '' }}>
                    {{ $dim }}
                </option>
            @endforeach
        </select>
    </div>

      <div class="filter-group" style="display:flex;gap:6px;">
        <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
        @if(request()->hasAny(['commune_id','zone_id','format_id','dispo_du','dispo_au','statut']))
          <a href="{{ route('admin.reservations.disponibilites') }}"
             class="btn btn-ghost btn-sm">✕</a>
        @endif
      </div>

    </div>

    {{-- Boutons export --}}
    <div style="display:flex;gap:8px;margin-top:12px;padding-top:12px;
                border-top:1px solid var(--border);">
      <button type="button" class="btn btn-ghost btn-sm">📊 Excel</button>
      <button type="button" class="btn btn-ghost btn-sm">📋 CSV</button>
      <button type="button" class="btn btn-ghost btn-sm"
              style="border-color:var(--red);color:var(--red);">
        📄 PDF avec images
      </button>
      <button type="button" class="btn btn-ghost btn-sm"
              style="border-color:var(--red);color:var(--red);">
        📄 PDF liste
      </button>
    </div>
  </form>

  {{-- ── Grille panneaux ── --}}
  @if($allPanels->isEmpty())
    <div style="text-align:center;padding:60px;color:var(--text3);">
      Aucun panneau trouvé.
    </div>
  @else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
                gap:14px;margin-bottom:100px;">

      @foreach($allPanels as $panel)
        @php
          // Statut sur la période filtrée
          $isOccupied = $occupiedIds->contains($panel->id);
          $panelStatus = $panel->status->value; // statut BDD

          if ($isOccupied && $startDate && $endDate) {
            $displayStatus = 'occupe';
          } else {
            $displayStatus = $panelStatus;
          }

          $statusConfig = [
            'libre'       => ['label'=>'Disponible',  'color'=>'#22c55e', 'bg'=>'rgba(34,197,94,0.08)',   'border'=>'rgba(34,197,94,0.3)'],
            'occupe'      => ['label'=>'Occupé',       'color'=>'#ef4444', 'bg'=>'rgba(239,68,68,0.08)',   'border'=>'rgba(239,68,68,0.3)'],
            'confirme'    => ['label'=>'Confirmé',     'color'=>'#a855f7', 'bg'=>'rgba(168,85,247,0.08)',  'border'=>'rgba(168,85,247,0.3)'],
            'option'      => ['label'=>'Option',       'color'=>'#e8a020', 'bg'=>'rgba(232,160,32,0.08)',  'border'=>'rgba(232,160,32,0.3)'],
            'maintenance' => ['label'=>'Maintenance',  'color'=>'#6b7280', 'bg'=>'rgba(107,114,128,0.08)','border'=>'rgba(107,114,128,0.3)'],
          ];
          $sc = $statusConfig[$displayStatus] ?? $statusConfig['libre'];

          // Couleur fond carte panneau selon catégorie
          $cardColors = ['#3b82f6','#a855f7','#f97316','#14b8a6','#e8a020','#22c55e'];
          $colorIdx   = crc32($panel->reference) % count($cardColors);
          $cardBg     = $cardColors[abs($colorIdx)];

          $isAvailable = ($displayStatus === 'libre');
        @endphp

        <div style="background:var(--surface);border:1px solid {{ $sc['border'] }};
                    border-radius:14px;overflow:hidden;position:relative;
                    transition:transform 0.15s,box-shadow 0.15s;cursor:pointer;"
             :style="selectedIds.includes({{ $panel->id }})
               ? 'border-color:var(--accent);box-shadow:0 0 0 2px rgba(232,160,32,0.3);'
               : ''"
             @mouseenter="$el.style.transform='translateY(-2px)'"
             @mouseleave="$el.style.transform='translateY(0)'">

          {{-- Badge statut --}}
          <div style="position:absolute;top:10px;right:10px;z-index:2;
                      padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;
                      background:{{ $sc['bg'] }};color:{{ $sc['color'] }};
                      border:1px solid {{ $sc['border'] }};">
            {{ $sc['label'] }}
          </div>

          {{-- Checkbox sélection --}}
          @if($isAvailable)
            <div style="position:absolute;top:10px;left:10px;z-index:2;">
              <input type="checkbox"
                     :checked="selectedIds.includes({{ $panel->id }})"
                     @change="togglePanel({{ $panel->id }}, {{ $panel->monthly_rate ?? 0 }})"
                     @click.stop
                     style="accent-color:var(--accent);width:16px;height:16px;">
            </div>
          @endif

          {{-- Visuel panneau --}}
          <div style="background:{{ $sc['bg'] }};padding:28px 20px 16px;
                      display:flex;justify-content:center;align-items:center;min-height:110px;"
               @click="@if($isAvailable) togglePanel({{ $panel->id }}, {{ $panel->monthly_rate ?? 0 }}) @endif">
            <div style="background:{{ $cardBg }};border-radius:8px;
                        padding:8px 20px;font-family:monospace;font-size:15px;
                        font-weight:700;color:#fff;letter-spacing:1px;
                        box-shadow:0 4px 12px rgba(0,0,0,0.3);">
              {{ $panel->reference }}
            </div>
          </div>

          {{-- Infos panneau --}}
          <div style="padding:12px 14px;">
            <div style="font-size:10px;color:var(--text3);margin-bottom:2px;">
              {{ $panel->reference }} · {{ $panel->commune->name ?? '—' }}
            </div>
            <div style="font-weight:700;font-size:14px;margin-bottom:8px;
                        color:var(--text);white-space:nowrap;overflow:hidden;
                        text-overflow:ellipsis;">
              {{ $panel->name }}
            </div>

            {{-- Tags --}}
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px;">
              @if($panel->category)
                <span style="background:var(--surface3);color:var(--text2);
                             font-size:10px;padding:2px 7px;border-radius:4px;
                             font-weight:600;">
                  {{ strtoupper(substr($panel->category->name ?? 'STD', 0, 3)) }}
                </span>
              @endif
              @if($panel->format)
                <span style="background:var(--surface3);color:var(--text2);
                             font-size:10px;padding:2px 7px;border-radius:4px;">
                  {{ $panel->format->width ?? '?' }}×{{ $panel->format->height ?? '?' }}m
                </span>
              @endif
              @if($panel->format?->print_type)
                <span style="background:var(--surface3);color:var(--text2);
                             font-size:10px;padding:2px 7px;border-radius:4px;">
                  {{ $panel->format->print_type }}
                </span>
              @endif
              @if($panel->is_lit)
                <span style="background:rgba(232,160,32,0.15);color:var(--accent);
                             font-size:10px;padding:2px 7px;border-radius:4px;">
                  💡 Éclairé
                </span>
              @endif
            </div>

            {{-- Zone description --}}
            @if($panel->zone_description)
              <div style="font-size:11px;color:var(--text2);margin-bottom:6px;
                          white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                📍 {{ $panel->zone_description }}
              </div>
            @endif

            {{-- GPS --}}
            @if($panel->latitude && $panel->longitude)
              <div style="font-size:10px;color:var(--text3);margin-bottom:8px;">
                🌐 GPS: {{ $panel->latitude }}, {{ $panel->longitude }}
              </div>
            @endif

            {{-- Prix --}}
            <div style="font-size:16px;font-weight:700;color:var(--accent);margin-bottom:8px;">
              @if($panel->monthly_rate)
                {{ number_format($panel->monthly_rate/1000, 0) }}K FCFA/mois
              @else
                — FCFA/mois
              @endif
            </div>

            {{-- Trafic --}}
            @if($panel->daily_traffic)
              <div style="font-size:11px;color:var(--text3);margin-bottom:10px;">
                👁 {{ number_format($panel->daily_traffic) }}k/j
              </div>
            @endif

            {{-- Boutons --}}
            <div style="display:flex;gap:6px;">
              <button type="button"
                      class="btn btn-ghost btn-sm"
                      style="flex:1;font-size:11px;"
                      @click.stop="openFiche({{ $panel->toJson() }})">
                📋 Fiche
              </button>
              @if($isAvailable)
                <button type="button"
                        class="btn btn-sm"
                        :style="selectedIds.includes({{ $panel->id }})
                          ? 'background:var(--accent);color:#000;flex:1.2;font-size:11px;'
                          : 'background:var(--surface3);color:var(--text);flex:1.2;font-size:11px;border:1px solid var(--border2);border-radius:7px;'"
                        @click.stop="togglePanel({{ $panel->id }}, {{ $panel->monthly_rate ?? 0 }})">
                  <span x-text="selectedIds.includes({{ $panel->id }}) ? '✓ Sélectionné' : '+ Sélectionner'"></span>
                </button>
              @else
                <div style="flex:1.2;padding:6px 10px;background:var(--surface3);
                            border-radius:7px;font-size:11px;color:var(--text3);
                            text-align:center;">
                  @if($displayStatus === 'occupe' && $startDate)
                    🔒 Occupé
                  @else
                    {{ $sc['label'] }}
                  @endif
                </div>
              @endif
            </div>

          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- ── Barre de sélection bottom ── --}}
  <div x-show="selectedIds.length > 0"
       style="position:fixed;bottom:0;left:235px;right:0;
              background:var(--surface);border-top:1px solid var(--border);
              padding:14px 24px;display:flex;align-items:center;
              justify-content:space-between;z-index:100;
              box-shadow:0 -4px 20px rgba(0,0,0,0.3);">
    <span style="font-size:14px;color:var(--text2);">
      <span x-text="selectedIds.length" style="font-weight:700;color:var(--text)"></span>
      panneau(x) sélectionné(s) —
      <span x-text="formatTotal()" style="font-weight:700;color:var(--accent);font-size:15px;"></span>
      FCFA / mois
    </span>
    <div style="display:flex;gap:8px;">
      <button type="button" class="btn btn-ghost btn-sm"
              @click="selectedIds = []; selectedRates = {}">
        ✕ Désélectionner
      </button>
      <button type="button" class="btn btn-ghost btn-sm"
              style="border-color:var(--red);color:var(--red);">
        📄 PDF images
      </button>
      <button type="button" class="btn btn-ghost btn-sm"
              style="border-color:var(--blue);color:var(--blue);">
        📋 PDF liste
      </button>
      <button type="button" class="btn btn-primary"
              @click="$dispatch('open-modal', 'confirm-selection')">
        ✅ Confirmer sélection
      </button>
    </div>
  </div>

</div>{{-- fin x-data disponibilites --}}

{{-- ══════════════════════════════════════
     MODAL — CONFIRMER LA SÉLECTION
══════════════════════════════════════ --}}
<div x-data="{ open: false }"
     x-on:open-modal.window="if($event.detail === 'confirm-selection') open = true"
     x-show="open" class="modal-overlay"
     @click.self="open = false" style="display:none;">
  <div class="modal modal-wide" style="max-width:560px;" @click.stop>
    <div class="modal-header">
      <span class="modal-title">✅ Confirmer la campagne</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>

    <form method="POST"
          action="{{ route('admin.reservations.confirmer-selection') }}"
          x-data="{ type: 'option' }">
      @csrf

      {{-- Inputs hidden pour les panneaux sélectionnés --}}
      <div id="hidden-panel-inputs"></div>

      <div class="modal-body">

        {{-- Info anti-double-booking --}}
        <div style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);
                    border-radius:8px;padding:12px 14px;margin-bottom:16px;
                    font-size:12px;color:var(--green);">
          📋 Anti double-booking : Le système vérifiera automatiquement les conflits
          sur la période sélectionnée avant de bloquer les panneaux.
        </div>

        {{-- Type option/ferme --}}
        <div style="display:flex;gap:8px;margin-bottom:16px;">
          <label style="flex:1;cursor:pointer;padding:10px;border-radius:8px;
                        display:flex;align-items:center;gap:8px;"
                 :style="type==='option'
                   ? 'border:1px solid var(--orange);background:rgba(249,115,22,0.08);'
                   : 'border:1px solid var(--border2);background:var(--surface2);'">
            <input type="radio" name="type" value="option" x-model="type"
                   style="accent-color:var(--orange);">
            <div>
              <div style="font-size:12px;font-weight:600;">Mise sous option</div>
              <div style="font-size:10px;color:var(--text2);">Blocage temporaire</div>
            </div>
          </label>
          <label style="flex:1;cursor:pointer;padding:10px;border-radius:8px;
                        display:flex;align-items:center;gap:8px;"
                 :style="type==='ferme'
                   ? 'border:1px solid var(--green);background:rgba(34,197,94,0.08);'
                   : 'border:1px solid var(--border2);background:var(--surface2);'">
            <input type="radio" name="type" value="ferme" x-model="type"
                   style="accent-color:var(--green);">
            <div>
              <div style="font-size:12px;font-weight:600;">Réservation ferme</div>
              <div style="font-size:10px;color:var(--text2);">Confirmation définitive</div>
            </div>
          </label>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Client *</label>
            <select name="client_id"
                    style="background:var(--surface2);border:1px solid var(--border2);
                           border-radius:8px;padding:9px 12px;color:var(--text);
                           font-size:13px;outline:none;width:100%;" required>
              <option value="">— Sélectionner —</option>
              @foreach($clients as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mfg">
            <label>Nom de la campagne</label>
            <input type="text" name="campaign_name"
                   placeholder="ex: Lancement produit"/>
          </div>
        </div>

        <div class="form-2col">
          <div class="mfg">
            <label>Date début *</label>
            <input type="date" name="start_date"
                   value="{{ request('dispo_du') }}" required/>
          </div>
          <div class="mfg">
            <label>Date fin *</label>
            <input type="date" name="end_date"
                   value="{{ request('dispo_au') }}" required/>
          </div>
        </div>

        <div class="mfg">
          <label>Note interne</label>
          <textarea name="notes"
                    placeholder="Ex: Confirmation reçue par email le…"
                    style="min-height:80px;"></textarea>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
        <button type="submit" class="btn btn-primary">✅ Confirmer et bloquer</button>
      </div>
    </form>
  </div>
</div>

{{-- ══════════════════════════════════════
     MODAL — FICHE TECHNIQUE PANNEAU
══════════════════════════════════════ --}}
<div x-data="{ open: false, panel: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'fiche-panel') {
       panel = $event.detail.data; open = true;
     }"
     x-show="open" class="modal-overlay"
     @click.self="open = false" style="display:none;">
  <div class="modal modal-wide" style="max-width:700px;max-height:85vh;overflow-y:auto;" @click.stop>
    <div class="modal-header">
      <span class="modal-title">📋 Fiche technique du panneau</span>
      <button class="modal-close" @click="open = false">✕</button>
    </div>
    <div class="modal-body">

      <div style="color:var(--accent);font-size:11px;font-weight:700;
                  letter-spacing:1px;margin-bottom:12px;">IDENTIFICATION</div>
      <div class="form-3col" style="margin-bottom:16px;">
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">RÉFÉRENCE</div>
          <div style="font-weight:600;" x-text="panel.reference"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">NOM / EMPLACEMENT</div>
          <div style="font-weight:600;" x-text="panel.name"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">COMMUNE</div>
          <div x-text="panel.commune ?? '—'"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">GPS LATITUDE</div>
          <div x-text="panel.latitude ?? '—'"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">GPS LONGITUDE</div>
          <div x-text="panel.longitude ?? '—'"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">ZONE</div>
          <div x-text="panel.zone ?? '—'"></div>
        </div>
      </div>

      <div style="color:var(--accent);font-size:11px;font-weight:700;
                  letter-spacing:1px;margin-bottom:12px;">SPÉCIFICATIONS TECHNIQUES</div>
      <div class="form-3col" style="margin-bottom:16px;">
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">FORMAT</div>
          <div x-text="panel.format ?? '—'"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">DIMENSIONS</div>
          <div x-text="(panel.format_width && panel.format_height) ? panel.format_width + '×' + panel.format_height + 'm' : '—'"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">ÉCLAIRÉ</div>
          <div x-text="panel.is_lit ? '💡 Oui' : 'Non'"
               :style="panel.is_lit ? 'color:var(--accent)' : ''"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">TRAFIC JOURNALIER</div>
          <div x-text="panel.daily_traffic ? panel.daily_traffic.toLocaleString('fr-FR') + ' véh/j' : '—'"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">TARIF / MOIS</div>
          <div style="color:var(--accent);font-weight:700;"
               x-text="panel.monthly_rate ? Number(panel.monthly_rate).toLocaleString('fr-FR') + ' FCFA' : '—'"></div>
        </div>
        <div>
          <div style="font-size:10px;color:var(--text3);margin-bottom:3px;">STATUT</div>
          <div x-text="panel.status"></div>
        </div>
      </div>

      @if(true)
        <div style="color:var(--accent);font-size:11px;font-weight:700;
                    letter-spacing:1px;margin-bottom:12px;">DESCRIPTION DE ZONE</div>
        <div style="background:var(--surface3);border-radius:8px;padding:12px;
                    font-size:13px;color:var(--text2);"
             x-text="panel.zone_description || 'Aucune description.'"></div>
      @endif

    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
      <button type="button" class="btn btn-ghost"
              style="border-color:var(--blue);color:var(--blue);">
        📄 Export fiche PDF
      </button>
    </div>
  </div>
</div>

@push('scripts')
<script>
function disponibilites() {
  return {
    selectedIds:   [],
    selectedRates: {},

    init() {
      // Sync les inputs hidden avec la modal au moment d'ouvrir
      window.addEventListener('open-modal', (e) => {
        if (e.detail === 'confirm-selection') {
          this.syncHiddenInputs();
        }
      });
    },

    togglePanel(id, rate) {
      const idx = this.selectedIds.indexOf(id);
      if (idx === -1) {
        this.selectedIds.push(id);
        this.selectedRates[id] = parseFloat(rate) || 0;
      } else {
        this.selectedIds.splice(idx, 1);
        delete this.selectedRates[id];
      }
    },

    formatTotal() {
      const total = Object.values(this.selectedRates).reduce((s, r) => s + r, 0);
      return Math.round(total).toLocaleString('fr-FR');
    },

    syncHiddenInputs() {
      const container = document.getElementById('hidden-panel-inputs');
      if (!container) return;
      container.innerHTML = '';
      this.selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'panel_ids[]';
        input.value = id;
        container.appendChild(input);
      });
    },

    openFiche(panel) {
      this.$dispatch('open-modal', { name: 'fiche-panel', data: panel });
    }
  }
}
</script>
@endpush

</x-admin-layout>