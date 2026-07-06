{{-- _pose_card.blade.php — Refonte radicale 2026-06-19 (hotfix SM2a).
     Conforme à spec §3.1 maquette T1 (ligne compacte de la liste).

     Avant : 139 lignes — thumbnail + boutons inline (Y aller / J'y suis /
     Souci) + bandeaux signalement + photo refusée.
     Après : 1 ligne compacte (pastille + ref/name/commune + chevron).
     Tap n'importe où sur la ligne → ouvre le drawer _drawer_pose_detail
     qui contient TOUTES les actions terrain (Y aller / Photo / Souci).

     CRITIQUE — data-* préservés pour les modules JS qui les lisent :
       - class="pose pose-line"
       - data-task-id / data-task-status (status-changes / pose-drawer)
       - data-lat / data-lng (geolocate, y-aller-modal)
       - data-search (filters)
       - data-late / data-scheduled-today / data-scheduled-at
       - data-has-problem / data-has-reject
       - data-commune
       - data-blocking-signal-label

     Bandeaux conservés AU-DESSUS de la ligne (utiles pour le tech
     même en mode compact) :
       - "Photo refusée" rouge (pige refusée par superviseur)
       - "Déjà signalé" jaune (motif déjà remonté)
   ──────────────────────────────────────────────────────────── --}}
@php
    $status = $task->status instanceof \App\Enums\PoseTaskStatus
        ? $task->status
        : \App\Enums\PoseTaskStatus::from((string) $task->status);
    $statusColor = $status->color();

    $sched   = $task->scheduled_at ?? $task->created_at;
    $isLate  = $sched && \Carbon\Carbon::parse($sched)->lt(\App\Models\PoseTask::lateThreshold());
    $isToday = $sched && \Carbon\Carbon::parse($sched)->isToday();

    $searchHay = mb_strtolower(implode(' ', array_filter([
        $task->panel?->reference, $task->panel?->name,
        $task->panel?->commune?->name, $task->panel?->quartier,
        $task->panel?->adresse, $task->campaign?->name,
        $task->campaign?->client?->name,
    ])));

    $lastProblem  = $task->lastProblemReport;
    $problemMotif = $lastProblem?->effectiveMotif();
    $problemLabel = $problemMotif?->label();
    $problemAgo   = $lastProblem?->created_at?->diffForHumans(null, true);
    $rejPige      = $task->latestRejectedPige;
    // 2026-07-06 : la focus card "🔥 MAINTENANT" a été retirée. La pose la
    // plus prioritaire est maintenant highlightée directement dans la liste
    // via un badge orange + halo. Cf. TechSpaceController::$nextTask.
    $isNext       = isset($highlightTaskId) && $highlightTaskId === $task->id;

    // 2026-07-06 hotfix v2 — URL Google Maps préparée ici pour que le drawer
    // T2 puisse la récupérer via pose-drawer.js.
    // Fallback en cascade pour NE JAMAIS retourner "#" (qui ne fait rien
    // au clic) : GPS > adresse+commune > nom du panneau+commune > commune.
    // Le pire cas donne quand même une URL Maps qui ouvre la commune.
    if ($task->panel?->latitude && $task->panel?->longitude) {
        $goUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $task->panel->latitude . ',' . $task->panel->longitude;
    } else {
        $searchQuery = trim(implode(' ', array_filter([
            $task->panel?->adresse,
            $task->panel?->name,
            $task->panel?->commune?->name,
            $task->panel?->commune?->city,
        ])));
        $goUrl = $searchQuery !== ''
            ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($searchQuery)
            : 'https://www.google.com/maps/';
    }
@endphp
<div class="pose pose-line {{ $isNext ? 'is-next' : '' }} {{ $lastProblem ? 'has-problem' : '' }} {{ $rejPige ? 'has-reject' : '' }}"
     data-task-id="{{ $task->id }}"
     data-task-status="{{ $status->value }}"
     data-search="{{ $searchHay }}"
     data-lat="{{ $task->panel?->latitude }}"
     data-lng="{{ $task->panel?->longitude }}"
     data-go-url="{{ $goUrl }}"
     data-progress="{{ (int) ($task->progress_percent ?? 0) }}"
     data-scheduled-today="{{ $isToday ? '1' : '0' }}"
     data-late="{{ $isLate ? '1' : '0' }}"
     data-has-problem="{{ $lastProblem ? '1' : '0' }}"
     data-has-reject="{{ $rejPige ? '1' : '0' }}"
     data-scheduled-at="{{ $sched ? \Carbon\Carbon::parse($sched)->toIso8601String() : '' }}"
     data-commune="{{ $task->panel?->commune?->name }}"
     @if($lastProblem) data-blocking-signal-label="{{ $problemLabel }}" @endif>

    {{-- Badge "🔥 MAINTENANT" pour la pose la plus prioritaire (nextTask).
         Remplace visuellement l'ancienne focus card retirée le 2026-07-06. --}}
    @if($isNext)
        <div class="pose-next-badge">
            <span>🔥</span>
            <span>MAINTENANT</span>
        </div>
    @endif

    {{-- Bandeaux alerte AU-DESSUS de la ligne (visibles sans ouvrir le drawer) --}}
    @include('public.tech.partials._banner_rejected_photo', ['rejPige' => $rejPige])
    @if($lastProblem)
        <div class="pose-reported-banner" data-problem-banner>
            ⚠ Tu as déjà dit : <strong data-problem-label>{{ $problemLabel ?: '—' }}</strong>
            <span class="reported-when" data-problem-when>{{ $problemAgo ? 'il y a '.$problemAgo : '' }}</span>
        </div>
    @endif

    {{-- Ligne compacte : pastille statut + ref/nom + meta légère + chevron --}}
    <div class="pose-row" role="button" tabindex="0"
         aria-label="Voir le détail de la pose {{ $task->panel?->reference ?? '' }}">
        <span class="pose-dot" style="background:{{ $statusColor }}" title="{{ $status->label() }}"></span>
        <div class="pose-row-info">
            <div class="pose-ref">{{ $task->panel?->reference ?? '—' }}</div>
            @if($task->panel?->name)
                <div class="pose-name">{{ \Illuminate\Support\Str::limit($task->panel->name, 36) }}</div>
            @endif
            <div class="pose-sub">
                @if($isLate)<span class="late">⏰ En retard</span>@endif
                @if($task->panel?->commune?->name)<span>📍 {{ $task->panel->commune->name }}</span>@endif
                @if($task->scheduled_at)
                    <span>📅 {{ \Carbon\Carbon::parse($task->scheduled_at)->format('d/m à H\hi') }}</span>
                @endif
            </div>
        </div>
        <span class="pose-chevron" aria-hidden="true">›</span>
    </div>
</div>
