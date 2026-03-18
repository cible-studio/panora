<x-admin-layout>
<x-slot name="title">Maintenance — {{ $maintenance->panel->reference }}</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.maintenances.edit', $maintenance) }}" class="btn btn-ghost btn-sm">
        ✏️ Modifier
    </a>
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

        {{-- RÉSOUDRE --}}
        @if($maintenance->statut !== 'resolu')
        <div class="card">
            <div class="card-header">
                <div class="card-title">✅ Marquer comme résolu</div>
            </div>
            <div class="card-body">
                <form method="POST"
                      action="{{ route('admin.maintenances.resolve', $maintenance) }}">
                    @csrf
                    <div class="mfg">
                        <label>Solution apportée *</label>
                        <textarea name="solution"
                                  placeholder="Décrivez la solution..."></textarea>
                    </div>
                    <div class="mfg">
                        <label>Date de résolution *</label>
                        <input type="date" name="date_resolution"
                               value="{{ date('Y-m-d') }}">
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;">
                        ✅ Marquer résolu
                    </button>
                </form>
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
