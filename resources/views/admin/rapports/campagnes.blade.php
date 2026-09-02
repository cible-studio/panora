<x-admin-layout title="Rapport Campagnes">

<x-slot:topbarLeft>
    <a href="{{ route('admin.rapports.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux rapports
    </a>
</x-slot:topbarLeft>

<style>
    .rcamp-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:20px; }
    .rcamp-card {
        background:var(--surface); border:1px solid var(--border); border-radius:14px;
        padding:16px 18px; border-left:4px solid; display:flex; flex-direction:column; gap:6px;
    }
    .rcamp-card-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3); }
    .rcamp-card-value { font-size:28px; font-weight:800; line-height:1; }
    .rcamp-card-sub   { font-size:11px; color:var(--text3); margin-top:auto; }

    .rcamp-section {
        background:var(--surface); border:1px solid var(--border); border-radius:14px;
        padding:18px; margin-bottom:18px;
    }
    .rcamp-section-title {
        font-size:13px; font-weight:700; color:var(--text);
        text-transform:uppercase; letter-spacing:.5px; margin-bottom:14px;
        display:flex; align-items:center; gap:8px;
    }
    .rcamp-section-title svg { width:16px; height:16px; color:var(--accent); }

    .rcamp-bar-row {
        display:flex; align-items:center; gap:10px;
        padding:8px 0; border-bottom:1px solid var(--border);
    }
    .rcamp-bar-row:last-child { border-bottom:none; }
    .rcamp-bar-label { font-size:12px; font-weight:600; color:var(--text); min-width:180px; }
    .rcamp-bar-track { flex:1; height:18px; background:var(--surface2); border-radius:999px; overflow:hidden; position:relative; }
    .rcamp-bar-fill  { height:100%; border-radius:999px; transition:width .4s ease; display:flex; align-items:center; padding-left:8px; }
    .rcamp-bar-fill span { font-size:10px; font-weight:700; color:#fff; }
    .rcamp-bar-count { font-size:13px; font-weight:700; min-width:40px; text-align:right; }

    .rcamp-rank-row {
        display:flex; align-items:center; gap:12px;
        padding:10px 0; border-bottom:1px solid var(--border);
    }
    .rcamp-rank-row:last-child { border-bottom:none; }
    .rcamp-rank {
        width:26px; height:26px; border-radius:50%; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        font-size:11px; font-weight:800; color:#fff;
    }
    .rcamp-rank-1 { background:linear-gradient(135deg,#ffd700,#ffaa00); }
    .rcamp-rank-2 { background:linear-gradient(135deg,#c0c0c0,#9e9e9e); }
    .rcamp-rank-3 { background:linear-gradient(135deg,#cd7f32,#a05a25); }
    .rcamp-rank-default { background:var(--surface2); color:var(--text2); }

    .rcamp-top-cols { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:18px; }
    @media (max-width:1100px) { .rcamp-top-cols { grid-template-columns:1fr; } }

    /* Filtre période — homogène avec sla.blade.php */
    .rcamp-period {
        display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;
        background:var(--surface); border:1px solid var(--border); border-radius:12px;
        padding:12px 16px; margin-bottom:18px;
    }
</style>

{{-- ── Barre filtre période ──────────────────────────────────── --}}
<form method="GET" action="{{ route('admin.rapports.campagnes') }}" class="rcamp-period">
    <div style="display:flex;align-items:center;gap:10px;min-width:0">
        <div style="width:36px;height:36px;border-radius:10px;background:var(--accent-dim);
                    display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px">Période d'analyse</div>
            <div style="font-size:13px;font-weight:600;color:var(--text);margin-top:1px">
                {{ $dateFrom->format('d/m/Y') }} → {{ $dateTo->format('d/m/Y') }} · {{ $total }} campagne{{ $total > 1 ? 's' : '' }}
            </div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <select name="annee" onchange="this.form.submit()"
                style="height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:13px;font-weight:600;color:var(--text)">
            @foreach($anneesDisponibles as $a)
                <option value="{{ $a }}" {{ $a == $annee ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
        </select>
        <select name="mois_du" onchange="this.form.submit()"
                style="height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:13px;font-weight:600;color:var(--text)">
            @foreach(['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'] as $i => $m)
                <option value="{{ $i+1 }}" {{ ($i+1) == $moisDu ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>
        <span style="color:var(--text3);font-size:12px">→</span>
        <select name="mois_au" onchange="this.form.submit()"
                style="height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:13px;font-weight:600;color:var(--text)">
            @foreach(['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'] as $i => $m)
                <option value="{{ $i+1 }}" {{ ($i+1) == $moisAu ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>
    </div>
</form>

{{-- ── KPI cards (6) — design KPI unifié ─────────────────────── --}}
{{-- 2026-XX : 5 des 6 cartes sont cliquables → filtre la section
     "Liste détaillée" en bas. Data-status pointe vers la clé du
     tableau $campaignsByStatus retourné par le controller.
     La carte CA (dernière) reste passive — c'est un agrégat, pas
     un filtre de liste. --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:20px">
    <div class="kpi-card kpi-clickable" data-status="total" role="button" tabindex="0"
         aria-label="Voir la liste de toutes les campagnes" style="--kpi-color:var(--accent)">
        <div class="kpi-card__top-bar" style="background:var(--accent)"></div>
        <div class="kpi-card__icon" style="color:var(--accent)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg></div>
        <div class="kpi-card__value" style="color:var(--accent)">{{ $total }}</div>
        <div class="kpi-card__label">Total campagnes</div>
        <div class="kpi-card__sub">Sur la période sélectionnée</div>
    </div>

    <div class="kpi-card kpi-clickable" data-status="actif" role="button" tabindex="0"
         aria-label="Voir la liste des campagnes actives" style="--kpi-color:#22c55e">
        <div class="kpi-card__top-bar" style="background:#22c55e"></div>
        <div class="kpi-card__icon" style="color:#22c55e"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg></div>
        <div class="kpi-card__value" style="color:#22c55e">{{ $actives }}</div>
        <div class="kpi-card__label">Actives</div>
        <div class="kpi-card__sub">En cours d'affichage</div>
    </div>

    <div class="kpi-card kpi-clickable is-active" data-status="termine" role="button" tabindex="0"
         aria-label="Voir la liste des campagnes terminées" style="--kpi-color:#6b7280">
        <div class="kpi-card__top-bar" style="background:#6b7280"></div>
        <div class="kpi-card__icon" style="color:#6b7280"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="kpi-card__value" style="color:#6b7280">{{ $terminees }}</div>
        <div class="kpi-card__label">Terminées</div>
        <div class="kpi-card__sub">Achevées avec succès</div>
    </div>

    <div class="kpi-card kpi-clickable" data-status="annule" role="button" tabindex="0"
         aria-label="Voir la liste des campagnes annulées" style="--kpi-color:#ef4444">
        <div class="kpi-card__top-bar" style="background:#ef4444"></div>
        <div class="kpi-card__icon" style="color:#ef4444"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
        <div class="kpi-card__value" style="color:#ef4444">{{ $annulees }}</div>
        <div class="kpi-card__label">Annulées</div>
        <div class="kpi-card__sub">{{ $tauxAnnulation }}% du total</div>
    </div>

    <div class="kpi-card kpi-clickable" data-status="planifie" role="button" tabindex="0"
         aria-label="Voir la liste des campagnes planifiées" style="--kpi-color:#f97316">
        <div class="kpi-card__top-bar" style="background:#f97316"></div>
        <div class="kpi-card__icon" style="color:#f97316"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="kpi-card__value" style="color:#f97316">{{ $planifiees }}</div>
        <div class="kpi-card__label">Planifiées</div>
        <div class="kpi-card__sub">À démarrer prochainement</div>
    </div>

    <div class="kpi-card" style="--kpi-color:#fab80b">
        <div class="kpi-card__top-bar" style="background:#fab80b"></div>
        <div class="kpi-card__icon" style="color:#fab80b"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="kpi-card__value" style="color:#fab80b">
            @if($caTotal >= 1000000){{ number_format($caTotal/1000000, 1, ',', ' ') }}M
            @elseif($caTotal >= 1000){{ number_format($caTotal/1000, 0, ',', ' ') }}K
            @else{{ number_format($caTotal, 0, ',', ' ') }}@endif
            <span style="font-size:12px;font-weight:600">FCFA</span>
        </div>
        <div class="kpi-card__label">CA réalisé</div>
        <div class="kpi-card__sub">Actives + terminées + pause</div>
    </div>
</div>

{{-- ── Répartition par statut ───────────────────────────────── --}}
<div class="rcamp-section">
    <div class="rcamp-section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Répartition par statut
    </div>
    @php
        $statusRows = [
            ['label'=>'En cours',  'count'=>$actives,    'color'=>'#22c55e'],
            ['label'=>'Planifiée', 'count'=>$planifiees, 'color'=>'#f97316'],
            ['label'=>'En pause',  'count'=>$enPause,    'color'=>'#f59e0b'],
            ['label'=>'Terminée',  'count'=>$terminees,  'color'=>'#6b7280'],
            ['label'=>'Annulée',   'count'=>$annulees,   'color'=>'#ef4444'],
        ];
    @endphp
    @foreach($statusRows as $row)
        @php $pct = $total > 0 ? round($row['count'] / $total * 100) : 0; @endphp
        <div class="rcamp-bar-row">
            <div class="rcamp-bar-label">{{ $row['label'] }}</div>
            <div class="rcamp-bar-track">
                <div class="rcamp-bar-fill" style="width:{{ max(2, $pct) }}%;background:{{ $row['color'] }}">
                    @if($pct >= 8)<span>{{ $pct }}%</span>@endif
                </div>
            </div>
            <div class="rcamp-bar-count" style="color:{{ $row['color'] }}">{{ $row['count'] }}</div>
        </div>
    @endforeach
</div>

{{-- ── Motifs d'annulation ──────────────────────────────────── --}}
<div class="rcamp-section">
    <div class="rcamp-section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        Motifs d'annulation
        @if($annulees > 0)
            <span style="font-size:11px;color:var(--text3);font-weight:500;text-transform:none;letter-spacing:0">— {{ $annulees }} campagne{{ $annulees > 1 ? 's' : '' }}</span>
        @endif
    </div>
    @if($motifsAnnulation->isEmpty())
        <div style="text-align:center;padding:30px 0;color:#22c55e;font-size:13px">
            ✅ Aucune campagne annulée sur la période
        </div>
    @else
        @foreach($motifsAnnulation as $motif)
        <div class="rcamp-bar-row">
            <div class="rcamp-bar-label" style="color:{{ $motif['color'] }}">{{ $motif['label'] }}</div>
            <div class="rcamp-bar-track">
                <div class="rcamp-bar-fill" style="width:{{ max(2, $motif['pct']) }}%;background:{{ $motif['color'] }}">
                    @if($motif['pct'] >= 8)<span>{{ $motif['pct'] }}%</span>@endif
                </div>
            </div>
            <div class="rcamp-bar-count" style="color:{{ $motif['color'] }}">{{ $motif['count'] }}</div>
        </div>
        @endforeach
    @endif
</div>

{{-- ── Top campagnes (3 colonnes) ───────────────────────────── --}}
<div class="rcamp-top-cols">

    {{-- Top par CA --}}
    <div class="rcamp-section" style="margin-bottom:0">
        <div class="rcamp-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            Top CA
        </div>
        @if($topByCA->isEmpty())
            <div style="text-align:center;padding:24px 0;color:var(--text3);font-size:12px">Aucune campagne avec CA</div>
        @else
            @foreach($topByCA as $i => $c)
            @php $rank = $i + 1; $rankClass = match($rank){1=>'rcamp-rank-1',2=>'rcamp-rank-2',3=>'rcamp-rank-3',default=>'rcamp-rank-default'}; @endphp
            <div class="rcamp-rank-row">
                <div class="rcamp-rank {{ $rankClass }}">{{ $rank }}</div>
                <div style="flex:1;min-width:0">
                    <a href="{{ route('admin.campaigns.show', $c) }}" style="font-size:12px;font-weight:600;color:var(--text);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">{{ $c->name }}</a>
                    <div style="font-size:10px;color:var(--text3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $c->client?->name ?? '—' }}</div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:12px;font-weight:700;color:#fab80b">
                        @php $a = (float) $c->total_amount; @endphp
                        @if($a >= 1000000){{ number_format($a/1000000, 1, ',', ' ') }}M
                        @elseif($a >= 1000){{ number_format($a/1000, 0, ',', ' ') }}K
                        @else{{ number_format($a, 0, ',', ' ') }}@endif
                    </div>
                    <div style="font-size:9px;color:var(--text3)">FCFA</div>
                </div>
            </div>
            @endforeach
        @endif
    </div>

    {{-- Top panneaux les plus utilisés --}}
    <div class="rcamp-section" style="margin-bottom:0">
        <div class="rcamp-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            Panneaux les plus utilisés
        </div>
        @if($topPanels->isEmpty())
            <div style="text-align:center;padding:24px 0;color:var(--text3);font-size:12px">Aucune donnée</div>
        @else
            @foreach($topPanels as $i => $p)
            @php $rank = $i + 1; $rankClass = match($rank){1=>'rcamp-rank-1',2=>'rcamp-rank-2',3=>'rcamp-rank-3',default=>'rcamp-rank-default'}; @endphp
            <div class="rcamp-rank-row">
                <div class="rcamp-rank {{ $rankClass }}">{{ $rank }}</div>
                <div style="flex:1;min-width:0">
                    <a href="{{ route('admin.panels.show', $p->id) }}" style="font-family:ui-monospace,monospace;font-size:12px;font-weight:700;color:var(--accent);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">{{ $p->reference }}</a>
                    <div style="font-size:10px;color:var(--text3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $p->commune_name ?? '—' }}{{ $p->name ? ' · '.$p->name : '' }}</div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:14px;font-weight:700;color:#3b82f6">{{ $p->nb_campagnes }}</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase">campagne{{ $p->nb_campagnes > 1 ? 's' : '' }}</div>
                </div>
            </div>
            @endforeach
        @endif
    </div>

    {{-- Top par durée --}}
    <div class="rcamp-section" style="margin-bottom:0">
        <div class="rcamp-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Top durée
        </div>
        @if($topByDuration->isEmpty())
            <div style="text-align:center;padding:24px 0;color:var(--text3);font-size:12px">Aucune donnée</div>
        @else
            @foreach($topByDuration as $i => $c)
            @php $rank = $i + 1; $rankClass = match($rank){1=>'rcamp-rank-1',2=>'rcamp-rank-2',3=>'rcamp-rank-3',default=>'rcamp-rank-default'}; @endphp
            <div class="rcamp-rank-row">
                <div class="rcamp-rank {{ $rankClass }}">{{ $rank }}</div>
                <div style="flex:1;min-width:0">
                    <a href="{{ route('admin.campaigns.show', $c) }}" style="font-size:12px;font-weight:600;color:var(--text);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">{{ $c->name }}</a>
                    <div style="font-size:10px;color:var(--text3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $c->client?->name ?? '—' }}</div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:14px;font-weight:700;color:#8b5cf6">{{ $c->duree_jours ?? 0 }}</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase">jours</div>
                </div>
            </div>
            @endforeach
        @endif
    </div>

</div>

{{-- ── Tendance mensuelle ────────────────────────────────────── --}}
<div class="rcamp-section">
    <div class="rcamp-section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        Tendance mensuelle — campagnes par mois de démarrage
    </div>
    @if($tendance->isEmpty())
        <div style="text-align:center;padding:30px 0;color:var(--text3);font-size:13px">Aucune donnée sur la période</div>
    @else
        @php $maxNb = $tendance->max(fn($t) => $t->actives + $t->annulees); @endphp
        <div style="display:flex;align-items:flex-end;gap:8px;height:160px;padding:8px 0">
            @foreach($tendance as $point)
            @php
                $totalMois = $point->actives + $point->annulees;
                $hActives  = $maxNb > 0 ? ($point->actives  / $maxNb) * 100 : 0;
                $hAnnulees = $maxNb > 0 ? ($point->annulees / $maxNb) * 100 : 0;
                $moisLabel = \Carbon\Carbon::createFromFormat('Y-m', $point->mois)->locale('fr')->isoFormat('MMM YY');
            @endphp
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;min-width:0"
                 title="{{ $moisLabel }} · {{ $totalMois }} campagne(s) — {{ $point->actives }} actives + {{ $point->annulees }} annulées">
                <div style="font-size:10px;font-weight:700;color:var(--text2);margin-bottom:4px">{{ $totalMois }}</div>
                <div style="width:100%;max-width:32px;display:flex;flex-direction:column;justify-content:flex-end;height:120px">
                    @if($point->annulees > 0)
                    <div style="height:{{ $hAnnulees }}%;background:linear-gradient(180deg,#ef4444,#b91c1c);border-radius:3px 3px 0 0;min-height:2px"></div>
                    @endif
                    @if($point->actives > 0)
                    <div style="height:{{ $hActives }}%;background:linear-gradient(180deg,#22c55e,#15803d);border-radius:{{ $point->annulees > 0 ? '0' : '3px 3px 0 0' }};min-height:2px"></div>
                    @endif
                </div>
                <div style="font-size:9px;color:var(--text3);margin-top:4px;text-align:center;white-space:nowrap">{{ $moisLabel }}</div>
            </div>
            @endforeach
        </div>
        <div style="display:flex;justify-content:center;gap:18px;font-size:11px;color:var(--text2);margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
            <span style="display:inline-flex;align-items:center;gap:6px">
                <span style="width:11px;height:11px;border-radius:3px;background:#22c55e;display:inline-block"></span>
                Actives / planifiées / terminées
            </span>
            <span style="display:inline-flex;align-items:center;gap:6px">
                <span style="width:11px;height:11px;border-radius:3px;background:#ef4444;display:inline-block"></span>
                Annulées
            </span>
        </div>
    @endif
</div>

{{-- ═══ LISTE DÉTAILLÉE — 6 filtres cliquables (2026-XX) ══════════
     Feedback user : les 5 cartes KPI de statut (Total, Actives,
     Terminées, Annulées, Planifiées) sont cliquables → chaque clic
     affiche la liste correspondante ici. Défaut : Terminées.
     Les 6 tables sont rendues en HTML (5 masquées via [hidden]) et
     le JS toggle sans requête AJAX. --}}
<style>
    .kpi-clickable { cursor: pointer; transition: transform .12s ease, box-shadow .12s ease; }
    .kpi-clickable:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.08); }
    .kpi-clickable:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
    .kpi-clickable.is-active {
        box-shadow: 0 0 0 2px var(--kpi-color), 0 6px 18px rgba(0,0,0,.10);
        transform: translateY(-1px);
    }
    .rcamp-list-panel[hidden] { display: none !important; }
</style>

@php
    $statusMeta = [
        'total'    => ['label' => 'Toutes les campagnes',     'color' => 'var(--accent)', 'empty' => 'Aucune campagne sur cette période.'],
        'actif'    => ['label' => 'Campagnes actives',        'color' => '#22c55e',       'empty' => 'Aucune campagne active sur cette période.'],
        'termine'  => ['label' => 'Campagnes terminées',      'color' => '#6b7280',       'empty' => 'Aucune campagne terminée sur cette période.'],
        'annule'   => ['label' => 'Campagnes annulées',       'color' => '#ef4444',       'empty' => 'Aucune campagne annulée sur cette période.'],
        'planifie' => ['label' => 'Campagnes planifiées',     'color' => '#f97316',       'empty' => 'Aucune campagne planifiée sur cette période.'],
    ];
@endphp

<div class="card" style="margin-top:18px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px">
        <h2 style="margin:0;font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px">
            <span>📋</span>
            <span id="rcamp-list-title">{{ $statusMeta['termine']['label'] }}</span>
            <span id="rcamp-list-count" style="background:rgba(107,114,128,.12);color:#6b7280;font-size:12px;font-weight:600;padding:2px 8px;border-radius:10px">
                {{ $campaignsByStatus['termine']->count() }}
            </span>
        </h2>
        <span style="font-size:11px;color:var(--text3)">
            💡 Clique une carte ci-dessus pour filtrer · triées par date de fin
        </span>
    </div>

    @foreach($statusMeta as $key => $meta)
        @php $list = $campaignsByStatus[$key]; @endphp
        <div class="rcamp-list-panel" data-panel="{{ $key }}" @if($key !== 'termine') hidden @endif>
            @if($list->isEmpty())
                <div style="padding:24px;text-align:center;color:var(--text3);font-size:13px;background:rgba(107,114,128,.04);border-radius:8px">
                    {{ $meta['empty'] }}
                </div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border);color:var(--text2);font-size:11px;text-transform:uppercase;letter-spacing:.4px">
                                <th style="text-align:left;padding:8px 10px;font-weight:600">Campagne</th>
                                <th style="text-align:left;padding:8px 10px;font-weight:600">Client</th>
                                <th style="text-align:left;padding:8px 10px;font-weight:600">Période</th>
                                <th style="text-align:left;padding:8px 10px;font-weight:600">Commercial</th>
                                @if($key === 'total')
                                    <th style="text-align:left;padding:8px 10px;font-weight:600">Statut</th>
                                @endif
                                <th style="text-align:right;padding:8px 10px;font-weight:600">Panneaux</th>
                                <th style="text-align:right;padding:8px 10px;font-weight:600">Montant</th>
                                <th style="text-align:right;padding:8px 10px;font-weight:600">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($list as $c)
                                @php
                                    $statusValue = is_object($c->status) ? $c->status->value : $c->status;
                                    $statusColors = [
                                        'actif'    => '#22c55e', 'termine' => '#6b7280',
                                        'annule'   => '#ef4444', 'planifie' => '#f97316',
                                        'pause'    => '#f59e0b',
                                    ];
                                    $statusLabels = [
                                        'actif' => 'Actif', 'termine' => 'Terminé',
                                        'annule' => 'Annulé', 'planifie' => 'Planifié',
                                        'pause' => 'En pause',
                                    ];
                                @endphp
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:10px;font-weight:600">
                                        <a href="{{ route('admin.campaigns.show', $c) }}" style="color:var(--text);text-decoration:none">
                                            {{ $c->name }}
                                        </a>
                                    </td>
                                    <td style="padding:10px">
                                        @if($c->client)
                                            <a href="{{ route('admin.clients.show', $c->client) }}" style="color:var(--text2);text-decoration:none">
                                                {{ $c->client->name }}
                                            </a>
                                        @else
                                            <span style="color:var(--text3)">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:10px;color:var(--text2);white-space:nowrap">
                                        {{ $c->start_date?->format('d/m/Y') }}
                                        <span style="color:var(--text3)">→</span>
                                        <strong>{{ $c->end_date?->format('d/m/Y') }}</strong>
                                    </td>
                                    <td style="padding:10px;color:var(--text2)">
                                        {{ $c->user?->name ?? '—' }}
                                    </td>
                                    @if($key === 'total')
                                        <td style="padding:10px">
                                            <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:{{ ($statusColors[$statusValue] ?? '#6b7280') }}18;color:{{ $statusColors[$statusValue] ?? '#6b7280' }}">
                                                {{ $statusLabels[$statusValue] ?? $statusValue }}
                                            </span>
                                        </td>
                                    @endif
                                    <td style="padding:10px;text-align:right;color:var(--text2)">
                                        {{ $c->total_panels ?? 0 }}
                                    </td>
                                    <td style="padding:10px;text-align:right;font-weight:600;white-space:nowrap">
                                        @if($c->total_amount)
                                            {{ number_format($c->total_amount, 0, ',', ' ') }} FCFA
                                        @else
                                            <span style="color:var(--text3)">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:10px;text-align:right">
                                        <a href="{{ route('admin.campaigns.show', $c) }}"
                                           style="display:inline-block;padding:4px 10px;background:rgba(59,130,246,.08);color:#3b82f6;border-radius:6px;font-size:11px;text-decoration:none;font-weight:600">
                                            Voir →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($key === 'termine' && !$list->isEmpty())
                    <div style="margin-top:12px;font-size:11px;color:var(--text3);font-style:italic">
                        💡 Le commercial responsable est prévenu par email à la fin de chaque campagne.
                        Le suivi post-campagne (satisfaction, opportunités) est à sa main.
                    </div>
                @endif
            @endif
        </div>
    @endforeach
</div>

@php
    // Construction extraite ici pour éviter que le parser Blade ne
    // trébuche sur le [ ... ] du closure multi-ligne dans un @json().
    $statusMetaJs = collect($statusMeta)->map(fn ($m, $k) => [
        'label' => $m['label'],
        'color' => $m['color'],
        'count' => $campaignsByStatus[$k]->count(),
    ])->all();
@endphp
<script>
(function () {
    'use strict';
    const cards   = document.querySelectorAll('.kpi-clickable[data-status]');
    const panels  = document.querySelectorAll('.rcamp-list-panel[data-panel]');
    const titleEl = document.getElementById('rcamp-list-title');
    const countEl = document.getElementById('rcamp-list-count');

    // Metadonnees cote JS pour titre + couleur du compteur.
    // Construction faite en PHP ci-dessus (le parser Blade se prenait
    // les pieds dans les crochets/arrows dans une directive inline).
    const meta = {!! json_encode($statusMetaJs, JSON_UNESCAPED_UNICODE) !!};

    function activate(status) {
        cards.forEach(c => c.classList.toggle('is-active', c.dataset.status === status));
        panels.forEach(p => { p.hidden = (p.dataset.panel !== status); });
        if (meta[status]) {
            titleEl.textContent = meta[status].label;
            countEl.textContent = meta[status].count;
            countEl.style.color = meta[status].color;
            countEl.style.background = meta[status].color + '1F';
        }
    }

    cards.forEach(card => {
        card.addEventListener('click', () => activate(card.dataset.status));
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                activate(card.dataset.status);
            }
        });
    });
})();
</script>

</x-admin-layout>
