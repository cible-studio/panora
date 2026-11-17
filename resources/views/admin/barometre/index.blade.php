<x-admin-layout>
<x-slot name="title">Baromètre clients {{ $edition->edition }}</x-slot>

@php
    $statuts   = \App\Services\Barometre\BarometreService::STATUTS;
    $sources   = \App\Models\BarometreEnvoi::SOURCES;
    $c         = $compteurs;
    $clos      = $edition->estClose();
    $limite    = $edition->date_limite->copy()->locale('fr')->translatedFormat('j F Y');
    $bloque    = $reglage !== null || !$production || $clos;
    $taux      = $c['invites'] > 0 ? round(100 * $c['repondus'] / $c['invites']) : null;
    $nbFiltre  = [
        'tous'      => $c['clients'],
        'a_envoyer' => $c['a_envoyer'],
        'invites'   => $liste->whereIn('statut', ['invite', 'ouvert', 'en_cours'])->count(),
        'repondus'  => $c['repondus'],
        'a_traiter' => $c['sans_email'] + $c['a_choisir'] + $c['doublons'],
        'exclus'    => $c['exclus'],
    ];
@endphp

<style>
    .bm-head { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
    .bm-title { font-size:19px; font-weight:800; color:var(--text); }
    .bm-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:16px; }
    .bm-kpi { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:12px 14px; }
    .bm-kpi-l { font-size:10px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; display:flex; align-items:center; gap:4px; }
    .bm-kpi-v { font-size:22px; font-weight:800; color:var(--text); font-variant-numeric:tabular-nums; margin-top:3px; }
    .bm-kpi-s { font-size:11px; color:var(--text3); margin-top:2px; }
    .bm-actions { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:10px; margin-bottom:16px; }
    .bm-box { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:14px; display:flex; flex-direction:column; gap:8px; }
    .bm-box h3 { margin:0; font-size:13px; font-weight:800; color:var(--text); display:flex; align-items:center; gap:4px; }
    .bm-box p { margin:0; font-size:12px; color:var(--text3); line-height:1.5; }
    .bm-row { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
    .bm-row select, .bm-row input[type=date] { flex:1; min-width:140px; }
    .bm-banner { border-radius:10px; padding:10px 14px; margin-bottom:12px; font-size:12.5px; line-height:1.5; }
    .bm-warn { background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); }
    .bm-err  { background:rgba(239,68,68,.10); border:1px solid rgba(239,68,68,.35); }
    .bm-info { background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.25); }
    .bm-tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); flex-wrap:wrap; padding:0 12px; }
    .bm-tab { padding:9px 12px; font-size:12.5px; font-weight:700; color:var(--text3); text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-1px; }
    .bm-tab.on { color:var(--accent); border-bottom-color:var(--accent); }
    .bm-table td { vertical-align:top; }
    .bm-muted { color:var(--text3); font-size:11px; }
    .bm-mail { font-size:12px; color:var(--text2); word-break:break-all; }
</style>

<div class="bm-head">
    <div class="bm-title">📋 Baromètre clients {{ $edition->edition }}</div>
    @if($production)
        <span class="badge badge-green">Production</span>
    @else
        <span class="badge badge-orange">Mode test</span>
    @endif
    @if($clos)
        <span class="badge badge-gray">Clos depuis le {{ $limite }}</span>
    @endif
    <x-aide titre="Qui reçoit le questionnaire">
        Un seul mail par entreprise cliente, à l'interlocuteur principal ; sinon à celui du dernier dossier
        (campagne, réservation, devis) ; sinon à celui avec qui l'équipe a échangé le plus récemment par mail ;
        sinon au seul interlocuteur qui a une adresse ; sinon à l'adresse de la fiche. Les adresses de la régie
        et celles écartées des envois commerciaux (désinscrits, adresses invalides) ne sont jamais utilisées.
        Le lien du mail est personnel : il ouvre le questionnaire au nom de l'entreprise et expire seul à la date limite.
    </x-aide>
