{{--
    Suivi des mairies — le tableau de la liste (2026-11-18). Rendu au
    chargement de la page ET à chaque clic sur une carte ou un onglet
    (TaxeSuiviController::index, ajax=1) : une seule source pour les deux.

    Reçoit : $lignes (mairies affichées : onglet + filtre), $t (leurs
    totaux, TaxeExerciceService::totaux), $exercice, $reprise.
    Chaque ligne porte sa case de sélection et ses montants (data-*) pour
    la barre de sélection : la barre additionne ces montants, elle ne
    calcule rien d'autre.
--}}
@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    // Libellés des statuts : source unique dans TaxeExerciceService.
    $statuts = \App\Services\Taxes\TaxeExerciceService::LIBELLES_STATUT;
    $badge = function (array $l) use ($statuts) {
        [$classe, $libelle, $aide] = $statuts[\App\Services\Taxes\TaxeExerciceService::statutAffiche($l)];
        return '<span class="badge ' . $classe . '" title="' . e($aide) . '">' . e($libelle) . '</span>';
    };
@endphp
<table class="sm-table">
    <thead>
        <tr>
            <th class="sm-col-sel" data-ligne-ignore>
                <input type="checkbox" id="sm-sel-tableau" title="Sélectionner toutes les mairies affichées" aria-label="Sélectionner toutes les mairies affichées">
            </th>
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
            <td class="sm-col-sel" data-ligne-ignore>
                <label class="sm-sel-zone">
                    <input type="checkbox" class="sm-sel"
                           data-id="{{ $l['commune_id'] }}" data-nom="{{ $l['commune']->name }}"
                           data-du="{{ (int) $l['total_du'] }}" data-paye="{{ (int) $l['total_paye'] }}" data-solde="{{ (int) $l['solde'] }}"
                           aria-label="Sélectionner {{ $l['commune']->name }}">
                </label>
            </td>
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
        <tr><td colspan="15" style="text-align:center;padding:30px;color:var(--text3)">Aucune mairie dans cette liste.</td></tr>
    @endforelse
    </tbody>
    @if($lignes->isNotEmpty())
    <tfoot>
        <tr>
            <td></td>
            <td>TOTAL <span class="sm-muted">· {{ $t['mairies'] }} mairie{{ $t['mairies'] > 1 ? 's' : '' }}</span></td>
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
