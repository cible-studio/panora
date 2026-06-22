<x-admin-layout>
<x-slot name="title">{{ $commune->name }} — Fiche commune</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.taxes.index') }}" class="btn btn-ghost btn-sm"
       style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    <a href="{{ route('admin.taxes.commune.payments-history', ['commune' => $commune, 'year' => $year]) }}"
       class="btn btn-primary btn-sm">📋 Historique paiements</a>
</x-slot>

@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
    $statusCfg = [
        'paye'      => ['bg' => 'rgba(34,197,94,.15)',  'c' => '#15803d', 'l' => '✓ Soldé'],
        'partiel'   => ['bg' => 'rgba(245,158,11,.15)', 'c' => '#b45309', 'l' => '◐ Partiel'],
        'non_paye'  => ['bg' => 'rgba(239,68,68,.10)',  'c' => '#b91c1c', 'l' => '⏳ Non payé'],
        'aucun'     => ['bg' => 'rgba(107,114,128,.10)','c' => '#4b5563', 'l' => '— Aucun'],
    ];
@endphp

{{-- ════ HERO COMMUNE ════ --}}
<div style="background:linear-gradient(135deg,rgba(232,160,32,.10),rgba(245,158,11,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="width:60px;height:60px;border-radius:14px;background:rgba(232,160,32,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:30px;box-shadow:0 4px 12px rgba(232,160,32,.18)">🏛️</div>
    <div style="flex:1;min-width:240px">
        <div style="font-size:20px;font-weight:800;color:var(--text);letter-spacing:-.2px">{{ $commune->name }}</div>
        <div style="font-size:12.5px;color:var(--text3);margin-top:4px;line-height:1.6">
            {{ $commune->city ?? '—' }} ·
            Tarif ODP : <strong>{{ $fmt($commune->odp_rate) }}</strong>/m² ·
            Tarif TM : <strong>{{ $fmt($commune->tm_rate ?: config('billing.tm_default', 1000)) }}</strong>/m²
        </div>
    </div>
    <form method="GET" style="display:flex;align-items:center;gap:6px">
        <label style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Année</label>
        <select name="year" onchange="this.form.submit()" class="filter-input" style="height:36px;font-size:12px;font-weight:600">
            @foreach($anneesDispos as $a)
                <option value="{{ $a }}" {{ $a == $year ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
        </select>
    </form>
</div>

{{-- ════ INFOS GÉNÉRALES (snapshot live) ════ --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px">
    <div class="fin-card" style="padding:14px 18px;border-left:4px solid #3b82f6">
        <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">📐 Panneaux</div>
        <div style="font-size:24px;font-weight:800;color:var(--text)">{{ $nbPanneaux }}</div>
        <div style="font-size:11px;color:var(--text3)">parc actif</div>
    </div>
    <div class="fin-card" style="padding:14px 18px;border-left:4px solid #16a34a">
        <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">📊 Occupés</div>
        <div style="font-size:24px;font-weight:800;color:#15803d">{{ $nbOccupes }}</div>
        <div style="font-size:11px;color:var(--text3)">avec campagne / réservation</div>
    </div>
    <div class="fin-card" style="padding:14px 18px;border-left:4px solid var(--accent)">
        <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">📈 Taux occupation</div>
        <div style="font-size:24px;font-weight:800;color:var(--accent)">{{ $tauxOccupation }} %</div>
        <div style="font-size:11px;color:var(--text3)">occupés / total</div>
    </div>
    <div class="fin-card" style="padding:14px 18px;border-left:4px solid {{ $annual['solde'] > 0 ? '#ef4444' : '#16a34a' }}">
        <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">⏳ Solde annuel</div>
        <div style="font-size:24px;font-weight:800;color:{{ $annual['solde'] > 0 ? '#b91c1c' : '#15803d' }}">{{ $fmt($annual['solde']) }}</div>
        <div style="font-size:11px;color:var(--text3)">FCFA restant à payer</div>
    </div>
</div>

{{-- ════ SUIVI FISCAL MENSUEL ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:18px">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border)">
        <div style="font-size:13px;font-weight:800;color:var(--text)">📅 Suivi fiscal mensuel — {{ $year }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:2px">ODP fixe (lié à l'existence du panneau) + TM variable (lié à l'occupation effective).</div>
    </div>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px">
            <thead>
                <tr style="background:var(--surface2);border-bottom:1px solid var(--border)">
                    <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Mois</th>
                    <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:#3b82f6;text-transform:uppercase;letter-spacing:.5px">ODP dû</th>
                    <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:#a855f7;text-transform:uppercase;letter-spacing:.5px">TM due</th>
                    <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.5px">Total dû</th>
                    <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:.5px">Payé</th>
                    <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:#b45309;text-transform:uppercase;letter-spacing:.5px">Solde</th>
                    <th style="text-align:center;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matrix as $idx => $row)
                    @php
                        $cfg = $statusCfg[$row['statut']] ?? $statusCfg['aucun'];
                        // Séparateur trimestriel : insertion d'une ligne récap après chaque 3e mois.
                        $isQuarterEnd = in_array($row['mois'], [3, 6, 9, 12], true);
                        $qIdx = intdiv($row['mois'] - 1, 3);
                    @endphp
                    <tr style="border-bottom:1px solid rgba(0,0,0,.04)">
                        <td style="padding:9px 14px;font-weight:600;color:var(--text2)">{{ $row['mois_label'] }}</td>
                        <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#3b82f6">{{ $fmt($row['odp_du']) }}</td>
                        <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#a855f7">{{ $fmt($row['tm_du']) }}</td>
                        <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;font-weight:700;color:var(--accent)">{{ $fmt($row['total_du']) }}</td>
                        <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#15803d;font-weight:600">{{ $fmt($row['total_paye']) }}</td>
                        <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;font-weight:700;color:{{ $row['solde'] > 0 ? '#b45309' : '#15803d' }}">{{ $fmt($row['solde']) }}</td>
                        <td style="padding:9px 14px;text-align:center">
                            <span style="display:inline-block;padding:2px 9px;border-radius:999px;background:{{ $cfg['bg'] }};color:{{ $cfg['c'] }};font-size:10.5px;font-weight:700">{{ $cfg['l'] }}</span>
                        </td>
                    </tr>
                    @if($isQuarterEnd)
                        @php $q = $quarterly[$qIdx]; @endphp
                        <tr style="background:rgba(59,130,246,.06);border-top:1px dashed rgba(59,130,246,.30);border-bottom:1px dashed rgba(59,130,246,.30)">
                            <td style="padding:9px 14px;font-weight:800;color:#1d4ed8;font-size:11.5px">↳ Cumul {{ $q['label'] }}</td>
                            <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#3b82f6;font-weight:700">{{ $fmt($q['odp_du']) }}</td>
                            <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#a855f7;font-weight:700">{{ $fmt($q['tm_du']) }}</td>
                            <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;font-weight:800;color:var(--accent)">{{ $fmt($q['total_du']) }}</td>
                            <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;font-weight:800;color:#15803d">{{ $fmt($q['total_paye']) }}</td>
                            <td style="padding:9px 14px;text-align:right;font-variant-numeric:tabular-nums;font-weight:800;color:{{ $q['solde'] > 0 ? '#b45309' : '#15803d' }}">{{ $fmt($q['solde']) }}</td>
                            <td style="padding:9px 14px;text-align:center;font-size:10.5px;color:#1d4ed8;font-weight:700">TRIM</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:linear-gradient(90deg,rgba(232,160,32,.10),rgba(232,160,32,.04));border-top:2px solid var(--accent);font-weight:800">
                    <td style="padding:13px 14px;font-size:13px;color:var(--text)">🏆 CUMUL ANNUEL {{ $year }}</td>
                    <td style="padding:13px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#3b82f6;font-size:13px">{{ $fmt($annual['odp_du']) }}</td>
                    <td style="padding:13px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#a855f7;font-size:13px">{{ $fmt($annual['tm_du']) }}</td>
                    <td style="padding:13px 14px;text-align:right;font-variant-numeric:tabular-nums;color:var(--accent);font-size:14px">{{ $fmt($annual['total_du']) }}</td>
                    <td style="padding:13px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#15803d;font-size:14px">{{ $fmt($annual['total_paye']) }}</td>
                    <td style="padding:13px 14px;text-align:right;font-variant-numeric:tabular-nums;color:{{ $annual['solde'] > 0 ? '#b45309' : '#15803d' }};font-size:14px">{{ $fmt($annual['solde']) }}</td>
                    <td style="padding:13px 14px;text-align:center">
                        @php
                            $couv = $annual['total_du'] > 0 ? round(($annual['total_paye'] / $annual['total_du']) * 100, 1) : 0;
                        @endphp
                        <span style="display:inline-block;padding:3px 10px;border-radius:999px;background:rgba(59,130,246,.15);color:#1d4ed8;font-size:11px;font-weight:800">📊 {{ $couv }}%</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

</x-admin-layout>
