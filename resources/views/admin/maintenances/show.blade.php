<x-admin-layout>
<x-slot name="title">Maintenance — {{ $maintenance->panel->reference }}</x-slot>

<x-slot name="topbarLeft">
    <a href="{{ route('admin.maintenances.index') }}" class="btn btn-ghost btn-sm">← Retour</a>
</x-slot>

<x-slot name="topbarActions">
    @if($maintenance->isLocked())
        <span class="badge badge-gray" style="font-size:13px;">🔒 Verrouillée</span>
        <form method="POST" action="{{ route('admin.maintenances.reopen', $maintenance) }}"
              style="display:inline;"
              onsubmit="return confirm('Rouvrir cette maintenance ? Une nouvelle fiche sera créée et le panneau passera à nouveau en maintenance.');">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm">🔄 Rouvrir</button>
        </form>
    @else
        <a href="{{ route('admin.maintenances.edit', $maintenance) }}" class="btn btn-ghost btn-sm">
            ✏️ Modifier
        </a>
    @endif
</x-slot>

<div style="display:grid; grid-template-columns:1fr 320px; gap:20px;">

    {{-- COLONNE GAUCHE --}}
    <div>
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">
                        🔧 {{ $maintenance->type_panne }}
                    </div>
                    <div style="font-size:12px; color:var(--text3); margin-top:3px;">
                        Panneau :
                        <a href="{{ route('admin.panels.show', $maintenance->panel) }}"
                           style="color:var(--accent);">
                            {{ $maintenance->panel->reference }}
                        </a>
                    </div>
                </div>
                @if($maintenance->priorite === 'urgente')
                    <span class="badge badge-red" style="font-size:13px;">🔴 Urgente</span>
                @elseif($maintenance->priorite === 'haute')
                    <span class="badge badge-orange" style="font-size:13px;">🟠 Haute</span>
                @elseif($maintenance->priorite === 'normale')
                    <span class="badge badge-blue" style="font-size:13px;">🔵 Normale</span>
                @else
                    <span class="badge badge-gray" style="font-size:13px;">⚪ Faible</span>
                @endif
            </div>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px;">
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">PANNEAU</div>
                        <div style="font-weight:600;">{{ $maintenance->panel->name }}</div>
                        <div style="font-size:12px; color:var(--text3);">
                            {{ $maintenance->panel->commune->name }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">SIGNALÉ PAR</div>
                        <div style="font-weight:600;">
                            {{ $maintenance->signaledBy?->name ?? '—' }}
                        </div>
                        <div style="font-size:12px; color:var(--text3);">
                            {{ $maintenance->date_signalement?->format('d/m/Y') ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">TECHNICIEN</div>
                        <div style="font-weight:600;">
                            {{ $maintenance->technicien?->name ?? 'Non assigné' }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">STATUT</div>
                        @if($maintenance->statut === 'signale')
                            <span class="badge badge-orange">Signalé</span>
                        @elseif($maintenance->statut === 'en_cours')
                            <span class="badge badge-blue">En cours</span>
                        @elseif($maintenance->statut === 'resolu')
                            <span class="badge badge-green">Résolu ✓</span>
                        @else
                            <span class="badge badge-gray">Annulé</span>
                        @endif
                    </div>
                </div>

                @if($maintenance->description)
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:11px; color:var(--text3); margin-bottom:6px;">DESCRIPTION</div>
                    <div style="color:var(--text2);">{{ $maintenance->description }}</div>
                </div>
                @endif

                @if($maintenance->solution)
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:11px; color:var(--green); margin-bottom:6px;">✅ SOLUTION</div>
                    <div style="color:var(--text2);">{{ $maintenance->solution }}</div>
                    @if($maintenance->date_resolution)
                    <div style="font-size:12px; color:var(--text3); margin-top:6px;">
                        Résolu le {{ $maintenance->date_resolution?->format('d/m/Y') }}
                    </div>
                    @endif
                </div>
                @endif

            </div>
        </div>
    </div>

    {{-- COLONNE DROITE --}}
    <div>

        {{-- RÉSOUDRE (uniquement si ouverte) --}}
        @if(!$maintenance->isLocked())
        <div class="card">
            <div class="card-header">
                <div class="card-title">✅ Marquer comme résolu</div>
            </div>
            <div class="card-body">
                {{-- Affichage des erreurs de validation : avant cette
                     section, un POST sans 'solution' échouait silencieusement
                     (page rechargée, aucun feedback). --}}
                @if($errors->any())
                    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.30);border-radius:10px;padding:12px 14px;margin-bottom:14px">
                        <div style="font-size:12px;font-weight:800;color:#b91c1c;margin-bottom:4px">⚠️ Impossible de marquer comme résolu</div>
                        <ul style="margin:0;padding-left:18px;color:#991b1b;font-size:12.5px;line-height:1.5">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST"
                      action="{{ route('admin.maintenances.resolve', $maintenance) }}"
                      novalidate
                      id="form-resolve-maintenance">
                    @csrf
                    <div class="mfg">
                        <label for="resolve-solution">Solution apportée <span style="color:#ef4444">*</span></label>
                        <textarea id="resolve-solution"
                                  name="solution"
                                  required
                                  minlength="3"
                                  placeholder="Décrivez la solution apportée (obligatoire)..."
                                  style="{{ $errors->has('solution') ? 'border-color:#ef4444;background:rgba(239,68,68,.04);' : '' }}">{{ old('solution') }}</textarea>
                        @error('solution')
                            <div style="color:#ef4444;font-size:11.5px;margin-top:4px">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mfg">
                        <label for="resolve-date">Date de résolution <span style="color:#ef4444">*</span></label>
                        <input type="date" id="resolve-date"
                               name="date_resolution"
                               required
                               value="{{ old('date_resolution', date('Y-m-d')) }}"
                               style="{{ $errors->has('date_resolution') ? 'border-color:#ef4444;background:rgba(239,68,68,.04);' : '' }}">
                        @error('date_resolution')
                            <div style="color:#ef4444;font-size:11.5px;margin-top:4px">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;">
                        ✅ Marquer résolu
                    </button>
                </form>
            </div>
        </div>

        <script>
        // Garde-fou côté client : si la solution est vide, on bloque le submit
        // et on focus le champ avec un message visible. Sans cette garde,
        // l'utilisateur cliquait sans rien voir (textarea sans 'required'
        // HTML5 ne déclenchait pas la validation native).
        (function () {
            const form = document.getElementById('form-resolve-maintenance');
            if (!form) return;
            const solution = form.querySelector('[name="solution"]');
            const date     = form.querySelector('[name="date_resolution"]');
            form.addEventListener('submit', (e) => {
                const sVal = (solution?.value ?? '').trim();
                if (sVal.length < 3) {
                    e.preventDefault();
                    solution.style.borderColor = '#ef4444';
                    solution.style.background  = 'rgba(239,68,68,.04)';
                    solution.focus();
                    // Toast minimaliste sans dépendance
                    let t = document.getElementById('resolve-toast');
                    if (!t) {
                        t = document.createElement('div');
                        t.id = 'resolve-toast';
                        t.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:99999;background:#fff;color:#b91c1c;border:1px solid rgba(239,68,68,.4);padding:12px 16px;border-radius:10px;font-size:13px;font-weight:700;box-shadow:0 12px 32px -8px rgba(0,0,0,.25);max-width:300px';
                        document.body.appendChild(t);
                    }
                    t.textContent = '⚠️ La solution apportée est obligatoire (3 caractères min).';
                    setTimeout(() => { t.style.transition='opacity .4s'; t.style.opacity='0'; }, 3500);
                    setTimeout(() => t.remove(), 4000);
                    return;
                }
                if (!date?.value) {
                    e.preventDefault();
                    date.style.borderColor = '#ef4444';
                    date.focus();
                    return;
                }
            });
        })();
        </script>
        @else
        <div class="card" style="border-left:3px solid var(--text3);">
            <div class="card-body" style="font-size:13px;color:var(--text2);">
                🔒 Fiche verrouillée — pour signaler une nouvelle panne sur ce
                panneau, cliquez sur <strong>« Rouvrir »</strong> en haut de page :
                une nouvelle maintenance sera créée, reliée à celle-ci.
            </div>
        </div>
        @endif

        {{-- HISTORIQUE PARENT / RÉCURRENCES --}}
        @php $children = $maintenance->children()->orderByDesc('id')->get(); @endphp
        @if($maintenance->parent_maintenance_id || $children->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <div class="card-title">🔁 Historique récurrences</div>
            </div>
            <div class="card-body" style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
                @if($maintenance->parent_maintenance_id)
                    <div>
                        <span style="color:var(--text3);">Suite de :</span>
                        <a href="{{ route('admin.maintenances.show', $maintenance->parent_maintenance_id) }}"
                           style="color:var(--accent);">
                            Maintenance #{{ $maintenance->parent_maintenance_id }}
                        </a>
                    </div>
                @endif
                @foreach($children as $child)
                    <div>
                        <span style="color:var(--text3);">Rouverte plus tard :</span>
                        <a href="{{ route('admin.maintenances.show', $child) }}"
                           style="color:var(--accent);">
                            #{{ $child->id }} · {{ $child->statut }} · {{ $child->date_signalement?->format('d/m/Y') }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- INFOS PANNEAU --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🪧 Infos panneau</div>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div>
                        <div style="font-size:11px; color:var(--text3);">RÉFÉRENCE</div>
                        <div style="font-family:monospace; color:var(--accent); font-weight:700;">
                            {{ $maintenance->panel->reference }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3);">COMMUNE</div>
                        <div>{{ $maintenance->panel->commune->name }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3);">FORMAT</div>
                        <div>{{ $maintenance->panel->format->name }}</div>
                    </div>
                    <a href="{{ route('admin.panels.show', $maintenance->panel) }}"
                       class="btn btn-ghost btn-sm" style="margin-top:4px;">
                        Voir la fiche panneau →
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

</x-admin-layout>