</div>

@if($reglage)
    <div class="bm-banner bm-err">⛔ <strong>Envoi impossible :</strong> {{ $reglage }}</div>
@endif
@if(!$production)
    <div class="bm-banner bm-info">
        🧪 <strong>Mode test</strong> : seul « Envoyer un test » fonctionne, à votre adresse. Aucun client ne reçoit rien tant que
        <code>BAROMETRE_MODE</code> n'est pas sur <code>production</code> (à régler sur le serveur de production seulement).
    </div>
@endif
@if($clos)
    <div class="bm-banner bm-warn">🔒 La date limite est passée : les liens ont expiré et plus rien ne part. Pour prolonger, changez la date limite.</div>
@endif
@if($c['en_cours'] > 0)
    <div class="bm-banner bm-info">⏳ <strong>{{ $c['en_cours'] }} mail(s) en cours d'envoi.</strong> Rechargez la page dans une minute.</div>
@endif

<div class="bm-kpis">
    <div class="bm-kpi" style="border-left:4px solid #f59e0b">
        <div class="bm-kpi-l">À envoyer</div>
        <div class="bm-kpi-v">{{ $c['a_envoyer'] }}</div>
        <div class="bm-kpi-s">sur {{ $c['clients'] }} clients</div>
    </div>
    <div class="bm-kpi" style="border-left:4px solid #3b82f6">
        <div class="bm-kpi-l">Invités</div>
        <div class="bm-kpi-v">{{ $c['invites'] }}</div>
        <div class="bm-kpi-s">{{ $c['relances'] }} relancé(s)</div>
    </div>
    <div class="bm-kpi" style="border-left:4px solid #14b8a6">
        <div class="bm-kpi-l">Ont ouvert le lien
            <x-aide>Premier clic sur le lien personnel. Certaines messageries d'entreprise vérifient les liens avant le client : un « ouvert » n'est donc pas une preuve de lecture.</x-aide>
        </div>
        <div class="bm-kpi-v">{{ $c['ouverts'] }}</div>
        <div class="bm-kpi-s">&nbsp;</div>
    </div>
    <div class="bm-kpi" style="border-left:4px solid #16a34a">
        <div class="bm-kpi-l">Ont répondu
            <x-aide>Signalé par le formulaire Google à chaque réponse. Les réponses elles-mêmes restent dans la feuille Google.</x-aide>
        </div>
        <div class="bm-kpi-v" style="color:#15803d">{{ $c['repondus'] }}</div>
        <div class="bm-kpi-s">{{ $taux !== null ? $taux . ' % des invités' : '—' }} · {{ $reponses['total'] }} réponse(s) reçue(s)</div>
    </div>
    <div class="bm-kpi" style="border-left:4px solid #ef4444">
        <div class="bm-kpi-l">Sans destinataire</div>
        <div class="bm-kpi-v">{{ $nbFiltre['a_traiter'] }}</div>
        <div class="bm-kpi-s">{{ $c['sans_email'] }} sans e-mail · {{ $c['a_choisir'] }} à choisir</div>
    </div>
    <div class="bm-kpi" style="border-left:4px solid #94a3b8">
        <div class="bm-kpi-l">Exclus</div>
        <div class="bm-kpi-v">{{ $c['exclus'] }}</div>
        <div class="bm-kpi-s">régies, nouveaux clients…</div>
    </div>
</div>

