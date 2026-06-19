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
    $ntLate  = $ntSched && \Carbon\Carbon::parse($ntSched)->startOfDay()->lt(\Carbon\Carbon::today());
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
{{-- SM2a Lot 1.2 — Refonte visuelle T1 §3.3.
     Le squelette HTML, les ids et les data-* sont volontairement
     conservés (consommés par features/upload.js — hero handler). Seul
     le rendu CSS bouge (couleurs sémantiques + label "MAINTENANT 🔥"). --}}
<div class="next-pose-hero" id="next-pose-hero" data-next-task-id="{{ $nt->id }}">
    <span class="nph-badge" aria-hidden="true">🔥 MAINTENANT</span>
    <div class="nph-top">
        @if($ntThumb)
            <span class="nph-thumb" style="background-image:url('{{ $ntThumb }}')"></span>
        @else
            <span class="nph-thumb">🪧</span>
        @endif
        <div class="nph-info">
            <div class="nph-ref">{{ $nt->panel?->reference ?? '—' }}</div>
            <div class="nph-name">{{ $nt->panel?->name ?? '' }}</div>
            <div class="nph-meta">
                @if($ntLate)<span class="late">⏰ En retard</span>@endif
                @if($nt->panel?->commune?->name)<span>📍 {{ $nt->panel->commune->name }}</span>@endif
                @if($ntSched)<span>🕒 {{ \Carbon\Carbon::parse($ntSched)->format('d/m H:i') }}</span>@endif
            </div>
        </div>
    </div>
    <div class="nph-actions">
        <a class="nph-act go" href="{{ $ntGo }}" target="_blank" rel="noopener"
           data-next-go-maps>🧭 Y aller</a>
        <label class="nph-act cam" data-next-pose-photo>
            <input type="file" accept="image/*" capture="environment" data-photo-input data-next-photo>
            📷 Prendre la photo
        </label>
    </div>
</div>
