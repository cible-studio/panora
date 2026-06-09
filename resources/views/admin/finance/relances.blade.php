<x-admin-layout>
<x-slot name="title">Historique des relances</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.finance.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour au tableau de bord
    </a>
</x-slot:topbarLeft>

<div class="fin-relances-page">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:16px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:22px">📋</div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Historique des relances</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.4">{{ $relances->total() }} relance(s) enregistrée(s) au total</div>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="fin-filter-card" style="margin-bottom:16px">
        <div class="fin-filter-bar">
            <div class="fne-field" style="flex:1;min-width:200px">
                <label style="font-size:10px;text-transform:uppercase;font-weight:700;color:var(--text3);margin-bottom:4px;display:block">Client</label>
                <select name="client_id" onchange="this.form.submit()">
                    <option value="">— Tous —</option>
                    @foreach($clientsList as $cl)
                        <option value="{{ $cl->id }}" {{ (int) request('client_id') === $cl->id ? 'selected' : '' }}>{{ $cl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fne-field" style="min-width:160px">
                <label style="font-size:10px;text-transform:uppercase;font-weight:700;color:var(--text3);margin-bottom:4px;display:block">Canal</label>
                <select name="canal" onchange="this.form.submit()">
                    <option value="">— Tous —</option>
                    @foreach($canaux as $val => $lbl)
                        <option value="{{ $val }}" {{ request('canal') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            @if(request()->hasAny(['client_id', 'canal']))
                <a href="{{ route('admin.finance.relances') }}" class="btn btn-ghost btn-sm" style="height:38px;display:inline-flex;align-items:center">↺ Réinitialiser</a>
            @endif
        </div>
    </form>

    @if($relances->isEmpty())
        <div class="fin-card">
            <div class="fin-empty" style="padding:60px 20px">
                Aucune relance enregistrée pour le moment.
            </div>
        </div>
    @else
        <div class="fin-card">
            <div class="fin-card-body fin-card-body--flush">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Facture</th>
                            <th>Canal</th>
                            <th>Note</th>
                            <th>Suite</th>
                            <th>Auteur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($relances as $r)
                            <tr>
                                <td style="white-space:nowrap;color:var(--text2)">{{ $r->relance_date->format('d/m/Y') }}</td>
                                <td>
                                    @if($r->client)
                                        <a href="{{ route('admin.clients.show', $r->client) }}" style="color:var(--accent);text-decoration:none">{{ $r->client->name }}</a>
                                    @else
                                        <span style="color:var(--text3)">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($r->invoice)
                                        <a href="{{ route('admin.invoices.show', $r->invoice) }}" style="font-family:monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $r->invoice->reference }}</a>
                                    @else
                                        <span style="color:var(--text3);font-size:11px">— Relance globale</span>
                                    @endif
                                </td>
                                <td>{{ \App\Models\Relance::CANAUX[$r->canal] ?? $r->canal }}</td>
                                <td style="max-width:340px;color:var(--text2)">{{ Str::limit($r->note, 140) }}</td>
                                <td style="max-width:240px;color:var(--text2);font-size:12px;font-style:italic">
                                    @if($r->suite_donnee)
                                        {{ Str::limit($r->suite_donnee, 100) }}
                                    @else
                                        <span style="color:var(--text3)">—</span>
                                    @endif
                                </td>
                                <td style="color:var(--text2);font-size:12px;white-space:nowrap">{{ $r->user?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:14px 18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
                {{ $relances->withQueryString()->links() }}
            </div>
        </div>
    @endif
</div>

<style>
.fin-relances-page select {
    height: 38px;
    width: 100%;
    padding: 0 28px 0 10px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-size: 13px;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat;
    background-position: right 8px center;
}
.fin-filter-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 14px 18px; }
.fin-filter-bar { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; }
.fin-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
.fin-card-body--flush { padding: 0; }
.fin-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.fin-table th { text-align: left; padding: 10px 14px; background: var(--surface2); font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); border-bottom: 1px solid var(--border); }
.fin-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
.fin-table tr:hover td { background: rgba(232, 160, 32, .04); }
.fin-empty { text-align: center; color: var(--text3); font-size: 13px; background: var(--surface2); }
</style>

</x-admin-layout>