<div class="bm-actions">
    <div class="bm-box">
        <h3>📅 Date limite
            <x-aide>Les liens envoyés expirent seuls ce jour-là à minuit, et le formulaire Google se ferme tout seul. La changer prolonge (ou raccourcit) aussi les liens déjà envoyés.</x-aide>
        </h3>
        <p>Réponses jusqu'au <strong>{{ $limite }}</strong> inclus.</p>
        <form method="POST" action="{{ route('admin.barometre.date-limite') }}" class="bm-row">
            @csrf
            <input type="date" name="date_limite" value="{{ $edition->date_limite->toDateString() }}" min="{{ now()->toDateString() }}" class="filter-input" required>
            <button type="submit" class="btn btn-ghost btn-sm">Changer</button>
        </form>
    </div>

    <div class="bm-box">
        <h3>🧪 Envoyer un test
            <x-aide>Le mail exact que recevrait ce client, envoyé à votre adresse ({{ auth()->user()->email }}). Son lien ouvre le questionnaire au nom « TEST — client » : une réponse envoyée depuis un test ne compte pour personne.</x-aide>
        </h3>
        <form method="POST" action="{{ route('admin.barometre.tester') }}" class="bm-row">
            @csrf
            <select name="client_id" class="filter-select" required>
                @foreach($liste as $l)
                    <option value="{{ $l['client']->id }}">{{ $l['client']->name }}{{ $l['destinataire']['email'] ? '' : ' (sans destinataire)' }}</option>
                @endforeach
            </select>
            <label class="bm-muted"><input type="checkbox" name="relance" value="1"> relance</label>
            <button type="submit" class="btn btn-ghost btn-sm" @disabled($reglage || $clos)>Envoyer à moi</button>
        </form>
    </div>

    <div class="bm-box">
        <h3>📨 Envoyer à tous</h3>
        <p>Invitation aux <strong>{{ $c['a_envoyer'] }}</strong> clients « à envoyer ». Une entreprise ne la reçoit jamais deux fois.</p>
        <form method="POST" action="{{ route('admin.barometre.envoyer') }}" class="bm-row" data-confirmer="Envoyer l'invitation à {{ $c['a_envoyer'] }} client(s) maintenant ?">
            @csrf
            <input type="hidden" name="nombre" value="{{ $c['a_envoyer'] }}">
            <button type="submit" class="btn btn-primary btn-sm" @disabled($bloque || $c['a_envoyer'] === 0)>Envoyer à {{ $c['a_envoyer'] }} client(s)</button>
        </form>
    </div>

    <div class="bm-box">
        <h3>🔁 Relancer
            <x-aide>Une seule relance, aux invités qui n'ont pas encore répondu (d'après le formulaire Google). Même lien personnel qu'à l'invitation.</x-aide>
        </h3>
        <p><strong>{{ $c['a_relancer'] }}</strong> invité(s) sans réponse, pas encore relancé(s).</p>
        <form method="POST" action="{{ route('admin.barometre.relancer') }}" class="bm-row" data-confirmer="Envoyer la relance à {{ $c['a_relancer'] }} client(s) maintenant ?">
            @csrf
            <input type="hidden" name="nombre" value="{{ $c['a_relancer'] }}">
            <button type="submit" class="btn btn-primary btn-sm" @disabled($bloque || $c['a_relancer'] === 0)>Relancer {{ $c['a_relancer'] }} client(s)</button>
        </form>
    </div>
</div>

