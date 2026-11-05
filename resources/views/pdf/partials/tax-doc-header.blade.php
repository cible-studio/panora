{{-- En-tête des PDF taxes communales.
     Variables attendues :
       $docTitle  (string)  — ex. « Détail des taxes communales »
       $docPeriod (?string) — ex. « Janvier → Septembre 2026 » (optionnel)

     2026-11-05 — en-tête éditorial commun à tous les documents internes
     (pdf.partials.doc-entete : logo et régie alignés, filet, type, titre) ;
     la date d'édition est au pied de page. Avant : barre de marque propre
     aux taxes (logo + « CIBLE CI — titre » + « Édité le » à droite). --}}
@include('pdf.partials.doc-entete', [
    'docKicker'   => 'Taxes communales',
    'docTitle'    => $docTitle,
    'docSubtitle' => $docPeriod ?? null,
])
