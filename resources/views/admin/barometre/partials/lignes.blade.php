{{--
    Baromètre clients — lignes de la liste (page complète ET rechargement
    sans quitter la page, BarometreController::index ?ajax=1).
    $lignes : LengthAwarePaginator de lignes de BarometreService::liste().
--}}
@php
    $statuts = \App\Services\Barometre\BarometreService::STATUTS;
    $sources = \App\Models\BarometreEnvoi::SOURCES;
@endphp
@forelse($lignes as $l)
    @php
        [$classe, $libelle] = $statuts[$l['statut']];
        $d = $l['destinataire'];
        $e = $l['envoi'];
    @endphp
    <tr>
        <td>
            <a href="{{ route('admin.clients.show', $l['client']) }}" class="bm-client">{{ $l['client']->name }}</a>
            @if($d['contact'])
                <div class="bm-sous" title="{{ $sources[$d['source']] ?? '' }}">{{ $d['contact'] }}</div>
            @elseif($d['email'] && isset($sources[$d['source']]))
                <div class="bm-sous">{{ $sources[$d['source']] }}</div>
            @endif
        </td>
        <td class="bm-mail">{{ $d['email'] ?: '—' }}</td>
        <td>
            <span class="badge {{ $classe }}">{{ $libelle }}</span>
            @if($l['detail'])
                <div class="bm-detail">{{ $l['detail'] }}</div>
            @endif
        </td>
        <td>
            <div class="bm-suivi">
                @if($e?->invite_le)<span class="bm-puce" title="Invitation envoyée">✉ {{ $e->invite_le->format('d/m') }}</span>@endif
                @if($e?->ouvert_le)<span class="bm-puce" title="Premier clic sur le lien ({{ $e->ouvertures }} au total)">👁 {{ $e->ouvert_le->format('d/m') }}{{ $e->ouvertures > 1 ? ' ×' . $e->ouvertures : '' }}</span>@endif
                @if($e?->relance_statut === \App\Models\BarometreEnvoi::ENVOYE)<span class="bm-puce" title="Relance envoyée">🔁 {{ $e->relance_le?->format('d/m') }}</span>@endif
                @if($e?->relance_statut === \App\Models\BarometreEnvoi::DEMANDE)<span class="bm-puce">🔁 en cours</span>@endif
                @if($e?->relance_statut === \App\Models\BarometreEnvoi::ECHEC)<span class="bm-puce" style="color:#dc2626" title="{{ $e->erreur }}">🔁 échec</span>@endif
                @if($l['reponse'])<span class="bm-puce" style="color:#15803d" title="Réponse reçue">✓ {{ $l['reponse']->repondu_le?->format('d/m') }}</span>@endif
                @if(!$e?->invite_le && !$l['reponse'])<span class="bm-sous">—</span>@endif
            </div>
        </td>
        <td class="bm-act">
            @if($l['statut'] === 'exclu')
                <form method="POST" action="{{ route('admin.barometre.reintegrer', $l['client']) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="bm-lien remettre">Remettre</button>
                </form>
            @elseif($l['statut'] !== 'repondu')
                <button type="button" class="bm-lien" data-exclure="{{ $l['client']->name }}"
                        data-action="{{ route('admin.barometre.exclure', $l['client']) }}">Retirer</button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="5" class="bm-vide">Aucun client dans cette liste.</td></tr>
@endforelse
