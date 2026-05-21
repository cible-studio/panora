<x-admin-layout title="Corriger les noms importés">

<x-slot:topbarLeft>
    <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux clients
    </a>
</x-slot:topbarLeft>

<div style="max-width:1100px;margin:0 auto">

    {{-- ── Intro / explication ───────────────────────────────────── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px">
        <div style="display:flex;align-items:flex-start;gap:14px">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            </div>
            <div style="flex:1">
                <div style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:6px">Corriger les noms importés "PERSONNE / ENTREPRISE"</div>
                <div style="font-size:13px;color:var(--text2);line-height:1.6">
                    Certains clients ont été importés avec le nom de la personne en début et l'entreprise en fin
                    (ex&nbsp;: <code style="background:var(--surface2);padding:1px 6px;border-radius:4px;font-size:12px">ARMÈLE DJO / BEM</code>).
                    Cet outil découpe sur le dernier <code style="background:var(--surface2);padding:1px 6px;border-radius:4px;font-size:12px">&nbsp;/&nbsp;</code>&nbsp;:
                    la <strong>partie droite</strong> devient le <strong>nom du client</strong> (entreprise),
                    la <strong>partie gauche</strong> devient le <strong>contact</strong> (personne).
                </div>
                <div style="font-size:12px;color:var(--text3);margin-top:8px">
                    Les clients qui ont déjà un contact saisi manuellement le conservent (priorité à la saisie existante).
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:10px;padding:12px 16px;margin-bottom:14px;color:#16a34a;font-weight:600">
            ✓ {{ session('success') }}
        </div>
    @endif

    @if($candidates->isEmpty())
        {{-- ── Aucun candidat ──────────────────────────────────── --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:48px 24px;text-align:center">
            <div style="width:56px;height:56px;border-radius:50%;background:rgba(34,197,94,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px">Aucun client à corriger</div>
            <div style="font-size:13px;color:var(--text3)">Aucun nom de client ne contient le pattern "&nbsp;/&nbsp;" — base propre.</div>
        </div>
    @else
        {{-- ── Tableau preview ─────────────────────────────────── --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:0;margin-bottom:14px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--text)">{{ $candidates->count() }} client(s) à corriger</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px">Prévisualisation — aucune modification n'a encore été écrite en base.</div>
                </div>
                <form method="POST" action="{{ route('admin.clients.fix-import-names.apply') }}"
                      onsubmit="return confirm('Appliquer la correction sur {{ $candidates->count() }} client(s) ? Cette action ne peut pas être annulée automatiquement.');">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Appliquer la correction
                    </button>
                </form>
            </div>

            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:var(--surface2);border-bottom:1px solid var(--border)">
                            <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">ID · NCC</th>
                            <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Avant (name)</th>
                            <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">→ Nouveau name (entreprise)</th>
                            <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">→ Nouveau contact (personne)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($candidates as $row)
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:10px 14px;color:var(--text3);font-family:monospace;font-size:11px">
                                #{{ $row['id'] }}<br>
                                <span style="opacity:.7">{{ $row['ncc'] ?? '—' }}</span>
                            </td>
                            <td style="padding:10px 14px;color:var(--text2)">
                                <span style="text-decoration:line-through;opacity:.65">{{ $row['old_name'] }}</span>
                            </td>
                            <td style="padding:10px 14px;color:var(--text);font-weight:700">
                                {{ $row['new_name'] }}
                            </td>
                            <td style="padding:10px 14px;color:var(--text)">
                                @if($row['preserved'])
                                    <span style="color:var(--text2)">{{ $row['existing_contact'] }}</span>
                                    <span style="font-size:10px;color:#22c55e;background:rgba(34,197,94,.12);padding:2px 7px;border-radius:8px;margin-left:6px;font-weight:600">existant conservé</span>
                                @else
                                    {{ $row['new_contact'] ?: '—' }}
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div style="font-size:12px;color:var(--text3);text-align:center;padding:8px 0">
            ⚠ Recommandé&nbsp;: sauvegarde la table <code style="background:var(--surface2);padding:1px 6px;border-radius:4px">clients</code> avant d'appliquer.
        </div>
    @endif

</div>

</x-admin-layout>
