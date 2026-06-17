<x-admin-layout>
<x-slot name="title">Techniciens</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux poses
    </a>
</x-slot:topbarLeft>

<x-slot:topbarActions>
    <a href="{{ route('admin.pose-tasks.techniciens.create') }}" class="btn btn-primary btn-sm">
        + Nouveau technicien
    </a>
</x-slot:topbarActions>

<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
    <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:22px">🔧</div>
    <div style="flex:1">
        <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Techniciens terrain</div>
        <div style="font-size:12.5px;color:var(--text3);line-height:1.5">
            Les techniciens ne se connectent pas à Panora — ils accèdent à leurs poses du jour via un lien public unique (token).
            Ils reçoivent les notifications de poses par WhatsApp.
        </div>
    </div>
    <div style="font-size:13px;color:var(--text2)">
        <strong>{{ $techniciens->total() }}</strong> technicien(s)
    </div>
</div>

{{-- Recherche --}}
<form method="GET" style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:16px;display:flex;gap:10px;align-items:center">
    <input type="text" name="q" placeholder="Rechercher par nom, email, WhatsApp ou code agent…" value="{{ request('q') }}"
           style="flex:1;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text);outline:none">
    <button type="submit" class="btn btn-ghost btn-sm">🔍 Filtrer</button>
    @if(request('q'))
        <a href="{{ route('admin.pose-tasks.techniciens.index') }}" class="btn btn-ghost btn-sm">↺ Reset</a>
    @endif
</form>

@if(session('success'))
    <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:10px;padding:12px 16px;margin-bottom:14px;color:#15803d;font-size:13px">
        ✅ {{ session('success') }}
    </div>
@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">📋 Liste des techniciens</div>
    </div>
    @if($techniciens->isEmpty())
        <div style="padding:40px 20px;text-align:center;color:var(--text3);font-size:13px">
            Aucun technicien enregistré.
            <a href="{{ route('admin.pose-tasks.techniciens.create') }}" style="color:var(--accent);font-weight:700;text-decoration:none;margin-left:6px">+ Créer le premier</a>
        </div>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead style="background:var(--surface2);color:var(--text3)">
                    <tr>
                        <th style="text-align:left;padding:10px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Nom</th>
                        <th style="text-align:left;padding:10px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Contact</th>
                        <th style="text-align:left;padding:10px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Code agent</th>
                        <th style="text-align:left;padding:10px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Lien public</th>
                        <th style="text-align:left;padding:10px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Statut</th>
                        <th style="text-align:right;padding:10px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($techniciens as $t)
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:12px 14px;font-weight:700;color:var(--text)">{{ $t->name }}</td>
                            <td style="padding:12px 14px;color:var(--text2);font-size:12.5px">
                                @if($t->whatsapp_number)
                                    📱 {{ $t->whatsapp_number }}<br>
                                @endif
                                @if($t->email && !str_starts_with($t->email, 'tech_'))
                                    <span style="font-size:11.5px;color:var(--text3)">{{ $t->email }}</span>
                                @endif
                            </td>
                            <td style="padding:12px 14px;color:var(--text2);font-family:monospace;font-size:12px">{{ $t->agent_code ?? '—' }}</td>
                            <td style="padding:12px 14px">
                                @if($t->tech_public_token)
                                    <div style="display:flex;align-items:center;gap:6px">
                                        <code style="font-size:10.5px;background:var(--surface2);padding:3px 6px;border-radius:5px;color:var(--text3)">{{ Str::limit($t->tech_public_token, 12) }}…</code>
                                        <button type="button"
                                                onclick="navigator.clipboard.writeText('{{ url('/tech/' . $t->tech_public_token . '/poses') }}');this.textContent='✓';setTimeout(()=>this.textContent='📋',1500)"
                                                title="Copier le lien complet"
                                                style="background:none;border:none;cursor:pointer;font-size:13px;padding:2px 4px">📋</button>
                                    </div>
                                @else
                                    <span style="color:var(--text3);font-size:11.5px">— pas encore généré</span>
                                @endif
                            </td>
                            <td style="padding:12px 14px">
                                @if($t->is_active)
                                    <span style="display:inline-flex;align-items:center;gap:4px;background:rgba(34,197,94,.12);color:#15803d;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700">✅ Actif</span>
                                @else
                                    <span style="display:inline-flex;align-items:center;gap:4px;background:rgba(107,114,128,.12);color:#4b5563;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700">⏸ Inactif</span>
                                @endif
                            </td>
                            <td style="padding:12px 14px;text-align:right">
                                <div style="display:inline-flex;gap:4px">
                                    <a href="{{ route('admin.pose-tasks.techniciens.edit', $t) }}" class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
                                    <form method="POST" action="{{ route('admin.pose-tasks.techniciens.toggle-active', $t) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm" title="{{ $t->is_active ? 'Désactiver' : 'Réactiver' }}">
                                            {{ $t->is_active ? '⏸' : '▶' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.pose-tasks.techniciens.regenerate-token', $t) }}" style="display:inline"
                                          onsubmit="return confirm('Générer un nouveau lien public ? L\'ancien ne fonctionnera plus.')">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm" title="Régénérer le lien public">🔄</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.pose-tasks.techniciens.destroy', $t) }}" style="display:inline"
                                          onsubmit="return confirm('Supprimer le technicien {{ $t->name }} ? Action irréversible.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:#ef4444" title="Supprimer">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:14px 18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
            {{ $techniciens->withQueryString()->links() }}
        </div>
    @endif
</div>

</x-admin-layout>
