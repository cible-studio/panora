<x-admin-layout title="Effacer les NCC auto-générés">

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
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </div>
            <div style="flex:1">
                <div style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:6px">Effacer les NCC générés automatiquement</div>
                <div style="font-size:13px;color:var(--text2);line-height:1.6">
                    Lors de l'import, l'application a généré un NCC par défaut pour les clients
                    qui n'en avaient pas (format <code style="background:var(--surface2);padding:1px 6px;border-radius:4px;font-size:12px">CLT-2026-0001</code>).
                    Cet outil <strong>remet à vide</strong> ces NCC bidon — les NCC saisis manuellement
                    ou importés depuis l'Excel ne sont <strong>pas touchés</strong>.
                </div>
                <div style="font-size:12px;color:var(--text3);margin-top:8px">
                    Critère ciblé : NCC qui matchent exactement <code style="background:var(--surface2);padding:1px 6px;border-radius:4px;font-size:12px">CLT-AAAA-NNNN</code>.
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
            <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px">Aucun NCC auto-généré</div>
            <div style="font-size:13px;color:var(--text3)">Tous les NCC en base sont des saisies réelles.</div>
        </div>
    @else
        {{-- ── Tableau preview ─────────────────────────────────── --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:0;margin-bottom:14px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--text)">{{ $candidates->count() }} NCC à effacer</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px">Prévisualisation — aucune modification n'a encore été écrite en base.</div>
                </div>
                <form method="POST" action="{{ route('admin.clients.clear-auto-ncc.apply') }}"
                      onsubmit="return confirm('Effacer le NCC de {{ $candidates->count() }} client(s) ? Action irréversible (les NCC seront vidés).');">
                    @csrf
                    <button type="submit" class="btn btn-danger" style="display:inline-flex;align-items:center;gap:6px">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                        Effacer ces NCC
                    </button>
                </form>
            </div>

            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:var(--surface2);border-bottom:1px solid var(--border)">
                            <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">ID</th>
                            <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Client</th>
                            <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Contact</th>
                            <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">NCC actuel</th>
                            <th style="padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">→ Après</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($candidates as $client)
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:10px 14px;color:var(--text3);font-family:monospace;font-size:11px">#{{ $client->id }}</td>
                            <td style="padding:10px 14px;color:var(--text);font-weight:600">{{ $client->name }}</td>
                            <td style="padding:10px 14px;color:var(--text2)">{{ $client->contact_name ?? '—' }}</td>
                            <td style="padding:10px 14px;color:var(--text2);font-family:monospace;font-size:12px">
                                <span style="text-decoration:line-through;opacity:.65">{{ $client->ncc }}</span>
                            </td>
                            <td style="padding:10px 14px;color:var(--text3);font-style:italic">vide</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div style="font-size:12px;color:var(--text3);text-align:center;padding:8px 0">
            ⚠ Recommandé : sauvegarde la table <code style="background:var(--surface2);padding:1px 6px;border-radius:4px">clients</code> avant d'appliquer.
        </div>
    @endif

</div>

</x-admin-layout>
