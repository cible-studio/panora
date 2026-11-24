<x-admin-layout>
<x-slot name="title">Baromètre clients {{ $edition->edition }}</x-slot>

@php
    $c        = $compteurs;
    $clos     = $edition->estClose();
    $limite   = $edition->date_limite->copy()->locale('fr')->translatedFormat('j F Y');
    $jours    = (int) now()->startOfDay()->diffInDays($edition->date_limite, false);
    $taux     = $c['invites'] > 0 ? round(100 * $c['repondus'] / $c['invites']) . ' % des invités' : 'aucun invité pour l\'instant';
    $moi      = (string) auth()->user()->email;
    $bloquants = array_values(array_filter([
        $reglage,
        !$production ? 'Mode test : les clients ne reçoivent rien tant que BAROMETRE_MODE n\'est pas sur production (réglage du serveur).' : null,
        $clos ? 'La date limite est passée : prolongez-la dans ⚙ Réglages.' : null,
    ]));
    $titres = [
        'tous'              => 'Tous les clients',
        'a_envoyer'         => 'À envoyer',
        'invites'           => 'Invités, sans réponse',
        'repondus'          => 'Ont répondu',
        'sans_destinataire' => 'Sans destinataire',
        'exclus'            => 'Exclus',
    ];
    $ic = fn (string $d) => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
    $cartes = [
        ['f' => 'tous', 'l' => 'Tous les clients', 'sub' => 'base clients de Panora', 'c' => 'var(--accent)',
         'svg' => $ic('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>')],
        ['f' => 'a_envoyer', 'l' => 'À envoyer', 'sub' => 'recevront l\'invitation', 'c' => '#f59e0b',
         'svg' => $ic('<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>')],
        ['f' => 'invites', 'l' => 'Invités', 'sub' => $c['ouverts'] . ' ont ouvert le lien', 'c' => '#3b82f6',
         'svg' => $ic('<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>')],
        ['f' => 'repondus', 'l' => 'Ont répondu', 'sub' => $taux, 'c' => '#22c55e',
         'svg' => $ic('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>')],
        ['f' => 'sans_destinataire', 'l' => 'Sans destinataire', 'sub' => $c['sans_email'] . ' sans e-mail · ' . ($c['a_choisir'] + $c['doublons']) . ' à vérifier', 'c' => '#ef4444',
         'svg' => $ic('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>')],
        ['f' => 'exclus', 'l' => 'Exclus', 'sub' => 'régies, nouveaux clients…', 'c' => '#94a3b8',
         'svg' => $ic('<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>')],
    ];
    $premierAEnvoyer = $liste->first(fn ($l) => in_array($l['statut'], \App\Services\Barometre\BarometreService::A_ENVOYER, true));
@endphp

<x-slot:topbarActions>
    <button type="button" class="btn btn-ghost btn-sm" data-modal="bm-modal-reglages">⚙ <span class="btn-label">Réglages</span></button>
    <button type="button" class="btn btn-ghost btn-sm" data-modal="bm-modal-test">🧪 <span class="btn-label">Envoyer un test</span></button>
    @if($c['invites'] > 0)
        <button type="button" class="btn btn-ghost btn-sm" data-modal="bm-modal-relance">🔁 <span class="btn-label">Relancer · {{ $c['a_relancer'] }}</span></button>
    @endif
    <button type="button" class="btn btn-primary btn-sm" data-modal="bm-modal-envoi">📨 <span class="btn-label">Envoyer à {{ $c['a_envoyer'] }} client{{ $c['a_envoyer'] > 1 ? 's' : '' }}</span></button>
</x-slot:topbarActions>

