<x-admin-layout>
<x-slot name="title">Formats Panneaux</x-slot>

<x-slot name="topbarLeft">
    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost btn-sm">
        ← Retour aux paramètres
    </a>
</x-slot>

<x-slot name="topbarActions">
    <button type="button" onclick="openFormatModal('create')" class="btn btn-primary btn-sm">
        ＋ Nouveau format
    </button>
</x-slot>

@if(session('success'))
    <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);color:#16a34a;padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px">
        ✅ {{ session('success') }}
    </div>
@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">📐 Formats ({{ $formats->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Largeur</th>
                    <th>Hauteur</th>
                    <th>Surface</th>
                    <th>Type impression</th>
                    <th style="width:170px;text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($formats as $format)
                @php
                    $formatData = [
                        'id'         => $format->id,
                        'name'       => $format->name,
                        'width'      => $format->width,
                        'height'     => $format->height,
                        'surface'    => $format->surface,
                        'print_type' => $format->print_type,
                    ];
                @endphp
                <tr>
                    <td><strong>{{ $format->name }}</strong></td>
                    <td>{{ $format->width ? $format->width . ' m' : '—' }}</td>
                    <td>{{ $format->height ? $format->height . ' m' : '—' }}</td>
                    <td>{{ $format->surface ? $format->surface . ' m²' : '—' }}</td>
                    <td>{{ $format->print_type ?? '—' }}</td>
                    <td style="text-align:right">
                        <div style="display:inline-flex; gap:6px;">
                            <button type="button"
                                    data-format='@json($formatData)'
                                    onclick="openFormatModal('edit', JSON.parse(this.dataset.format))"
                                    class="btn btn-ghost btn-sm">✏️ Modifier</button>
                            <form method="POST"
                                  action="{{ route('admin.settings.formats.destroy', $format) }}"
                                  onsubmit="return confirm('Supprimer ce format ?')"
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
                    <td colspan="6" style="text-align:center; color:var(--text3); padding:24px;">
                        Aucun format créé
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $formats->links() }}
    </div>
</div>

{{-- ════ MODAL CRÉATION / ÉDITION FORMAT ════ --}}
<div id="format-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9000;align-items:center;justify-content:center;padding:20px"
     onclick="if(event.target===this) closeFormatModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:600px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.4)"
         onclick="event.stopPropagation()">
        <div style="padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <h3 id="format-modal-title" style="font-size:15px;font-weight:700;color:var(--text);margin:0">Nouveau format</h3>
            <button type="button" onclick="closeFormatModal()"
                    style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--text3);padding:4px 10px;border-radius:8px"
                    onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='none'">✕</button>
        </div>

        <form id="format-form" method="POST" action="" style="padding:18px 22px;overflow-y:auto">
            @csrf
            <input type="hidden" name="_method" id="format-method" value="POST">

            <div class="mfg">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Nom du format *</label>
                <input type="text" name="name" id="format-name" required maxlength="50"
                       placeholder="Ex: 4×3, 8×3, 12×4…"
                       style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px">
                <div class="mfg">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Largeur (m)</label>
                    <input type="number" step="0.01" min="0" name="width" id="format-width" placeholder="0"
                           oninput="updateSurfaceCalc()"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box;font-family:monospace;text-align:right">
                </div>
                <div class="mfg">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Hauteur (m)</label>
                    <input type="number" step="0.01" min="0" name="height" id="format-height" placeholder="0"
                           oninput="updateSurfaceCalc()"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box;font-family:monospace;text-align:right">
                </div>
                <div class="mfg">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Surface (m²)</label>
                    <input type="number" step="0.01" min="0" name="surface" id="format-surface" placeholder="auto"
                           style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box;font-family:monospace;text-align:right">
                </div>
            </div>

            <div class="mfg" style="margin-top:14px">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Type d'impression</label>
                <input type="text" name="print_type" id="format-print" maxlength="80"
                       placeholder="Ex: Bâche PVC, Trivision, Backlight…"
                       style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
            </div>

            <div id="format-error" style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#dc2626;padding:8px 12px;border-radius:8px;font-size:12px;margin-top:12px"></div>

            <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end">
                <button type="button" onclick="closeFormatModal()" class="btn btn-ghost">Annuler</button>
                <button type="submit" id="format-submit" class="btn btn-primary">
                    <span id="format-submit-label">✅ Créer</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';
    const modal     = document.getElementById('format-modal');
    const form      = document.getElementById('format-form');
    const titleEl   = document.getElementById('format-modal-title');
    const methodEl  = document.getElementById('format-method');
    const nameEl    = document.getElementById('format-name');
    const widthEl   = document.getElementById('format-width');
    const heightEl  = document.getElementById('format-height');
    const surfEl    = document.getElementById('format-surface');
    const printEl   = document.getElementById('format-print');
    const submitLbl = document.getElementById('format-submit-label');
    const errEl     = document.getElementById('format-error');

    const CREATE_URL = "{{ route('admin.settings.formats.store') }}";
    const UPDATE_URL = "{{ route('admin.settings.formats.update', ['format' => 'ID']) }}";

    let surfaceTouched = false;
    surfEl.addEventListener('input', () => { surfaceTouched = true; });

    window.updateSurfaceCalc = function() {
        if (surfaceTouched) return;
        const w = parseFloat(widthEl.value);
        const h = parseFloat(heightEl.value);
        if (!isNaN(w) && !isNaN(h) && w > 0 && h > 0) {
            surfEl.value = (w * h).toFixed(2);
        }
    };

    window.openFormatModal = function(mode, data = null) {
        errEl.style.display = 'none';
        errEl.textContent = '';
        surfaceTouched = false;
        if (mode === 'create') {
            titleEl.textContent = '➕ Nouveau format';
            form.action = CREATE_URL;
            methodEl.value = 'POST';
            nameEl.value = '';
            widthEl.value = '';
            heightEl.value = '';
            surfEl.value = '';
            printEl.value = '';
            submitLbl.textContent = '✅ Créer';
        } else if (mode === 'edit' && data) {
            titleEl.textContent = '✏️ Modifier — ' + data.name;
            form.action = UPDATE_URL.replace('ID', data.id);
            methodEl.value = 'PUT';
            nameEl.value = data.name || '';
            widthEl.value = data.width ?? '';
            heightEl.value = data.height ?? '';
            surfEl.value = data.surface ?? '';
            printEl.value = data.print_type || '';
            surfaceTouched = !!data.surface;
            submitLbl.textContent = '💾 Enregistrer';
        }
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => nameEl.focus(), 50);
    };

    window.closeFormatModal = function() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.style.display === 'flex') closeFormatModal();
    });
})();
</script>

</x-admin-layout>
