<x-admin-layout>
<x-slot name="title">Zones</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost btn-sm">
        ← Retour aux paramètres
    </a>
    <button type="button" onclick="openZoneModal('create')" class="btn btn-primary btn-sm">
        ＋ Nouvelle zone
    </button>
</x-slot>

@if(session('success'))
    <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);color:#16a34a;padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px">
        ✅ {{ session('success') }}
    </div>
@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">🗺️ Zones ({{ $zones->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Commune</th>
                    <th>Niveau demande</th>
                    <th style="width:170px;text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($zones as $zone)
                @php
                    $zoneData = [
                        'id'           => $zone->id,
                        'name'         => $zone->name,
                        'commune_id'   => $zone->commune_id,
                        'description'  => $zone->description,
                        'demand_level' => $zone->demand_level,
                    ];
                @endphp
                <tr>
                    <td><strong>{{ $zone->name }}</strong></td>
                    <td>{{ $zone->commune?->name ?? '—' }}</td>
                    <td>
                        @if($zone->demand_level === 'tres_haute')
                            <span class="badge badge-red">Très haute</span>
                        @elseif($zone->demand_level === 'haute')
                            <span class="badge badge-orange">Haute</span>
                        @elseif($zone->demand_level === 'normale')
                            <span class="badge badge-blue">Normale</span>
                        @else
                            <span class="badge badge-gray">Faible</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        <div style="display:inline-flex; gap:6px;">
                            <button type="button"
                                    data-zone='@json($zoneData)'
                                    onclick="openZoneModal('edit', JSON.parse(this.dataset.zone))"
                                    class="btn btn-ghost btn-sm">✏️ Modifier</button>
                            <form method="POST"
                                  action="{{ route('admin.settings.zones.destroy', $zone) }}"
                                  onsubmit="return confirm('Supprimer cette zone ?')"
                                  style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm" title="Supprimer">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center; color:var(--text3); padding:24px;">
                        Aucune zone créée
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $zones->links() }}
    </div>
</div>

{{-- ════ MODAL CRÉATION / ÉDITION ZONE ════ --}}
<div id="zone-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9000;align-items:center;justify-content:center;padding:20px"
     onclick="if(event.target===this) closeZoneModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:560px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.4)"
         onclick="event.stopPropagation()">
        <div style="padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <h3 id="zone-modal-title" style="font-size:15px;font-weight:700;color:var(--text);margin:0">Nouvelle zone</h3>
            <button type="button" onclick="closeZoneModal()"
                    style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--text3);padding:4px 10px;border-radius:8px"
                    onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='none'">✕</button>
        </div>

        <form id="zone-form" method="POST" action="" style="padding:18px 22px;overflow-y:auto">
            @csrf
            <input type="hidden" name="_method" id="zone-method" value="POST">

            <div class="mfg">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Nom de la zone *</label>
                <input type="text" name="name" id="zone-name" required maxlength="100"
                       placeholder="Ex: Plateau Centre, Cocody II-Plateaux…"
                       style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
            </div>

            <div class="mfg" style="margin-top:14px">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Commune</label>
                <select name="commune_id" id="zone-commune"
                        style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
                    <option value="">— Aucune —</option>
                    @foreach($communes as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mfg" style="margin-top:14px">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Niveau de demande *</label>
                <select name="demand_level" id="zone-demand" required
                        style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
                    <option value="faible">⚪ Faible</option>
                    <option value="normale" selected>🔵 Normale</option>
                    <option value="haute">🟠 Haute</option>
                    <option value="tres_haute">🔴 Très haute</option>
                </select>
            </div>

            <div class="mfg" style="margin-top:14px">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Description</label>
                <textarea name="description" id="zone-description" rows="3" maxlength="500"
                          placeholder="Description optionnelle…"
                          style="width:100%;padding:10px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;resize:vertical;box-sizing:border-box;font-family:inherit"></textarea>
            </div>

            <div id="zone-error" style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#dc2626;padding:8px 12px;border-radius:8px;font-size:12px;margin-top:12px"></div>

            <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end">
                <button type="button" onclick="closeZoneModal()" class="btn btn-ghost">Annuler</button>
                <button type="submit" id="zone-submit" class="btn btn-primary">
                    <span id="zone-submit-label">✅ Créer</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';
    const modal      = document.getElementById('zone-modal');
    const form       = document.getElementById('zone-form');
    const titleEl    = document.getElementById('zone-modal-title');
    const methodEl   = document.getElementById('zone-method');
    const nameEl     = document.getElementById('zone-name');
    const communeEl  = document.getElementById('zone-commune');
    const demandEl   = document.getElementById('zone-demand');
    const descEl     = document.getElementById('zone-description');
    const submitLbl  = document.getElementById('zone-submit-label');
    const errEl      = document.getElementById('zone-error');

    const CREATE_URL = "{{ route('admin.settings.zones.store') }}";
    const UPDATE_URL = "{{ route('admin.settings.zones.update', ['zone' => 'ID']) }}";

    window.openZoneModal = function(mode, data = null) {
        errEl.style.display = 'none';
        errEl.textContent = '';
        if (mode === 'create') {
            titleEl.textContent = '➕ Nouvelle zone';
            form.action = CREATE_URL;
            methodEl.value = 'POST';
            nameEl.value = '';
            communeEl.value = '';
            demandEl.value = 'normale';
            descEl.value = '';
            submitLbl.textContent = '✅ Créer';
        } else if (mode === 'edit' && data) {
            titleEl.textContent = '✏️ Modifier — ' + data.name;
            form.action = UPDATE_URL.replace('ID', data.id);
            methodEl.value = 'PUT';
            nameEl.value = data.name || '';
            communeEl.value = data.commune_id || '';
            demandEl.value = data.demand_level || 'normale';
            descEl.value = data.description || '';
            submitLbl.textContent = '💾 Enregistrer';
        }
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => nameEl.focus(), 50);
    };

    window.closeZoneModal = function() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.style.display === 'flex') closeZoneModal();
    });
})();
</script>

</x-admin-layout>
