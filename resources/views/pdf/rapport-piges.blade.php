{{-- ═══════════════════════════════════════════════════════════════════
     RAPPORT DE PIGES — PDF prêt à envoyer au client (2026-11-04)

     Généré par App\Services\Piges\RapportPigesService. IDENTIQUE au
     document Word que l'équipe envoyait (« MOOV _ MILLIONS A SE PARTAGER
     PHASE 2 », docs/), à la demande de l'utilisateur :
       · logo CIBLE en haut à gauche de la première page ;
       · titre centré, gras, souligné, en capitales ; la date à gauche ;
       · commune centrée, grasse, soulignée ;
       · « ❖ emplacement » puis la photo, à la suite (pas de page de garde,
         les communes s'enchaînent comme dans Word) ;
       · police Times, numéro de page en bas à droite.
     Une première version ajoutait récapitulatif, charte, référence, format,
     date et GPS sous chaque photo : retirée (« pas identique »).

     Uniquement des piges vérifiées (règle du 2026-11-04).

     Variables : $titre, $groupes (commune → [['pige', 'photo' => {src,w,h}|null]]),
     $debut, $fin (dates des photos). Les autres ($nbPhotos, $clients…)
     restent fournies par le service, inutilisées ici.
     ═══════════════════════════════════════════════════════════════════ --}}
@php
    $date = match (true) {
        !$debut                 => now()->format('d/m/Y'),
        $debut->isSameDay($fin) => $debut->format('d/m/Y'),
        default                 => 'Du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>{{ $titre }}</title>
<style>
    @page { margin: 25mm 22mm 20mm 25mm !important; }
    /* 14 pt : la taille du document Word (mesurée sur le modèle). */
    body { font-family: 'Times New Roman', Times, serif; font-size: 14pt; color: #000; margin: 0; }

    /* Logo dans la marge haute de la première page, comme dans Word. */
    .rp-logo { margin: -14mm 0 2mm -13mm; }
    .rp-logo img { height: 16mm; }

    .rp-titre { text-align: center; font-weight: bold; text-decoration: underline; text-transform: uppercase; margin: 0 0 4mm; }
    .rp-date { margin: 0 0 4mm; }
    .rp-commune { text-align: center; font-weight: bold; text-decoration: underline; text-transform: uppercase; margin: 6mm 0 3mm; }

    .rp-panneau { page-break-inside: avoid; margin: 0 0 6mm; }
    .rp-lieu { margin: 0 0 2mm 6mm; padding-left: 5mm; text-indent: -5mm; line-height: 1.3; }
    .rp-puce { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; }
    .rp-photo { margin: 0 0 3mm 6mm; page-break-inside: avoid; }
    .rp-sans-photo { margin: 0 0 3mm 6mm; padding: 20mm 0; width: 120mm; text-align: center; color: #666; border: 1px solid #ccc; }

    .rp-page { position: fixed; bottom: -12mm; right: 0; font-size: 9pt; }
    .rp-page:after { content: counter(page); }
</style>
</head>
<body>

<div class="rp-page"></div>

@if(!empty($logoCibleLight))
    <div class="rp-logo"><img src="{{ $logoCibleLight }}" alt="CIBLE"></div>
@endif

<div class="rp-titre">{{ $titre }}</div>
<div class="rp-date">{{ $date }}</div>

@foreach($groupes as $commune => $items)
    {{-- Un emplacement, puis sa ou ses photos (plusieurs photos d'un même
         panneau restent sous une seule légende). Le nom de la commune est
         dans le bloc du premier panneau : jamais seul en bas de page. --}}
    @foreach($items->groupBy(fn ($item) => $item['pige']->panel_id) as $photos)
        @php $panneau = $photos->first()['pige']->panel; @endphp
        <div class="rp-panneau">
            @if($loop->first)<div class="rp-commune">{{ $commune }}</div>@endif
            <div class="rp-lieu"><span class="rp-puce">❖</span> {{ $panneau?->name ?: $panneau?->reference }}</div>
            @php $premiere = $photos->first()['photo']; @endphp
            @if($premiere)
                <div class="rp-photo"><img src="{{ $premiere['src'] }}" style="width:{{ $premiere['w'] }}mm;height:{{ $premiere['h'] }}mm"></div>
            @else
                <div class="rp-sans-photo">Photo indisponible</div>
            @endif
        </div>
        @foreach($photos->slice(1) as $autre)
            @if($autre['photo'])
                <div class="rp-photo"><img src="{{ $autre['photo']['src'] }}" style="width:{{ $autre['photo']['w'] }}mm;height:{{ $autre['photo']['h'] }}mm"></div>
            @endif
        @endforeach
    @endforeach
@endforeach

</body>
</html>
