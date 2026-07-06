{{-- _focus_card.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Hero "Prochaine pose" : la pose la plus prioritaire mise en avant en
     haut de la liste (retard → aujourd'hui → reste). Deux gros boutons
     d'action directe : Y aller + Photo.

     Données calculées localement (logique Carbon, lien Google Maps,
     thumbnail). Aucun appel dépendant — c'est une carte autonome.

     Variables passées via @include :
       - $task (PoseTask) — la pose à afficher (= $nextTask côté parent).

     IDs/data-attrs critiques pour le JS (lignes 2738-2807 du <script>) :
       - id="next-pose-hero"
       - data-next-task-id="{id}"
       - data-next-go-maps   (lien Y aller)
       - data-next-pose-photo (label cam)
       - data-next-photo + data-photo-input (input file) --}}
@php
    $nt = $task;
    $ntStatus = $nt->status instanceof \App\Enums\PoseTaskStatus
        ? $nt->status
        : \App\Enums\PoseTaskStatus::tryFrom((string) $nt->status);
    $ntSched = $nt->scheduled_at ?? $nt->created_at;
    $ntLate  = $ntSched && \Carbon\Carbon::parse($ntSched)->lt(\App\Models\PoseTask::lateThreshold());
    $ntToday = $ntSched && \Carbon\Carbon::parse($ntSched)->isToday();
    $ntFirstPhoto = $nt->panel?->photos?->sortBy('ordre')->first();
    $ntThumb = $ntFirstPhoto ? asset('storage/' . $ntFirstPhoto->path) : null;
    if ($nt->panel?->latitude && $nt->panel?->longitude) {
        $ntGo = 'https://www.google.com/maps/dir/?api=1&destination=' . $nt->panel->latitude . ',' . $nt->panel->longitude;
    } else {
        $ntLoc = array_filter([$nt->panel?->adresse, $nt->panel?->quartier, $nt->panel?->commune?->name, 'Côte d\'Ivoire']);
        $ntGo  = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(implode(', ', $ntLoc));
    }
@endphp
{{-- SM2a Lot 1.2 + hotfix 2026-06-19 (refonte radicale).
     IDs et data-* conservés pour upload.js bindHero (lit data-next-task-id).
     AJOUTÉ : data-task-id (canonique) + data-task-status + data-lat/lng
     pour que TOUS les modules JS qui font closest('[data-task-id]')
     (status-changes, y-aller-modal, report, pose-drawer, off-schedule)
     fonctionnent quand le bouton est DANS la focus card et pas dans une
     pose-line de la liste.
     Avant ce fix : closest('[data-task-id]') retournait null →
     urlForTask(tpl, undefined) → URL "/tech/{token}/.../undefined/...".
     Bouton "Y aller" préfixé data-go-maps pour bénéficier de la modale T7
     d'avertissement + bump status en_route partagé. --}}
<div class="next-pose-hero pose"
     id="next-pose-hero"
     data-next-task-id="{{ $nt->id }}"
     data-task-id="{{ $nt->id }}"
     data-task-status="{{ $ntStatus?->value ?? 'planifiee' }}"
     data-lat="{{ $nt->panel?->latitude }}"
     data-lng="{{ $nt->panel?->longitude }}"
     data-commune="{{ $nt->panel?->commune?->name }}"
     data-scheduled-at="{{ $ntSched ? \Carbon\Carbon::parse($ntSched)->toIso8601String() : '' }}"
     data-scheduled-today="{{ $ntToday ? '1' : '0' }}"
     data-late="{{ $ntLate ? '1' : '0' }}">
    <span class="nph-badge" aria-hidden="true">🔥 MAINTENANT</span>
    <div class="nph-top">
        @if($ntThumb)
            <span class="nph-thumb" style="background-image:url('{{ $ntThumb }}')"></span>
        @else
            <span class="nph-thumb">🪧</span>
        @endif
        <div class="nph-info">
            <div class="nph-ref">{{ $nt->panel?->reference ?? '—' }}</div>
            <div class="nph-name pose-name">{{ $nt->panel?->name ?? '' }}</div>
            <div class="nph-meta">
                @if($ntLate)<span class="late">⏰ En retard</span>@endif
                @if($nt->panel?->commune?->name)<span>📍 {{ $nt->panel->commune->name }}</span>@endif
                @if($ntSched)<span>🕒 {{ \Carbon\Carbon::parse($ntSched)->format('d/m H:i') }}</span>@endif
            </div>
        </div>
    </div>
    <div class="nph-actions">
        <a class="nph-act go" href="{{ $ntGo }}" target="_blank" rel="noopener"
           data-go-maps data-next-go-maps>🧭 Y aller</a>
        <label class="nph-act cam" data-next-pose-photo>
            <input type="file" accept="image/*" capture="environment" data-photo-input data-next-photo>
            📷 Prendre la photo
        </label>
    </div>
</div>
