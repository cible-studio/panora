<x-admin-layout>
<x-slot name="title">Suivi des mairies — Exercice {{ $exercice }}</x-slot>

<x-slot:topbarLeft>
    @include('admin.partials._smart_back', ['defaut' => [
        'url'   => route('admin.taxes.index'),
        'label' => 'Taxes communales',
    ]])
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    <a href="{{ route('admin.taxes.suivi.excel', ['exercice' => $exercice]) }}" class="btn btn-ghost btn-sm" title="Même présentation que la fiche de la direction">📊 <span class="btn-label">Excel</span></a>
    <a href="{{ route('admin.taxes.suivi.pdf', ['exercice' => $exercice]) }}" class="btn btn-ghost btn-sm">📄 <span class="btn-label">PDF</span></a>
</x-slot>

@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    // Libellés des statuts : source unique dans TaxeExerciceService.
    $statuts = \App\Services\Taxes\TaxeExerciceService::LIBELLES_STATUT;
    $badge = function (array $l) use ($statuts) {
        [$classe, $libelle, $aide] = $statuts[\App\Services\Taxes\TaxeExerciceService::statutAffiche($l)];
        return '<span class="badge ' . $classe . '" title="' . e($aide) . '">' . e($libelle) . '</span>';
    };
    $libellesZones = ['abidjan' => 'Abidjan et banlieue', 'interieur' => 'Intérieur', 'tout' => 'Toutes les mairies'];
    $tg = $totaux['tout'];
@endphp

<style>
    .sm-head { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
    .sm-title { font-size:19px; font-weight:800; color:var(--text); }
    .sm-years { display:flex; gap:6px; flex-wrap:wrap; }
    .sm-year { padding:5px 12px; border-radius:20px; border:1px solid var(--border2); color:var(--text2); font-size:12px; font-weight:700; text-decoration:none; }
    .sm-year.on { background:var(--accent); border-color:var(--accent); color:#000; }
    .sm-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:10px; margin-bottom:16px; }
    .sm-kpi { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:12px 14px; }
    .sm-kpi-l { font-size:10px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; display:flex; align-items:center; gap:4px; }
    .sm-kpi-v { font-size:20px; font-weight:800; color:var(--text); font-variant-numeric:tabular-nums; margin-top:3px; }
    .sm-kpi-s { font-size:11px; color:var(--text3); margin-top:2px; }
    .sm-tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:0; flex-wrap:wrap; }
    .sm-tab { padding:9px 14px; font-size:12.5px; font-weight:700; color:var(--text3); text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-1px; }
    .sm-tab.on { color:var(--accent); border-bottom-color:var(--accent); }
    .sm-table th, .sm-table td { white-space:nowrap; }
    .sm-table td.num, .sm-table th.num { text-align:right; font-variant-numeric:tabular-nums; }
    .sm-table tfoot td { font-weight:800; border-top:2px solid var(--border2); background:var(--surface2); }
    .sm-muted { color:var(--text3); font-size:11px; }
    .sm-neg { color:#2563eb; }
    .sm-pos { color:#b91c1c; }
    .sm-banner { border-radius:10px; padding:10px 14px; margin-bottom:14px; font-size:12.5px; line-height:1.5; }
    .sm-banner-warn { background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); color:var(--text); }
    .sm-banner-info { background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.25); color:var(--text); }
</style>

<div class="sm-head">
    <div class="sm-title">🏛️ Suivi des mairies</div>
    <div class="sm-years">
        @foreach($disponibles as $a)
            <a href="{{ route('admin.taxes.suivi.index', ['exercice' => $a, 'zone' => $onglet]) }}" class="sm-year {{ $a === $exercice ? 'on' : '' }}">{{ $a }}</a>
        @endforeach
    </div>
    @if($reprise)
        <span class="badge badge-orange">Année de reprise</span>
        <x-aide titre="Année de reprise">
            Pour {{ $exercice }}, le montant dû d'une mairie est ce qu'elle a réclamé
            (arriérés compris), saisi par les comptables dans sa fiche. Le calcul de
            Panora est affiché à côté pour contrôle, sans compter dans le solde.
            Le solde au 31/12/{{ $exercice }} devient l'arriéré de {{ $exercice + 1 }} ;
            à partir de {{ $exercice + 1 }}, le dû est le calcul de Panora.
        </x-aide>
    @else
        <x-aide titre="Comment c'est calculé">
            Arriérés = solde de l'exercice {{ $exercice - 1 }}. ODP = tarif × m² × mois où le panneau
            est debout. TM = tarif × m² × mois de campagne, rangés dans le trimestre où ils
            commencent. Tarifs : ceux de Panora, en vigueur le mois concerné. Tout le parc
            Panora, hors pieds Lumipub (gérés à part, comme sur la page Taxes).
        </x-aide>
    @endif
