<x-admin-layout>
<x-slot name="title">Alertes</x-slot>

<x-slot name="topbarActions">
    @if($totalNonLues > 0)
    <form method="POST" action="{{ route('admin.alerts.read-all') }}">
        @csrf
        <button type="submit" class="btn btn-ghost btn-sm">
            ✓ Tout marquer lu
        </button>
    </form>
    @endif
</x-slot>

{{-- STATS --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-label">Non lues</div>
        <div class="stat-value" style="color:var(--accent);">{{ $totalNonLues }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Danger</div>
        <div class="stat-value" style="color:var(--red);">{{ $totalDanger }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Avertissement</div>
        <div class="stat-value" style="color:var(--orange);">{{ $totalWarning }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Info</div>
        <div class="stat-value" style="color:var(--blue);">{{ $totalInfo }}</div>
    </div>
</div>

{{-- LISTE ALERTES --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🔔 Alertes ({{ $alertes->total() }})</div>
    </div>

    <div style="padding:16px; display:flex; flex-direction:column; gap:10px;">
        @forelse($alertes as $alerte)
        <div style="display:flex; align-items:flex-start; gap:14px; padding:14px;
                    border-radius:10px; border:1px solid var(--border);
                    background: {{ $alerte->is_read ? 'var(--surface)' : 'var(--surface2)' }};">

            {{-- ICÔNE --}}
            <div style="font-size:20px; flex-shrink:0;">
                @if($alerte->niveau === 'danger') 🔴
                @elseif($alerte->niveau === 'warning') 🟠
                @else 🔵
                @endif
            </div>

            {{-- CONTENU --}}
            <div style="flex:1; min-width:0;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                    <div style="font-weight:600; font-size:13px;">{{ $alerte->title }}</div>
                    @if(!$alerte->is_read)
                        <span class="badge badge-orange" style="font-size:10px;">Nouveau</span>
                    @endif
                    {{-- Type badge --}}
                    @if($alerte->type === 'maintenance')
                        <span class="badge badge-red" style="font-size:10px;">Maintenance</span>
                    @elseif($alerte->type === 'reservation')
                        <span class="badge badge-blue" style="font-size:10px;">Réservation</span>
                    @elseif($alerte->type === 'campagne')
                        <span class="badge badge-purple" style="font-size:10px;">Campagne</span>
                    @elseif($alerte->type === 'facture')
                        <span class="badge badge-orange" style="font-size:10px;">Facture</span>
                    @else
                        <span class="badge badge-gray" style="font-size:10px;">Système</span>
                    @endif
                </div>
                <div style="color:var(--text2); font-size:12px; margin-bottom:6px;">
                    {{ $alerte->message }}
                </div>
                <div style="color:var(--text3); font-size:11px;">
                    {{ $alerte->created_at->diffForHumans() }}
                </div>
            </div>

            {{-- ACTIONS --}}
            <div style="display:flex; gap:6px; flex-shrink:0;">
                @if(!$alerte->is_read)
                <form method="POST" action="{{ route('admin.alerts.read', $alerte) }}">
                    @csrf
                    <button class="btn btn-success btn-sm" title="Marquer comme lu">
                        ✓
                    </button>
                </form>
                @endif
                <form method="POST"
                      action="{{ route('admin.alerts.destroy', $alerte) }}"
                      onsubmit="return confirm('Supprimer cette alerte ?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm">🗑️</button>
                </form>
            </div>

        </div>
        @empty
        <div style="text-align:center; color:var(--text3); padding:40px;">
            <div style="font-size:40px; margin-bottom:12px;">🎉</div>
            <div style="font-size:16px; font-weight:600;">Aucune alerte !</div>
            <div style="font-size:13px; margin-top:4px;">Tout va bien.</div>
        </div>
        @endforelse
    </div>

    <div style="padding:16px; border-top:1px solid var(--border);">
        {{ $alertes->links() }}
    </div>
</div>

</x-admin-layout>

