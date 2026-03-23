<x-admin-layout>
<x-slot name="title">Pige — {{ $pige->panel->reference }}</x-slot>

<x-slot name="topbarActions">
    @if(!$pige->is_verified)
    <form method="POST" action="{{ route('admin.piges.verify', $pige) }}">
        @csrf
        <button type="submit" class="btn btn-success btn-sm">
            ✅ Vérifier la pige
        </button>
    </form>
    @endif
</x-slot>

<div style="display:grid; grid-template-columns:1fr 300px; gap:20px;">

    {{-- PHOTO --}}
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">📷 Photo de pige</div>
                @if($pige->is_verified)
                    <span class="badge badge-green">✓ Vérifiée</span>
                @else
                    <span class="badge badge-orange">En attente</span>
                @endif
            </div>
            <img src="{{ asset('storage/'.$pige->photo_path) }}"
                 style="width:100%; max-height:500px; object-fit:contain;
                        background:var(--surface2);">
            @if($pige->notes)
            <div class="card-body">
                <div style="font-size:11px; color:var(--text3); margin-bottom:6px;">NOTES</div>
                <div style="color:var(--text2);">{{ $pige->notes }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- INFOS --}}
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">ℹ️ Informations</div>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:12px;">

                    <div>
                        <div style="font-size:11px; color:var(--text3);">PANNEAU</div>
                        <div style="font-weight:600; color:var(--accent); font-family:monospace;">
                            {{ $pige->panel->reference }}
                        </div>
                        <div style="font-size:12px; color:var(--text3);">
                            {{ $pige->panel->commune->name }}
                        </div>
                    </div>

                    @if($pige->campaign)
                    <div>
                        <div style="font-size:11px; color:var(--text3);">CAMPAGNE</div>
                        <div style="font-weight:600;">{{ $pige->campaign->name }}</div>
                    </div>
                    @endif

                    <div>
                        <div style="font-size:11px; color:var(--text3);">PRISE PAR</div>
                        <div style="font-weight:600;">{{ $pige->takenBy->name }}</div>
                    </div>

                    <div>
                        <div style="font-size:11px; color:var(--text3);">DATE DE PRISE</div>
                        <div style="font-weight:600;">
                            {{ $pige->taken_at->format('d/m/Y') }}
                        </div>
                    </div>

                    @if($pige->gps_lat && $pige->gps_lng)
                    <div>
                        <div style="font-size:11px; color:var(--text3);">COORDONNÉES GPS</div>
                        <div style="font-family:monospace; font-size:11px; color:var(--text2);">
                            {{ $pige->gps_lat }}, {{ $pige->gps_lng }}
                        </div>
                    </div>
                    @endif

                    @if($pige->is_verified)
                    <div style="padding:12px; background:rgba(34,197,94,.1);
                                border:1px solid rgba(34,197,94,.3); border-radius:8px;">
                        <div style="color:var(--green); font-weight:600; font-size:12px;">
                            ✅ Vérifiée
                        </div>
                        <div style="font-size:11px; color:var(--text3); margin-top:4px;">
                            Par {{ $pige->verifiedBy?->name }}
                        </div>
                        <div style="font-size:11px; color:var(--text3);">
                            Le {{ $pige->verified_at?->format('d/m/Y à H:i') }}
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        </div>

        {{-- ACTIONS --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Actions</div>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <a href="{{ route('admin.panels.show', $pige->panel) }}"
                       class="btn btn-ghost btn-sm">
                        🪧 Voir le panneau
                    </a>
                    @if($pige->campaign)
                    <a href="{{ route('admin.campaigns.show', $pige->campaign) }}"
                       class="btn btn-ghost btn-sm">
                        📢 Voir la campagne
                    </a>
                    @endif
                    <form method="POST"
                          action="{{ route('admin.piges.destroy', $pige) }}"
                          onsubmit="return confirm('Supprimer cette pige ?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger btn-sm" style="width:100%;">
                            🗑️ Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

</div>

</x-admin-layout>