</div>

@if($reprise && $aSaisir > 0)
    <div class="sm-banner sm-banner-warn">
        ⚠ <strong>{{ $aSaisir }} mairie{{ $aSaisir > 1 ? 's' : '' }}</strong> sans montant réclamé saisi pour {{ $exercice }}.
        Ouvre la fiche de la mairie et renseigne ce qu'elle a réclamé : sans cela, son dû {{ $exercice }} reste à 0
        et son solde ne se reportera pas correctement en {{ $exercice + 1 }}.
    </div>
@endif

<div class="sm-kpis">
    <div class="sm-kpi" style="border-left:4px solid var(--accent)">
        <div class="sm-kpi-l">Total dû
            <x-aide>Arriérés + ODP + TM de l'exercice, toutes mairies.</x-aide>
        </div>
        <div class="sm-kpi-v">{{ $fmt($tg['total_du']) }}</div>
        <div class="sm-kpi-s">dont arriérés {{ $fmt($tg['arrieres']) }}</div>
    </div>
    <div class="sm-kpi" style="border-left:4px solid #16a34a">
        <div class="sm-kpi-l">Payé</div>
        <div class="sm-kpi-v" style="color:#15803d">{{ $fmt($tg['total_paye']) }}</div>
        <div class="sm-kpi-s">règlements de l'exercice {{ $exercice }}</div>
    </div>
    <div class="sm-kpi" style="border-left:4px solid #ef4444">
        <div class="sm-kpi-l">Reste à payer
            <x-aide>Somme des soldes positifs. Un trop-perçu d'une mairie ne vient pas en déduction d'une autre.</x-aide>
        </div>
        <div class="sm-kpi-v" style="color:#b91c1c">{{ $fmt($tg['reste']) }}</div>
        <div class="sm-kpi-s">{{ $tg['mairies'] }} mairie{{ $tg['mairies'] > 1 ? 's' : '' }}</div>
    </div>
    <div class="sm-kpi" style="border-left:4px solid #f59e0b">
        <div class="sm-kpi-l">Dû à ce jour
            <x-aide titre="Dû à ce jour">Ce qu'une mairie peut réclamer aujourd'hui : arriérés, plus l'ODP et la TM des trimestres déjà commencés, moins ce qui est payé.</x-aide>
        </div>
        <div class="sm-kpi-v" style="color:#b45309">{{ $fmt($tg['exigible']) }}</div>
        <div class="sm-kpi-s">au {{ now()->format('d/m/Y') }}</div>
    </div>
    @if($reprise)
        <div class="sm-kpi" style="border-left:4px solid #64748b">
            <div class="sm-kpi-l">Calcul Panora
                <x-aide>Ce que Panora calcule pour {{ $exercice }} avec ses panneaux et ses tarifs. Contrôle seulement : n'entre pas dans le solde de l'année de reprise.</x-aide>
            </div>
            <div class="sm-kpi-v" style="color:var(--text2)">{{ $fmt($tg['controle']) }}</div>
            <div class="sm-kpi-s">contrôle, hors solde</div>
        </div>
    @else
        <div class="sm-kpi" style="border-left:4px solid #a855f7">
            <div class="sm-kpi-l">À venir
                <x-aide>TM des campagnes planifiées qui n'ont pas encore démarré. Hors solde : elle y entre quand la campagne démarre.</x-aide>
            </div>
            <div class="sm-kpi-v" style="color:#7e22ce">{{ $fmt($tg['prevu_tm']) }}</div>
            <div class="sm-kpi-s">TM des campagnes planifiées</div>
        </div>
    @endif
</div>