@if($nonReconnues->isNotEmpty())
    <div class="card" style="margin-bottom:16px">
        <div style="padding:12px 14px;font-weight:800;font-size:13px">
            ❓ Réponses non reconnues ({{ $nonReconnues->count() }})
            <x-aide>Le nom tapé dans le questionnaire ne correspond à aucun client (ou à plusieurs). Choisissez le client : il passera en « A répondu » et ne sera pas relancé.</x-aide>
        </div>
        <div class="table-wrap">
            <table class="bm-table">
                <thead><tr><th>Nom tapé dans le questionnaire</th><th>Reçue le</th><th>Client</th></tr></thead>
                <tbody>
                @foreach($nonReconnues as $r)
                    <tr>
                        <td><strong>{{ $r->structure }}</strong></td>
                        <td>{{ $r->repondu_le?->format('d/m/Y H:i') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.barometre.rattacher', $r) }}" class="bm-row">
                                @csrf
                                <select name="client_id" class="filter-select" required>
                                    <option value="">— Choisir le client —</option>
                                    @foreach($liste as $l)
                                        <option value="{{ $l['client']->id }}">{{ $l['client']->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-ghost btn-sm">Rattacher</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card">
    <div class="bm-tabs">
        @foreach(\App\Http\Controllers\Admin\BarometreController::FILTRES as $cle => $libelle)
            <a href="{{ route('admin.barometre.index', ['filtre' => $cle]) }}" class="bm-tab {{ $filtre === $cle ? 'on' : '' }}">
                {{ $libelle }} <span class="bm-muted">· {{ $nbFiltre[$cle] }}</span>
            </a>
        @endforeach
    </div>
    <div class="table-wrap">
        <table class="bm-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Destinataire</th>
                    <th>Statut</th>
                    <th>Invité le</th>
                    <th>Ouvert</th>
                    <th>Relance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($lignes as $l)
                @php
                    [$classe, $libelle] = $statuts[$l['statut']];
                    $d = $l['destinataire'];
                    $e = $l['envoi'];
                @endphp
                <tr>
                    <td><a href="{{ route('admin.clients.show', $l['client']) }}"><strong>{{ $l['client']->name }}</strong></a></td>
                    <td>
                        @if($d['email'])
                            {{ $d['contact'] ?: '—' }}
                            <div class="bm-mail">{{ $d['email'] }}</div>
                            @if(isset($sources[$d['source']]))
                                <div class="bm-muted">{{ $sources[$d['source']] }}</div>
                            @endif
                        @else
                            <span class="bm-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $classe }}">{{ $libelle }}</span>
                        @if($l['detail'])
                            <div class="bm-muted" style="max-width:280px;white-space:normal">{{ $l['detail'] }}</div>
                        @endif
                        @if($l['reponse'])
                            <div class="bm-muted">le {{ $l['reponse']->repondu_le?->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td>{{ $e?->invite_le?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>
                        @if($e?->ouvert_le)
                            {{ $e->ouvert_le->format('d/m') }} <span class="bm-muted">({{ $e->ouvertures }}×)</span>
                        @else — @endif
                    </td>
                    <td>
                        @if($e?->relance_statut === \App\Models\BarometreEnvoi::ENVOYE)
                            {{ $e->relance_le?->format('d/m/Y') }}
                        @elseif($e?->relance_statut === \App\Models\BarometreEnvoi::DEMANDE)
                            <span class="bm-muted">en cours</span>
                        @elseif($e?->relance_statut === \App\Models\BarometreEnvoi::ECHEC)
                            <span class="badge badge-red" title="{{ $e->erreur }}">échec</span>
                        @else — @endif
                    </td>
                    <td style="white-space:nowrap">
                        @if($l['statut'] === 'exclu')
                            <form method="POST" action="{{ route('admin.barometre.reintegrer', $l['client']) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">Remettre</button>
                            </form>
                        @elseif($l['statut'] !== 'repondu')
                            <form method="POST" action="{{ route('admin.barometre.exclure', $l['client']) }}" data-exclure="{{ $l['client']->name }}">
                                @csrf
                                <input type="hidden" name="motif">
                                <button type="submit" class="btn btn-ghost btn-sm">Retirer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text3)">Aucun client dans cette liste.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($reponses['tests'] > 0)
    <p class="bm-muted" style="margin-top:10px">{{ $reponses['tests'] }} réponse(s) de test reçue(s), non comptée(s).</p>
@endif

@push('scripts')
<script>
    document.querySelectorAll('form[data-confirmer]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.dataset.confirmer)) e.preventDefault();
        });
    });
    document.querySelectorAll('form[data-exclure]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            var motif = window.prompt('Pourquoi retirer ' + f.dataset.exclure + ' du baromètre ? (ex. Régie, Nouveau client)');
            if (!motif || !motif.trim()) { e.preventDefault(); return; }
            f.querySelector('input[name=motif]').value = motif.trim();
        });
    });
</script>
@endpush
</x-admin-layout>
