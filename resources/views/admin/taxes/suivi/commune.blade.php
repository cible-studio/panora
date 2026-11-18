<x-admin-layout>
<x-slot name="title">{{ $commune->name }} — Suivi des taxes {{ $exercice }}</x-slot>

<x-slot:topbarLeft>
    @include('admin.partials._smart_back', ['defaut' => [
        'url'   => route('admin.taxes.suivi.index', ['exercice' => $exercice, 'zone' => $ligne['is_abidjan'] ? 'abidjan' : 'interieur']),
        'label' => 'Suivi des mairies',
    ]])
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    {{-- 2026-11-18 — Deux versions du relevé : celle à remettre à la mairie,
         et la version interne (contrôle Panora, versements annulés, qui a saisi). --}}
    <a href="{{ route('admin.taxes.suivi.releve', ['commune' => $commune, 'exercice' => $exercice, 'version' => 'interne']) }}" class="btn btn-ghost btn-sm"
       title="Pour la régie : avec le contrôle Panora, les versements annulés et qui a saisi">📄 <span class="btn-label">Relevé interne</span></a>
    <a href="{{ route('admin.taxes.suivi.releve', ['commune' => $commune, 'exercice' => $exercice]) }}" class="btn btn-primary btn-sm"
       title="À remettre à la mairie quand elle se présente">📄 Relevé pour la mairie</a>
</x-slot>

@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    // Libellés des statuts : source unique dans TaxeExerciceService.
    $statuts = \App\Services\Taxes\TaxeExerciceService::LIBELLES_STATUT;
    $badge = function (array $l) use ($statuts) {
        [$classe, $libelle, $aide] = $statuts[\App\Services\Taxes\TaxeExerciceService::statutAffiche($l)];
        return '<span class="badge ' . $classe . '" title="' . e($aide) . '">' . e($libelle) . '</span>';
    };
    $saisie = $ligne['saisie'];
    $anneeCourante = (int) now()->year;
    $exerciceDefaut = in_array($anneeCourante, $disponibles, true) ? $anneeCourante : $exercice;
@endphp

