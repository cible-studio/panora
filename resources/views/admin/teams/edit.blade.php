<x-admin-layout>
<x-slot name="title">Modifier l'équipe — {{ $team->name }}</x-slot>

@php
    // Bouton retour intelligent — cf. create.blade.php pour la doc.
    // Whitelist alignée avec resources/views/admin/partials/_smart_back.blade.php
    // ET PoseTeamController::redirectBack (single source of truth).
    $backMap = [
        'finance'                => ['route' => 'admin.finance.index',                'label' => 'Tableau de bord financier'],
        'finance.relances'       => ['route' => 'admin.finance.relances',             'label' => 'Historique des relances'],
        'rapports'               => ['route' => 'admin.rapports.index',               'label' => 'Rapports & Analyses'],
        'performance.commercial' => ['route' => 'admin.performance.commercial.index', 'label' => 'Performance commerciale'],
        'performance.tech'       => ['route' => 'admin.performance.tech.index',       'label' => 'Performance techniciens'],
        'performance.team'       => ['route' => 'admin.performance.team.index',       'label' => 'Performance équipes'],
        'teams'                  => ['route' => 'admin.teams.index',                  'label' => 'Gérer équipes'],
        'posetasks'              => ['route' => 'admin.pose-tasks.index',             'label' => 'Tâches de pose'],
        'posetasks.techniciens'  => ['route' => 'admin.pose-tasks.techniciens.index', 'label' => 'Techniciens'],
        'clients'                => ['route' => 'admin.clients.index',                'label' => 'Clients'],
        'campaigns'              => ['route' => 'admin.campaigns.index',              'label' => 'Campagnes'],
        'invoices'               => ['route' => 'admin.invoices.index',               'label' => 'Factures'],
        'panels'                 => ['route' => 'admin.panels.index',                 'label' => 'Panneaux'],
    ];
    $backKey = (string) request()->query('back', '');
    $backCfg = $backMap[$backKey] ?? null;
    $backUrl   = $backCfg ? route($backCfg['route']) : route('admin.teams.index');
    $backLabel = $backCfg ? $backCfg['label']         : 'Équipes';
@endphp

<x-slot:topbarLeft>
    <a href="{{ $backUrl }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        {{ $backLabel }}
    </a>
</x-slot:topbarLeft>

<div style="max-width:680px;margin:0 auto">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px 28px;margin-bottom:14px">
        <div style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:4px;display:flex;align-items:center;gap:10px">
            <span style="width:32px;height:32px;border-radius:8px;background:{{ $team->colorBgHex() }};border:2px solid {{ $team->colorHex() }};display:inline-flex;align-items:center;justify-content:center;font-size:14px;color:{{ $team->colorHex() }};font-weight:800">{{ strtoupper(substr($team->name, 0, 1)) }}</span>
            Modifier l'équipe
        </div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:20px">Édite le nom, la couleur, le leader ou le statut.</div>

        @if(session('error'))
            <div style="padding:10px 14px;background:rgba(239,68,68,.10);border-left:4px solid #dc2626;border-radius:8px;margin-bottom:14px;font-size:13px;color:#b91c1c">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.teams.update', $team) }}" class="teams-form">
            @csrf @method('PUT')
            <input type="hidden" name="back" value="{{ $backKey }}">
            @include('admin.teams._form')
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid var(--border)">
                <a href="{{ $backUrl }}" class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>

    {{-- Membres existants + ajout --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px 24px">
        <div style="font-size:14px;font-weight:800;color:var(--text);margin-bottom:10px">👥 Membres ({{ $team->members->count() }})</div>

        @if($team->members->isEmpty())
            <div style="text-align:center;padding:20px;color:var(--text3);font-style:italic;font-size:13px">Aucun membre rattaché.</div>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
                @foreach($team->members as $m)
                    <span style="display:inline-flex;align-items:center;gap:8px;padding:5px 12px;border-radius:999px;background:var(--surface2);border:1px solid var(--border);font-size:13px">
                        {{ $m->name }}
                        <span style="font-size:10.5px;color:var(--text3);font-family:monospace">{{ $m->agent_code }}</span>
                        <form method="POST" action="{{ route('admin.teams.members.remove', [$team, $m]) }}" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;color:#b91c1c;cursor:pointer;font-size:12px;padding:0;line-height:1" title="Retirer">✕</button>
                        </form>
                    </span>
                @endforeach
            </div>
        @endif

        @if($techniciensLibres->isNotEmpty())
            <form method="POST" action="{{ route('admin.teams.members.add', $team) }}">
                @csrf
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:6px">+ Ajouter des techniciens libres</div>
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;max-height:200px;overflow-y:auto;margin-bottom:10px">
                    @foreach($techniciensLibres as $u)
                        <label style="display:flex;align-items:center;gap:8px;padding:5px 0;font-size:13px;cursor:pointer">
                            <input type="checkbox" name="user_ids[]" value="{{ $u->id }}">
                            {{ $u->name }} <span style="color:var(--text3);font-family:monospace;font-size:11px">({{ $u->agent_code }})</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary btn-sm">+ Ajouter les techniciens cochés</button>
            </form>
        @else
            <div style="font-size:11.5px;color:var(--text3);font-style:italic">Tous les techniciens actifs sont déjà rattachés à une équipe.</div>
        @endif
    </div>
</div>

<style>
.teams-form .fne-field { margin-bottom: 14px; }
.teams-form label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text2); margin-bottom: 6px; }
.teams-form label .req { color: #ef4444; }
.teams-form input[type="text"], .teams-form select, .teams-form textarea {
    width: 100%; padding: 8px 10px; height: 38px;
    border: 1px solid var(--border); border-radius: 8px;
    font-size: 13px; background: var(--surface); color: var(--text);
    font-family: inherit; outline: none; box-sizing: border-box;
}
.teams-form textarea { height: auto; min-height: 60px; resize: vertical; line-height: 1.5; }
.teams-form select { cursor: pointer; padding-right: 28px; -webkit-appearance:none; appearance:none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat; background-position: right 8px center;
}
</style>

</x-admin-layout>
