{{-- Pied de page du DEVIS — propre au devis, ne pas réutiliser ailleurs.

     Fixe : répété en bas de chaque page (décision du 2026-11-05, « 1-a » :
     le devis garde son pied).

     Sur UNE seule ligne depuis le 2026-11-19 (lot B, arbitrage écrit) :
     les deux lignes d'avant (charte + mention légale) prenaient la place du
     contenu et chevauchaient le bas des pages pleines. La ligne reprend la
     mention légale du modèle officiel ; `white-space: nowrap` garantit
     qu'elle ne passe jamais à la ligne.

     Paramètre :
       $footerHint (string) — le texte de la ligne (mention légale).

     ⚠ La vue réserve la place dans la marge basse de @page et pose les
     styles de .dv-pied (resources/views/pdf/quote.blade.php). --}}
<div class="dv-pied">{{ $footerHint ?? '' }}</div>
