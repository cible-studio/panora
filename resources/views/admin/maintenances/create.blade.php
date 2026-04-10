<x-admin-layout>
<x-slot name="title">Signaler une panne</x-slot>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">🔧 Signaler une panne</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.maintenances.store') }}">
                @csrf

                @if($errors->any())
                <div style="background:rgba(239,68,68,.1); border:1px solid var(--red);
                            border-radius:8px; padding:12px; margin-bottom:16px;">
                    <div style="color:var(--red); font-weight:600; margin-bottom:8px;">❌ Erreurs :</div>
                    <ul style="color:var(--red); padding-left:16px;">
                        @foreach($errors->all() as $error)
                            <li style="font-size:13px;">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="section-label">Panneau concerné</div>

                {{-- Champ caché qui envoie l'ID --}}
                <input type="hidden" name="panel_id" id="panel_id_hidden"
                       value="{{ old('panel_id') }}">

                <div class="mfg" style="position:relative;">
                    <label>Panneau * <span style="font-size:11px;color:var(--text3);">Tapez la référence ou le nom</span></label>

                    <input type="text"
                           id="panel-search"
                           placeholder="Ex: P-ABC-123 ou Carrefour Anono..."
                           autocomplete="off"
                           class="{{ $errors->has('panel_id') ? 'error' : '' }}"
                           style="width:100%;">

                    {{-- Dropdown résultats --}}
                    <div id="panel-dropdown"
                         style="display:none;position:absolute;top:100%;left:0;right:0;
                                background:var(--surface);border:1px solid var(--border2);
                                border-radius:10px;z-index:100;max-height:280px;overflow-y:auto;
                                box-shadow:0 8px 24px rgba(0,0,0,.3);margin-top:4px;">
                    </div>

                    {{-- Tag panneau sélectionné --}}
                    <div id="panel-selected"
                         style="display:none;margin-top:8px;padding:10px 12px;
                                background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.3);
                                border-radius:8px;align-items:center;gap:10px;">
                        <span style="font-size:16px;">🪧</span>
                        <div style="flex:1;">
                            <div id="panel-selected-ref" style="font-weight:700;color:var(--accent);font-family:monospace;"></div>
                            <div id="panel-selected-name" style="font-size:12px;color:var(--text2);margin-top:2px;"></div>
                        </div>
                        <button type="button" onclick="clearPanel()"
                                style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:16px;">✕</button>
                    </div>

                    @error('panel_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="section-label">Détails de la panne</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Type de panne *</label>
                        <input type="text" name="type_panne"
                               value="{{ old('type_panne') }}"
                               placeholder="Ex: Éclairage défaillant, Bâche déchirée..."
                               class="{{ $errors->has('type_panne') ? 'error' : '' }}">
                        @error('type_panne')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mfg">
                        <label>Priorité *</label>
                        <select name="priorite">
                            <option value="faible"  {{ old('priorite') === 'faible'  ? 'selected' : '' }}>⚪ Faible</option>
                            <option value="normale" {{ old('priorite', 'normale') === 'normale' ? 'selected' : '' }}>🔵 Normale</option>
                            <option value="haute"   {{ old('priorite') === 'haute'   ? 'selected' : '' }}>🟠 Haute</option>
                            <option value="urgente" {{ old('priorite') === 'urgente' ? 'selected' : '' }}>🔴 Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"
                              placeholder="Décrivez la panne en détail...">{{ old('description') }}</textarea>
                </div>

                <div class="section-label">Assignation</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Technicien assigné</label>
                        <select name="technicien_id">
                            <option value="">— Non assigné —</option>
                            @foreach($techniciens as $tech)
                            <option value="{{ $tech->id }}"
                                {{ old('technicien_id') == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mfg">
                        <label>Date de signalement *</label>
                        <input type="date" name="date_signalement"
                               value="{{ old('date_signalement', date('Y-m-d')) }}"
                               class="{{ $errors->has('date_signalement') ? 'error' : '' }}">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        🔧 Signaler la panne
                    </button>
                    <a href="{{ route('admin.maintenances.index') }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
const PANELS = {!! json_encode($panels->map(function($p) {
    return [
        'id'      => $p->id,
        'ref'     => $p->reference,
        'name'    => $p->name,
        'commune' => $p->commune->name,
        'status'  => $p->status->value,
    ];
})->values()) !!};

const searchInput  = document.getElementById('panel-search');
const dropdown     = document.getElementById('panel-dropdown');
const hiddenInput  = document.getElementById('panel_id_hidden');
const selectedBox  = document.getElementById('panel-selected');
const selectedRef  = document.getElementById('panel-selected-ref');
const selectedName = document.getElementById('panel-selected-name');

// Pré-remplir si old value
@if(old('panel_id'))
const preselected = PANELS.find(p => p.id == {{ old('panel_id') }});
if (preselected) selectPanel(preselected);
@endif

searchInput.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    if (q.length < 1) { dropdown.style.display = 'none'; return; }

    const results = PANELS.filter(p =>
        p.ref.toLowerCase().includes(q) ||
        p.name.toLowerCase().includes(q) ||
        p.commune.toLowerCase().includes(q)
    ).slice(0, 10);

    if (results.length === 0) {
        dropdown.innerHTML = '<div style="padding:12px 14px;color:var(--text3);font-size:13px;">Aucun panneau trouvé</div>';
    } else {
        const statusColors = {
            libre: '#22c55e', occupe: '#e8a020', maintenance: '#ef4444',
            option: '#3b82f6', confirme: '#8b5cf6'
        };
        dropdown.innerHTML = results.map(p => {
            const color = statusColors[p.status] || '#6b7280';
            return `<div onclick='selectPanel(${JSON.stringify(p)})'
                        style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);transition:background .1s;"
                        onmouseover="this.style.background='var(--surface2)'"
                        onmouseout="this.style.background=''">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="font-family:monospace;font-weight:700;color:var(--accent);font-size:13px;">${p.ref}</span>
                            <span style="font-size:10px;padding:2px 7px;border-radius:10px;
                                  background:${color}20;color:${color};border:1px solid ${color}40;font-weight:600;">
                                ${p.status}
                            </span>
                        </div>
                        <div style="font-size:12px;color:var(--text2);margin-top:3px;">${p.name}</div>
                        <div style="font-size:11px;color:var(--text3);">📍 ${p.commune}</div>
                    </div>`;
        }).join('');
    }
    dropdown.style.display = 'block';
});

function selectPanel(panel) {
    if (typeof panel === 'string') panel = JSON.parse(panel);
    hiddenInput.value = panel.id;
    searchInput.value = '';
    dropdown.style.display = 'none';
    selectedRef.textContent  = panel.ref;
    selectedName.textContent = panel.name + ' — ' + panel.commune;
    selectedBox.style.display = 'flex';
    searchInput.style.display = 'none';
}

function clearPanel() {
    hiddenInput.value = '';
    selectedBox.style.display = 'none';
    searchInput.style.display = 'block';
    searchInput.value = '';
    searchInput.focus();
}

document.addEventListener('click', e => {
    if (!e.target.closest('#panel-search') && !e.target.closest('#panel-dropdown')) {
        dropdown.style.display = 'none';
    }
});
</script>

</x-admin-layout>
