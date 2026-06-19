{{-- _banner_t9_rejected.blade.php — SM2a Lot 1.5.
     Bandeau rouge épinglé "à refaire" affiché AU-DESSUS de la card
     MAINTENANT (T1 §3.7 + variante T9 §intégrée dans accueil).
     Apparaît si le tech a au moins 1 pige refusée par l'admin
     (status='rejete' côté Pige). Tap → ouvre l'historique piges
     filtré sur les refus (en attendant T9 drawer Phase 5).

     Variables consommées :
       - $pigesRejected (int) — déjà calculé côté TechSpaceController
       - $token (str)
--}}
@if(($pigesRejected ?? 0) > 0)
<a href="{{ route('tech.space.piges', $token) }}?status=rejete"
   class="sm2-t9-banner"
   role="alert"
   aria-label="Photos refusées à refaire">
    <span class="sm2-t9-icon" aria-hidden="true">⚠️</span>
    <div class="sm2-t9-text">
        <strong>{{ $pigesRejected }} photo{{ $pigesRejected > 1 ? 's' : '' }} à refaire</strong>
        <span class="sm2-t9-sub">le chef a demandé une nouvelle prise — touche pour voir</span>
    </div>
    <span class="sm2-t9-chevron" aria-hidden="true">›</span>
</a>
@endif
