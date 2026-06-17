<x-admin-layout>
<x-slot name="title">Équipes de pose</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.teams.create') }}" class="btn btn-primary btn-sm">+ Nouvelle équipe</a>
</x-slot>

<div style="max-width:1200px;margin:0 auto">
    {{-- Hero --}}
    <div style="background:linear-gradient(135deg,rgba(99,102,241,.10),rgba(168,85,247,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:54px;height:54px;border-radius:14px;background:rgba(99,102,241,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:26px">👥</div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:18px;font-weight:800;color:var(--text)">Équipes de pose</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:4px;line-height:1.5">
                {{ $teams->count() }} équipe(s) · gestion membres + leader + couleur.
                Les techniciens d'une équipe verront leur équipe affichée sur leurs poses.
            </div>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div style="padding:10px 14px;background:rgba(34,197,94,.10);border-left:4px solid #16a34a;border-radius:8px;margin-bottom:12px;font-size:13px;color:#15803d">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="padding:10px 14px;background:rgba(239,68,68,.10);border-left:4px solid #dc2626;border-radius:8px;margin-bottom:12px;font-size:13px;color:#b91c1c">{{ session('error') }}</div>
    @endif

    {{-- Liste --}}
    @forelse($teams as $team)
        <div style="background:var(--surface);border:1px solid var(--border);border-left:5px solid {{ $team->colorHex() }};border-radius:14px;overflow:hidden;margin-bottom:12px;{{ !$team->is_active ? 'opacity:.6' : '' }}">
            <div style="padding:16px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <div style="width:42px;height:42px;border-radius:10px;background:{{ $team->colorBgHex() }};border:2px solid {{ $team->colorHex() }};display:flex;align-items:center;justify-content:center;font-weight:800;color:{{ $team->colorHex() }}">
                    {{ strtoupper(substr($team->name, 0, 1)) }}
                </div>
                <div style="flex:1;min-width:240px">
                    <div style="font-size:15px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px">
                        {{ $team->name }}
                        @if(!$team->is_active)
                            <span style="font-size:10.5px;font-weight:700;padding:1px 7px;border-radius:999px;background:var(--surface2);color:var(--text3)">Inactive</span>
                        @endif
                    </div>
                    <div style="font-size:11.5px;color:var(--text3);margin-top:3px">
                        Leader : <strong style="color:var(--text2)">{{ $team->leader?->name ?? '—' }}</strong>
                        · Membres : <strong style="color:var(--text2)">{{ $team->activeMembers->count() }}</strong>
                        @if($team->description)
                            <span style="display:block;margin-top:3px;font-style:italic">{{ \Illuminate\Support\Str::limit($team->description, 80) }}</span>
                        @endif
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a href="{{ route('admin.teams.edit', $team) }}" class="btn btn-ghost btn-sm">✏️ Modifier</a>
                    <form method="POST" action="{{ route('admin.teams.destroy', $team) }}"
                          onsubmit="return confirm('Supprimer l'équipe « {{ $team->name }} » ?\n\n{{ $team->activeMembers->count() }} membre(s) actuel(s) seront détachés (deviendront sans équipe).\nL'historique des poses restera consultable.\n\nConfirmer ?');"
                          style="display:inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" style="color:#b91c1c">🗑 Supprimer</button>
                    </form>
                </div>
            </div>
            @if($team->activeMembers->isNotEmpty())
                <div style="padding:10px 20px;background:var(--surface2);border-top:1px solid var(--border);display:flex;flex-wrap:wrap;gap:8px">
                    @foreach($team->activeMembers as $m)
                        <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:var(--surface);border:1px solid var(--border);font-size:12px;color:var(--text2)">
                            {{ $m->name }}
                            <span style="font-size:10px;color:var(--text3);font-family:monospace">{{ $m->agent_code }}</span>
                            <form method="POST" action="{{ route('admin.teams.members.remove', [$team, $m]) }}" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b91c1c;cursor:pointer;font-size:11px;padding:0 0 0 4px" title="Retirer">✕</button>
                            </form>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:60px;text-align:center">
            <div style="font-size:48px;margin-bottom:12px">👥</div>
            <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px">Aucune équipe créée</div>
            <div style="font-size:12px;color:var(--text3);margin-bottom:20px">Cliquez sur « + Nouvelle équipe » pour démarrer.</div>
            <a href="{{ route('admin.teams.create') }}" class="btn btn-primary btn-sm">+ Nouvelle équipe</a>
        </div>
    @endforelse
</div>

</x-admin-layout>
