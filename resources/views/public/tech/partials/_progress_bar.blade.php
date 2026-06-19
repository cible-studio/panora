{{-- _progress_bar.blade.php — SM2a Lot 1.1.
     Barre de progression simple du carnet du jour (T1 §3.2) :
       - barre verte 10px
       - compteur "X/Y" en gras vert à droite
       - message motivant en dessous, dépend de l'avancement
     Cf. SM2_DOSSIER_SPECIFICATION.md §3 (écran T1) + §6.1 (couleurs).

     Remplace l'ancien bloc `.progress-staged` (paliers 10/25/50/75/100)
     qui vivait dans _topbar.blade.php SM1.

     Variables consommées :
       - $tech          → User (pour adresser le tech par son prénom)
       - $totalAssigned → int (poses du jour assignées)
       - $totalDone     → int (poses du jour terminées)
       - $progressPct   → int 0..100
--}}
@if(($totalAssigned ?? 0) > 0)
@php
    $firstName = trim((string) explode(' ', (string) $tech->name)[0]);
    $remaining = max(0, ($totalAssigned ?? 0) - ($totalDone ?? 0));
    $pct = (int) ($progressPct ?? 0);
    if ($pct < 30) {
        $motivMsg = "Allez {$firstName} !";
    } elseif ($pct < 70) {
        $motivMsg = 'Continue, tu avances bien !';
    } else {
        $motivMsg = $remaining > 0
            ? "Plus que {$remaining} !"
            : 'C\'est terminé — bravo !';
    }
@endphp
<div class="sm2-progress" aria-label="Progression du jour" role="group">
    <div class="sm2-progress-row">
        <div class="sm2-progress-track" aria-hidden="true">
            <div class="sm2-progress-fill" style="width:{{ $pct }}%"></div>
        </div>
        <strong class="sm2-progress-count">{{ $totalDone }}/{{ $totalAssigned }}</strong>
    </div>
    <div class="sm2-progress-msg">{{ $motivMsg }}</div>
</div>
@endif
