{{-- ═══════════════════════════════════════════════════════════════════
     RAPPORT DE PIGES — PDF prêt à envoyer au client (2026-11-04)

     Généré par App\Services\Piges\RapportPigesService. Reprend le document
     Word que l'équipe montait à la main (« MOOV _ MILLIONS A SE PARTAGER
     PHASE 2 ») : photos groupées par commune, deux panneaux par page, une
     légende « ❖ emplacement » au-dessus de chaque photo. En plus : page de
     récapitulatif, référence, format, date de la photo, lien vers sa
     position GPS, charte et pied de page.

     Uniquement des piges vérifiées (règle du 2026-11-04).

     Variables : $titre, $groupes (commune → [['pige', 'photo' => {src,w,h}|null]]),
     $nbPhotos, $nbPanneaux, $debut, $fin, $clients, $campagnes, $vagues
     (2026-11-04 : une seule → sous-titre ; plusieurs → chaque photo).
     ═══════════════════════════════════════════════════════════════════ --}}
@php
    $ch = $charte ?? \App\Support\PdfCharte::data();

    $periode = match (true) {
        !$debut                                         => null,
        $debut->isSameDay($fin)                         => 'le ' . $debut->format('d/m/Y'),
        default                                         => 'du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
    };

    $vagues = $vagues ?? collect();

    $sousTitre = implode(' · ', array_filter([
        $clients->count() === 1 ? 'Client : ' . $clients->first() : null,
        $campagnes->count() === 1 && $campagnes->first() !== $titre ? 'Campagne : ' . $campagnes->first() : null,
        $campagnes->count() > 1 ? $campagnes->count() . ' campagnes' : null,
        // Vague (2026-11-04) : le message photographié.
        $vagues->count() === 1 ? $vagues->first()->libelle() : null,
    ]));

    // Plusieurs campagnes ou vagues dans le même rapport : chaque photo dit la sienne.
    $afficherCampagne = $campagnes->count() > 1;
    $afficherVague    = $vagues->count() > 1;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>{{ $titre }}</title>
@include('pdf.partials.charte-styles')
<style>
    @page { margin: 12mm 12mm 20mm 12mm !important; }
    body { font-size: 9px; }

    .rp-saut { page-break-after: always; }

    .rp-recap td { vertical-align: top; }
    .rp-recap tfoot td { padding: 6px 8px; }
    .rp-commune {
        font-family: {!! $ch['ff_titres'] !!};
        font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
        color: {{ $ch['noir'] }};
        border-bottom: 2px solid {{ $ch['rouge'] }};
        padding-bottom: 4px; margin-bottom: 9px;
    }
    .rp-commune span { font-family: {!! $ch['ff_texte'] !!}; font-size: 8.5px; font-weight: 400; text-transform: none; letter-spacing: 0; color: {{ $ch['texte_doux'] }}; }

    .rp-pige { margin-bottom: 7mm; page-break-inside: avoid; }
    .rp-lieu { font-family: {!! $ch['ff_titres'] !!}; font-size: 10.5px; font-weight: 700; color: {{ $ch['noir'] }}; line-height: 1.35; }
    .rp-puce { font-family: 'DejaVu Sans', sans-serif; color: {{ $ch['rouge'] }}; font-size: 10px; }
    .rp-meta { font-size: 8px; color: {{ $ch['texte_doux'] }}; margin: 2px 0 5px 0; }
    .rp-meta strong { color: {{ $ch['noir'] }}; }
    .rp-meta a { color: {{ $ch['bleu'] }}; text-decoration: none; }
    .rp-photo { text-align: center; }
    .rp-photo img { border: 1px solid {{ $ch['gris'] }}; }
    .rp-sans-photo { background: {{ $ch['gris_clair'] }}; color: {{ $ch['texte_doux'] }}; text-align: center; padding: 30mm 0; font-size: 9px; }
</style>
</head>
<body>

@include('pdf.partials.charte-footer', ['footerHint' => $titre . ' — rapport de piges'])

{{-- ═══ Page 1 : récapitulatif ═══ --}}
@include('pdf.partials.charte-header', [
    'docKicker'   => 'Rapport de piges',
    'docTitle'    => $titre,
    'docSubtitle' => $sousTitre ?: null,
    'docMeta'     => array_filter([
        'Édité le ' . now()->format('d/m/Y à H:i'),
        $periode ? 'Photos prises ' . $periode : null,
    ]),
])

