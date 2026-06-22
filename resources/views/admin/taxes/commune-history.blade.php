<x-admin-layout>
<x-slot name="title">Historique paiements — {{ $commune->name }}</x-slot>

<x-slot:topbarLeft>
    <a href="{{ url()->previous() && !str_contains(url()->previous(), '/commune/') ? url()->previous() : route('admin.taxes.index') }}"
       class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px"
       title="Retour">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
</x-slot:topbarLeft>

@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
@endphp

{{-- ════ HERO ════ --}}
<div style="background:linear-gradient(135deg,rgba(232,160,32,.10),rgba(245,158,11,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="width:54px;height:54px;border-radius:14px;background:rgba(232,160,32,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:26px;box-shadow:0 4px 12px rgba(232,160,32,.18)">🏛️</div>
    <div style="flex:1;min-width:240px">
        <div style="font-size:18px;font-weight:800;color:var(--text);letter-spacing:-.2px">{{ $commune->name }}</div>
        <div style="font-size:12.5px;color:var(--text3);margin-top:4px;line-height:1.5">
            {{ $commune->city ?? '—' }} · {{ $rows->count() }} versement(s) enregistré(s) · année {{ $year }}
        </div>
    </div>
    <form method="GET" style="display:flex;align-items:center;gap:6px">
        <label style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Année référence</label>
        <select name="year" onchange="this.form.submit()" class="filter-input" style="height:36px;font-size:12px">
            @foreach($anneesDispos as $a)
                <option value="{{ $a }}" {{ $a == $year ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
        </select>
    </form>
</div>

{{-- ════ KPI CARDS ════ --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:18px">
    <div class="fin-card" style="padding:14px 18px;border-left:4px solid var(--accent)">
        <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">💰 Total dû (année {{ $year }})</div>
        <div style="font-size:22px;font-weight:800;color:var(--text)">{{ $fmt($totalDuLive) }}</div>
        <div style="font-size:11px;color:var(--text3)">FCFA — recalculé en temps réel</div>
    </div>
    <div class="fin-card" style="padding:14px 18px;border-left:4px solid #16a34a">
        <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">✅ Total payé</div>
        <div style="font-size:22px;font-weight:800;color:#15803d">{{ $fmt($totalPaye) }}</div>
        <div style="font-size:11px;color:var(--text3)">FCFA — cumul de tous les versements</div>
    </div>
    <div class="fin-card" style="padding:14px 18px;border-left:4px solid {{ $soldeFinal > 0 ? '#ef4444' : '#16a34a' }}">
        <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">⏳ Solde restant</div>
        <div style="font-size:22px;font-weight:800;color:{{ $soldeFinal > 0 ? '#b91c1c' : '#15803d' }}">{{ $fmt($soldeFinal) }}</div>
        <div style="font-size:11px;color:var(--text3)">FCFA{{ $soldeFinal == 0 && $totalPaye > 0 ? ' — Soldé 🎉' : '' }}</div>
    </div>
    <div class="fin-card" style="padding:14px 18px;border-left:4px solid #3b82f6">
        <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">📊 Taux couverture</div>
        <div style="font-size:22px;font-weight:800;color:#1d4ed8">
            {{-- Hotfix TX-5 (2026-06-22) : on lit la valeur calculée
                 côté controller (avec son garde-fou "trop-payé → 100%")
                 au lieu de recalculer inline avec un risque de divergence. --}}
            {{ $tauxCouverture ?? ($totalDuLive > 0 ? (int) round(($totalPaye / $totalDuLive) * 100) : 0) }} %
        </div>
        <div style="font-size:11px;color:var(--text3)">payé / dû</div>
    </div>
</div>

{{-- ════ TABLE — historique chronologique ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <div>
            <div style="font-size:13px;font-weight:800;color:var(--text)">📋 Versements — du plus ancien au plus récent</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">Aucune opération n'est jamais supprimée (immutabilité fiscale). Cumul et solde recalculés en temps réel.</div>
        </div>
    </div>

    @if($rows->isEmpty())
        <div style="padding:40px;text-align:center;color:var(--text3);font-style:italic">
            Aucun versement enregistré pour cette commune.<br>
            <a href="{{ route('admin.taxes.index') }}" style="color:var(--accent);font-weight:700;text-decoration:none">→ Enregistrer un paiement depuis le dashboard taxes</a>
        </div>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                <thead>
                    <tr style="background:var(--surface2);border-bottom:1px solid var(--border)">
                        <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Date</th>
                        <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Période</th>
                        <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Mode</th>
                        <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Référence</th>
                        <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">ODP</th>
                        <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">TM</th>
                        <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.5px">Versé</th>
                        <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:.5px">Cumul payé</th>
                        <th style="text-align:right;padding:10px 14px;font-size:10px;font-weight:700;color:#b45309;text-transform:uppercase;letter-spacing:.5px">Solde après</th>
                        <th style="text-align:left;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Auteur</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $monthNames = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                    @endphp
                    @foreach($rows as $r)
                        @php
                            $p = $r['payment'];
                            $verseLigne = (int) $p->odp_paye + (int) $p->tm_paye;
                            $periodLabel = match($p->period_type) {
                                'mensuel'     => $monthNames[$p->period_value] . ' ' . $p->period_year,
                                'trimestriel' => 'T' . $p->period_value . ' ' . $p->period_year,
                                'annuel'      => 'Année ' . $p->period_year,
                                default       => $p->period_type,
                            };
                            $isDeleted = !is_null($p->deleted_at);
                        @endphp
                        <tr style="border-bottom:1px solid var(--border-soft, rgba(0,0,0,.04));{{ $isDeleted ? 'opacity:.55;background:rgba(239,68,68,.04)' : '' }}">
                            <td style="padding:10px 14px;white-space:nowrap;color:var(--text2);font-weight:600">
                                {{ $p->paid_at?->format('d/m/Y') ?? '—' }}
                                @if($isDeleted)
                                    <div style="font-size:9.5px;color:#b91c1c;font-weight:700;margin-top:2px">🗑 Annulé le {{ $p->deleted_at->format('d/m/Y') }}</div>
                                @endif
                            </td>
                            <td style="padding:10px 14px;color:var(--text2)">{{ $periodLabel }}</td>
                            <td style="padding:10px 14px">
                                @if($p->mode)
                                    <span style="font-size:11.5px;background:var(--surface2);border:1px solid var(--border);padding:2px 9px;border-radius:999px;font-weight:600">{{ \App\Models\CommuneTaxPayment::MODES[$p->mode] ?? $p->mode }}</span>
                                @else
                                    <span style="font-size:10.5px;color:var(--text3);font-style:italic">— non renseigné</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;font-family:ui-monospace,monospace;font-size:11.5px;color:var(--text2)">{{ $p->reference ?: '—' }}</td>
                            <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#3b82f6;font-weight:600">{{ $fmt($p->odp_paye) }}</td>
                            <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#a855f7;font-weight:600">{{ $fmt($p->tm_paye) }}</td>
                            <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;font-weight:800;color:var(--accent)">{{ $fmt($verseLigne) }}</td>
                            <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#1d4ed8;font-weight:700">{{ $fmt($r['cumul_total']) }}</td>
                            <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#b45309;font-weight:700">{{ $fmt($r['solde_apres']) }}</td>
                            <td style="padding:10px 14px;color:var(--text3);font-size:11.5px">{{ $p->recordedBy?->name ?? '—' }}</td>
                        </tr>
                        @if($p->comment)
                            <tr style="background:rgba(232,160,32,.04)">
                                <td colspan="10" style="padding:6px 14px 10px 14px;font-size:11.5px;color:var(--text2);font-style:italic;border-bottom:1px solid var(--border)">
                                    💬 {{ $p->comment }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:var(--surface2);border-top:2px solid var(--border);font-weight:800">
                        <td colspan="6" style="padding:12px 14px;text-align:right;color:var(--text2)">TOTAL VERSÉ</td>
                        <td style="padding:12px 14px;text-align:right;color:var(--accent);font-variant-numeric:tabular-nums;font-size:14px">{{ $fmt($totalPaye) }}</td>
                        <td style="padding:12px 14px;text-align:right;color:#1d4ed8;font-variant-numeric:tabular-nums;font-size:14px">{{ $fmt($totalPaye) }}</td>
                        <td style="padding:12px 14px;text-align:right;color:#b45309;font-variant-numeric:tabular-nums;font-size:14px">{{ $fmt($soldeFinal) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>

</x-admin-layout>
