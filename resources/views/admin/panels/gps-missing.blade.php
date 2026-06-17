<x-admin-layout>
<x-slot name="title">Saisie GPS — panneaux sans coordonnées</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.map') }}" class="btn btn-ghost btn-sm">← Carte des panneaux</a>
</x-slot>

<div style="max-width:1280px;margin:0 auto">

    {{-- En-tête + stats globales --}}
    <div style="background:linear-gradient(135deg,rgba(232,160,32,.06),rgba(180,83,9,.04));border:1px solid var(--border);border-radius:16px;padding:18px 22px;margin-bottom:14px;display:flex;gap:18px;align-items:center;flex-wrap:wrap">
        <div style="width:52px;height:52px;border-radius:14px;background:rgba(232,160,32,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:24px">📍</div>
        <div style="flex:1;min-width:260px">
            <div style="font-size:17px;font-weight:800;color:var(--text)">Saisie manuelle des coordonnées GPS</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:3px;line-height:1.5">
                {{ $stats['missing'] }} panneau{{ $stats['missing'] > 1 ? 'x' : '' }} sans coordonnées sur {{ $stats['total'] }} ({{ $stats['total'] > 0 ? round($stats['missing']/$stats['total']*100, 1) : 0 }}%).
                Saisis lat/lng à la chaîne, le <code style="background:var(--surface2);padding:1px 5px;border-radius:4px;font-size:11px">gps_source</code> est posé à <strong>manual</strong> à la validation.
            </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <div style="text-align:center;padding:8px 14px;background:var(--surface);border:1px solid var(--border);border-radius:10px">
                <div style="font-size:9px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Total</div>
                <div style="font-size:20px;font-weight:800;color:var(--text)">{{ $stats['total'] }}</div>
            </div>
            <div style="text-align:center;padding:8px 14px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);border-radius:10px">
                <div style="font-size:9px;font-weight:800;color:#15803d;text-transform:uppercase;letter-spacing:.5px">Géoloc.</div>
                <div style="font-size:20px;font-weight:800;color:#15803d">{{ $stats['with_gps'] }}</div>
            </div>
            <div style="text-align:center;padding:8px 14px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:10px">
                <div style="font-size:9px;font-weight:800;color:#b45309;text-transform:uppercase;letter-spacing:.5px">Manquantes</div>
                <div style="font-size:20px;font-weight:800;color:#b45309">{{ $stats['missing'] }}</div>
            </div>
        </div>
    </div>

    {{-- Erreurs de validation --}}
    @if($errors->any())
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.30);border-radius:10px;padding:12px 16px;margin-bottom:14px;color:#b91c1c">
            <div style="font-weight:800;font-size:13px;margin-bottom:6px">⚠ Validation refusée :</div>
            <ul style="margin:0;padding-left:20px;font-size:12.5px;line-height:1.6">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Barre de filtres --}}
    <form method="GET" class="card" style="margin-bottom:14px;padding:14px 18px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
        <div style="display:flex;flex-direction:column;gap:4px">
            <label style="font-size:10px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Recherche</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Ref ou nom…"
                   style="height:36px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text);min-width:200px">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">
            <label style="font-size:10px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Commune</label>
            <select name="commune_id"
                    style="height:36px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text);min-width:180px">
                <option value="">— Toutes —</option>
                @foreach($communes as $com)
                    <option value="{{ $com->id }}" {{ (int) request('commune_id') === $com->id ? 'selected' : '' }}>{{ $com->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="height:36px">🔍 Filtrer</button>
        @if(request()->hasAny(['search', 'commune_id']))
            <a href="{{ route('admin.panels.gps.missing') }}" class="btn btn-ghost btn-sm" style="height:36px">↺ Réinitialiser</a>
        @endif
        <div style="margin-left:auto;font-size:12.5px;color:var(--text3)">
            {{ $panels->total() }} panneau{{ $panels->total() > 1 ? 'x' : '' }} à géolocaliser sur cette vue
        </div>
    </form>

    {{-- Grille édition --}}
    @if($panels->isEmpty())
        <div class="card" style="padding:50px 20px;text-align:center;color:var(--text3)">
            🎉 Aucun panneau à géolocaliser sur ce filtre.
        </div>
    @else
        <form method="POST" action="{{ route('admin.panels.gps.update') }}" id="gps-bulk-form">
            @csrf
            <div class="card" style="overflow:visible">
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:var(--surface2)">
                                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);width:120px">Référence</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border)">Nom</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border)">Commune</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);width:160px">Latitude</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);width:160px">Longitude</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);width:80px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($panels as $i => $p)
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:10px 12px">
                                        <a href="{{ route('admin.panels.show', $p) }}"
                                           style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);text-decoration:none"
                                           target="_blank" title="Ouvrir la fiche">{{ $p->reference }}</a>
                                    </td>
                                    <td style="padding:10px 12px;color:var(--text)">{{ $p->name }}</td>
                                    <td style="padding:10px 12px;color:var(--text2)">{{ $p->commune?->name ?? '—' }}</td>
                                    <td style="padding:10px 12px">
                                        <input type="hidden" name="gps[{{ $i }}][id]" value="{{ $p->id }}">
                                        <input type="number" step="0.0000001" min="3.5" max="11.5"
                                               name="gps[{{ $i }}][lat]"
                                               placeholder="5.3441234"
                                               style="width:100%;height:32px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;font-size:12.5px;font-family:monospace;color:var(--text)">
                                    </td>
                                    <td style="padding:10px 12px">
                                        <input type="number" step="0.0000001" min="-9" max="-1.5"
                                               name="gps[{{ $i }}][lng]"
                                               placeholder="-4.0078901"
                                               style="width:100%;height:32px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;font-size:12.5px;font-family:monospace;color:var(--text)">
                                    </td>
                                    <td style="padding:10px 12px">
                                        <a href="https://www.google.com/maps/search/{{ urlencode(($p->name ?? '') . ' ' . ($p->commune?->name ?? '') . ' Côte d\'Ivoire') }}"
                                           target="_blank" rel="noopener"
                                           class="btn btn-ghost btn-sm" style="font-size:11px"
                                           title="Ouvrir Google Maps dans un nouvel onglet pour récupérer les coordonnées">🗺 Maps</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:14px 18px;background:var(--surface2);border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                    <div style="font-size:11.5px;color:var(--text3);line-height:1.5">
                        💡 Astuce : utilise le bouton « Maps » pour ouvrir Google Maps,
                        clique-droit sur le bon emplacement, copie les coordonnées et colle
                        lat / lng dans les champs ci-dessus.
                    </div>
                    <button type="submit" class="btn btn-primary">
                        ✅ Enregistrer les coordonnées saisies
                    </button>
                </div>
            </div>
            <div style="margin-top:14px;display:flex;justify-content:center">
                {{ $panels->links() }}
            </div>
        </form>
    @endif

</div>

</x-admin-layout>
