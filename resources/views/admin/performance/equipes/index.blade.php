<x-admin-layout>
<x-slot name="title">Performance équipes</x-slot>

{{-- 2026-06-18 (feedback patronne) : topbarActions réservé aux exports PDF.
     Les boutons de navigation cross-pages sont déplacés dans une action bar
     sous le hero (cf. plus bas). topbarLeft affiche un retour intelligent
     si on arrive avec ?back=<key>. --}}
<x-slot:topbarLeft>
    @include('admin.performance.partials._smart_back')
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    <a href="{{ route('admin.performance.team.export.pdf', array_filter(request()->only(['from', 'to', 'preset']))) }}"
       class="btn btn-ghost btn-sm"
       title="Télécharger le PDF du leaderboard équipes">📄 Exporter PDF</a>
</x-slot>

<div class="perf-page">
    <div style="background:linear-gradient(135deg,rgba(168,85,247,.10),rgba(99,102,241,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:54px;height:54px;border-radius:14px;background:rgba(168,85,247,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:26px">👥</div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:18px;font-weight:800;color:var(--text)">Performance équipes</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:4px;line-height:1.5">
                {{ $leaderboard->count() }} équipe(s) active(s) · {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
            </div>
        </div>
    </div>

    {{-- ════ Action bar (anciens boutons topbar) ════
         Liens cross-page propagent ?back=performance.team → le bouton
         smart back de la page destination ramène ici. --}}
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px">
        <a href="{{ route('admin.performance.tech.index', ['back' => 'performance.team']) }}" class="btn btn-ghost btn-sm">📋 Performance techniciens</a>
        <a href="{{ route('admin.performance.commercial.index', ['back' => 'performance.team']) }}" class="btn btn-ghost btn-sm">📊 Performance commerciale</a>
        <a href="{{ route('admin.teams.index', ['back' => 'performance.team']) }}" class="btn btn-ghost btn-sm">⚙ Gérer équipes</a>
        {{-- 2026-06-19 — Bouton ouvre la modale rapide (au lieu de naviguer).
             Mode reload_on_success : recharge après création pour faire
             apparaître la nouvelle équipe dans le classement ci-dessous. --}}
        <button type="button" onclick="openQuickTeamModal({reload_on_success:true})"
                class="btn btn-primary btn-sm" style="margin-left:auto"
                title="Créer une équipe rapidement (modale)">+ Nouvelle équipe</button>
    </div>

    @include('admin.partials._quick_team_modal', [
        'target_select_id' => 'noop-no-select-here',
        // TOUS les techniciens — ceux déjà dans une équipe affichent un badge
        // "↻ équipe X" et la sélection les TRANSFÈRE vers la nouvelle équipe.
        'available_techs'  => \App\Models\User::techniciens()->with('poseTeams:id,name')->get(['id', 'name', 'agent_code']),
    ])

    <form method="GET" class="perf-filter-card" style="margin-bottom:16px">
        <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div class="fne-field" style="min-width:140px">
                <label>Période</label>
                <select name="preset" onchange="this.form.submit()">
                    <option value="year"    {{ ($preset ?? '') === 'year'    ? 'selected' : '' }}>Cette année</option>
                    <option value="quarter" {{ ($preset ?? '') === 'quarter' ? 'selected' : '' }}>Ce trimestre</option>
                    <option value="month"   {{ ($preset ?? '') === 'month'   ? 'selected' : '' }}>Ce mois</option>
                    <option value="all"     {{ ($preset ?? '') === 'all'     ? 'selected' : '' }}>Tout</option>
                </select>
            </div>
            <div class="fne-field" style="min-width:140px">
                <label>Du</label>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" onchange="this.form.submit()">
            </div>
            <div class="fne-field" style="min-width:140px">
                <label>Au</label>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" onchange="this.form.submit()">
            </div>
        </div>
    </form>

    <div class="perf-card">
        <div class="perf-card-head"><div><div class="perf-card-title">🏆 Classement équipes</div><div class="perf-card-sub">Ordonné par poses réalisées</div></div></div>
        <div class="perf-card-body--flush">
            <table class="perf-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Équipe</th>
                        <th>Leader</th>
                        <th style="text-align:right">Membres</th>
                        <th style="text-align:right">Réalisées</th>
                        <th style="text-align:right">Réactivité moy</th>
                        <th style="text-align:right">% Retard</th>
                        <th style="text-align:right">% Piges rejetées</th>
                        <th style="text-align:right">Signalements</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaderboard as $idx => $row)
                        @php
                            $team = $row['team'];
                            $k = $row['kpis'];
                            $medals = ['🥇','🥈','🥉'];
                            $rank = $medals[$idx] ?? ($idx + 1);
                        @endphp
                        <tr>
                            <td style="font-weight:800">{{ $rank }}</td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:8px">
                                    <span style="width:20px;height:20px;border-radius:6px;background:{{ $team->colorBgHex() }};border:2px solid {{ $team->colorHex() }}"></span>
                                    <strong>{{ $team->name }}</strong>
                                </span>
                            </td>
                            <td style="color:var(--text2)">{{ $team->leader?->name ?? '—' }}</td>
                            <td style="text-align:right;font-weight:700">{{ $row['members_count'] }}</td>
                            <td style="text-align:right;font-weight:800;color:#16a34a">{{ $k['nb_poses_realisees'] }}</td>
                            <td style="text-align:right;color:var(--text2)">{{ $k['reactivite_avg_min'] !== null ? $k['reactivite_avg_min'].' min' : '—' }}</td>
                            <td style="text-align:right;font-weight:700;color:{{ $k['taux_poses_en_retard'] <= 5 ? '#15803d' : ($k['taux_poses_en_retard'] <= 15 ? '#b45309' : '#b91c1c') }}">{{ $k['taux_poses_en_retard'] }} %</td>
                            <td style="text-align:right;font-weight:700;color:{{ $k['taux_piges_rejetees'] <= 5 ? '#15803d' : ($k['taux_piges_rejetees'] <= 15 ? '#b45309' : '#b91c1c') }}">{{ $k['taux_piges_rejetees'] }} %</td>
                            <td style="text-align:right;color:var(--text2)">{{ $k['nb_signalements'] }}</td>
                            <td style="text-align:right"><a href="{{ route('admin.performance.team.show', $team) }}" class="btn btn-ghost btn-sm" style="font-size:11px">Détail →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text3);font-style:italic">Aucune équipe active.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.perf-page select, .perf-page input[type="date"] { height:38px; width:100%; padding:0 10px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; font-family:inherit; outline:none; box-sizing:border-box; }
.perf-page select { padding-right:28px; cursor:pointer; -webkit-appearance:none; appearance:none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat:no-repeat; background-position:right 8px center; }
.perf-page label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text3); margin-bottom:4px; }
.perf-filter-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:14px 18px; }
.perf-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
.perf-card-head { padding:14px 18px; border-bottom:1px solid var(--border); background:var(--surface2); }
.perf-card-title { font-size:14px; font-weight:800; color:var(--text); }
.perf-card-sub { font-size:11.5px; color:var(--text3); margin-top:3px; }
.perf-card-body--flush { padding:0; overflow-x:auto; }
.perf-table { width:100%; border-collapse:collapse; font-size:13px; }
.perf-table th { text-align:left; padding:10px 14px; background:var(--surface2); font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3); border-bottom:1px solid var(--border); }
.perf-table td { padding:10px 14px; border-bottom:1px solid var(--border); color:var(--text); }
.perf-table tr:hover td { background:rgba(232,160,32,.04); }
</style>

</x-admin-layout>
