{{-- Footer fixe des PDF taxes communales : délègue au pied de page commun
     de la charte (pdf.partials.charte-footer : régie · slogan · coordonnées
     + numéro de page) en conservant la mention plateforme d'origine.
     Variable optionnelle :
       $footerHint (?string) — précision de bas de page (ex. la formule). --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'Plateforme Panora · opérée par ' . ($operatorName ?? 'CIBLE CI')
        . ' — Document généré automatiquement'
        . (!empty($footerHint) ? ' · ' . $footerHint : ''),
])
