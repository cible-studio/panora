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
{{-- SM2a Lot 5.2 — Le bandeau ouvre désormais le drawer T9 (au lieu de
     naviguer vers la page piges?status=rejete). Le drawer est rendu une
     seule fois dans tech-space (cf. _drawer_t9_rejected.blade.php). --}}
@if(($pigesRejected ?? 0) > 0)
<button type="button"
        class="sm2-t9-banner-pinned"
        data-action="open-t9"
        aria-label="Photos refusées à refaire">
    <span class="sm2-t9-pinned-icon" aria-hidden="true">⚠️</span>
    <div class="sm2-t9-pinned-text">
        <strong>{{ $pigesRejected }} photo{{ $pigesRejected > 1 ? 's' : '' }} à refaire</strong>
        <span class="sm2-t9-pinned-sub">le chef a demandé une nouvelle prise — touche pour voir</span>
    </div>
    <span class="sm2-t9-pinned-chevron" aria-hidden="true">›</span>
</button>
@endif
