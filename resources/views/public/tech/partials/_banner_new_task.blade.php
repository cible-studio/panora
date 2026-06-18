{{-- _banner_new_task.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Bandeau "🆕 On t'a donné un nouveau panneau" affiché par le JS
     heartbeat (lignes ~1680 du <script>) quand le polling 20s détecte
     un id de pose plus récent que `lastKnownTaskId`. Clic = reload page.
     Aucune variable Blade consommée — pur HTML statique. --}}
<div class="new-task-banner" data-new-task-banner onclick="window.location.reload()">
    🆕 <span data-new-task-text>On t'a donné un nouveau panneau</span> — touche pour voir
</div>
