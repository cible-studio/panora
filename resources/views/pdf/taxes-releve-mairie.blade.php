<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Relevé des taxes — {{ $commune->name }} — {{ $exercice }}</title>
    {{-- Suivi des mairies (2026-11-16) — le relevé d'une mairie. Chiffres :
         TaxeExerciceService (aucun calcul ici, sauf l'écart affiché du
         contrôle interne, identique à la fiche à l'écran).

         Refonte du 2026-11-18 (« on ne voit pas où sont les versements, les
         arriérés… trop basique ») : un récapitulatif en tête, puis des
         sections titrées — ce qui est dû, les versements reçus, le solde
         posé en opération, la base de calcul. Deux versions :
           · mairie (défaut) : le document à lui remettre ;
           · interne ($interne) : en plus, le calcul Panora de contrôle
             (année de reprise), qui a saisi quoi, les versements annulés
             et leur motif. --}}
    <style>@page { size: A4 portrait; margin: 12mm 14mm 20mm 14mm !important; }</style>
    @include('pdf.partials.tax-doc-styles')
    {{-- Bloc @php…@endphp, pas la forme courte : mêlée à un bloc plus bas,
         elle ferait avaler à Blade tout ce qui les sépare. --}}
    @php $chR = \App\Support\PdfCharte::data(); @endphp
    <style>
        /* Un relevé aux nombreux versements passe sur deux pages : aucune
           ligne coupée, un total jamais seul en haut de page. */
        .tdoc-table tr { page-break-inside:avoid; }
        .tdoc-table tr.tdoc-total { page-break-before:avoid; }
        .tdoc-table { font-size:10px; }
        .tdoc-table tbody td { padding-top:5px; padding-bottom:5px; }
        .tdoc-total td { font-size:11px; padding-top:8px; padding-bottom:8px; }
        .tdoc-total .tdoc-amount { font-size:12px; }
        .tdoc-total .tdoc-amount span { display:inline; font-size:9.5px; margin-left:4px; }

        /* Titres de section numérotés */
        .rl-sec { font-family:{!! $chR['ff_titres'] !!}; font-size:10.5px; font-weight:700; letter-spacing:.6px;
                  text-transform:uppercase; color:{{ $chR['noir'] }}; margin:15px 0 5px; page-break-after:avoid; }
        .rl-sec .n { color:{{ $chR['rouge'] }}; margin-right:5px; }
        .rl-sec .d { font-family:{!! $chR['ff_texte'] !!}; font-size:8.5px; font-weight:400; letter-spacing:0;
                     text-transform:none; color:{{ $chR['texte_doux'] }}; margin-left:6px; }

        /* Récapitulatif */
        .rl-recap { width:100%; border-collapse:collapse; margin:0 0 4px; border-top:0.9pt solid {{ $chR['noir'] }};
                    border-bottom:0.4pt solid {{ $chR['gris'] }}; }
        .rl-recap td { width:20%; padding:7px 8px 8px 0; vertical-align:top; }
        .rl-recap .l { display:block; font-size:7.5px; font-weight:700; letter-spacing:.7px; text-transform:uppercase; color:{{ $chR['texte_doux'] }}; }
        .rl-recap .v { display:block; font-family:{!! $chR['ff_mono'] !!}; font-size:12.5px; font-weight:700; margin-top:3px; white-space:nowrap; }
        .rl-recap .s { display:block; font-size:7.5px; color:{{ $chR['texte_doux'] }}; margin-top:2px; }
        .rl-recap .rl-fort .v { color:{{ $chR['rouge'] }}; }

        /* Solde posé en opération */
        .rl-op { width:100%; border-collapse:collapse; border-top:0.9pt solid {{ $chR['noir'] }}; border-bottom:0.9pt solid {{ $chR['noir'] }}; page-break-inside:avoid; }
        .rl-op td { padding:8px 6px; vertical-align:bottom; text-align:center; }
        .rl-op .l { display:block; font-size:7.5px; font-weight:700; letter-spacing:.7px; text-transform:uppercase; color:{{ $chR['texte_doux'] }}; }
        .rl-op .v { display:block; font-family:{!! $chR['ff_mono'] !!}; font-size:13px; font-weight:700; margin-top:3px; white-space:nowrap; }
        .rl-op .signe { font-size:15px; font-weight:700; color:{{ $chR['texte_doux'] }}; width:20px; padding-bottom:9px; }
        .rl-op .rl-res .v { color:{{ $chR['rouge'] }}; font-size:14px; }
        .rl-apres { font-size:9px; color:{{ $chR['texte_doux'] }}; margin-top:5px; line-height:1.5; }
        .rl-apres b { color:{{ $chR['noir'] }}; }

        /* Version interne */
        .rl-interne { font-family:{!! $chR['ff_titres'] !!}; font-size:8px; font-weight:700; letter-spacing:.8px; text-transform:uppercase;
                      color:{{ $chR['rouge'] }}; border:0.8pt solid {{ $chR['rouge'] }}; padding:2px 6px; }
        .rl-annule td { color:{{ $chR['texte_pale'] }}; }
        .rl-annule td.barre { text-decoration:line-through; }
        .tdoc-note { font-size:8.5px; line-height:1.5; margin-top:12px; page-break-inside:avoid; }
        /* Une section courte ne se coupe pas : son titre reste avec son tableau. */
        .rl-bloc { page-break-inside:avoid; }
    </style>
</head>
<body>
@php
    $fmt     = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $signe   = fn ($n) => ($n > 0 ? '+' : '') . $fmt($n);
    $valides = $reglements->reject(fn ($r) => $r->estAnnule())->values();
    $annules = $reglements->filter(fn ($r) => $r->estAnnule())->values();
    $interne = $interne ?? false;
    $mode    = fn ($r) => $r->mode ? strip_tags(preg_replace('/^\S+\s/u', '', $r->mode_label)) : '—';
    $saisie  = $ligne['saisie'] ?? null;
    $aujourdhui = now()->format('d/m/Y');
    $sec = 0;
@endphp

@include('pdf.partials.tax-doc-header', [
    'docTitle'  => 'Relevé des taxes ODP et TM — ' . $commune->name,
    'docPeriod' => 'Exercice ' . $exercice . ($reprise ? ' (année de reprise)' : '') . ($interne ? ' · version interne' : ''),
])

@include('pdf.partials.tax-doc-meta', ['metaCols' => [
    ['label' => 'Mairie',    'value' => $commune->name],
    ['label' => 'Zone',      'value' => $ligne['is_abidjan'] ? 'Abidjan et banlieue' : 'Intérieur'],
    ['label' => 'Exercice',  'value' => (string) $exercice, 'accent' => true],
    ['label' => 'Panneaux',  'value' => $ligne['nb_panneaux'] . ' panneau' . ($ligne['nb_panneaux'] > 1 ? 'x' : '') . ' · ' . $ligne['nb_faces'] . ' face' . ($ligne['nb_faces'] > 1 ? 's' : ''), 'small' => true],
    ['label' => 'Arrêté au', 'value' => $aujourdhui],
]])

@if($interne)
    <p style="margin:-6px 0 8px"><span class="rl-interne">Document interne — ne pas remettre à la mairie</span></p>
@endif

{{-- 2026-11-27 (point 1 validé) — Année de reprise, montant réclamé pas
     encore saisi : le dû vaut 0, donc tout versement paraîtrait « payé en
     trop ». Le relevé le dit au lieu d'afficher un faux solde. --}}
@php $aSaisir = !empty($ligne['a_saisir']); @endphp
@if($aSaisir)
    <p style="margin:-2px 0 8px;padding:6px 9px;border:0.8pt solid #c2570d;color:#c2570d;font-size:9px;font-weight:700">
        Montant réclamé par la mairie pour {{ $exercice }} non encore saisi : le dû et le solde ne sont pas arrêtés.
    </p>
@endif

{{-- ══ Récapitulatif ══ --}}
<table class="rl-recap">
    <tr>
        <td><span class="l">Arriérés</span><span class="v">{{ $fmt($ligne['arrieres']) }}</span>
            <span class="s">{{ $reprise ? 'années passées, réclamés' : 'solde ' . ($exercice - 1) }}</span></td>
        <td><span class="l">Total dû {{ $exercice }}</span><span class="v">{{ $aSaisir ? 'À saisir' : $fmt($ligne['total_du']) }}</span>
            <span class="s">arriérés + ODP + TM</span></td>
        <td><span class="l">Versé</span><span class="v">{{ $fmt($ligne['total_paye']) }}</span>
            <span class="s">{{ $valides->count() }} versement{{ $valides->count() > 1 ? 's' : '' }}</span></td>
        @if($aSaisir)
        <td class="rl-fort"><span class="l">Solde</span><span class="v">Non arrêté</span>
            <span class="s">montant réclamé à saisir</span></td>
        @else
        <td class="rl-fort"><span class="l">{{ $ligne['solde'] >= 0 ? 'Solde restant dû' : 'Payé en trop' }}</span><span class="v">{{ $fmt(abs($ligne['solde'])) }}</span>
            <span class="s">{{ $ligne['solde'] >= 0 ? 'total dû − versé' : 'déduit de ' . ($exercice + 1) }}</span></td>
        @endif
        <td><span class="l">Dû au {{ $aujourdhui }}</span><span class="v">{{ $fmt($ligne['exigible']) }}</span>
            <span class="s">trimestres déjà commencés</span></td>
    </tr>
</table>

{{-- ══ 1. Ce qui est dû ══ --}}
<div class="rl-sec"><span class="n">{{ ++$sec }}.</span>Ce qui est dû
    <span class="d">{{ $reprise ? 'montants réclamés par la mairie, arriérés compris' : 'arriérés + ODP et TM de chaque trimestre' }}</span></div>
<table class="tdoc-table">
    <thead><tr><th>Période</th><th class="right">ODP</th><th class="right">TM</th><th class="right">Total</th></tr></thead>
    <tbody>
        <tr><td>Arriérés {{ $reprise ? 'des exercices antérieurs' : '(solde ' . ($exercice - 1) . ')' }}</td><td></td><td></td><td class="right mono">{{ $fmt($ligne['arrieres']) }}</td></tr>
        @foreach([1, 2, 3, 4] as $q)
            <tr>
                <td>{{ $q === 1 ? '1er' : $q . 'e' }} trimestre {{ $exercice }}</td>
                <td class="right mono">{{ $fmt($ligne['odp_trim'][$q]) }}</td>
                <td class="right mono">{{ $fmt($ligne['tm_trim'][$q]) }}</td>
                <td class="right mono">{{ $fmt($ligne['odp_trim'][$q] + $ligne['tm_trim'][$q]) }}</td>
            </tr>
        @endforeach
        <tr class="tdoc-total">
            <td>TOTAL DÛ</td>
            <td class="right mono">{{ $fmt($ligne['odp']) }}</td>
            <td class="right mono">{{ $fmt($ligne['tm']) }}</td>
            <td class="tdoc-amount mono">{{ $fmt($ligne['total_du']) }}<span>FCFA</span></td>
        </tr>
    </tbody>
</table>

{{-- ══ 2. Versements reçus ══ --}}
{{-- Courte, la section reste d'un bloc ; longue, elle continue sur la page suivante. --}}
<div class="{{ $valides->count() <= 12 ? 'rl-bloc' : '' }}">
<div class="rl-sec"><span class="n">{{ ++$sec }}.</span>Versements reçus par la mairie
    <span class="d">{{ $valides->count() }} versement{{ $valides->count() > 1 ? 's' : '' }} pour l'exercice {{ $exercice }}</span></div>
<table class="tdoc-table">
    <thead><tr>
        <th style="width:18px">N°</th><th style="width:58px">Date</th><th>Objet</th><th>Mode</th><th>Référence</th><th style="width:48px">Quittance</th>
        @if($interne)<th>Saisi par</th>@endif
        <th class="right" style="width:82px">Montant</th>
    </tr></thead>
    <tbody>
    @forelse($valides as $i => $r)
        <tr>
            <td class="mono">{{ $i + 1 }}</td>
            <td>{{ $r->paid_at?->format('d/m/Y') }}</td>
            <td>{{ $r->objet_label }}</td>
            <td>{{ $mode($r) }}</td>
            <td>{{ $r->reference ?: '—' }}</td>
            <td>{{ $r->quittance_path ? 'Jointe' : '—' }}</td>
            @if($interne)<td>{{ $r->saisiPar?->name ?? '—' }}</td>@endif
            <td class="right mono">{{ $fmt($r->montant) }}</td>
        </tr>
    @empty
        <tr><td colspan="{{ $interne ? 8 : 7 }}" class="tdoc-muted" style="text-align:center">Aucun versement enregistré pour cet exercice.</td></tr>
    @endforelse
        <tr class="tdoc-total">
            <td colspan="{{ $interne ? 7 : 6 }}" class="right">TOTAL VERSÉ</td>
            <td class="tdoc-amount mono">{{ $fmt($ligne['total_paye']) }}<span>FCFA</span></td>
        </tr>
    </tbody>
</table>
</div>

{{-- ══ 3. Solde, posé en opération ══ --}}
<div class="rl-bloc">
<div class="rl-sec"><span class="n">{{ ++$sec }}.</span>Solde de l'exercice {{ $exercice }}</div>
<table class="rl-op">
    <tr>
        <td><span class="l">Total dû</span><span class="v">{{ $fmt($ligne['total_du']) }}</span></td>
        <td class="signe">−</td>
        <td><span class="l">Versé</span><span class="v">{{ $fmt($ligne['total_paye']) }}</span></td>
        <td class="signe">=</td>
        @if($aSaisir)
        <td class="rl-res"><span class="l">Solde</span><span class="v">Non arrêté</span></td>
        @else
        <td class="rl-res"><span class="l">{{ $ligne['solde'] >= 0 ? 'Solde restant dû' : 'Payé en trop' }}</span><span class="v">{{ $fmt(abs($ligne['solde'])) }} FCFA</span></td>
        @endif
    </tr>
</table>
<p class="rl-apres">
    @if($aSaisir)
        Le montant réclamé par la mairie pour {{ $exercice }} doit être saisi dans le Suivi des mairies pour arrêter le solde.
    @elseif($ligne['solde'] > 0)
        Ce solde sera reporté en arriérés sur l'exercice {{ $exercice + 1 }} s'il n'est pas réglé d'ici le 31/12/{{ $exercice }}.
    @elseif($ligne['solde'] < 0)
        La régie a versé {{ $fmt(-$ligne['solde']) }} FCFA de plus que le dû : la différence est déduite de l'exercice {{ $exercice + 1 }}.
    @else
        L'exercice {{ $exercice }} est entièrement réglé.
    @endif
    @if($ligne['exigible'] !== max(0, $ligne['solde']))
        <br>Dû au {{ $aujourdhui }} (arriérés et trimestres déjà commencés, moins le versé) : <b>{{ $fmt($ligne['exigible']) }} FCFA</b>.
    @endif
</p>
</div>

{{-- ══ 4. Base de calcul ══ --}}
@if(!empty($ligne['base']))
<div class="rl-bloc">
<div class="rl-sec"><span class="n">{{ ++$sec }}.</span>Base de calcul
    <span class="d">panneaux de la régie dans la commune</span></div>
<table class="tdoc-table">
    <thead><tr><th>Dimension d'une face</th><th class="right">Panneaux</th><th class="right">Faces</th></tr></thead>
    <tbody>
    @foreach($ligne['base'] as $dim => $b)
        <tr><td>{{ $dim }} m²</td><td class="right mono">{{ $b['panneaux'] }}</td><td class="right mono">{{ $b['faces'] }}</td></tr>
    @endforeach
        <tr class="tdoc-total"><td>TOTAL</td><td class="right mono">{{ $ligne['nb_panneaux'] }}</td><td class="right mono">{{ $ligne['nb_faces'] }}</td></tr>
    </tbody>
</table>
</div>
@endif

@if($interne)
    {{-- ══ Interne : contrôle du réclamé par le calcul Panora (année de reprise) ══ --}}
    @if($reprise)
        @php
            $reclame = $ligne['odp'] + $ligne['tm'];
        @endphp
        <div class="rl-bloc">
        <div class="rl-sec"><span class="n">{{ ++$sec }}.</span>Contrôle : réclamé et calcul Panora
            <span class="d">sans les arriérés · le calcul n'entre pas dans le solde</span></div>
        <table class="tdoc-table">
            <thead><tr><th></th><th class="right">Réclamé par la mairie</th><th class="right">Calcul Panora</th><th class="right">Écart (réclamé − calcul)</th></tr></thead>
            <tbody>
                <tr><td>ODP {{ $exercice }}</td><td class="right mono">{{ $fmt($ligne['odp']) }}</td><td class="right mono">{{ $fmt($ligne['controle_odp']) }}</td><td class="right mono">{{ $signe($ligne['odp'] - $ligne['controle_odp']) }}</td></tr>
                <tr><td>TM {{ $exercice }}</td><td class="right mono">{{ $fmt($ligne['tm']) }}</td><td class="right mono">{{ $fmt($ligne['controle_tm']) }}</td><td class="right mono">{{ $signe($ligne['tm'] - $ligne['controle_tm']) }}</td></tr>
                <tr class="tdoc-total"><td>ODP + TM</td><td class="right mono">{{ $fmt($reclame) }}</td><td class="right mono">{{ $fmt($ligne['controle_total']) }}</td><td class="right mono">{{ $signe($reclame - $ligne['controle_total']) }}</td></tr>
            </tbody>
        </table>
        <p class="rl-apres">
            @if($saisie)
                Réclamé saisi par <b>{{ $saisie->saisiPar?->name ?? '—' }}</b> le {{ $saisie->updated_at?->format('d/m/Y') }}.
                @if($saisie->commentaire) Référence / commentaire : {{ $saisie->commentaire }}@endif
            @else
                Montant réclamé pas encore saisi.
            @endif
            Un écart important signale un panneau absent de Panora, un tarif à revoir ou une réclamation à discuter avec la mairie.
        </p>
        </div>
    @endif

    {{-- ══ Interne : versements annulés, pour mémoire ══ --}}
    <div class="{{ $annules->count() <= 10 ? 'rl-bloc' : '' }}">
    <div class="rl-sec"><span class="n">{{ ++$sec }}.</span>Versements annulés
        <span class="d">pour mémoire · hors totaux</span></div>
    <table class="tdoc-table">
        <thead><tr><th style="width:58px">Date</th><th>Objet</th><th>Annulé le</th><th>Par</th><th>Motif</th><th class="right" style="width:82px">Montant</th></tr></thead>
        <tbody>
        @forelse($annules as $r)
            <tr class="rl-annule">
                <td class="barre">{{ $r->paid_at?->format('d/m/Y') }}</td>
                <td class="barre">{{ $r->objet_label }}</td>
                <td>{{ $r->annule_le?->format('d/m/Y') }}</td>
                <td>{{ $r->annulePar?->name ?? '—' }}</td>
                <td>{{ $r->motif_annulation ?: '—' }}</td>
                <td class="right mono barre">{{ $fmt($r->montant) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="tdoc-muted" style="text-align:center">Aucun versement annulé.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
@endif

<div class="tdoc-note">
    @if($reprise)
        ■ Exercice {{ $exercice }} (année de reprise) : montants établis d'après la réclamation de la mairie, arriérés compris.
        Le solde au 31/12/{{ $exercice }} devient l'arriéré de {{ $exercice + 1 }}.
    @else
        ■ ODP = tarif × surface (m²) × nombre de mois d'occupation du domaine public (un mât compte pour un panneau) ·
        TM = tarif × surface (m²) × nombre de mois de campagne. Tarifs de la mairie en vigueur chaque mois.
        Arriérés = solde de l'exercice {{ $exercice - 1 }}.
    @endif
</div>

@include('pdf.partials.tax-doc-footer', ['footerHint' => 'Relevé ' . ($interne ? 'interne ' : '') . $commune->name . ' · exercice ' . $exercice])
</body>
</html>
