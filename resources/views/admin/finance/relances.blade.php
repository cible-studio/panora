<x-admin-layout>
<x-slot name="title">Historique des relances</x-slot>

@php
    // Bouton retour intelligent : ramène à la page précédente si le user
    // arrive depuis ailleurs (fiche facture, fiche client, dashboard…),
    // sinon fallback sur le tableau de bord financier.
    $referer = url()->previous();
    $cur = url()->current();
    $backUrl = ($referer && $referer !== $cur && !str_contains($referer, '/admin/finance/relances'))
        ? $referer
        : route('admin.finance.index');
@endphp

<x-slot:topbarLeft>
    <a href="{{ $backUrl }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px"
       title="Revenir sur la page précédente">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    <a href="{{ route('admin.finance.index', ['tab' => 'recouvrement']) }}" class="btn btn-ghost btn-sm">
        📞 Recouvrement
    </a>
    <a href="{{ route('admin.finance.index', ['tab' => 'creances']) }}" class="btn btn-ghost btn-sm">
        📉 Créances
    </a>
</x-slot>

<div class="fin-relances-page">
    {{-- Hero header — style cohérent avec les autres dashboards. --}}
    <div style="background:linear-gradient(135deg,rgba(232,160,32,.10),rgba(245,158,11,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:54px;height:54px;border-radius:14px;background:rgba(232,160,32,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:26px;box-shadow:0 4px 12px rgba(232,160,32,.18)">📋</div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:18px;font-weight:800;color:var(--text);letter-spacing:-.2px">Historique des relances</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:4px;line-height:1.5">
                {{ $relances->total() }} relance(s) enregistrée(s){{ request()->hasAny(['client_id','invoice_id','canal','outcome','from','to']) ? ' selon le filtre actif' : ' au total' }}.
                Filtre, suit, et déclenche une nouvelle action en un clic.
            </div>
        </div>
        <a href="{{ route('admin.finance.index', ['tab' => 'recouvrement']) }}"
           class="btn btn-primary btn-sm" style="font-size:12.5px;font-weight:700">
            ＋ Enregistrer une relance
        </a>
    </div>

    {{-- KPI cards --}}
    @php
        $outcomeLabels = [
            'promesse_paiement' => '📅 Promesse de paiement',
            'paiement_recu'     => '✅ Paiement reçu',
            'a_relancer'        => '🔁 À relancer',
            'sans_reponse'      => '📵 Sans réponse',
            'desaccord'         => '⚠ Désaccord',
            'autre'             => '📝 Autre',
        ];
        $canalLabels = $canaux;
    @endphp
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
        <div class="fin-card" style="padding:14px 18px;border-left:4px solid var(--accent)">
            <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Total relances</div>
            <div style="font-size:26px;font-weight:800;color:var(--text)">{{ $kpis['total'] ?? 0 }}</div>
            <div style="font-size:11px;color:var(--text3)">sur le périmètre filtré</div>
        </div>
        <div class="fin-card" style="padding:14px 18px;border-left:4px solid #3b82f6">
            <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Ce mois</div>
            <div style="font-size:26px;font-weight:800;color:#1d4ed8">{{ $kpis['ce_mois'] ?? 0 }}</div>
            <div style="font-size:11px;color:var(--text3)">{{ now()->translatedFormat('F Y') }}</div>
        </div>
        <a href="?outcome=promesse_paiement" class="fin-card" style="padding:14px 18px;border-left:4px solid #16a34a;text-decoration:none;color:inherit;display:block;cursor:pointer">
            <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">📅 Promesses</div>
            <div style="font-size:26px;font-weight:800;color:#15803d">{{ $kpis['promesses'] ?? 0 }}</div>
            <div style="font-size:11px;color:var(--text3)">paiement promis</div>
        </a>
        <a href="?outcome=a_relancer" class="fin-card" style="padding:14px 18px;border-left:4px solid #f59e0b;text-decoration:none;color:inherit;display:block;cursor:pointer">
            <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">🔁 À relancer</div>
            <div style="font-size:26px;font-weight:800;color:#b45309">{{ $kpis['a_relancer'] ?? 0 }}</div>
            <div style="font-size:11px;color:var(--text3)">recontact prévu</div>
        </a>
    </div>

    {{-- Stats par canal + par résultat (cliquables) --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">
        <div class="fin-card">
            <div class="fin-card-head">
                <div>
                    <div class="fin-card-title">📡 Répartition par canal</div>
                    <div class="fin-card-sub">Mode de contact utilisé</div>
                </div>
            </div>
            <div class="fin-card-body" style="padding:8px 18px 14px">
                @if(empty($byCanal))
                    <div class="fin-empty" style="padding:18px">Aucune donnée.</div>
                @else
                    <div style="display:grid;gap:6px">
                        @foreach($canalLabels as $canalKey => $canalLbl)
                            @php $count = (int) ($byCanal[$canalKey] ?? 0); @endphp
                            @if($count > 0)
                                @php $pct = $kpis['total'] > 0 ? round($count / $kpis['total'] * 100, 1) : 0; @endphp
                                <a href="?canal={{ $canalKey }}" style="display:block;text-decoration:none;color:inherit">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px">
                                        <span style="font-size:12.5px;color:var(--text2);font-weight:500">{{ $canalLbl }}</span>
                                        <span style="font-size:11.5px;color:var(--text3);font-weight:700">{{ $count }} ({{ rtrim(rtrim(number_format($pct,1,',',''),'0'),',') }} %)</span>
                                    </div>
                                    <div style="height:5px;background:var(--surface2);border-radius:4px;overflow:hidden">
                                        <div style="height:100%;width:{{ $pct }}%;background:#3b82f6;border-radius:4px"></div>
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="fin-card">
            <div class="fin-card-head">
                <div>
                    <div class="fin-card-title">🎯 Répartition par résultat</div>
                    <div class="fin-card-sub">Issue de l'échange</div>
                </div>
            </div>
            <div class="fin-card-body" style="padding:8px 18px 14px">
                @if(empty($byOutcome))
                    <div class="fin-empty" style="padding:18px">Aucune donnée.</div>
                @else
                    <div style="display:grid;gap:6px">
                        @foreach($outcomeLabels as $outcomeKey => $outcomeLbl)
                            @php $count = (int) ($byOutcome[$outcomeKey] ?? 0); @endphp
                            @if($count > 0)
                                @php $pct = $kpis['total'] > 0 ? round($count / $kpis['total'] * 100, 1) : 0; @endphp
                                <a href="?outcome={{ $outcomeKey }}" style="display:block;text-decoration:none;color:inherit">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px">
                                        <span style="font-size:12.5px;color:var(--text2);font-weight:500">{{ $outcomeLbl }}</span>
                                        <span style="font-size:11.5px;color:var(--text3);font-weight:700">{{ $count }} ({{ rtrim(rtrim(number_format($pct,1,',',''),'0'),',') }} %)</span>
                                    </div>
                                    <div style="height:5px;background:var(--surface2);border-radius:4px;overflow:hidden">
                                        <div style="height:100%;width:{{ $pct }}%;background:#f59e0b;border-radius:4px"></div>
                                    </div>
                                </a>
                            @endif
                        @endforeach
                        @if(!empty($byOutcome[null]) || isset($byOutcome['']))
                            @php $count = (int) ($byOutcome[null] ?? $byOutcome[''] ?? 0); @endphp
                            @if($count > 0)
                                <a href="?outcome=__empty" style="display:block;text-decoration:none;color:var(--text3)">
                                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:11.5px;font-style:italic;padding:4px 0">
                                        <span>— Non renseigné</span>
                                        <span>{{ $count }}</span>
                                    </div>
                                </a>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Section "Relances à venir" — clients dont la dernière relance
         a outcome=a_relancer ou promesse_paiement. Aide à savoir qui
         recontacter en priorité. --}}
    @if($aVenir->isNotEmpty())
        <div class="fin-card" style="margin-bottom:18px">
            <div class="fin-card-head">
                <div>
                    <div class="fin-card-title">🔮 Relances à donner suite</div>
                    <div class="fin-card-sub">{{ $aVenir->count() }} client(s) dont la dernière relance attend une suite (à recontacter ou promesse à confirmer)</div>
                </div>
            </div>
            <div class="fin-card-body fin-card-body--flush">
                <div style="overflow-x:auto">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Dernière relance</th>
                                <th>Résultat</th>
                                <th>Suite à donner</th>
                                <th>Auteur</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($aVenir->sortBy('relance_date') as $r)
                                <tr>
                                    <td>
                                        @if($r->client)
                                            <a href="{{ route('admin.clients.show', $r->client) }}"
                                               style="color:var(--accent);text-decoration:none;font-weight:600">{{ $r->client->name }}</a>
                                        @else
                                            <span style="color:var(--text3)">—</span>
                                        @endif
                                    </td>
                                    <td style="white-space:nowrap;color:var(--text2)">{{ $r->relance_date->format('d/m/Y') }}</td>
                                    <td>
                                        @php
                                            $oLabel = $outcomeLabels[$r->outcome] ?? $r->outcome;
                                            $oColor = $r->outcome === 'promesse_paiement' ? '#15803d' : '#b45309';
                                        @endphp
                                        <span style="padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $oColor }}18;color:{{ $oColor }};border:1px solid {{ $oColor }}44">
                                            {{ $oLabel }}
                                        </span>
                                    </td>
                                    <td style="font-size:12px;color:var(--text2);max-width:260px">
                                        {{ $r->suite_donnee ?: '—' }}
                                    </td>
                                    <td style="font-size:12px;color:var(--text3)">{{ $r->user?->name ?? '—' }}</td>
                                    <td style="text-align:right;white-space:nowrap">
                                        @if($r->client_id)
                                            {{-- Lien direct vers Recouvrement avec auto-ouverture de la
                                                 modale "+ Enregistrer une relance" pré-remplie sur ce client.
                                                 Le retour ramène à cette page (back() intelligent). --}}
                                            <a href="{{ route('admin.finance.index', ['tab' => 'recouvrement', 'open_relance' => 1, 'client_id' => $r->client_id]) }}"
                                               class="btn btn-primary btn-sm" style="font-size:11px;font-weight:700"
                                               title="Ouvrir la modale de relance avec ce client présélectionné">
                                                📞 Relancer
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Filtres enrichis --}}
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
            <div class="fne-field" style="min-width:180px">
                <label style="font-size:10px;text-transform:uppercase;font-weight:700;color:var(--text3);margin-bottom:4px;display:block">Résultat</label>
                <select name="outcome" onchange="this.form.submit()">
                    <option value="">— Tous —</option>
                    @foreach($outcomeLabels as $oKey => $oLbl)
                        <option value="{{ $oKey }}" {{ request('outcome') === $oKey ? 'selected' : '' }}>{{ $oLbl }}</option>
                    @endforeach
                    <option value="__empty" {{ request('outcome') === '__empty' ? 'selected' : '' }}>— Non renseigné</option>
                </select>
            </div>
            <div class="fne-field" style="min-width:130px">
                <label style="font-size:10px;text-transform:uppercase;font-weight:700;color:var(--text3);margin-bottom:4px;display:block">Du</label>
                <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()">
            </div>
            <div class="fne-field" style="min-width:130px">
                <label style="font-size:10px;text-transform:uppercase;font-weight:700;color:var(--text3);margin-bottom:4px;display:block">Au</label>
                <input type="date" name="to" value="{{ request('to') }}" onchange="this.form.submit()">
            </div>
            @if(request()->hasAny(['client_id', 'canal', 'outcome', 'from', 'to']))
                <a href="{{ route('admin.finance.relances') }}" class="btn btn-ghost btn-sm" style="height:38px;display:inline-flex;align-items:center">↺ Réinitialiser</a>
            @endif
        </div>
    </form>

    @if($relances->isEmpty())
        <div class="fin-card">
            <div class="fin-empty" style="padding:50px 20px;text-align:center">
                <div style="font-size:36px;margin-bottom:10px">📞</div>
                <div style="font-size:14px;color:var(--text2);font-weight:600;margin-bottom:4px">Aucune relance enregistrée pour le moment.</div>
                <div style="font-size:12px;color:var(--text3);line-height:1.5;max-width:420px;margin:0 auto">
                    Va dans <a href="{{ route('admin.finance.index', ['tab' => 'recouvrement']) }}" style="color:var(--accent);text-decoration:none;font-weight:700">Recouvrement</a>
                    et clique <strong>+ Relancer</strong> sur une ligne client pour enregistrer la première.
                </div>
            </div>
        </div>
    @else
        @php
            $canalIcons = [
                'telephone' => '📞', 'email' => '📧', 'whatsapp' => '💬',
                'visite'    => '🚶', 'courrier' => '✉️', 'autre' => '📝',
            ];
            $outcomeColors = [
                'promesse_paiement' => ['bg' => 'rgba(34,197,94,.12)',  'c' => '#15803d'],
                'paiement_recu'     => ['bg' => 'rgba(34,197,94,.18)',  'c' => '#15803d'],
                'a_relancer'        => ['bg' => 'rgba(245,158,11,.14)', 'c' => '#b45309'],
                'sans_reponse'      => ['bg' => 'rgba(107,114,128,.12)','c' => '#4b5563'],
                'desaccord'         => ['bg' => 'rgba(239,68,68,.10)',  'c' => '#b91c1c'],
                'autre'             => ['bg' => 'rgba(99,102,241,.10)', 'c' => '#4338ca'],
            ];
        @endphp
        <div class="fin-card">
            <div class="fin-card-head">
                <div>
                    <div class="fin-card-title">📋 Toutes les relances</div>
                    <div class="fin-card-sub">{{ $relances->total() }} relance(s) — page {{ $relances->currentPage() }}/{{ $relances->lastPage() }}</div>
                </div>
            </div>
            <div class="fin-card-body fin-card-body--flush">
                <div style="overflow-x:auto">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Facture</th>
                                <th>Canal</th>
                                <th>Résultat</th>
                                <th>Note (observations)</th>
                                <th>Suite à donner</th>
                                <th>Auteur</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($relances as $r)
                                @php
                                    $oCfg = $outcomeColors[$r->outcome] ?? ['bg' => 'var(--surface2)', 'c' => 'var(--text3)'];
                                    $oLbl = $outcomeLabels[$r->outcome] ?? null;
                                @endphp
                                <tr>
                                    <td style="white-space:nowrap;color:var(--text2);font-weight:600">{{ $r->relance_date->format('d/m/Y') }}</td>
                                    <td>
                                        @if($r->client)
                                            <a href="{{ route('admin.clients.show', $r->client) }}"
                                               style="color:var(--accent);text-decoration:none;font-weight:600"
                                               title="Voir la fiche client">{{ $r->client->name }}</a>
                                        @else
                                            <span style="color:var(--text3)">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($r->invoice)
                                            <a href="{{ route('admin.invoices.show', $r->invoice) }}"
                                               style="font-family:monospace;color:var(--accent);text-decoration:none;font-weight:700"
                                               title="Ouvrir la facture">{{ $r->invoice->reference }}</a>
                                        @else
                                            <span style="display:inline-block;font-size:10.5px;color:var(--text3);background:var(--surface2);padding:2px 8px;border-radius:6px;font-style:italic"
                                                  title="Relance enregistrée au niveau client (pas attachée à une facture précise)">— Relance globale</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--text2)">
                                            {{ $canalIcons[$r->canal] ?? '📞' }}
                                            {{ \App\Models\Relance::CANAUX[$r->canal] ?? $r->canal }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($oLbl)
                                            <span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $oCfg['bg'] }};color:{{ $oCfg['c'] }};white-space:nowrap">{{ $oLbl }}</span>
                                        @else
                                            <span style="color:var(--text3);font-size:11px;font-style:italic">— Non renseigné</span>
                                        @endif
                                    </td>
                                    <td style="max-width:320px;color:var(--text2);font-size:12.5px;line-height:1.5">
                                        @if($r->note)
                                            <span title="{{ $r->note }}">{{ Str::limit($r->note, 110) }}</span>
                                        @else
                                            <span style="color:var(--text3)">—</span>
                                        @endif
                                    </td>
                                    <td style="max-width:200px;color:var(--text2);font-size:12px">
                                        @if($r->suite_donnee)
                                            <span title="{{ $r->suite_donnee }}" style="font-style:italic">💬 {{ Str::limit($r->suite_donnee, 70) }}</span>
                                        @else
                                            <span style="color:var(--text3);font-size:11px">—</span>
                                        @endif
                                    </td>
                                    <td style="color:var(--text2);font-size:12px;white-space:nowrap">{{ $r->user?->name ?? '—' }}</td>
                                    <td style="text-align:right;white-space:nowrap">
                                        @if($r->client_id)
                                            <a href="{{ route('admin.finance.index', ['tab' => 'recouvrement', 'open_relance' => 1, 'client_id' => $r->client_id]) }}"
                                               class="btn btn-ghost btn-sm"
                                               style="font-size:11px;color:var(--accent);font-weight:700"
                                               title="Enregistrer une nouvelle relance pour ce client">📞 Relancer</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="padding:14px 18px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px">
                <div style="font-size:12px;color:var(--text3)">
                    Affichage {{ $relances->firstItem() }}–{{ $relances->lastItem() }} sur {{ $relances->total() }}
                </div>
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
