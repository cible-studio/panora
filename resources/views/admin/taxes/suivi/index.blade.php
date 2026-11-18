<x-admin-layout>
<x-slot name="title">Suivi des mairies — Exercice {{ $exercice }}</x-slot>

<x-slot:topbarLeft>
    @include('admin.partials._smart_back', ['defaut' => [
        'url'   => route('admin.taxes.index'),
        'label' => 'Taxes communales',
    ]])
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    <a href="{{ route('admin.taxes.suivi.excel', ['exercice' => $exercice]) }}" class="btn btn-ghost btn-sm" title="Tout l'exercice, même présentation que la fiche de la direction">📊 <span class="btn-label">Excel</span></a>
    <a href="{{ route('admin.taxes.suivi.pdf', ['exercice' => $exercice]) }}" class="btn btn-ghost btn-sm" title="Tout l'exercice">📄 <span class="btn-label">PDF</span></a>
</x-slot>

@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $libellesZones = ['abidjan' => 'Abidjan et banlieue', 'interieur' => 'Intérieur', 'tout' => 'Toutes les mairies'];
    $titresFiltres = \App\Http\Controllers\Admin\TaxeSuiviController::FILTRES;
    $tg = $totaux['tout'];
    $mairies = fn (int $n) => $n . ' mairie' . ($n > 1 ? 's' : '');
@endphp

