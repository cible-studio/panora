{{-- _banner_rejected_photo.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Bandeau rouge "🚫 Photo refusée" affiché dans la card d'une pose dont
     la dernière pige (Pige::latestRejectedPige) a status='rejete'. Prioritaire
     sur le bandeau signalement (cf. _pose_card.blade.php pour le rendu
     conditionnel parent).

     Variables consommées (passées via @include depuis _pose_card) :
       - $rejPige (Pige|null) — pige refusée avec rejection_reason éventuelle. --}}
@if(!empty($rejPige))
<div class="pose-rejected-banner">
    🚫 <strong>Photo refusée</strong>
    @if($rejPige->rejection_reason)
        · <span class="reject-reason">{{ $rejPige->rejection_reason }}</span>
    @endif
    <div style="font-size:11px;opacity:.85;margin-top:2px">
        Refais la photo et envoie-la d'ici.
    </div>
</div>
@endif
