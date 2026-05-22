<x-admin-layout>
<x-slot name="title">Piges archivées</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.piges.index') }}" class="btn btn-ghost btn-sm">
        ← Retour aux piges actives
    </a>
</x-slot>

@if(session('success'))
    <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);color:#16a34a;padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px">
        ✅ {{ session('success') }}
    </div>
@endif

{{-- Bandeau d'information : conservation légale --}}
<div style="background:rgba(232,160,32,0.06);border:1px solid rgba(232,160,32,0.3);border-radius:10px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:flex-start;gap:12px">
    <div style="font-size:22px;flex-shrink:0">📦</div>
    <div>
        <div style="font-weight:700;color:var(--accent);margin-bottom:4px">Archives — piges des campagnes supprimées</div>
        <div style="font-size:13px;color:var(--text2);line-height:1.55">
            Ces piges sont conservées en base pour leur valeur légale (preuve de prestation),
            comptable (rattachées à une facture) et historique (audit, reconquête client).
            Elles sont automatiquement archivées quand leur campagne est supprimée.
        </div>
    </div>
</div>

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:20px">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:6px">📦 Piges archivées</div>
        <div style="font-size:28px;font-weight:800;color:var(--accent)">{{ number_format($totalArchived) }}</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:6px">🗑 Campagnes supprimées</div>
        <div style="font-size:28px;font-weight:800;color:var(--text2)">{{ number_format($campaignsAffected) }}</div>
    </div>
</div>

{{-- Recherche simple --}}
<div class="card" style="margin-bottom:14px">
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;padding:14px 18px">
        <div style="flex:1;min-width:240px">
            <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">🔍 Recherche</label>
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Référence panneau, nom panneau, campagne…"
                   style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-size:13px;box-sizing:border-box">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
        @if(request()->hasAny(['q', 'campaign_id']))
            <a href="{{ route('admin.piges.archives') }}" class="btn btn-ghost btn-sm">↺ Réinitialiser</a>
        @endif
    </form>
</div>

{{-- Liste --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">📦 Liste des archives ({{ $piges->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Panneau</th>
                    <th>Campagne (supprimée)</th>
                    <th>Technicien</th>
                    <th>Date pose</th>
                    <th>Archivée le</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse($piges as $pige)
                <tr>
                    <td style="width:80px">
                        @if($pige->photo_path)
                            <a href="{{ $pige->getPhotoUrl() }}" target="_blank" rel="noopener" title="Voir la photo en grand">
                                <img src="{{ $pige->getThumbUrl() }}" alt="Pige #{{ $pige->id }}"
                                     style="width:64px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border2)">
                            </a>
                        @else
                            <span style="color:var(--text3);font-size:11px">—</span>
                        @endif
                    </td>
                    <td>
                        <strong style="color:var(--accent);font-family:monospace;font-size:13px">
                            {{ $pige->panel?->reference ?? '—' }}
                        </strong>
                        <div style="font-size:11px;color:var(--text3)">{{ $pige->panel?->commune?->name ?? '' }}</div>
                    </td>
                    <td>
                        @if($pige->campaign)
                            <span style="color:var(--text2);text-decoration:line-through;text-decoration-color:var(--text3)">{{ $pige->campaign->name }}</span>
                            <div style="font-size:10px;color:var(--text3);font-style:italic;margin-top:2px">
                                🗑 Supprimée le {{ $pige->campaign->deleted_at?->format('d/m/Y') ?? '—' }}
                                @if($pige->campaign->client?->name)
                                    · {{ $pige->campaign->client->name }}
                                @endif
                            </div>
                        @else
                            <span style="color:var(--text3);font-style:italic">Référence orpheline</span>
                        @endif
                    </td>
                    <td>{{ $pige->technicien?->name ?? '—' }}</td>
                    <td style="font-size:12px;color:var(--text2)">{{ $pige->taken_at?->format('d/m/Y') ?? '—' }}</td>
                    <td style="font-size:12px;color:var(--text2)">{{ $pige->archived_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        @php $cfg = $pige->getStatusConfig(); @endphp
                        <span class="badge" style="background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }};border:1px solid {{ $cfg['bd'] }}">
                            {{ $cfg['label'] }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:var(--text3);padding:40px">
                        Aucune pige archivée 🎉 — toutes les campagnes sont encore actives.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($piges->hasPages())
    <div style="padding:14px 18px">{{ $piges->links() }}</div>
    @endif
</div>

</x-admin-layout>
