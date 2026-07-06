{{-- _pose_list.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Liste des poses groupée par commune (day-section) avec :
       - en-tête commune (count + progress bar par zone)
       - foreach($tasks) qui rend une _pose_card par pose

     Variables passées via @include :
       - $groupedByCommune (Collection groupée par nom de commune)
       - $doneByCommune    (array : commune → nb poses terminées)
       - $today            (Carbon::today() partagée pour cohérence des
                            calculs isLate / isToday dans chaque card). --}}
@foreach($groupedByCommune as $communeName => $tasks)
    @php
        $hasOverdue = $tasks->contains(function ($t) use ($today) {
            $d = $t->scheduled_at ?? $t->created_at;
            return $d && \Carbon\Carbon::parse($d)->startOfDay()->lt($today);
        });
        // Feedback patronne 2026-07-06 : marquer les communes où une pose
        // est démarrée pour que le tech ne s'éparpille pas ailleurs avant
        // d'avoir fini là où il a commencé.
        $hasStartedZone = $tasks->contains(fn($t) => in_array((string) $t->status, ['en_route', 'en_cours'], true));
        $zid = 'zone-' . md5($communeName);
    @endphp
    @php
        $doneZone   = $doneByCommune[$communeName] ?? 0;
        $activeZone = $tasks->count();
        $totalZone  = $activeZone + $doneZone;
        $pctZone    = $totalZone > 0 ? (int) round($doneZone / $totalZone * 100) : 0;
    @endphp
    <div class="day-section" id="{{ $zid }}" data-zone="{{ $communeName }}">
        <div class="commune-header {{ $hasOverdue ? 'has-overdue' : '' }} {{ $hasStartedZone ? 'has-started' : '' }}">
            <div class="ch-left">
                <h2>📍 {{ $communeName }}</h2>
                <span class="count">{{ $doneZone }}/{{ $totalZone }} faite{{ $totalZone > 1 ? 's' : '' }}</span>
                @if($hasStartedZone)
                    <span class="ch-started-badge">🚗 EN COURS</span>
                @endif
            </div>
            <div class="ch-progress" title="{{ $pctZone }}% de la zone terminée">
                <div class="ch-progress-fill" style="width:{{ $pctZone }}%"></div>
            </div>
        </div>

        @foreach($tasks as $task)
            @include('public.tech.partials._pose_card', [
                'task'            => $task,
                'today'           => $today,
                'highlightTaskId' => $highlightTaskId ?? null,
            ])
        @endforeach
    </div>
@endforeach
