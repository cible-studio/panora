{{-- En-tête éditorial des documents internes (2026-11-05). À utiliser avec
     pdf.partials.doc-styles. Mêmes paramètres que charte-header, pour qu'un
     document passe de l'un à l'autre en changeant le nom du partiel :

       $docTitle    (string)   — titre (casse normale, pas en capitales)
       $docKicker   (?string)  — type de document, petit, en rouge (alias $docType)
       $docSubtitle (?string)  — ligne de synthèse sous le titre
       $docSubtitleHtml (?string) — la même, avec des <strong> : HTML DE
                                 CONFIANCE, la vue échappe (e()) les valeurs
       $docMeta     (?array)   — informations complémentaires (période,
                                 filtres…), réunies sur une ligne ; les lignes
                                 « Édité le / Généré le » sont écartées : la
                                 date d'édition est au pied de page
       $docLogo     (?string)  — data-URI du logo (défaut : $logoCibleLight)

     Ligne 1 : logo à gauche, la régie (nom, adresse, site) à droite, centrés
     sur la même ligne — puis un filet ; dessous, type, titre, synthèse. --}}
@php
    $chE    = $charte ?? \App\Support\PdfCharte::data();
    $coordE = $chE['coordonnees'] ?? [];
    $logoE  = $docLogo ?? ($logoCibleLight ?? null);
    $typeE  = $docType ?? $docKicker ?? null;
    $metaE  = array_values(array_filter(
        (array) ($docMeta ?? []),
        fn ($ligne) => trim(strip_tags((string) $ligne)) !== ''
            && !preg_match('/^\s*(é|e)dit[ée]|^\s*g[ée]n[ée]r[ée]/iu', strip_tags((string) $ligne))
    ));
@endphp
@include('pdf.partials.charte-lisere')
<table class="doc-tete">
    <tr>
        <td class="doc-logo">
            @if(!empty($logoE))<img src="{{ $logoE }}" alt="{{ $chE['nom'] }}">@endif
        </td>
        <td class="doc-societe">
            <strong>{{ $chE['nom'] }}</strong>
            @if(!empty($coordE['adresse']))<br>{{ $coordE['adresse'] }}@endif
            @if(!empty($coordE['site']))<br>{{ $coordE['site'] }}@endif
        </td>
    </tr>
</table>
<div class="doc-filet"></div>
@if(!empty($typeE))<div class="doc-type">{{ $typeE }}</div>@endif
<div class="doc-titre">{{ $docTitle ?? '' }}</div>
@if(!empty($docSubtitleHtml))
    <div class="doc-soustitre">{!! $docSubtitleHtml !!}</div>
@elseif(!empty($docSubtitle))
    <div class="doc-soustitre">{{ $docSubtitle }}</div>
@endif
@if($metaE)<div class="doc-meta">{{ implode(' · ', $metaE) }}</div>@endif
<div class="doc-fin-entete"></div>