<style>
    .bm-etat { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); background:var(--surface); border:1px solid var(--border); border-radius:14px; margin-bottom:16px; overflow:hidden; }
    .bm-etape { display:flex; gap:10px; align-items:flex-start; padding:12px 14px; border-right:1px solid var(--border); background:none; border-top:0; border-bottom:0; border-left:0; text-align:left; color:inherit; font:inherit; }
    .bm-etape:last-child { border-right:0; }
    button.bm-etape { cursor:pointer; }
    button.bm-etape:hover { background:var(--surface2); }
    .bm-pastille { width:9px; height:9px; border-radius:50%; margin-top:5px; flex-shrink:0; }
    .bm-ok { background:#22c55e; } .bm-attention { background:#f59e0b; } .bm-ko { background:#ef4444; }
    .bm-etape-l { font-size:10px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; }
    .bm-etape-v { font-size:13px; font-weight:700; color:var(--text); margin-top:2px; line-height:1.35; }
    .bm-etape-s { font-size:11px; color:var(--text3); margin-top:2px; line-height:1.4; word-break:break-word; }
    .bm-cartes { display:grid; grid-template-columns:repeat(auto-fit,minmax(165px,1fr)); gap:10px; margin-bottom:16px; }
    .bm-cartes .kpi-card { transition:border-color .15s, transform .15s, box-shadow .15s; }
    .bm-cartes .kpi-card:hover { border-color:var(--kpi-color); transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,.12); }
    .bm-liste { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
    .bm-liste-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 16px; border-bottom:1px solid var(--border); }
    .bm-liste-titre { display:flex; align-items:center; gap:8px; font-size:14px; font-weight:800; color:var(--text); }
    .bm-compte { font-size:11px; font-weight:800; padding:2px 9px; border-radius:20px; background:var(--surface2); color:var(--text2); }
    .bm-outils { display:flex; gap:8px; align-items:center; flex:0 1 auto; }
    .bm-outils .btn { white-space:nowrap; }
    .bm-recherche { position:relative; display:block; width:300px; }
    .bm-recherche svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text3); pointer-events:none; }
    .bm-recherche input { width:100%; padding:8px 12px 8px 32px; background:var(--surface2); border:1px solid var(--border2); border-radius:10px; color:var(--text); font-size:13px; outline:none; }
    .bm-recherche input:focus { border-color:var(--accent); }
    .bm-table { width:100%; border-collapse:collapse; }
    .bm-table th { text-align:left; font-size:10px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; padding:10px 16px; border-bottom:1px solid var(--border); white-space:nowrap; }
    .bm-table td { padding:9px 16px; border-bottom:1px solid var(--border); vertical-align:middle; font-size:13px; }
    .bm-table tr:last-child td { border-bottom:0; }
    .bm-table tbody tr:hover { background:var(--surface2); }
    .bm-client { font-weight:700; color:var(--text); text-decoration:none; }
    .bm-client:hover { color:var(--accent); }
    .bm-sous { font-size:11.5px; color:var(--text3); margin-top:2px; }
    .bm-mail { font-size:12px; color:var(--text2); word-break:break-all; }
    .bm-detail { font-size:11px; color:var(--text3); margin-top:3px; max-width:300px; line-height:1.4; }
    .bm-suivi { display:flex; gap:4px; flex-wrap:wrap; }
    .bm-puce { font-size:11px; padding:2px 7px; border-radius:6px; background:var(--surface2); color:var(--text2); white-space:nowrap; }
    .bm-act { text-align:right; white-space:nowrap; }
    .bm-lien { background:none; border:0; padding:4px 6px; font-size:12px; font-weight:700; color:var(--text3); cursor:pointer; border-radius:6px; }
    .bm-lien:hover { color:var(--red, #ef4444); background:var(--surface2); }
    .bm-lien.remettre:hover { color:var(--accent); }
    .bm-vide { text-align:center; padding:36px 16px; color:var(--text3); font-size:13px; }
    .bm-pagination { padding:12px 16px; border-top:1px solid var(--border); }
    .bm-pagination:empty { display:none; }
    .bm-alerte { border-radius:12px; padding:12px 14px; margin-bottom:12px; font-size:12.5px; line-height:1.55; }
    .bm-alerte-ko { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.3); }
    .bm-alerte-info { background:rgba(59,130,246,.07); border:1px solid rgba(59,130,246,.25); }
    .bm-alerte ul { margin:6px 0 0; padding-left:18px; }
    .bm-chargement { opacity:.45; pointer-events:none; transition:opacity .15s; }
    .bm-courant { font-size:11px; color:var(--text3); margin-top:4px; word-break:break-all; }
    #bm-modal-reglages input::placeholder { font-style:italic; opacity:.55; }
    .bm-deux { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    @media (max-width:760px) { .bm-outils { flex-wrap:wrap; width:100%; } .bm-recherche { width:100%; } }
    @media (max-width:640px) { .bm-deux { grid-template-columns:1fr; } .bm-etape { border-right:0; border-bottom:1px solid var(--border); } }
</style>

{{-- ══ Où en est-on : ce qui doit être prêt avant d'envoyer ══ --}}
<div class="bm-etat">
    <button type="button" class="bm-etape" data-modal="bm-modal-reglages" title="Régler le questionnaire">
        <span class="bm-pastille {{ $reglage ? 'bm-ko' : 'bm-ok' }}"></span>
        <div>
            <div class="bm-etape-l">Questionnaire</div>
            <div class="bm-etape-v">{{ $reglage ? 'À relier' : 'Relié à Google Forms' }}</div>
            <div class="bm-etape-s">{{ $reglage ? 'Collez son lien prérempli' : 'lien personnel par client' }}</div>
        </div>
    </button>
    <div class="bm-etape">
        <span class="bm-pastille {{ $jetonGoogle ? ($derniere ? 'bm-ok' : 'bm-attention') : 'bm-ko' }}"></span>
        <div>
            <div class="bm-etape-l">Réponses Google
                <x-aide titre="Réponses Google">À chaque réponse, le questionnaire prévient Panora (nom de la structure et heure seulement). C'est ce qui permet de ne relancer que ceux qui n'ont pas répondu. À installer une fois : docs/outils/barometre-panora.gs.</x-aide>
            </div>
            <div class="bm-etape-v">{{ $jetonGoogle ? ($derniere ? $reponses['total'] . ' réponse(s) reçue(s)' : 'En attente de réponses') : 'Pas encore relié' }}</div>
            <div class="bm-etape-s">{{ $jetonGoogle ? ($derniere ? 'dernière le ' . \Illuminate\Support\Carbon::parse($derniere)->format('d/m à H:i') : 'aucune reçue pour l\'instant') : 'jeton à poser sur le serveur' }}</div>
        </div>
    </div>
    <button type="button" class="bm-etape" data-modal="bm-modal-reglages" title="Changer la signature ou l'adresse de réponse">
        <span class="bm-pastille {{ $signature['nom'] ? 'bm-ok' : 'bm-attention' }}"></span>
        <div>
            <div class="bm-etape-l">Expéditeur</div>
            <div class="bm-etape-v">{{ $expediteur }}</div>
            <div class="bm-etape-s">réponses à {{ $repondreA ?: (config('brevo.sender.email') ?: 'l\'expéditeur') }}</div>
        </div>
    </button>
    <button type="button" class="bm-etape" data-modal="bm-modal-reglages" title="Changer la date limite">
        <span class="bm-pastille {{ $clos ? 'bm-ko' : ($jours <= 3 ? 'bm-attention' : 'bm-ok') }}"></span>
        <div>
            <div class="bm-etape-l">Date limite</div>
            <div class="bm-etape-v">{{ $limite }}</div>
            <div class="bm-etape-s">{{ $clos ? 'clos : les liens ont expiré' : ($jours === 0 ? 'dernier jour' : 'dans ' . $jours . ' jour' . ($jours > 1 ? 's' : '')) }}</div>
        </div>
    </button>
    <div class="bm-etape">
        <span class="bm-pastille {{ $production ? 'bm-ok' : 'bm-attention' }}"></span>
        <div>
            <div class="bm-etape-l">Mode</div>
            <div class="bm-etape-v">{{ $production ? 'Production' : 'Test' }}</div>
            <div class="bm-etape-s">{{ $production ? 'l\'envoi aux clients est ouvert' : 'seul l\'envoi de test part' }}</div>
        </div>
    </div>
</div>

@if($c['en_cours'] > 0)
    <div class="bm-alerte bm-alerte-info">⏳ <strong>{{ $c['en_cours'] }} mail(s) en cours d'envoi.</strong> Les statuts se mettent à jour d'ici une minute ou deux : rechargez la page.</div>
@endif

{{-- ══ Cartes : chacune filtre la liste (second clic : tout) ══ --}}
<div class="bm-cartes">
    @foreach($cartes as $k)
        <button type="button" class="kpi-card {{ $filtre === $k['f'] && $k['f'] !== 'tous' ? 'is-active' : '' }}"
                data-filtre="{{ $k['f'] }}" style="--kpi-color:{{ $k['c'] }}" title="Afficher : {{ $titres[$k['f']] }}">
            <div class="kpi-card__top-bar" style="background:{{ $k['c'] }}"></div>
            <div class="kpi-card__icon" style="color:{{ $k['c'] }}">{!! $k['svg'] !!}</div>
            <div class="kpi-card__value" style="color:{{ $k['c'] }}">{{ $nombres[$k['f']] }}</div>
            <div class="kpi-card__label">{{ $k['l'] }}</div>
            <div class="kpi-card__sub">{{ $k['sub'] }}</div>
            <div class="kpi-card__arrow" style="color:{{ $k['c'] }}">→</div>
        </button>
    @endforeach
</div>

@if($nonReconnues->isNotEmpty())
    <div class="bm-liste" style="margin-bottom:16px">
        <div class="bm-liste-head">
            <div class="bm-liste-titre">❓ Réponses à rattacher <span class="bm-compte">{{ $nonReconnues->count() }}</span>
                <x-aide titre="Réponses à rattacher">Le nom tapé dans le questionnaire ne correspond à aucun client (ou à plusieurs). Choisissez le client : il passera en « Ont répondu » et ne sera pas relancé.</x-aide>
            </div>
        </div>
        <table class="bm-table">
            <tbody>
            @foreach($nonReconnues as $r)
                <tr>
                    <td><strong>{{ $r->structure }}</strong><div class="bm-sous">reçue le {{ $r->repondu_le?->format('d/m/Y à H:i') }}</div></td>
                    <td class="bm-act">
                        <form method="POST" action="{{ route('admin.barometre.rattacher', $r) }}" style="display:flex;gap:6px;justify-content:flex-end">
                            @csrf
                            <select name="client_id" class="filter-select" required>
                                <option value="">Choisir le client…</option>
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
@endif

{{-- ══ La liste : recherche + pagination, rechargées sans quitter la page ══ --}}
<div class="bm-liste">
    <div class="bm-liste-head">
        <div class="bm-liste-titre">
            <span id="bm-titre">{{ $titres[$filtre] }}</span>
            <span class="bm-compte" id="bm-total">{{ $lignes->total() }}</span>
            <x-aide titre="Qui reçoit le questionnaire">
                Un seul mail par entreprise : à l'interlocuteur principal ; sinon à celui du dernier dossier (campagne,
                réservation, devis) ; sinon à celui avec qui l'équipe a échangé le plus récemment par mail ; sinon au seul
                interlocuteur qui a une adresse ; sinon à l'adresse de la fiche. Jamais une adresse de la régie, ni une
                adresse écartée des envois commerciaux (désinscrit, adresse invalide).
            </x-aide>
        </div>
        <div class="bm-outils">
        @if($nombres['sans_destinataire'] > 0)
            <a href="{{ route('admin.barometre.a-completer') }}" class="btn btn-ghost btn-sm"
               title="Excel des clients sans destinataire, par commercial : colonnes jaunes à remplir">📥 Contacts à compléter · {{ $nombres['sans_destinataire'] }}</a>
        @endif
        <label class="bm-recherche">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="search" id="bm-q" value="{{ $q }}" placeholder="Client, interlocuteur, e-mail…" autocomplete="off">
        </label>
        </div>
    </div>
    <div class="table-wrap">
        <table class="bm-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>E-mail</th>
                    <th>Statut</th>
                    <th>Suivi</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="bm-lignes">
                @include('admin.barometre.partials.lignes', ['lignes' => $lignes])
            </tbody>
        </table>
    </div>
    <div class="bm-pagination" id="bm-pagination">@if($lignes->hasPages()){{ $lignes->links() }}@endif</div>
</div>

@if($reponses['tests'] > 0)
    <p class="bm-sous" style="margin-top:10px">{{ $reponses['tests'] }} réponse(s) de test reçue(s), non comptée(s).</p>
@endif

{{-- ══════════════════ FENÊTRES ══════════════════ --}}

{{-- Envoyer un test --}}
<div id="bm-modal-test" class="modal-overlay" style="display:none">
    <div class="modal" style="width:520px">
        <div class="modal-header">
            <div class="modal-title">🧪 Envoyer un test</div>
            <button type="button" class="modal-close" data-fermer>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.barometre.tester') }}">
            @csrf
            <div class="modal-body">
                <p class="bm-sous" style="font-size:12.5px;margin:0 0 14px">
                    Le mail exact que recevrait ce client, envoyé à <strong>votre</strong> adresse : <strong>{{ $moi }}</strong>.
                    Son lien ouvre le questionnaire au nom « TEST — client » : ne validez pas le questionnaire.
                </p>
                <div class="mfg">
                    <label>Client</label>
                    <select name="client_id" required>
                        @foreach($liste as $l)
                            <option value="{{ $l['client']->id }}" @selected($premierAEnvoyer && $premierAEnvoyer['client']->id === $l['client']->id)>
                                {{ $l['client']->name }}{{ $l['destinataire']['email'] ? ' — ' . $l['destinataire']['email'] : ' (sans destinataire)' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <label style="display:flex;gap:8px;align-items:center;font-size:13px;cursor:pointer">
                    <input type="checkbox" name="relance" value="1"> Version « relance »
                </label>
                @if($reglage || $clos)
                    <div class="bm-alerte bm-alerte-ko" style="margin:14px 0 0">⛔ {{ $reglage ?: 'La date limite est passée : prolongez-la dans ⚙ Réglages.' }}</div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-fermer>Annuler</button>
                <button type="submit" class="btn btn-primary" @disabled($reglage || $clos)>Envoyer à {{ $moi }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Envoyer à tous --}}
<div id="bm-modal-envoi" class="modal-overlay" style="display:none">
    <div class="modal" style="width:520px">
        <div class="modal-header">
            <div class="modal-title">📨 Envoyer l'invitation</div>
            <button type="button" class="modal-close" data-fermer>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.barometre.envoyer') }}">
            @csrf
            <input type="hidden" name="nombre" value="{{ $c['a_envoyer'] }}">
            <div class="modal-body">
                <p style="font-size:14px;margin:0 0 10px"><strong>{{ $c['a_envoyer'] }}</strong> entreprise(s) vont recevoir l'invitation, de la part de <strong>{{ $expediteur }}</strong>.</p>
                <p class="bm-sous" style="font-size:12.5px;margin:0">
                    Une seule fois chacune. Le lien de chaque client expire seul le {{ $limite }} à minuit.
                    Ne reçoivent rien : {{ $nombres['sans_destinataire'] }} sans destinataire, {{ $nombres['exclus'] }} exclu(s), {{ $c['repondus'] }} qui ont déjà répondu.
                </p>
                @if($bloquants || $c['a_envoyer'] === 0)
                    <div class="bm-alerte bm-alerte-ko" style="margin:14px 0 0">
                        ⛔ <strong>Envoi impossible pour l'instant :</strong>
                        <ul>
                            @foreach($bloquants as $b)<li>{{ $b }}</li>@endforeach
                            @if($c['a_envoyer'] === 0)<li>Personne à qui envoyer.</li>@endif
                        </ul>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-fermer>Annuler</button>
                <button type="submit" class="btn btn-primary" @disabled($bloquants || $c['a_envoyer'] === 0)>Envoyer à {{ $c['a_envoyer'] }} client(s)</button>
            </div>
        </form>
    </div>
</div>

{{-- Relancer --}}
<div id="bm-modal-relance" class="modal-overlay" style="display:none">
    <div class="modal" style="width:520px">
        <div class="modal-header">
            <div class="modal-title">🔁 Relancer</div>
            <button type="button" class="modal-close" data-fermer>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.barometre.relancer') }}">
            @csrf
            <input type="hidden" name="nombre" value="{{ $c['a_relancer'] }}">
            <div class="modal-body">
                <p style="font-size:14px;margin:0 0 10px"><strong>{{ $c['a_relancer'] }}</strong> invité(s) n'ont pas encore répondu.</p>
                <p class="bm-sous" style="font-size:12.5px;margin:0">
                    Une seule relance par client, avec le même lien qu'à l'invitation.
                    {{ $c['relances'] }} déjà relancé(s), {{ $c['repondus'] }} ont répondu : ils ne reçoivent rien.
                </p>
                @if($bloquants || $c['a_relancer'] === 0)
                    <div class="bm-alerte bm-alerte-ko" style="margin:14px 0 0">
                        ⛔ <strong>Relance impossible pour l'instant :</strong>
                        <ul>
                            @foreach($bloquants as $b)<li>{{ $b }}</li>@endforeach
                            @if($c['a_relancer'] === 0)<li>Personne à relancer.</li>@endif
                        </ul>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-fermer>Annuler</button>
                <button type="submit" class="btn btn-primary" @disabled($bloquants || $c['a_relancer'] === 0)>Relancer {{ $c['a_relancer'] }} client(s)</button>
            </div>
        </form>
    </div>
</div>

{{-- Réglages --}}
<div id="bm-modal-reglages" class="modal-overlay" style="display:none">
    <div class="modal" style="width:600px">
        <div class="modal-header">
            <div class="modal-title">⚙ Réglages du baromètre {{ $edition->edition }}</div>
            <button type="button" class="modal-close" data-fermer>✕</button>
        </div>
        <form method="POST" action="{{ route('admin.barometre.reglages') }}">
            @csrf
            <div class="modal-body">
                <div class="mfg">
                    <label>Lien prérempli du questionnaire
                        <x-aide titre="Lien prérempli">Dans Google Forms : ⋮ (en haut à droite) → « Obtenir le lien prérempli ». Tapez un nom dans « Votre structure », puis « Obtenir le lien » et copiez-le ici. Panora y mettra le nom de chaque client.</x-aide>
                    </label>
                    <input type="url" name="lien" value="{{ old('lien') }}" placeholder="https://docs.google.com/forms/d/e/…/viewform?usp=pp_url&entry.…=…">
                    @if($formulaire)
                        <div class="bm-courant">✓ Actuel : {{ $formulaire }} — laissez vide pour le garder.</div>
                    @endif
                </div>
                <div class="mfg">
                    <label>Date limite des réponses</label>
                    <input type="date" name="date_limite" value="{{ old('date_limite', $edition->date_limite->toDateString()) }}" required>
                    <div class="bm-courant">Les liens expirent ce jour-là à minuit, et le questionnaire Google se ferme tout seul.</div>
                </div>
                <div class="bm-deux">
                    <div class="mfg">
                        <label>Signature : nom</label>
                        <input type="text" name="signature_nom" value="{{ old('signature_nom', $edition->signature_nom) }}" placeholder="ex. : Josiane SAY">
                    </div>
                    <div class="mfg">
                        <label>Société</label>
                        <input type="text" name="signature_societe" value="{{ old('signature_societe', $edition->signature_societe) }}" placeholder="ex. : CIBLE CI">
                    </div>
                </div>
                <div class="mfg">
                    <label>Fonction</label>
                    <input type="text" name="signature_fonction" value="{{ old('signature_fonction', $edition->signature_fonction) }}" placeholder="ex. : Commerciale">
                </div>
                <div class="mfg">
                    <label>Les réponses des clients arrivent à</label>
                    <input type="email" name="repondre_a" value="{{ old('repondre_a', $edition->repondre_a) }}" placeholder="vide = {{ config('brevo.sender.email') ?: 'l\'adresse d\'envoi' }}">
                    <div class="bm-courant">
                        Le mail part de {{ config('brevo.sender.email') ?: 'l\'adresse Brevo' }} sous le nom « {{ $expediteur }} ».
                        Quand un client clique sur « Répondre », son message arrive à l'adresse saisie ici ;
                        laissez vide pour qu'il revienne à {{ config('brevo.sender.email') ?: 'l\'adresse d\'envoi' }}.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-fermer>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

{{-- Retirer un client --}}
<div id="bm-modal-exclure" class="modal-overlay" style="display:none">
    <div class="modal" style="width:460px">
        <div class="modal-header">
            <div class="modal-title">Retirer <span id="bm-exclure-nom"></span></div>
            <button type="button" class="modal-close" data-fermer>✕</button>
        </div>
        <form method="POST" action="" id="bm-exclure-form">
            @csrf
            <div class="modal-body">
                <p class="bm-sous" style="font-size:12.5px;margin:0 0 14px">Il ne recevra ni l'invitation ni la relance. Si son lien est déjà parti, il est coupé. On peut le remettre à tout moment.</p>
                <div class="mfg">
                    <label>Pourquoi</label>
                    <select name="motif" required>
                        @foreach(\App\Http\Controllers\Admin\BarometreController::MOTIFS as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mfg">
                    <label>Précision (facultatif, obligatoire pour « Autre »)</label>
                    <input type="text" name="precision" maxlength="200">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-fermer>Annuler</button>
                <button type="submit" class="btn btn-danger">Retirer</button>
            </div>
        </form>
    </div>
</div>

{{-- Renvoyer l'invitation (2026-11-24) : la fenêtre dit à qui elle partira,
     d'après la fiche à l'instant du clic (BarometreService::apercuRenvoi). --}}
<div id="bm-modal-renvoi" class="modal-overlay" style="display:none">
    <div class="modal" style="width:500px">
        <div class="modal-header">
            <div class="modal-title">📨 Renvoyer l'invitation · <span id="bm-renvoi-nom"></span></div>
            <button type="button" class="modal-close" data-fermer>✕</button>
        </div>
        <form method="POST" action="" id="bm-renvoi-form">
            @csrf
            <div class="modal-body">
                <div id="bm-renvoi-chargement" class="bm-sous" style="font-size:13px">Lecture de la fiche du client…</div>
                <div id="bm-renvoi-contenu" style="display:none;font-size:13.5px;line-height:1.55">
                    <div id="bm-renvoi-message"></div>
                    <div id="bm-renvoi-ancien" class="bm-sous" style="font-size:12.5px;margin-top:10px"></div>
                    <div id="bm-renvoi-note" class="bm-sous" style="font-size:12px;margin-top:10px"></div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="bm-renvoi-fiche" href="#" class="btn btn-ghost" style="display:none;margin-right:auto" target="_blank" rel="noopener">Ouvrir la fiche du client ↗</a>
                <button type="button" class="btn btn-ghost" data-fermer>Annuler</button>
                <button type="submit" class="btn btn-primary" id="bm-renvoi-ok" style="display:none">Renvoyer</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // ── Renvoyer l'invitation : aperçu du destinataire ──
    const echapper = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const personne = p => p && p.email
        ? (p.nom ? '<strong>' + echapper(p.nom) + '</strong> (' + echapper(p.email) + ')' : '<strong>' + echapper(p.email) + '</strong>')
        : '—';
    async function ouvrirRenvoi(b) {
        const m = document.getElementById('bm-modal-renvoi');
        document.getElementById('bm-renvoi-nom').textContent = b.dataset.renvoyer;
        document.getElementById('bm-renvoi-form').action = b.dataset.action;
        document.getElementById('bm-renvoi-chargement').style.display = 'block';
        document.getElementById('bm-renvoi-contenu').style.display = 'none';
        document.getElementById('bm-renvoi-ok').style.display = 'none';
        document.getElementById('bm-renvoi-fiche').style.display = 'none';
        m.style.display = 'flex';

        let a;
        try {
            const r = await fetch(b.dataset.apercu, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) throw new Error(r.status);
            a = await r.json();
        } catch (err) {
            a = { possible: false, cas: 'erreur', motif: 'La fiche du client n\'a pas pu être lue. Rechargez la page et réessayez.' };
        }

        const msg = document.getElementById('bm-renvoi-message');
        const ancien = document.getElementById('bm-renvoi-ancien');
        const note = document.getElementById('bm-renvoi-note');
        const ok = document.getElementById('bm-renvoi-ok');
        const fiche = document.getElementById('bm-renvoi-fiche');
        ancien.innerHTML = ''; note.textContent = '';

        if (a.possible) {
            msg.innerHTML = (a.cas === 'meme_personne'
                ? '✉ Même interlocuteur, <strong>nouvelle adresse</strong> : l\'invitation partira à '
                : '✉ Vous allez envoyer l\'invitation à ') + personne(a.nouveau) + '.';
            ancien.innerHTML = 'Au lieu de ' + personne(a.ancien) + ', qui l\'avait reçue.';
            note.textContent = 'Même lien personnel. La relance suivra cette nouvelle adresse.';
            ok.textContent = 'Renvoyer à ' + (a.nouveau.nom || a.nouveau.email);
            ok.disabled = false;
            ok.style.display = '';
        } else if (a.cas === 'identique') {
            msg.innerHTML = '⚠ La fiche donne toujours ' + personne(a.ancien) + ', la personne qui a déjà reçu l\'invitation.';
            note.textContent = 'Corrigez d\'abord la fiche : retirez l\'ancien interlocuteur, ajoutez le nouveau et cochez-le principal. Puis cliquez de nouveau sur « Renvoyer ».';
        } else {
            msg.innerHTML = '⚠ ' + echapper(a.motif);
            if (a.ancien && a.ancien.email) ancien.innerHTML = 'Invitation reçue par ' + personne(a.ancien) + '.';
        }
        if (!a.possible && a.fiche && ['identique', 'aucune', 'a_choisir', 'doublon'].includes(a.cas)) {
            fiche.href = a.fiche;
            fiche.style.display = '';
        }
        document.getElementById('bm-renvoi-chargement').style.display = 'none';
        document.getElementById('bm-renvoi-contenu').style.display = 'block';
    }
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-renvoyer]');
        if (b) ouvrirRenvoi(b);
    });
    document.getElementById('bm-renvoi-form').addEventListener('submit', () => {
        const ok = document.getElementById('bm-renvoi-ok');
        ok.disabled = true;
        ok.textContent = 'Envoi…';
    });

    // ── Fenêtres ──
    const ouvrir = id => { const m = document.getElementById(id); if (m) m.style.display = 'flex'; };
    const fermer = m => { m.style.display = 'none'; };
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-modal]');
        if (b) { ouvrir(b.dataset.modal); return; }
        const x = e.target.closest('[data-fermer]');
        if (x) { fermer(x.closest('.modal-overlay')); return; }
        if (e.target.classList && e.target.classList.contains('modal-overlay') && e.target.id.startsWith('bm-modal')) fermer(e.target);
        const r = e.target.closest('[data-exclure]');
        if (r) {
            document.getElementById('bm-exclure-nom').textContent = r.dataset.exclure;
            document.getElementById('bm-exclure-form').action = r.dataset.action;
            ouvrir('bm-modal-exclure');
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelectorAll('.modal-overlay[id^="bm-modal"]').forEach(fermer);
    });
    @if($errors->any() || old('date_limite'))
        ouvrir('bm-modal-reglages');
    @endif

    // ── Liste : cartes, recherche, pagination sans recharger la page ──
    const base   = @json(route('admin.barometre.index'));
    const titres = @json($titres);
    const etat   = { filtre: @json($filtre), q: @json($q) };
    const corps  = document.getElementById('bm-lignes');
    let enCours  = null;

    async function charger(page) {
        const p = new URLSearchParams();
        if (etat.filtre !== 'tous') p.set('filtre', etat.filtre);
        if (etat.q) p.set('q', etat.q);
        if (page > 1) p.set('page', page);
        history.replaceState(null, '', base + (p.toString() ? '?' + p : ''));
        p.set('ajax', '1');

        enCours?.abort();
        enCours = new AbortController();
        corps.classList.add('bm-chargement');
        try {
            const r = await fetch(base + '?' + p, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: enCours.signal });
            if (!r.ok) throw new Error(r.status);
            const d = await r.json();
            corps.innerHTML = d.lignes;
            document.getElementById('bm-pagination').innerHTML = d.pagination;
            document.getElementById('bm-total').textContent = d.total;
            document.getElementById('bm-titre').textContent = titres[etat.filtre];
        } catch (err) {
            if (err.name !== 'AbortError') window.location.reload();
        } finally {
            corps.classList.remove('bm-chargement');
        }
    }

    document.querySelectorAll('.bm-cartes [data-filtre]').forEach(carte => carte.addEventListener('click', () => {
        const f = carte.dataset.filtre;
        etat.filtre = (f === 'tous' || etat.filtre === f) ? 'tous' : f;
        document.querySelectorAll('.bm-cartes [data-filtre]').forEach(k =>
            k.classList.toggle('is-active', k.dataset.filtre === etat.filtre && etat.filtre !== 'tous'));
        charger(1);
    }));

    let minuterie;
    document.getElementById('bm-q').addEventListener('input', e => {
        clearTimeout(minuterie);
        minuterie = setTimeout(() => { etat.q = e.target.value.trim(); charger(1); }, 300);
    });

    document.getElementById('bm-pagination').addEventListener('click', e => {
        const a = e.target.closest('a[href]');
        if (!a) return;
        e.preventDefault();
        charger(parseInt(new URL(a.href).searchParams.get('page') || '1', 10));
        document.querySelector('.bm-liste:last-of-type')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
})();
</script>
@endpush
</x-admin-layout>
