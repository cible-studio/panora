<x-admin-layout>
<x-slot name="title">Communes</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost btn-sm">
        ← Retour aux paramètres
    </a>
    <button type="button" onclick="openCommuneModal('create')" class="btn btn-primary btn-sm">
        ＋ Nouvelle commune
    </button>
</x-slot>

@if(session('success'))
    <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);color:#16a34a;padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px">
        ✅ {{ session('success') }}
    </div>
@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">🏙️ Communes ({{ $communes->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Ville</th>
                    <th>Région</th>
                    <th style="text-align:right;">Tarif ODP</th>
                    <th style="text-align:right;">Tarif TM</th>
                    <th style="text-align:right;">Tarif DB</th>
                    <th style="width:170px;text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($communes as $commune)
                <tr>
                    <td><strong>{{ $commune->name }}</strong></td>
                    <td>{{ $commune->city ?? '—' }}</td>
                    <td>{{ $commune->region ?? '—' }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ number_format($commune->odp_rate, 0, ',', ' ') }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ number_format($commune->tm_rate, 0, ',', ' ') }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ number_format($commune->db_rate, 0, ',', ' ') }}</td>
                    <td style="text-align:right">
                        <div style="display:inline-flex; gap:6px;">
                            <button type="button"
                                    onclick='openCommuneModal("edit", @json([
                                        "id" => $commune->id,
                                        "name" => $commune->name,
                                        "city" => $commune->city,
                                        "region" => $commune->region,
                                        "odp_rate" => $commune->odp_rate,
                                        "tm_rate" => $commune->tm_rate,
                                        "db_rate" => $commune->db_rate,
                                    ]))'
                                    class="btn btn-ghost btn-sm">✏️ Modifier</button>
                            <a href="{{ route('admin.settings.communes.edit', $commune) }}"
                               class="btn btn-ghost btn-sm" title="Détails / Historique tarifs">📊</a>
                            <form method="POST"
                                  action="{{ route('admin.settings.communes.destroy', $commune) }}"
                                  onsubmit="return confirm('Supprimer cette commune ?')"
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
                    <td colspan="7" style="text-align:center; color:var(--text3); padding:24px;">
                        Aucune commune créée
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $communes->links() }}
    </div>
</div>

{{-- ════ MODAL CRÉATION / ÉDITION COMMUNE ════ --}}
<div id="commune-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9000;align-items:center;justify-content:center;padding:20px"
     onclick="if(event.target===this) closeCommuneModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:640px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.4)"
         onclick="event.stopPropagation()">
        <div style="padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <h3 id="commune-modal-title" style="font-size:15px;font-weight:700;color:var(--text);margin:0">Nouvelle commune</h3>
            <button type="button" onclick="closeCommuneModal()"
                    style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--text3);padding:4px 10px;border-radius:8px"
                    onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='none'">✕</button>
        </div>

        <form id="commune-form" method="POST" action="" style="padding:18px 22px;overflow-y:auto">
            @csrf
            <input type="hidden" name="_method" id="commune-method" value="POST">

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                <div class="mfg" style="grid-column:span 3">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Nom de la commune *</label>
                    <input type="text" name="name" id="commune-name" required maxlength="100"
                           placeholder="Ex: Cocody, Plateau, Yopougon…"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
                </div>

                <div class="mfg">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Ville</label>
                    <input type="text" name="city" id="commune-city" maxlength="100"
                           placeholder="Abidjan"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
                </div>

                <div class="mfg" style="grid-column:span 2">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Région</label>
                    <input type="text" name="region" id="commune-region" maxlength="100"
                           placeholder="Lagunes, Comoé…"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
                </div>

                <div class="mfg">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Tarif ODP</label>
                    <input type="number" step="1" min="0" name="odp_rate" id="commune-odp" placeholder="0"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box;font-family:monospace;text-align:right">
                </div>
                <div class="mfg">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Tarif TM</label>
                    <input type="number" step="1" min="0" name="tm_rate" id="commune-tm" placeholder="0"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box;font-family:monospace;text-align:right">
                </div>
                <div class="mfg">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Tarif DB</label>
                    <input type="number" step="1" min="0" name="db_rate" id="commune-db" placeholder="0"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box;font-family:monospace;text-align:right">
                </div>
            </div>

            <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.25);color:#1e40af;padding:8px 12px;border-radius:8px;font-size:11px;margin-top:12px">
                ℹ️ Les modifications de tarifs sont historisées automatiquement (les calculs rétroactifs gardent les anciennes valeurs).
            </div>

            <div id="commune-error" style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#dc2626;padding:8px 12px;border-radius:8px;font-size:12px;margin-top:12px"></div>

            <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end">
                <button type="button" onclick="closeCommuneModal()" class="btn btn-ghost">Annuler</button>
                <button type="submit" id="commune-submit" class="btn btn-primary">
                    <span id="commune-submit-label">✅ Créer</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';
    const modal     = document.getElementById('commune-modal');
    const form      = document.getElementById('commune-form');
    const titleEl   = document.getElementById('commune-modal-title');
    const methodEl  = document.getElementById('commune-method');
    const nameEl    = document.getElementById('commune-name');
    const cityEl    = document.getElementById('commune-city');
    const regionEl  = document.getElementById('commune-region');
    const odpEl     = document.getElementById('commune-odp');
    const tmEl      = document.getElementById('commune-tm');
    const dbEl      = document.getElementById('commune-db');
    const submitLbl = document.getElementById('commune-submit-label');
    const errEl     = document.getElementById('commune-error');

    const CREATE_URL = "{{ route('admin.settings.communes.store') }}";
    const UPDATE_URL = "{{ route('admin.settings.communes.update', ['commune' => 'ID']) }}";

    window.openCommuneModal = function(mode, data = null) {
        errEl.style.display = 'none';
        errEl.textContent = '';
        if (mode === 'create') {
            titleEl.textContent = '➕ Nouvelle commune';
            form.action = CREATE_URL;
            methodEl.value = 'POST';
            nameEl.value = '';
            cityEl.value = '';
            regionEl.value = '';
            odpEl.value = '';
            tmEl.value = '';
            dbEl.value = '';
            submitLbl.textContent = '✅ Créer';
        } else if (mode === 'edit' && data) {
            titleEl.textContent = '✏️ Modifier — ' + data.name;
            form.action = UPDATE_URL.replace('ID', data.id);
            methodEl.value = 'PUT';
            nameEl.value = data.name || '';
            cityEl.value = data.city || '';
            regionEl.value = data.region || '';
            odpEl.value = data.odp_rate ?? '';
            tmEl.value = data.tm_rate ?? '';
            dbEl.value = data.db_rate ?? '';
            submitLbl.textContent = '💾 Enregistrer';
        }
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => nameEl.focus(), 50);
    };

    window.closeCommuneModal = function() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.style.display === 'flex') closeCommuneModal();
    });
})();
</script>

</x-admin-layout>
