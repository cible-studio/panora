<div id="panel-periodes" class="rpt-panel" style="display:none">

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Répartition des durées de campagnes</span>
        </div>
        @php $colors = ['#3b82f6','#e8a020','#a855f7','#14b8a6','#22c55e']; @endphp
        @forelse($repartitionDurees as $i => $row)
        <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:12px;color:var(--text)">{{ $row['label'] }}</span>
                <div style="display:flex;gap:10px;font-size:11px;color:var(--text3)">
                    <span>{{ $row['count'] }} campagne(s)</span>
                    <span style="font-weight:700;color:{{ $colors[$i % count($colors)] }}">{{ $row['pct'] }}%</span>
                </div>
            </div>
            <div style="height:8px;background:var(--surface2);border-radius:10px;overflow:hidden">
                <div style="height:100%;width:{{ $row['pct'] }}%;background:{{ $colors[$i % count($colors)] }};border-radius:10px"></div>
            </div>
        </div>
        @empty
        <div style="color:var(--text3);font-size:13px;text-align:center;padding:24px">Aucune donnée sur cette période</div>
        @endforelse
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Activité mensuelle {{ $annee }}</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;min-width:600px">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['Mois','Campagnes','Panneaux mobilisés','CA (FCFA)','Taux'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($tableauMensuel as $row)
                    <tr style="border-bottom:1px solid var(--border);transition:background .1s" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-size:12px;color:var(--text);font-weight:600">{{ $row['mois'] }}</td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($row['nb_campagnes']) }}</td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($row['panneaux_mobilises']) }}</td>
                        <td style="padding:10px 16px;font-size:12px;font-weight:600;color:var(--accent)">{{ $row['ca'] > 0 ? number_format($row['ca'], 0, ',', ' ') : '—' }}</td>
                        <td style="padding:10px 16px">
                            @php $tc = $row['taux'] >= 75 ? '#ef4444' : ($row['taux'] >= 50 ? '#f97316' : ($row['taux'] >= 25 ? '#e8a020' : '#22c55e')); @endphp
                            @if($row['taux'] > 0)
                            <span style="padding:2px 10px;border-radius:20px;background:{{ $tc }}22;color:{{ $tc }};font-size:11px;font-weight:700">{{ $row['taux'] }}%</span>
                            @else<span style="color:var(--text3);font-size:11px">—</span>@endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>