<style>
    .sf-hero { display:flex; align-items:center; gap:14px; flex-wrap:wrap; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px 20px; margin-bottom:14px; }
    .sf-hero-t { font-size:20px; font-weight:800; color:var(--text); }
    .sf-hero-s { font-size:12px; color:var(--text3); margin-top:3px; }
    .sf-years { display:flex; gap:6px; flex-wrap:wrap; margin-left:auto; }
    .sf-year { padding:5px 12px; border-radius:20px; border:1px solid var(--border2); color:var(--text2); font-size:12px; font-weight:700; text-decoration:none; }
    .sf-year.on { background:var(--accent); border-color:var(--accent); color:#000; }
    .sf-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:14px; }
    .sf-kpi { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:11px 14px; }
    .sf-kpi-l { font-size:10px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; }
    .sf-kpi-v { font-size:19px; font-weight:800; font-variant-numeric:tabular-nums; margin-top:3px; color:var(--text); }
    .sf-grid { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); gap:14px; }
    @media (max-width: 900px) { .sf-grid { grid-template-columns:1fr; } }
    .sf-table td.num, .sf-table th.num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .sf-table tr.tot td { font-weight:800; background:var(--surface2); }
    .sf-muted { color:var(--text3); font-size:11px; }
    .sf-annule td { text-decoration:line-through; color:var(--text3); }
    .sf-annule td.sf-keep { text-decoration:none; }
    .sf-form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:0 12px; }
    .sf-errors { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.3); border-radius:10px; padding:10px 14px; margin-bottom:14px; font-size:12.5px; color:#b91c1c; }
    .sf-note { font-size:12px; color:var(--text3); line-height:1.5; }
    /* 2026-11-18 — Montant en clair sous le champ (« 1 342 000 FCFA ») : un zéro oublié se voit. */
    .sf-clair { display:block; min-height:15px; margin-top:3px; font-size:11.5px; font-weight:700; color:var(--text2); font-variant-numeric:tabular-nums; }
    details.sf-annuler summary { cursor:pointer; color:#b91c1c; font-size:11.5px; font-weight:700; list-style:none; }
    details.sf-annuler summary::-webkit-details-marker { display:none; }
</style>

<div class="sf-hero">
    <div style="font-size:30px">🏛️</div>
    <div>
        <div class="sf-hero-t">{{ $commune->name }}</div>
        <div class="sf-hero-s">
            {{ $ligne['is_abidjan'] ? 'Abidjan et banlieue' : 'Intérieur' }} ·
            Tarifs Panora aujourd'hui : ODP <strong>{{ $fmt($rates['odp'] ?? 0) }}</strong> · TM <strong>{{ $fmt($rates['tm'] ?? 0) }}</strong> FCFA/m²/mois
            @if(isset($ratesEclaire) && $ratesEclaire !== $rates)
                · 💡 panneaux éclairés : ODP <strong>{{ $fmt($ratesEclaire['odp']) }}</strong> · TM <strong>{{ $fmt($ratesEclaire['tm']) }}</strong>
            @endif
            @if(\App\Support\Acces::peut('tarifs_communes'))
                · <a href="{{ route('admin.settings.communes.tariffs') }}" style="color:var(--accent)">modifier les tarifs</a>
            @endif
        </div>
    </div>
    <div class="sf-years">
        @foreach($disponibles as $a)
            <a href="{{ route('admin.taxes.suivi.commune', ['commune' => $commune, 'exercice' => $a]) }}" class="sf-year {{ $a === $exercice ? 'on' : '' }}">{{ $a }}</a>
        @endforeach
    </div>
</div>

@if($errors->any())
    <div class="sf-errors">
        @foreach($errors->all() as $e)<div>✕ {{ $e }}</div>@endforeach
    </div>
@endif

<div class="sf-kpis">
    <div class="sf-kpi"><div class="sf-kpi-l">Arriérés</div><div class="sf-kpi-v">{{ $fmt($ligne['arrieres']) }}</div></div>
    <div class="sf-kpi"><div class="sf-kpi-l">Total dû {{ $exercice }}</div><div class="sf-kpi-v" style="color:var(--accent)">{{ $fmt($ligne['total_du']) }}</div></div>
    <div class="sf-kpi"><div class="sf-kpi-l">Payé</div><div class="sf-kpi-v" style="color:#15803d">{{ $fmt($ligne['total_paye']) }}</div></div>
    <div class="sf-kpi">
        <div class="sf-kpi-l">Solde</div>
        <div class="sf-kpi-v" style="color:{{ $ligne['solde'] > 0 ? '#b91c1c' : ($ligne['solde'] < 0 ? '#2563eb' : '#15803d') }}">{{ $fmt($ligne['solde']) }}</div>
        <div style="margin-top:4px">{!! $badge($ligne) !!}</div>
    </div>
    <div class="sf-kpi">
        <div class="sf-kpi-l">Dû à ce jour
            <x-aide>Arriérés, plus l'ODP et la TM des trimestres déjà commencés, moins ce qui est payé.</x-aide>
        </div>
        <div class="sf-kpi-v" style="color:#b45309">{{ $fmt($ligne['exigible']) }}</div>
    </div>
</div>

@if($reprise)
{{-- ═══ EXERCICE DE REPRISE : ce que la mairie a réclamé ═══ --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">✍️ Ce que la mairie a réclamé pour {{ $exercice }}
            <x-aide titre="Année de reprise">
                Pour {{ $exercice }}, c'est ce montant qui fait foi : saisis-le depuis l'avis ou la
                réclamation de la mairie, arriérés des années passées compris. Le calcul de Panora,
                à droite, sert de contrôle. Le solde au 31/12/{{ $exercice }} deviendra l'arriéré de {{ $exercice + 1 }}.
            </x-aide>
        </div>
        @if($saisie)
            <span class="sf-muted">Saisi par {{ $saisie->saisiPar?->name ?? '—' }} le {{ $saisie->updated_at?->format('d/m/Y à H:i') }}</span>
        @else
            <span class="badge badge-orange">À saisir</span>
        @endif
    </div>
    <div class="card-body">
        <div class="sf-grid">
            <form method="POST" action="{{ route('admin.taxes.suivi.reprise', $commune) }}">
                @csrf @method('PUT')
                <input type="hidden" name="exercice" value="{{ $exercice }}">
                <div class="sf-form-grid">
                    <div class="mfg"><label>Arriérés (années passées)</label>
                        <input type="number" data-clair name="arrieres" min="0" step="1" required value="{{ old('arrieres', $saisie?->arrieres ?? 0) }}"></div>
                    <div class="mfg"><label>ODP {{ $exercice }}</label>
                        <input type="number" data-clair name="odp" min="0" step="1" required value="{{ old('odp', $saisie?->odp ?? 0) }}"></div>
                </div>
                <div class="sf-form-grid">
                    @foreach([1, 2, 3, 4] as $q)
                        <div class="mfg"><label>TM {{ $q === 1 ? '1er' : $q . 'e' }} trim.</label>
                            <input type="number" data-clair name="tm_t{{ $q }}" min="0" step="1" required value="{{ old('tm_t' . $q, $saisie?->{'tm_t' . $q} ?? 0) }}"></div>
                    @endforeach
                </div>
                <div class="mfg"><label>Commentaire (référence de l'avis, contact à la mairie…)</label>
                    <textarea name="commentaire" maxlength="2000">{{ old('commentaire', $saisie?->commentaire) }}</textarea></div>
                <button type="submit" class="btn btn-primary btn-sm">💾 Enregistrer les montants réclamés</button>
            </form>

            <div>
                <div class="section-label" style="margin-top:0">Calcul Panora {{ $exercice }} — contrôle</div>
                <table class="sf-table">
                    <tr><td>ODP</td><td class="num">{{ $fmt($ligne['controle_odp']) }}</td></tr>
                    <tr><td>TM</td><td class="num">{{ $fmt($ligne['controle_tm']) }}</td></tr>
                    <tr class="tot"><td>Total calculé</td><td class="num">{{ $fmt($ligne['controle_total']) }}</td></tr>
                    @if($saisie)
                        @php $reclame = $ligne['odp'] + $ligne['tm']; $ecart = $reclame - $ligne['controle_total']; @endphp
                        {{-- 18/11 — Libellé précisé : c'est la somme ODP + TM réclamées, détail affiché. --}}
                        <tr><td>Réclamé {{ $exercice }} : ODP + TM <span class="sf-muted">(sans les arriérés)</span>
                                <div class="sf-muted">ODP {{ $fmt($ligne['odp']) }} + TM {{ $fmt($ligne['tm']) }}</div></td>
                            <td class="num">{{ $fmt($reclame) }}</td></tr>
                        {{-- 18/11 — Écart précisé : l'opération faite, l'écart ODP et l'écart TM
                             (un écart total peut cacher deux écarts de sens contraire), et son sens. --}}
                        @php
                            $signe    = fn ($n) => ($n > 0 ? '+' : '') . $fmt($n);
                            $ecartOdp = $ligne['odp'] - $ligne['controle_odp'];
                            $ecartTm  = $ligne['tm'] - $ligne['controle_tm'];
                        @endphp
                        <tr><td>Écart : réclamé − calcul Panora
                                <div class="sf-muted">{{ $fmt($reclame) }} − {{ $fmt($ligne['controle_total']) }} · dont ODP {{ $signe($ecartOdp) }} · TM {{ $signe($ecartTm) }}</div>
                                <div class="sf-muted">@if($ecart < 0)La mairie réclame {{ $fmt(-$ecart) }} de moins que le calcul.@elseif($ecart > 0)La mairie réclame {{ $fmt($ecart) }} de plus que le calcul.@else Réclamé et calcul identiques.@endif</div></td>
                            <td class="num" style="color:{{ $ecart > 0 ? '#b91c1c' : '#2563eb' }}">{{ $signe($ecart) }}</td></tr>
                    @endif
                </table>
                <p class="sf-note" style="margin-top:8px">Un gros écart signale un panneau absent de Panora, un tarif à revoir, ou une réclamation à discuter avec la mairie.</p>
            </div>
        </div>
    </div>
</div>
@endif

<div class="sf-grid">
    {{-- ═══ L'EXERCICE, comme la fiche de la direction ═══ --}}
    <div class="card">
        <div class="card-header"><div class="card-title">📋 Exercice {{ $exercice }}</div>
            <span class="sf-muted">{{ $reprise ? 'montants réclamés par la mairie' : 'calcul Panora' }}</span></div>
        <div class="table-wrap">
            <table class="sf-table">
                <thead><tr><th></th><th class="num">ODP</th><th class="num">TM</th><th class="num">Total</th>
                    @unless($reprise)<th class="num">À venir
                        <x-aide>TM des campagnes planifiées, pas encore démarrées. Hors solde.</x-aide></th>@endunless
                </tr></thead>
                <tbody>
                    <tr><td>Arriérés {{ $reprise ? '(réclamés)' : '(solde ' . ($exercice - 1) . ')' }}</td><td></td><td></td><td class="num">{{ $fmt($ligne['arrieres']) }}</td>@unless($reprise)<td></td>@endunless</tr>
                    @foreach([1, 2, 3, 4] as $q)
                        <tr>
                            <td>{{ $q === 1 ? '1er' : $q . 'e' }} trimestre</td>
                            <td class="num">{{ $ligne['odp_trim'][$q] ? $fmt($ligne['odp_trim'][$q]) : '—' }}</td>
                            <td class="num">{{ $ligne['tm_trim'][$q] ? $fmt($ligne['tm_trim'][$q]) : '—' }}</td>
                            <td class="num">{{ $fmt($ligne['odp_trim'][$q] + $ligne['tm_trim'][$q]) }}</td>
                            @unless($reprise)<td class="num sf-muted">{{ $ligne['prevu_trim'][$q] ? $fmt($ligne['prevu_trim'][$q]) : '—' }}</td>@endunless
                        </tr>
                    @endforeach
                    <tr class="tot"><td>Total dû</td><td class="num">{{ $fmt($ligne['odp']) }}</td><td class="num">{{ $fmt($ligne['tm']) }}</td><td class="num">{{ $fmt($ligne['total_du']) }}</td>@unless($reprise)<td class="num sf-muted">{{ $fmt($ligne['prevu_tm']) }}</td>@endunless</tr>
                    <tr><td>Règlements ({{ $ligne['nb_reglements'] }})</td><td></td><td></td><td class="num" style="color:#15803d">− {{ $fmt($ligne['total_paye']) }}</td>@unless($reprise)<td></td>@endunless</tr>
                    <tr class="tot"><td>Solde</td><td></td><td></td><td class="num">{{ $fmt($ligne['solde']) }}</td>@unless($reprise)<td></td>@endunless</tr>
                </tbody>
            </table>
        </div>
        @if($reprise)
            <div class="card-body sf-note">L'ODP réclamée est annuelle, comme dans la fiche : elle figure au 1er trimestre.</div>
        @endif
    </div>

    {{-- ═══ BASE : dimensions, panneaux, faces ═══ --}}
    <div class="card">
        <div class="card-header"><div class="card-title">📐 Base {{ $exercice }}</div>
            <a href="{{ route('admin.taxes.details', ['commune_id' => $commune->id, 'year' => $exercice, 'period_type' => 'annuel', 'period_value' => 0, 'inclure_lumipub' => \App\Services\Taxes\TaxeExerciceService::inclureLumipub() ? 1 : 0]) }}"
               class="btn btn-ghost btn-sm">Détail par panneau →</a></div>
        <div class="table-wrap">
            <table class="sf-table">
                <thead><tr><th>Dim. d'une face</th><th class="num">Panneaux</th><th class="num">Faces</th></tr></thead>
                <tbody>
                @forelse($ligne['base'] as $dim => $b)
                    <tr><td>{{ $dim }} m²</td><td class="num">{{ $b['panneaux'] }}</td><td class="num">{{ $b['faces'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="sf-muted" style="text-align:center;padding:18px">Aucun panneau Panora taxé sur cet exercice.</td></tr>
                @endforelse
                @if(!empty($ligne['base']))
                    <tr class="tot"><td>Total</td><td class="num">{{ $ligne['nb_panneaux'] }}</td><td class="num">{{ $ligne['nb_faces'] }}</td></tr>
                @endif
                </tbody>
            </table>
        </div>
        <div class="card-body sf-note">Un panneau = un mât planté (les deux faces d'un double-face comptent pour un panneau). Pieds Lumipub non comptés : leur ODP / TM se gère à part.</div>
    </div>
</div>

{{-- ═══ RÈGLEMENTS ═══ --}}
<div class="card" id="reglements">
    <div class="card-header">
        <div class="card-title">💰 Règlements de l'exercice {{ $exercice }}
            <x-aide titre="Règlements">
                Un versement = une ligne, qui ne s'efface jamais. En cas d'erreur, annule-la avec un
                motif : elle reste visible, barrée, et sort des totaux. Un versement de janvier peut
                régler l'exercice précédent : choisis l'exercice qu'il règle.
            </x-aide>
        </div>
        <span class="sf-muted">{{ $ligne['nb_reglements'] }} versement{{ $ligne['nb_reglements'] > 1 ? 's' : '' }} · {{ $fmt($ligne['total_paye']) }} FCFA</span>
    </div>
    <div class="table-wrap">
        <table class="sf-table">
            <thead><tr><th>Versé le</th><th class="num">Montant</th><th>Objet</th><th>Mode</th><th>Référence</th><th>Quittance</th><th>Saisi par</th><th></th></tr></thead>
            <tbody>
            @forelse($reglements as $r)
                <tr class="{{ $r->estAnnule() ? 'sf-annule' : '' }}">
                    <td>{{ $r->paid_at?->format('d/m/Y') }}</td>
                    <td class="num"><strong>{{ $fmt($r->montant) }}</strong></td>
                    <td>{{ $r->objet_label }}</td>
                    <td>{{ $r->mode_label ?? '—' }}</td>
                    <td>{{ $r->reference ?: '—' }}
                        @if($r->commentaire)<div class="sf-muted" style="white-space:pre-line;max-width:280px">{{ $r->commentaire }}</div>@endif</td>
                    <td class="sf-keep">
                        @if($r->quittance_path)
                            <a href="{{ route('admin.taxes.suivi.reglements.quittance', $r) }}" target="_blank" rel="noopener" style="color:var(--accent)">📎 Voir</a>
                        @else — @endif
                    </td>
                    <td class="sf-muted">{{ $r->saisiPar?->name ?? ($r->origine !== 'saisie' ? 'reprise' : '—') }}</td>
                    <td class="sf-keep">
                        @if($r->estAnnule())
                            <span class="badge badge-gray" title="{{ $r->motif_annulation }}">Annulé le {{ $r->annule_le->format('d/m/Y') }}</span>
                            <div class="sf-muted">{{ $r->annulePar?->name }} — {{ $r->motif_annulation }}</div>
                        @else
                            <details class="sf-annuler">
                                <summary>Annuler…</summary>
                                <form method="POST" action="{{ route('admin.taxes.suivi.reglements.annuler', $r) }}" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
                                    @csrf
                                    <input type="text" name="motif" required minlength="3" maxlength="500" placeholder="Motif (obligatoire)" class="filter-input" style="height:30px;font-size:12px;min-width:180px">
                                    <button type="submit" class="btn btn-danger btn-sm">Confirmer</button>
                                </form>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="sf-muted" style="text-align:center;padding:20px">Aucun règlement saisi pour {{ $exercice }}.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-body" style="border-top:1px solid var(--border)">
        <div class="section-label" style="margin-top:0">Nouveau règlement</div>
        <form method="POST" action="{{ route('admin.taxes.suivi.reglements.store', $commune) }}" enctype="multipart/form-data">
            @csrf
            <div class="sf-form-grid">
                <div class="mfg"><label>Exercice réglé</label>
                    <select name="exercice" required>
                        @foreach($disponibles as $a)
                            <option value="{{ $a }}" {{ (int) old('exercice', $exercice) === $a ? 'selected' : '' }}>{{ $a }}</option>
                        @endforeach
                    </select></div>
                <div class="mfg"><label>Versé le</label>
                    <input type="date" name="paid_at" required max="{{ now()->toDateString() }}" value="{{ old('paid_at', now()->toDateString()) }}"></div>
                <div class="mfg"><label>Montant (FCFA)</label>
                    <input type="number" data-clair name="montant" required min="1" step="1" value="{{ old('montant') }}"></div>
                <div class="mfg"><label>Objet</label>
                    <select name="objet" required>
                        @foreach(\App\Models\TaxeReglement::OBJETS as $k => $lib)
                            <option value="{{ $k }}" {{ old('objet', 'global') === $k ? 'selected' : '' }}>{{ $lib }}</option>
                        @endforeach
                    </select></div>
                <div class="mfg"><label>Mode</label>
                    <select name="mode">
                        <option value="">—</option>
                        @foreach(\App\Models\TaxeReglement::MODES as $k => $lib)
                            <option value="{{ $k }}" {{ old('mode') === $k ? 'selected' : '' }}>{{ $lib }}</option>
                        @endforeach
                    </select></div>
                <div class="mfg"><label>Référence (quittance, chèque…)</label>
                    <input type="text" name="reference" maxlength="100" value="{{ old('reference') }}"></div>
            </div>
            <div class="sf-form-grid">
                <div class="mfg"><label>Quittance (PDF ou photo, 10 Mo max)</label>
                    <input type="file" name="quittance" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
                <div class="mfg" style="grid-column:span 2"><label>Commentaire</label>
                    <textarea name="commentaire" maxlength="2000" style="min-height:42px">{{ old('commentaire') }}</textarea></div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">💾 Enregistrer le règlement</button>
        </form>
    </div>
</div>

{{-- ═══ CHAÎNE DES ARRIÉRÉS ═══ --}}
@if($chaine->count() > 1)
<div class="card">
    <div class="card-header"><div class="card-title">🔗 D'un exercice à l'autre
        <x-aide>Le solde d'un exercice devient l'arriéré du suivant. Un trop-perçu vient en déduction.</x-aide></div></div>
    <div class="table-wrap">
        <table class="sf-table">
            <thead><tr><th>Exercice</th><th class="num">Arriérés reçus</th><th class="num">ODP + TM</th><th class="num">Total dû</th><th class="num">Payé</th><th class="num">Solde reporté</th></tr></thead>
            <tbody>
            @foreach($chaine as $e)
                <tr>
                    <td><a href="{{ route('admin.taxes.suivi.commune', ['commune' => $commune, 'exercice' => $e['exercice']]) }}" style="color:var(--accent)">{{ $e['exercice'] }}</a>
                        @if($e['mode'] === 'reprise')<span class="sf-muted">· reprise</span>@endif</td>
                    <td class="num">{{ $fmt($e['arrieres']) }}</td>
                    <td class="num">{{ $fmt($e['odp'] + $e['tm']) }}</td>
                    <td class="num">{{ $fmt($e['total_du']) }}</td>
                    <td class="num" style="color:#15803d">{{ $fmt($e['total_paye']) }}</td>
                    <td class="num"><strong>{{ $fmt($e['solde']) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@push('scripts')
<script>
// 2026-11-18 — Montant en clair sous chaque champ de montant (« 1 342 000 FCFA ») :
// les champs affichent « 1342000 », un zéro oublié passait inaperçu.
// Affichage seul : rien n'est calculé ni envoyé.
(function () {
    var grouper = function (chiffres) { return chiffres.replace(/\B(?=(\d{3})+(?!\d))/g, ' '); };
    document.querySelectorAll('input[data-clair]').forEach(function (champ) {
        var clair = document.createElement('span');
        clair.className = 'sf-clair';
        clair.setAttribute('aria-live', 'polite');
        champ.insertAdjacentElement('afterend', clair);
        var maj = function () {
            // Valeur lue comme un nombre (« 1e5 » = 100 000) ; vide ou invalide : rien.
            var n = champ.value === '' ? NaN : Math.trunc(Number(champ.value));
            clair.textContent = isFinite(n) ? (n < 0 ? '−' : '') + grouper(String(Math.abs(n))) + ' FCFA' : '';
        };
        champ.addEventListener('input', maj);
        maj();
    });
})();
</script>
@endpush
</x-admin-layout>
