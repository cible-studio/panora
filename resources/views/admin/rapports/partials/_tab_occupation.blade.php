<div id="panel-occupation" class="rpt-panel">

    {{-- Jauge globale --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:8px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Taux global du réseau</span>
            </div>
            <span style="font-size:24px;font-weight:800;color:var(--accent)">{{ $occupation['taux'] }}%</span>
        </div>
        <div style="height:14px;background:var(--surface2);border-radius:20px;overflow:hidden">
            <div style="height:100%;width:{{ $occupation['taux'] }}%;background:linear-gradient(90deg,#e8a020,#f97316);border-radius:20px;transition:width .8s cubic-bezier(.4,0,.2,1)"></div>
        </div>
        <div style="display:flex;gap:20px;margin-top:10px;font-size:11px;color:var(--text3)">
            <span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;background:#ef4444;border-radius:50%;display:inline-block"></span>Occupés {{ $occupation['occupes'] }}</span>
            <span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;background:#22c55e;border-radius:50%;display:inline-block"></span>Libres {{ $occupation['libres'] }}</span>
            <span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;background:#6b7280;border-radius:50%;display:inline-block"></span>Maintenance {{ $occupation['maintenance'] }}</span>
        </div>
    </div>

    {{-- Barres par commune --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Taux d'occupation par commune</span>
        </div>
        @forelse($occParCommune as $row)
        <div style="margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:12px;color:var(--text)">{{ $row['commune'] }}</span>
                <div style="display:flex;gap:12px;font-size:11px;color:var(--text3)">
                    <span>{{ $row['total'] }} pann.</span>
                    <span style="font-weight:700;color:{{ $row['color'] }}">{{ $row['taux'] }}%</span>
                </div>
            </div>
            <div style="height:8px;background:var(--surface2);border-radius:10px;overflow:hidden">
                <div style="height:100%;width:{{ $row['taux'] }}%;background:{{ $row['color'] }};border-radius:10px;transition:width .6s {{ $loop->index * 60 }}ms ease-out"></div>
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:30px;color:var(--text3)">Aucune donnée disponible</div>
        @endforelse
    </div>

    {{-- Évolution mensuelle (barres custom) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Évolution mensuelle — 12 derniers mois</span>
        </div>
        <div id="chart-evol" style="display:flex;align-items:flex-end;gap:4px;height:120px"></div>
        <div id="chart-evol-labels" style="display:flex;gap:4px;margin-top:6px"></div>
    </div>

    {{-- Courbe Chart.js : tendance occupation 12 mois (analyse parc) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Tendance d'occupation du parc — 12 derniers mois</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Pourcentage moyen mensuel</span>
        </div>
        <div style="position:relative;width:100%;height:260px">
            <canvas id="chart-occupation-trend" role="img" aria-label="Tendance d'occupation 12 mois"></canvas>
        </div>
    </div>
</div>