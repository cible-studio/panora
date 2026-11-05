{{-- Footer fixe des PDF taxes communales : délègue au pied de page commun
     harmonisé (pdf.partials.charte-footer : régie · coordonnées · date
     d'édition · page). 2026-11-05 : « Plateforme Panora · opérée par… —
     Document généré automatiquement » remplacé par la mention commune.
     Variable optionnelle :
       $footerHint (?string) — précision de bas de page (ex. la formule). --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'Document confidentiel' . (!empty($footerHint) ? ' · ' . $footerHint : ''),
])