<div class="card">
    <div class="sm-tabs" style="padding:0 12px">
        @foreach(['abidjan', 'interieur', 'tout'] as $z)
            <a href="{{ route('admin.taxes.suivi.index', ['exercice' => $exercice, 'zone' => $z]) }}" class="sm-tab {{ $onglet === $z ? 'on' : '' }}">
                {{ $libellesZones[$z] }}
                <span class="sm-muted">· {{ $totaux[$z]['mairies'] }}</span>
            </a>
        @endforeach
    </div>

    @php
        $lignes = $onglet === 'tout' ? $zones['abidjan']->concat($zones['interieur'])->sortBy(fn ($l) => \App\Models\Commune::normalizeForMatch($l['commune']->name)) : $zones[$onglet];
        $t = $totaux[$onglet];
    @endphp

    <div class="table-wrap">
        <table class="sm-table">
            <thead>
                <tr>
                    <th>Mairie</th>
                    <th class="num">Panneaux</th>
                    <th class="num">Faces</th>
                    <th class="num">Arriérés</th>
                    <th class="num">ODP {{ $exercice }}</th>
                    <th class="num">TM T1</th>
                    <th class="num">TM T2</th>
                    <th class="num">TM T3</th>
                    <th class="num">TM T4</th>
                    <th class="num">Total dû</th>
                    <th class="num">Payé</th>
                    <th class="num">Solde</th>
                    <th class="num">Dû à ce jour</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
            @forelse($lignes as $l)
                <tr data-ligne-url="{{ route('admin.taxes.suivi.commune', ['commune' => $l['commune_id'], 'exercice' => $exercice]) }}">
                    <td>
                        <strong>{{ $l['commune']->name }}</strong>
                    </td>
                    <td class="num">{{ $l['nb_panneaux'] ?: '—' }}</td>
                    <td class="num">{{ $l['nb_faces'] ?: '—' }}</td>
                    <td class="num">{{ $l['arrieres'] ? $fmt($l['arrieres']) : '—' }}</td>
                    <td class="num">{{ $l['odp'] ? $fmt($l['odp']) : '—' }}</td>
                    @foreach([1, 2, 3, 4] as $q)
                        <td class="num">{{ $l['tm_trim'][$q] ? $fmt($l['tm_trim'][$q]) : '—' }}</td>
                    @endforeach
                    <td class="num">
                        <strong>{{ $fmt($l['total_du']) }}</strong>
                        @if($reprise && $l['controle_total'] > 0)
                            <div class="sm-muted" title="Calcul de Panora, pour contrôle">Panora : {{ $fmt($l['controle_total']) }}</div>
                        @endif
                    </td>
                    <td class="num" style="color:#15803d">
                        {{ $l['total_paye'] ? $fmt($l['total_paye']) : '—' }}
                        @if($l['nb_reglements'] > 0)
                            <div class="sm-muted">{{ $l['nb_reglements'] }} règl.</div>
                        @endif
                    </td>
                    <td class="num {{ $l['solde'] > 0 ? 'sm-pos' : ($l['solde'] < 0 ? 'sm-neg' : '') }}"><strong>{{ $fmt($l['solde']) }}</strong></td>
                    <td class="num">{{ $l['exigible'] ? $fmt($l['exigible']) : '—' }}</td>
                    <td>{!! $badge($l) !!}</td>
                </tr>
            @empty
                <tr><td colspan="14" style="text-align:center;padding:30px;color:var(--text3)">Aucune mairie sur cet exercice.</td></tr>
            @endforelse
            </tbody>
            @if($lignes->isNotEmpty())
            <tfoot>
                <tr>
                    <td>TOTAL</td>
                    <td class="num">{{ $t['panneaux'] }}</td>
                    <td class="num">{{ $t['faces'] }}</td>
                    <td class="num">{{ $fmt($t['arrieres']) }}</td>
                    <td class="num">{{ $fmt($t['odp']) }}</td>
                    @foreach([1, 2, 3, 4] as $q)
                        <td class="num">{{ $fmt($t['tm_trim'][$q]) }}</td>
                    @endforeach
                    <td class="num">{{ $fmt($t['total_du']) }}</td>
                    <td class="num" style="color:#15803d">{{ $fmt($t['total_paye']) }}</td>
                    <td class="num">{{ $fmt($t['solde']) }}</td>
                    <td class="num">{{ $fmt($t['exigible']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<div class="sm-banner sm-banner-info">
    ℹ Clique sur une mairie pour voir sa base (dimensions, panneaux, faces), le détail par trimestre,
    enregistrer un règlement{{ $reprise ? ', saisir ce qu\'elle a réclamé' : '' }} ou sortir son relevé PDF.
</div>

@include('admin.partials.ligne-cliquable')
</x-admin-layout>
