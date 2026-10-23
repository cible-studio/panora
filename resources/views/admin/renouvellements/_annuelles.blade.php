{{-- Onglet « Campagnes annuelles » (2026-10-23). --}}
@php
    $fcfa = fn($n) => number_format((int) $n, 0, ',', ' ') . ' FCFA';
    $statuts = \App\Models\Renouvellement::STATUTS;
    $etapesLib = ['j60' => 'J-60', 'j45' => 'J-45', 'j30' => 'J-30', 'j15' => 'J-15', 'j7' => 'J-7'];
    $tuiles = [
        [null, 'Échéances à venir', '#3f7fc0', $compteurs['a_venir']['nb'], $fcfa($compteurs['a_venir']['montant']), 'dont ' . $compteurs['30']['nb'] . ' dans 30 j', 'Campagnes annuelles qui ne sont pas encore terminées.'],
        ['attente', 'Sans réponse', '#81358a', $compteurs['attente']['nb'], $fcfa($compteurs['a_risque']) . ' à risque', $compteurs['sans_reponse'] . ' à appeler (≤ J-25)', 'Clients qui n\'ont pas répondu, échéance dans 60 jours ou moins. À risque : le CA de ceux qui sont à 30 jours ou moins (date limite passée).'],
        ['discussion', 'En discussion', '#fab80b', $compteurs['discussion']['nb'], $fcfa($compteurs['discussion']['montant']), 'renouveler · ajuster · rappel', 'Le client veut renouveler, ajuster son dispositif, ou être rappelé.'],
        ['confirmes', 'Confirmés', '#3aa835', $compteurs['confirmes']['nb'], $fcfa($compteurs['confirmes']['montant']), 'CA sécurisé', 'Renouvellements confirmés par le MP, ou saisis avec « Renouveler la campagne ».'],
        ['perdus', 'Ne renouvellent pas', '#e20613', $compteurs['perdus']['nb'], $fcfa($compteurs['perdus']['montant']), 'CA perdu', 'Le client a répondu qu\'il ne renouvelle pas.'],
    ];
@endphp

{{-- ═══ COMPTEURS (cliquables = filtres) ═══ --}}
<div class="rn-kpis">
    @foreach($tuiles as [$cle, $lib, $coul, $nb, $ca, $sous, $aide])
        <a href="{{ $lien(['vue' => $vue === $cle ? null : $cle]) }}" class="rn-kpi {{ $vue === $cle && $cle ? 'on' : '' }}" data-tip="{{ $aide }}">
            <div class="lib"><span class="pt" style="background:{{ $coul }};"></span>{{ $lib }}</div>
            <div class="val">{{ $nb }}</div>
            <div class="ca">{{ $ca }}<br><small>{{ $sous }}</small></div>
        </a>
    @endforeach
</div>

