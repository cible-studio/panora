{{-- En-tête commun des PDF (charte graphique, 2026-10-01).
     Liseré 5 couleurs + logo de la régie (version pour fond clair) + titre
     + bloc méta à droite. N'apparaît qu'en tête du document (flux normal).

     Paramètres (tous optionnels sauf $docTitle) :
       $docTitle    (string)       — titre du document
       $docKicker   (?string)      — surtitre rouge en capitales (ex. « Rapport »)
       $docSubtitle (?string)      — ligne de sous-titre
       $docMeta     (?array)       — lignes du bloc de droite (HTML échappé) ;
                                     défaut : « Édité le jj/mm/aaaa à hh:mm »
       $docLogo     (?string)      — data-URI du logo (défaut : $logoCibleLight)
       $docRule     (?bool)        — filet noir sous l'en-tête (défaut : true)
     $logoCibleLight / $operatorName / $charte viennent du view composer. --}}
@php
    $ch       = $charte ?? \App\Support\PdfCharte::data();
    $hdLogo   = $docLogo ?? ($logoCibleLight ?? null);
    $hdMeta   = $docMeta ?? ['Édité le ' . now()->format('d/m/Y à H:i')];
    $hdRule   = $docRule ?? true;
@endphp
@include('pdf.partials.charte-lisere')
<table class="ch-head">
    <tr>
        @if(!empty($hdLogo))
            <td class="ch-head-logo"><img src="{{ $hdLogo }}" alt="{{ $ch['nom'] }}"></td>
        @endif
        <td>
            @if(!empty($docKicker))<div class="ch-kicker">{{ $docKicker }}</div>@endif
            <div class="ch-title">{{ $docTitle ?? '' }}</div>
            @if(!empty($docSubtitle))<div class="ch-subtitle">{{ $docSubtitle }}</div>@endif
        </td>
        @if(!empty($hdMeta))
            <td class="ch-head-meta">
                @foreach($hdMeta as $ligne)
                    <div>{{ $ligne }}</div>
                @endforeach
            </td>
        @endif
    </tr>
</table>
@if($hdRule)<div class="ch-head-rule"></div>@endif