<style>
    .sm-head { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
    .sm-title { font-size:19px; font-weight:800; color:var(--text); }
    .sm-years { display:flex; gap:6px; flex-wrap:wrap; }
    .sm-year { padding:5px 12px; border-radius:20px; border:1px solid var(--border2); color:var(--text2); font-size:12px; font-weight:700; text-decoration:none; }
    .sm-year.on { background:var(--accent); border-color:var(--accent); color:#000; }
    .sm-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:10px; margin-bottom:16px; }
    .sm-kpi { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:12px 14px; text-align:left; }
    .sm-kpi-l { font-size:10px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; display:flex; align-items:center; gap:4px; }
    .sm-kpi-v { font-size:20px; font-weight:800; color:var(--text); font-variant-numeric:tabular-nums; margin-top:3px; }
    .sm-kpi-s { font-size:11px; color:var(--text3); margin-top:2px; }
    /* 2026-11-18 — Cartes filtres : un clic n'affiche que les mairies concernées. */
    .sm-kpi[data-filtre] { cursor:pointer; transition:border-color .15s, transform .15s, box-shadow .15s; }
    .sm-kpi[data-filtre]:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,.10); }
    .sm-kpi[data-filtre]:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
    .sm-kpi.is-active { border-color:var(--accent); box-shadow:0 0 0 2px var(--accent-dim, rgba(245,158,11,.25)); }
    .sm-kpi-f { font-size:10.5px; font-weight:700; color:var(--accent); margin-top:6px; }
    .sm-tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:0; flex-wrap:wrap; }
    .sm-tab { padding:9px 14px; font-size:12.5px; font-weight:700; color:var(--text3); text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-1px; }
    .sm-tab.on { color:var(--accent); border-bottom-color:var(--accent); }
    .sm-filtre-actif { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:9px 14px; font-size:12.5px; color:var(--text2); border-bottom:1px solid var(--border); background:var(--surface2); }
    .sm-filtre-actif strong { color:var(--text); }
    .sm-filtre-actif button { background:none; border:none; color:var(--accent); font-weight:700; cursor:pointer; font-size:12px; padding:2px 6px; }
    .sm-table th, .sm-table td { white-space:nowrap; }
    .sm-table td.num, .sm-table th.num { text-align:right; font-variant-numeric:tabular-nums; }
    .sm-table tfoot td { font-weight:800; border-top:2px solid var(--border2); background:var(--surface2); }
    .sm-col-sel { width:34px; padding-right:0 !important; }
    .sm-sel-zone { display:flex; align-items:center; justify-content:center; padding:6px 4px; cursor:pointer; }
    .sm-muted { color:var(--text3); font-size:11px; }
    .sm-neg { color:#2563eb; }
    .sm-pos { color:#b91c1c; }
    .sm-banner { border-radius:10px; padding:10px 14px; margin-bottom:14px; font-size:12.5px; line-height:1.5; }
    .sm-banner-warn { background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); color:var(--text); }
    .sm-banner-warn button { background:none; border:none; color:var(--accent); font-weight:800; cursor:pointer; font-size:12.5px; padding:0 0 0 6px; }
    .sm-banner-info { background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.25); color:var(--text); }
    #sm-tableau.sm-chargement { opacity:.55; pointer-events:none; transition:opacity .15s; }

    /* Barre de sélection (même présentation que Taxes → Détail par panneau). */
    .sm-bulk { display:none; flex-direction:column; gap:10px; padding:12px 18px; background:var(--accent-dim); border-bottom:1px solid var(--accent); }
    .sm-bulk.visible { display:flex; }
    .sm-bulk-head { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .sm-bulk-clear { background:none; border:none; cursor:pointer; color:var(--text2); font-size:18px; padding:4px 10px; border-radius:6px; line-height:1; }
    .sm-bulk-clear:hover { background:var(--surface); color:#ef4444; }
    .sm-bulk-count { font-size:14px; font-weight:600; color:var(--text); display:inline-flex; align-items:baseline; gap:6px; }
    .sm-bulk-count strong { color:var(--accent); font-size:18px; font-weight:800; }
    .sm-bulk-noms { font-size:12px; color:var(--text3); font-weight:500; white-space:normal; }
    .sm-bulk-body { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
    .sm-bulk-totaux { font-size:13px; color:var(--text2); font-variant-numeric:tabular-nums; }
    .sm-bulk-totaux strong { color:var(--text); }
    .sm-bulk-sep { color:var(--text3); margin:0 4px; }
    .sm-bulk-actions { display:flex; gap:8px; flex-wrap:wrap; margin-left:auto; }
</style>

<div class="sm-head">
    <div class="sm-title">🏛️ Suivi des mairies</div>
    <div class="sm-years">
        @foreach($disponibles as $a)
            <a href="{{ route('admin.taxes.suivi.index', array_filter(['exercice' => $a, 'zone' => $onglet, 'filtre' => $filtre !== 'tous' ? $filtre : null])) }}"
               class="sm-year {{ $a === $exercice ? 'on' : '' }}" data-annee="{{ $a }}">{{ $a }}</a>
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
        <button type="button" data-filtre="a_saisir">Voir ces mairies →</button>
    </div>
@endif

{{-- ══ Cartes : chacune filtre la liste (second clic : toutes) ══ --}}
<div class="sm-kpis" id="sm-cartes">
    <div class="sm-kpi" role="button" tabindex="0" data-filtre="tous" style="border-left:4px solid var(--accent)" title="Afficher toutes les mairies">
        <div class="sm-kpi-l">Total dû
            <x-aide>Arriérés + ODP + TM de l'exercice, toutes mairies. Un clic sur cette carte affiche toutes les mairies.</x-aide>
        </div>
        <div class="sm-kpi-v">{{ $fmt($tg['total_du']) }}</div>
        <div class="sm-kpi-s">dont arriérés {{ $fmt($tg['arrieres']) }}</div>
        <div class="sm-kpi-f">{{ $mairies($nombres['tous']) }}</div>
    </div>
    <div class="sm-kpi {{ $filtre === 'paye' ? 'is-active' : '' }}" role="button" tabindex="0" data-filtre="paye" style="border-left:4px solid #16a34a" title="Afficher : {{ $titresFiltres['paye'] }}">
        <div class="sm-kpi-l">Payé</div>
        <div class="sm-kpi-v" style="color:#15803d">{{ $fmt($tg['total_paye']) }}</div>
        <div class="sm-kpi-s">règlements de l'exercice {{ $exercice }}</div>
        <div class="sm-kpi-f">{{ $mairies($nombres['paye']) }} → voir</div>
    </div>
    <div class="sm-kpi {{ $filtre === 'reste' ? 'is-active' : '' }}" role="button" tabindex="0" data-filtre="reste" style="border-left:4px solid #ef4444" title="Afficher : {{ $titresFiltres['reste'] }}">
        <div class="sm-kpi-l">Reste à payer
            <x-aide>Somme des soldes positifs. Un trop-perçu d'une mairie ne vient pas en déduction d'une autre.</x-aide>
        </div>
        <div class="sm-kpi-v" style="color:#b91c1c">{{ $fmt($tg['reste']) }}</div>
        <div class="sm-kpi-s">soldes positifs</div>
        <div class="sm-kpi-f">{{ $mairies($nombres['reste']) }} → voir</div>
    </div>
    <div class="sm-kpi {{ $filtre === 'exigible' ? 'is-active' : '' }}" role="button" tabindex="0" data-filtre="exigible" style="border-left:4px solid #f59e0b" title="Afficher : {{ $titresFiltres['exigible'] }}">
        <div class="sm-kpi-l">Dû à ce jour
            <x-aide titre="Dû à ce jour">Ce qu'une mairie peut réclamer aujourd'hui : arriérés, plus l'ODP et la TM des trimestres déjà commencés, moins ce qui est payé.</x-aide>
        </div>
        <div class="sm-kpi-v" style="color:#b45309">{{ $fmt($tg['exigible']) }}</div>
        <div class="sm-kpi-s">au {{ now()->format('d/m/Y') }}</div>
        <div class="sm-kpi-f">{{ $mairies($nombres['exigible']) }} → voir</div>
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
        <div class="sm-kpi {{ $filtre === 'a_venir' ? 'is-active' : '' }}" role="button" tabindex="0" data-filtre="a_venir" style="border-left:4px solid #a855f7" title="Afficher : {{ $titresFiltres['a_venir'] }}">
            <div class="sm-kpi-l">À venir
                <x-aide>TM des campagnes planifiées qui n'ont pas encore démarré. Hors solde : elle y entre quand la campagne démarre.</x-aide>
            </div>
            <div class="sm-kpi-v" style="color:#7e22ce">{{ $fmt($tg['prevu_tm']) }}</div>
            <div class="sm-kpi-s">TM des campagnes planifiées</div>
            <div class="sm-kpi-f">{{ $mairies($nombres['a_venir']) }} → voir</div>
        </div>
    @endif
</div>

<div class="card">
    <div class="sm-tabs" style="padding:0 12px">
        @foreach(['abidjan', 'interieur', 'tout'] as $z)
            <a href="{{ route('admin.taxes.suivi.index', array_filter(['exercice' => $exercice, 'zone' => $z, 'filtre' => $filtre !== 'tous' ? $filtre : null])) }}"
               class="sm-tab {{ $onglet === $z ? 'on' : '' }}" data-zone="{{ $z }}">
                {{ $libellesZones[$z] }}
                <span class="sm-muted">· <span data-compte-zone="{{ $z }}">{{ $onglets[$z] }}</span></span>
            </a>
        @endforeach
    </div>

    <div class="sm-filtre-actif" id="sm-filtre-actif" @if($filtre === 'tous') hidden @endif>
        Liste : <strong id="sm-filtre-titre">{{ $titresFiltres[$filtre] }}</strong>
        <button type="button" data-filtre="tous">✕ Toutes les mairies</button>
    </div>

    {{-- ══ Barre de sélection : exports des seules mairies cochées ══ --}}
    <div id="sm-bulk" class="sm-bulk" role="region" aria-label="Mairies sélectionnées">
        <div class="sm-bulk-head">
            <button type="button" id="sm-bulk-clear" class="sm-bulk-clear" title="Tout désélectionner">✕</button>
            <span class="sm-bulk-count"><strong id="sm-bulk-n">0</strong> <span id="sm-bulk-nom">mairie sélectionnée</span></span>
            <span class="sm-bulk-noms" id="sm-bulk-noms"></span>
        </div>
        <div class="sm-bulk-body">
            <div class="sm-bulk-totaux">
                Total dû <strong id="sm-sel-du">0</strong>
                <span class="sm-bulk-sep">·</span> Payé <strong id="sm-sel-paye">0</strong>
                <span class="sm-bulk-sep">·</span> Solde <strong id="sm-sel-solde">0</strong> FCFA
            </div>
            <div class="sm-bulk-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-export="{{ route('admin.taxes.suivi.excel') }}">📊 Excel de la sélection</button>
                <button type="button" class="btn btn-primary btn-sm keep-label" data-export="{{ route('admin.taxes.suivi.pdf') }}">📄 PDF de la sélection</button>
            </div>
        </div>
    </div>

    <div class="table-wrap" id="sm-tableau">
        @include('admin.taxes.suivi.partials.tableau')
    </div>
</div>

<div class="sm-banner sm-banner-info">
    ℹ Clique sur une carte pour n'afficher que les mairies concernées, coche des mairies pour sortir
    leur PDF ou leur Excel, ou clique sur une mairie pour voir sa base, le détail par trimestre,
    enregistrer un règlement{{ $reprise ? ', saisir ce qu\'elle a réclamé' : '' }} ou sortir son relevé PDF.
</div>

{{-- Exports de la sélection : envoyés en POST (la liste des mairies part avec). --}}
<form id="sm-export" method="POST" action="" style="display:none">
    @csrf
    <input type="hidden" name="exercice" value="{{ $exercice }}">
</form>

@include('admin.partials.ligne-cliquable')

@push('scripts')
<script>
// Suivi des mairies (2026-11-18) — cartes filtres et onglets sans recharger
// la page, sélection de mairies pour les exports (garde la sélection d'un
// filtre à l'autre, le temps de la session, pour cet exercice).
(function () {
    const base   = @json(route('admin.taxes.suivi.index'));
    const titres = @json($titresFiltres);
    const etat   = { exercice: @json($exercice), zone: @json($onglet), filtre: @json($filtre) };
    const conteneur = document.getElementById('sm-tableau');
    const barre  = document.getElementById('sm-bulk');
    const cle    = 'suivi-mairies-selection-' + etat.exercice;
    const fmt    = n => Math.round(n).toLocaleString('fr-FR');

    let sel = {};
    try { sel = JSON.parse(sessionStorage.getItem(cle) || '{}') || {}; } catch (e) { sel = {}; }
    const garder = () => { try { sessionStorage.setItem(cle, JSON.stringify(sel)); } catch (e) {} };
    const cases  = () => [...conteneur.querySelectorAll('input.sm-sel')];

    function majBarre() {
        const ids = Object.keys(sel);
        const n = ids.length;
        barre.classList.toggle('visible', n > 0);
        let du = 0, paye = 0, solde = 0;
        ids.forEach(id => { du += sel[id].du; paye += sel[id].paye; solde += sel[id].solde; });
        document.getElementById('sm-bulk-n').textContent = n;
        document.getElementById('sm-bulk-nom').textContent = n > 1 ? 'mairies sélectionnées' : 'mairie sélectionnée';
        const noms = ids.map(id => sel[id].nom).sort();
        document.getElementById('sm-bulk-noms').textContent = '— ' + noms.slice(0, 6).join(', ') + (noms.length > 6 ? ' et ' + (noms.length - 6) + ' autre(s)' : '');
        document.getElementById('sm-sel-du').textContent = fmt(du);
        document.getElementById('sm-sel-paye').textContent = fmt(paye);
        document.getElementById('sm-sel-solde').textContent = fmt(solde);
        const tableau = document.getElementById('sm-sel-tableau');
        if (tableau) {
            const visibles = cases();
            const cochees = visibles.filter(c => sel[c.dataset.id]).length;
            tableau.checked = visibles.length > 0 && cochees === visibles.length;
            tableau.indeterminate = cochees > 0 && cochees < visibles.length;
        }
    }
    function synchroniser() {
        cases().forEach(c => { c.checked = !!sel[c.dataset.id]; });
        majBarre();
    }
    function basculer(c, coche) {
        if (coche) {
            sel[c.dataset.id] = { nom: c.dataset.nom, du: +c.dataset.du, paye: +c.dataset.paye, solde: +c.dataset.solde };
        } else {
            delete sel[c.dataset.id];
        }
    }

    conteneur.addEventListener('change', e => {
        if (e.target.matches('input.sm-sel')) {
            basculer(e.target, e.target.checked);
        } else if (e.target.id === 'sm-sel-tableau') {
            cases().forEach(c => basculer(c, e.target.checked));
        } else {
            return;
        }
        garder();
        synchroniser();
    });
    document.getElementById('sm-bulk-clear').addEventListener('click', () => { sel = {}; garder(); synchroniser(); });

    // Exports de la sélection.
    const formulaire = document.getElementById('sm-export');
    barre.querySelectorAll('[data-export]').forEach(b => b.addEventListener('click', () => {
        formulaire.querySelectorAll('input[name="communes[]"]').forEach(i => i.remove());
        Object.keys(sel).forEach(id => {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = 'communes[]'; i.value = id;
            formulaire.appendChild(i);
        });
        formulaire.action = b.dataset.export;
        formulaire.submit();
    }));

    // Liste : cartes et onglets, sans recharger la page.
    let enCours = null;
    function adresse(ajax) {
        const p = new URLSearchParams({ exercice: etat.exercice, zone: etat.zone });
        if (etat.filtre !== 'tous') p.set('filtre', etat.filtre);
        if (ajax) p.set('ajax', '1');
        return base + '?' + p;
    }
    async function charger() {
        history.replaceState(null, '', adresse(false));
        document.querySelectorAll('.sm-year[data-annee]').forEach(a => {
            const u = new URL(a.href);
            u.searchParams.set('zone', etat.zone);
            if (etat.filtre !== 'tous') u.searchParams.set('filtre', etat.filtre); else u.searchParams.delete('filtre');
            a.href = u.toString();
        });
        document.querySelectorAll('#sm-cartes [data-filtre]').forEach(k =>
            k.classList.toggle('is-active', k.dataset.filtre === etat.filtre && etat.filtre !== 'tous'));
        document.querySelectorAll('.sm-tab[data-zone]').forEach(t => t.classList.toggle('on', t.dataset.zone === etat.zone));
        document.getElementById('sm-filtre-actif').hidden = etat.filtre === 'tous';
        document.getElementById('sm-filtre-titre').textContent = titres[etat.filtre];

        enCours?.abort();
        enCours = new AbortController();
        conteneur.classList.add('sm-chargement');
        try {
            const r = await fetch(adresse(true), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: enCours.signal });
            if (!r.ok) throw new Error(r.status);
            const d = await r.json();
            conteneur.innerHTML = d.tableau;
            Object.entries(d.onglets).forEach(([z, n]) => {
                const s = document.querySelector('[data-compte-zone="' + z + '"]');
                if (s) s.textContent = n;
            });
            synchroniser();
        } catch (err) {
            if (err.name !== 'AbortError') window.location.href = adresse(false);
        } finally {
            conteneur.classList.remove('sm-chargement');
        }
    }
    function filtrer(f) {
        etat.filtre = (f === 'tous' || etat.filtre === f) ? 'tous' : f;
        charger();
    }
    document.querySelectorAll('[data-filtre]').forEach(el => {
        el.addEventListener('click', e => {
            if (e.target.closest('.px-aide')) return; // l'icône ⓘ garde son rôle
            filtrer(el.dataset.filtre);
        });
        if (el.getAttribute('role') === 'button') {
            el.addEventListener('keydown', e => {
                if (e.target !== el || (e.key !== 'Enter' && e.key !== ' ')) return;
                e.preventDefault();
                filtrer(el.dataset.filtre);
            });
        }
    });
    document.querySelectorAll('.sm-tab[data-zone]').forEach(t => t.addEventListener('click', e => {
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
        e.preventDefault();
        etat.zone = t.dataset.zone;
        charger();
    }));

    synchroniser();
})();
</script>
@endpush
</x-admin-layout>
