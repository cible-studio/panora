<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des campagnes — {{ $charte['nom'] }}</title>
    {{-- Liste des campagnes, prête à envoyer à l'administration.

         2026-11-05 (v2, retour utilisateur : « ces cases font trop IA », logo
         mal aligné, date d'édition au pied) — mise en page éditoriale :
           · en-tête : logo et coordonnées sur une même ligne, un filet ;
           · titre = la sélection en clair (CampagneFiltres::description),
             une seule ligne de synthèse au lieu des cartes de chiffres ;
           · tableau à filets fins, sans bandeau noir ni badges : statut en
             texte précédé d'une pastille de couleur ;
           · pied : coordonnées, date d'édition, « page x / y ».
         Couleurs et polices : la charte ($charte), jamais un hex en dur. --}}
    @include('pdf.partials.charte-styles')
    @php
        $ch    = $charte;
        $coord = $ch['coordonnees'] ?? [];

        $nb      = $campaigns->count();
        $enCours = $campaigns->filter(fn ($c) => $c->status?->value === 'actif')->count();
        $fcfa    = fn ($n) => number_format((float) $n, 0, ',', "\u{202F}");

        // Pastille de statut : couleurs de la palette.
        $couleurStatut = fn ($c) => match ($c->status?->value) {
            'actif'            => $ch['vert'],
            'planifie', 'pause'=> $ch['jaune'],
            'annule'           => $ch['rouge'],
            'pose'             => $ch['bleu'],
            default            => $ch['texte_pale'],
        };
    @endphp
    <style>
        @page { margin: 14mm 15mm 19mm 15mm !important; }
        body { font-family: {!! $ch['ff_texte'] !!}; font-size: 8.5pt; line-height: 1.35; color: {{ $ch['noir'] }}; }

        /* ── En-tête ─────────────────────────────────────────────── */
        .ch-lisere td { height: 2px; }
        .tete { width: 100%; border-collapse: collapse; margin-top: 5mm; }
        .tete td { padding: 0; vertical-align: middle; }
        .tete .logo img { height: 12mm; display: block; }
        .tete .societe { text-align: right; font-size: 7.5pt; line-height: 1.55; color: {{ $ch['texte_doux'] }}; }
        .tete .societe strong { color: {{ $ch['noir'] }}; font-family: {!! $ch['ff_titres'] !!}; font-weight: 700; }
        .filet { height: 0; border-top: 0.8pt solid {{ $ch['noir'] }}; margin: 4mm 0 7mm; }

        .type  { font-family: {!! $ch['ff_titres'] !!}; font-size: 7.5pt; font-weight: 700; letter-spacing: 1.8pt; text-transform: uppercase; color: {{ $ch['rouge'] }}; }
        .titre { font-family: {!! $ch['ff_titres'] !!}; font-size: 19pt; font-weight: 700; line-height: 1.2; margin: 1.5mm 0 2mm; }
        .resume { font-size: 9.5pt; color: {{ $ch['texte_doux'] }}; margin: 0 0 8mm; }
        .resume strong { color: {{ $ch['noir'] }}; font-weight: 700; }

        /* ── Tableau ─────────────────────────────────────────────── */
        table.liste { width: 100%; border-collapse: collapse; }
        .liste th {
            font-family: {!! $ch['ff_titres'] !!}; font-size: 6.8pt; font-weight: 700; letter-spacing: 0.8pt;
            text-transform: uppercase; text-align: left; color: {{ $ch['texte_doux'] }};
            padding: 0 7px 5px; border-bottom: 0.9pt solid {{ $ch['noir'] }};
        }
        .liste td { padding: 6px 7px; border-bottom: 0.4pt solid {{ $ch['gris'] }}; vertical-align: top; }
        .liste .num { text-align: right; white-space: nowrap; }
        .liste .date { white-space: nowrap; }
        .liste .nom { font-weight: 700; }
        .liste .ref { font-size: 7pt; line-height: 1.2; margin-top: 1px; color: {{ $ch['texte_pale'] }}; }
        .liste .statut { white-space: nowrap; }
        .liste .point { font-family: 'DejaVu Sans', sans-serif; font-size: 7pt; }
        .liste tfoot td { border-top: 0.9pt solid {{ $ch['noir'] }}; border-bottom: none; padding-top: 7px; font-weight: 700; }
        .vide { text-align: center; padding: 18mm 0; color: {{ $ch['texte_pale'] }}; }

        /* ── Pied (répété sur chaque page) ───────────────────────── */
        .pied { position: fixed; left: 0; right: 0; bottom: -12mm; font-size: 7pt; color: {{ $ch['texte_doux'] }}; }
        .pied table { width: 100%; border-collapse: collapse; }
        .pied td { padding: 2mm 0 0; border-top: 0.4pt solid {{ $ch['gris'] }}; vertical-align: top; }
        .pied .droite { text-align: right; white-space: nowrap; }
        .pied .page:after { content: "Page " counter(page); }   /* DomPDF ne sait pas compter le total */
    </style>
</head>
<body>

<div class="pied">
    <table>
        <tr>
            <td>
                <strong style="color:{{ $ch['noir'] }}">{{ $ch['nom'] }}</strong>
                @if(!empty($coord['activite'])) · {{ $coord['activite'] }}@endif
                @if(!empty($coord['telephones']))<br>{{ $coord['telephones'] }}@endif
                @if(!empty($coord['email'])) · {{ $coord['email'] }}@endif
                @if(!empty($coord['site'])) · {{ $coord['site'] }}@endif
            </td>
            <td class="droite">
                Document confidentiel · édité le {{ $generated }}<br>
                <span class="page"></span>
            </td>
        </tr>
    </table>
</div>

@include('pdf.partials.charte-lisere')

<table class="tete">
    <tr>
        <td class="logo">
            @if(!empty($logoCibleLight))<img src="{{ $logoCibleLight }}" alt="{{ $ch['nom'] }}">@endif
        </td>
        <td class="societe">
            <strong>{{ $ch['nom'] }}</strong><br>
            @if(!empty($coord['adresse'])){{ $coord['adresse'] }}<br>@endif
            @if(!empty($coord['site'])){{ $coord['site'] }}@endif
        </td>
    </tr>
</table>
<div class="filet"></div>

<div class="type">Liste des campagnes</div>
<div class="titre">{{ $criteres ? implode(' · ', $criteres) : 'Toutes les campagnes' }}</div>
<div class="resume">
    <strong>{{ $nb }}</strong> campagne{{ $nb > 1 ? 's' : '' }}@if($enCours), dont <strong>{{ $enCours }}</strong> en cours @endif
    · <strong>{{ $fcfa($totalPanneaux) }}</strong> panneau{{ $totalPanneaux > 1 ? 'x' : '' }}
    · montant total <strong>{{ $fcfa($totalAmount) }} FCFA</strong>
</div>

<table class="liste">
    <thead>
        <tr>
            <th style="width:24%">Campagne</th>
            <th style="width:16%">Client</th>
            <th style="width:15%">Commercial</th>
            <th style="width:9%">Début</th>
            <th style="width:9%">Fin</th>
            <th style="width:12%">Statut</th>
            <th style="width:6%" class="num">Panneaux</th>
            <th style="width:9%" class="num">Montant (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($campaigns as $c)
            <tr>
                <td>
                    <span class="nom">{{ $c->name }}</span>
                    <div class="ref">N° {{ $c->id }}</div>
                </td>
                <td>{{ $c->client?->name ?? '—' }}</td>
                {{-- Commercial responsable ; à défaut, le créateur (règle de
                     la Performance commerciale). --}}
                <td>{{ $c->commercial?->name ?? $c->user?->name ?? '—' }}</td>
                <td class="date">{{ $c->start_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="date">{{ $c->end_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="statut"><span class="point" style="color:{{ $couleurStatut($c) }}">●</span> {{ $c->status?->label() ?? '—' }}</td>
                <td class="num">{{ $c->panels_count ?? 0 }}</td>
                <td class="num">{{ $fcfa($c->total_amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="vide">Aucune campagne ne correspond à cette sélection.</td></tr>
        @endforelse
    </tbody>
    @if($campaigns->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="6">Total · {{ $nb }} campagne{{ $nb > 1 ? 's' : '' }}</td>
            <td class="num">{{ $fcfa($totalPanneaux) }}</td>
            <td class="num">{{ $fcfa($totalAmount) }}</td>
        </tr>
    </tfoot>
    @endif
</table>

</body>
</html>