<table class="ch-kpis">
    <tr>
        <td class="ch-kpi k-rouge">
            <div class="ch-kpi-label">Panneaux</div>
            <div class="ch-kpi-value">{{ $nbPanneaux }}</div>
        </td>
        <td class="ch-kpi k-jaune">
            <div class="ch-kpi-label">Photos</div>
            <div class="ch-kpi-value">{{ $nbPhotos }}</div>
        </td>
        <td class="ch-kpi k-vert">
            <div class="ch-kpi-label">Communes</div>
            <div class="ch-kpi-value">{{ $groupes->count() }}</div>
        </td>
        <td class="ch-kpi k-bleu">
            <div class="ch-kpi-label">Période des photos</div>
            <div class="ch-kpi-value" style="font-size:10px">{{ $periode ? ucfirst($periode) : '—' }}</div>
        </td>
    </tr>
</table>

<div class="ch-section">Panneaux photographiés par commune</div>
<table class="ch-table rp-recap">
    <thead>
        <tr><th>Commune</th><th class="ch-right">Panneaux</th><th class="ch-right">Photos</th></tr>
    </thead>
    <tbody>
        @foreach($groupes as $commune => $items)
            <tr>
                <td class="ch-strong">{{ $commune }}</td>
                <td class="ch-right">{{ $items->pluck('pige.panel_id')->unique()->count() }}</td>
                <td class="ch-right">{{ $items->count() }}</td>
            </tr>
        @endforeach
    </tbody>
    {{-- En pied de tableau : dans le tbody, une ligne paire prendrait le
         fond gris des lignes alternées et le texte blanc du total
         deviendrait illisible. --}}
    <tfoot>
        <tr class="ch-total">
            <td>Total</td>
            <td class="ch-right">{{ $nbPanneaux }}</td>
            <td class="ch-right">{{ $nbPhotos }}</td>
        </tr>
    </tfoot>
</table>

<div class="ch-note ch-note-gris" style="margin-top:12px">
    Photos prises sur le terrain par les équipes de pose et vérifiées par {{ $ch['nom'] }} avant envoi.
    Chaque photo indique la référence du panneau, son format et la date de la prise de vue.
</div>

<div class="rp-saut"></div>

{{-- ═══ Photos : par commune, deux panneaux par page ═══ --}}
@foreach($groupes as $commune => $items)
    @php $nbPanneauxCommune = $items->pluck('pige.panel_id')->unique()->count(); @endphp
    @foreach($items->chunk(2) as $page)
        <div class="{{ ($loop->parent->last && $loop->last) ? '' : 'rp-saut' }}">
            <div class="rp-commune">
                {{ $commune }}
                <span>· {{ $nbPanneauxCommune }} panneau{{ $nbPanneauxCommune > 1 ? 'x' : '' }}{{ $loop->first ? '' : ' (suite)' }}</span>
            </div>

            @foreach($page as $item)
                @php
                    $pige  = $item['pige'];
                    $photo = $item['photo'];
                    $prise = $pige->taken_at ?? $pige->created_at;
                @endphp
                <div class="rp-pige">
                    <div class="rp-lieu"><span class="rp-puce">❖</span> {{ $pige->panel?->name ?: $pige->panel?->reference }}</div>
                    <div class="rp-meta">
                        Réf. <strong>{{ $pige->panel?->reference ?? '—' }}</strong>
                        @if($pige->panel?->format) · Format {{ $pige->panel->format->name }}@endif
                        @if($prise) · Photo du {{ $prise->format('d/m/Y à H:i') }}@endif
                        @if($afficherCampagne && $pige->campaign) · {{ $pige->campaign->name }}@endif
                        @if($afficherVague && $pige->poseTask?->vague) · <strong>{{ $pige->poseTask->vague->badge() }}</strong>@endif
                        @if($lien = $pige->getGoogleMapsUrl()) · <a href="{{ $lien }}">Position GPS de la photo</a>@endif
                    </div>
                    <div class="rp-photo">
                        @if($photo)
                            <img src="{{ $photo['src'] }}" style="width:{{ $photo['w'] }}mm;height:{{ $photo['h'] }}mm">
                        @else
                            <div class="rp-sans-photo">Photo indisponible</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endforeach

</body>
</html>
