<x-admin-layout>
<x-slot name="title">Catégories Panneaux</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost btn-sm">
        ← Retour aux paramètres
    </a>
    <button type="button" onclick="openCatModal('create')" class="btn btn-primary btn-sm">
        ＋ Nouvelle catégorie
    </button>
</x-slot>

@if(session('success'))
    <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);color:#16a34a;padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px">
        ✅ {{ session('success') }}
    </div>
@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">🏷️ Catégories ({{ $categories->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Description</th>
                    <th>Panneaux</th>
                    <th style="width:170px;text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                @php
                    $catData = [
                        'id'          => $category->id,
                        'name'        => $category->name,
                        'description' => $category->description,
                    ];
                @endphp
                <tr>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td>{{ $category->description ?? '—' }}</td>
                    <td>
                        <span class="badge badge-blue">
                            {{ $category->panels->count() }} panneaux
                        </span>
                    </td>
                    <td style="text-align:right">
                        <div style="display:inline-flex; gap:6px;">
                            <button type="button"
                                    data-cat='@json($catData)'
                                    onclick="openCatModal('edit', JSON.parse(this.dataset.cat))"
                                    class="btn btn-ghost btn-sm">✏️ Modifier</button>
                            <form method="POST"
                                  action="{{ route('admin.settings.categories.destroy', $category) }}"
                                  onsubmit="return confirm('Supprimer cette catégorie ?')"
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
                        Aucune catégorie créée
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $categories->links() }}
    </div>
</div>

{{-- ════ MODAL CRÉATION / ÉDITION CATÉGORIE ════ --}}
<div id="cat-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9000;align-items:center;justify-content:center;padding:20px"
     onclick="if(event.target===this) closeCatModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:520px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.4)"
         onclick="event.stopPropagation()">
        <div style="padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <h3 id="cat-modal-title" style="font-size:15px;font-weight:700;color:var(--text);margin:0">Nouvelle catégorie</h3>
            <button type="button" onclick="closeCatModal()"
                    style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--text3);padding:4px 10px;border-radius:8px"
                    onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='none'">✕</button>
        </div>

        <form id="cat-form" method="POST" action="" style="padding:18px 22px">
            @csrf
            <input type="hidden" name="_method" id="cat-method" value="POST">

            <div class="mfg">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Nom de la catégorie *</label>
                <input type="text" name="name" id="cat-name" required maxlength="100"
                       placeholder="Ex: Panneau 4×3, Abribus, Totem…"
                       style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
            </div>

            <div class="mfg" style="margin-top:14px">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Description</label>
                <textarea name="description" id="cat-description" rows="3" maxlength="500"
                          placeholder="Description optionnelle…"
                          style="width:100%;padding:10px 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;resize:vertical;box-sizing:border-box;font-family:inherit"></textarea>
            </div>

            <div id="cat-error" style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#dc2626;padding:8px 12px;border-radius:8px;font-size:12px;margin-top:12px"></div>

            <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end">
                <button type="button" onclick="closeCatModal()" class="btn btn-ghost">Annuler</button>
                <button type="submit" id="cat-submit" class="btn btn-primary">
                    <span id="cat-submit-label">✅ Créer</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';
    const modal     = document.getElementById('cat-modal');
    const form      = document.getElementById('cat-form');
    const titleEl   = document.getElementById('cat-modal-title');
    const methodEl  = document.getElementById('cat-method');
    const nameEl    = document.getElementById('cat-name');
    const descEl    = document.getElementById('cat-description');
    const submitLbl = document.getElementById('cat-submit-label');
    const errEl     = document.getElementById('cat-error');

    const CREATE_URL = "{{ route('admin.settings.categories.store') }}";
    const UPDATE_URL = "{{ route('admin.settings.categories.update', ['category' => 'ID']) }}";

    window.openCatModal = function(mode, data = null) {
        errEl.style.display = 'none';
        errEl.textContent = '';
        if (mode === 'create') {
            titleEl.textContent = '➕ Nouvelle catégorie';
            form.action = CREATE_URL;
            methodEl.value = 'POST';
            nameEl.value = '';
            descEl.value = '';
            submitLbl.textContent = '✅ Créer';
        } else if (mode === 'edit' && data) {
            titleEl.textContent = '✏️ Modifier — ' + data.name;
            form.action = UPDATE_URL.replace('ID', data.id);
            methodEl.value = 'PUT';
            nameEl.value = data.name || '';
            descEl.value = data.description || '';
            submitLbl.textContent = '💾 Enregistrer';
        }
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => nameEl.focus(), 50);
    };

    window.closeCatModal = function() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.style.display === 'flex') closeCatModal();
    });
})();
</script>

</x-admin-layout>