{{-- ═══ BARRE DE FILTRES ═══ --}}
<form method="GET" action="{{ route('admin.renouvellements.index') }}" class="rn-bar">
    @if($vue)<input type="hidden" name="vue" value="{{ $vue }}">@endif
    @if($horizon)<input type="hidden" name="horizon" value="{{ $horizon }}">@endif
    <label class="rn-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" name="q" value="{{ $q }}" placeholder="Client, campagne, interlocuteur…">
    </label>
    <div class="rn-seg" data-tip="Échéance de la campagne">
        @foreach([0 => 'Toutes', 30 => '≤ 30 j', 60 => '≤ 60 j', 90 => '≤ 90 j'] as $h => $libH)
            <a href="{{ $lien(['horizon' => $h, 'vue' => $vue]) }}" class="{{ $horizon === $h ? 'on' : '' }}">{{ $libH }}</a>
        @endforeach
    </div>
    @if($commerciaux->isNotEmpty())
        <select name="commercial" class="rn-select" onchange="this.form.submit()">
            <option value="">Tous les commerciaux</option>
            @foreach($commerciaux as $u)
                <option value="{{ $u->id }}" @selected($commercialId === $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    @endif
    @if($vue || $horizon || $q || $commercialId)
        <a href="{{ route('admin.renouvellements.index') }}" class="rn-muted" style="text-decoration:underline;">Réinitialiser</a>
    @endif
    @if($compteurs['taux_reponse'] !== null)
        <span class="rn-muted" style="margin-left:auto;" data-tip="Clients ayant répondu, parmi ceux qui ont reçu au moins un mail.">Taux de réponse : <strong style="color:var(--text);">{{ $compteurs['taux_reponse'] }} %</strong></span>
    @endif
</form>

{{-- ═══ LISTE ═══ --}}
<div class="rn-card" style="overflow:hidden;">
    @if($lignes->isEmpty())
        <div class="rn-vide">
            <div class="ic">📭</div>
            @if($vue || $q || $commercialId)
                Aucun dossier ne correspond à ces filtres.<br>
                <a href="{{ route('admin.renouvellements.index') }}" style="color:var(--accent);font-weight:700;">Tout afficher</a>
            @elseif($prochaine)
                Aucune campagne annuelle ne se termine dans les {{ $horizon }} prochains jours.<br>
                Prochaine échéance : <strong style="color:var(--text);">{{ $prochaine->client?->name }} — {{ $prochaine->name }}</strong>,
                le {{ $prochaine->end_date->format('d/m/Y') }} (premier mail le {{ $prochaine->end_date->copy()->subDays(60)->format('d/m/Y') }}).<br>
                <a href="{{ $lien(['horizon' => 0]) }}" style="color:var(--accent);font-weight:700;">Voir toutes les campagnes annuelles</a>
            @else
                Aucune campagne de 12 mois ou plus en cours.<br>
                <span class="rn-muted">Elles apparaîtront ici dès leur création ; la séquence de mails démarre 60 jours avant leur fin.</span>
            @endif
        </div>
    @else
        <div style="overflow-x:auto;">
        <table class="rn-table">
            <thead>
                <tr>
                    <th>Client · campagne</th>
                    <th>Échéance</th>
                    <th data-tip="Les 5 mails : J-60, J-45, J-30, J-15, J-7. Vert = envoyé, jaune = part au prochain envoi, contour = prévu, pointillé = arrêté.">Mails <span class="rn-info">?</span></th>
                    <th>Statut</th>
                    <th>Prochaine action</th>
                    @unless($estCom)<th class="rn-hide-sm">Commercial</th>@endunless
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($lignes as $l)
                @php
                    $c = $l['campagne'];
                    [$libS, $bgS, $fgS] = $statuts[$l['statut']];
                    $classeJ = $l['jours'] > 0 && $l['jours'] <= 15 ? 'chaud' : ($l['jours'] > 0 && $l['jours'] <= 30 ? 'tiede' : '');
                @endphp
                <tr class="rn-row" @click="dossier = {{ $c->id }}" tabindex="0" @keydown.enter="dossier = {{ $c->id }}">
                    <td>
                        <div class="rn-client">{{ $c->client?->name ?? '—' }}</div>
                        <div class="rn-camp">{{ $c->name }} · {{ $l['panneaux'] }} panneau{{ $l['panneaux'] > 1 ? 'x' : '' }}@if($l['montant'] > 0) · {{ $fcfa($l['montant']) }}@endif</div>
                    </td>
                    <td style="white-space:nowrap;">
                        {{ $c->end_date->format('d/m/Y') }}<br>
                        <span class="rn-j {{ $classeJ }}">{{ $l['jours'] > 0 ? 'J-' . $l['jours'] : 'terminée' }}</span>
                    </td>
                    <td>
                        <div class="rn-seq">
                            @foreach($l['sequence'] as $e)
                                <i class="rn-e-{{ $e['etat'] }}" data-tip="{{ $etapesLib[$e['etape']] }} · {{ $e['info'] }}"></i>
                            @endforeach
                        </div>
                    </td>
                    <td>
                        <span class="rn-chip" style="background:{{ $bgS }};color:{{ $fgS }};">{{ $libS }}</span>
                        @if($l['autre_planifiee'] && !in_array($l['statut'], ['confirme', 'refus'], true))
                            <span data-tip="Le client a une autre campagne planifiée (« {{ $l['autre_planifiee'] }} ») : le renouvellement est peut-être déjà réservé. Vérifiez puis mettez le statut à jour." style="cursor:help;">⚠️</span>
                        @endif
                    </td>
                    <td><span class="rn-action {{ $l['action'][1] }}">{{ $l['action'][0] }}</span></td>
                    @unless($estCom)<td class="rn-hide-sm">{{ $l['commercial']?->name ?? '—' }}</td>@endunless
                    <td class="rn-chevron">›</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
