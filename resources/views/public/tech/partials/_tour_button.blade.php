{{-- _tour_button.blade.php — SM2a Lot 1.5.
     Bouton secondaire "Voir ma tournée sur la carte" en bas du carnet
     T1 (spec §3.6). Pleine largeur, gris, taille tactile 48px.

     Variables consommées :
       - $token (str) — token tech pour l'URL map
       - $totalActive (int) — n'affiche le bouton que s'il y a des poses
                              à voir sur la carte. --}}
@if(($totalActive ?? 0) > 0)
<a href="{{ route('tech.space.map', $token) }}"
   class="sm2-tour-btn"
   aria-label="Ouvrir la carte de ma tournée">
    🗺️ <span>Voir ma tournée sur la carte</span>
</a>
@endif
