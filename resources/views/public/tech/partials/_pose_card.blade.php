{{-- _pose_card.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Card complète d'une pose dans la liste groupée par commune.

     Tous les calculs (status, isLate, thumbUrl, goUrl, searchHay,
     lastProblem, rejPige, isToday) sont LOCAUX au partial pour rester
     auto-suffisant. Le partial parent (_pose_list ou squelette) ne passe
     que $task + $today (référence Carbon partagée pour cohérence du tri).

     ⚠ NOTE PRÉ-EXISTANTE : la ligne data-blocking-signal-type utilise
     $problemType qui n'a jamais été défini dans le code original. Blade
     rend une chaîne vide → préservé tel quel pour rester pixel-identique
     (Phase 2 = refactor pur, pas de correctifs).

     IDs/data-attrs critiques pour le JS (à ne PAS modifier) :
       - class="pose pose-line"
       - data-task-id, data-task-status, data-search, data-lat, data-lng,
         data-scheduled-today, data-late, data-has-problem, data-has-reject,
         data-scheduled-at, data-commune, data-blocking-signal-*
       - data-action="photo"/"arrive"/"report"
       - data-go-maps
       - input[data-photo-input]

     Variables passées via @include :
       - $task (PoseTask) — la pose à afficher
       - $today (Carbon) — date pivot pour les comparaisons isLate / isToday --}}
@php
    $status = $task->status instanceof \App\Enums\PoseTaskStatus
        ? $task->status
        : \App\Enums\PoseTaskStatus::from((string) $task->status);
    $statusColor = $status->color();

    $sched = $task->scheduled_at ?? $task->created_at;
    $isLate = $sched && \Carbon\Carbon::parse($sched)->startOfDay()->lt($today);

    // Photo cible du panneau : 1re photo si dispo, sinon placeholder
    $firstPhoto = $task->panel?->photos?->sortBy('ordre')->first();
    $thumbUrl   = $firstPhoto ? asset('storage/' . $firstPhoto->path) : null;

    // "Y aller" : direction GPS si lat/lng dispo, sinon recherche adresse
    $hasGps = $task->panel?->latitude && $task->panel?->longitude;
    if ($hasGps) {
        $goUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $task->panel->latitude . ',' . $task->panel->longitude;
    } else {
        $loc = array_filter([$task->panel?->adresse, $task->panel?->quartier, $task->panel?->commune?->name, 'Côte d\'Ivoire']);
        $goUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(implode(', ', $loc));
    }

    $searchHay = mb_strtolower(implode(' ', array_filter([
        $task->panel?->reference, $task->panel?->name,
        $task->panel?->commune?->name, $task->panel?->quartier,
        $task->panel?->adresse, $task->campaign?->name,
        $task->campaign?->client?->name,
    ])));
@endphp
@php
    // Dernier signalement de problème terrain (s'il y en a un)
    $lastProblem  = $task->lastProblemReport;
    $problemMotif = $lastProblem?->effectiveMotif();
    $problemLabel = $problemMotif?->label();
    $problemAgo   = $lastProblem?->created_at?->diffForHumans(null, true);
@endphp
@php $rejPige = $task->latestRejectedPige; @endphp
@php
    $sched = $task->scheduled_at ?? $task->created_at;
    $isToday = $sched && \Carbon\Carbon::parse($sched)->isToday();
@endphp
<div class="pose pose-line {{ $lastProblem ? 'has-problem' : '' }} {{ $rejPige ? 'has-reject' : '' }}"
     data-task-id="{{ $task->id }}"
     data-task-status="{{ $status->value }}"
     data-search="{{ $searchHay }}"
     data-lat="{{ $task->panel?->latitude }}"
     data-lng="{{ $task->panel?->longitude }}"
     data-scheduled-today="{{ $isToday ? '1' : '0' }}"
     data-late="{{ $isLate ? '1' : '0' }}"
     data-has-problem="{{ $lastProblem ? '1' : '0' }}"
     data-has-reject="{{ $rejPige ? '1' : '0' }}"
     data-scheduled-at="{{ $sched ? \Carbon\Carbon::parse($sched)->toIso8601String() : '' }}"
     data-commune="{{ $task->panel?->commune?->name }}"
     @if($lastProblem)
     data-blocking-signal-type="{{ $problemType }}"
     data-blocking-signal-label="{{ $problemLabel }}"
     @endif>
    {{-- Bandeau ROUGE "photo refusée par le superviseur" — motif
         visible direct, le tech sait quoi corriger en re-prenant
         la photo. Prioritaire sur le bandeau signalement. --}}
    @include('public.tech.partials._banner_rejected_photo', ['rejPige' => $rejPige])
    {{-- Bandeau "déjà signalé" — rappel au tech pour ne pas
         re-signaler le même problème sans le savoir. --}}
    <div class="pose-reported-banner" data-problem-banner
         style="{{ $lastProblem ? '' : 'display:none' }}">
        ⚠ Tu as déjà dit : <strong data-problem-label>{{ $problemLabel ?: '—' }}</strong>
        <span class="reported-when" data-problem-when>{{ $problemAgo ? 'il y a '.$problemAgo : '' }}</span>
    </div>
    {{-- Geste 1 : tap n'importe où sur la ligne = caméra arrière --}}
    <label class="pose-main" data-action="photo">
        <input type="file" accept="image/*" capture="environment" data-photo-input>
        @if($thumbUrl)
            <span class="pose-thumb" style="background-image:url('{{ $thumbUrl }}')"></span>
        @else
            <span class="pose-thumb" title="Pas de photo de référence">🪧</span>
        @endif
        <div class="pose-info">
            <div class="pose-ref">
                {{ $task->panel?->reference ?? '—' }}
            </div>
            @if($task->panel?->name)
                <div class="pose-name">{{ $task->panel->name }}</div>
            @endif
            <div class="pose-sub">
                @if($isLate)
                    <span class="late">⏰ En retard</span>
                @endif
                @if($task->campaign)
                    <span>📢 {{ Str::limit($task->campaign->name, 28) }}</span>
                @endif
                @if($task->scheduled_at)
                    <span>📅 {{ \Carbon\Carbon::parse($task->scheduled_at)->format('d/m à H\hi') }}</span>
                @endif
            </div>
        </div>
        <span class="pose-dot" style="background:{{ $statusColor }}" title="{{ $status->label() }}"></span>
        <span class="pose-cam" aria-hidden="true">📷</span>
    </label>
    <div class="pose-actions-row">
        <a class="pose-act act-go" href="{{ $goUrl }}" target="_blank" rel="noopener" data-go-maps>🧭 Y aller</a>
        {{-- Bouton "Sur place" : visible si pas encore terminé.
             Désactivé si déjà en_cours pour éviter les re-clics. --}}
        <button type="button"
                class="pose-act act-arrive"
                data-action="arrive"
                {{ $status->value === 'en_cours' ? 'disabled' : '' }}>
            @if($status->value === 'en_cours')
                ✓ J'y suis
            @else
                📍 J'y suis
            @endif
        </button>
        <button type="button" class="pose-act act-warn" data-action="report">⚠️ Souci</button>
    </div>
</div>
