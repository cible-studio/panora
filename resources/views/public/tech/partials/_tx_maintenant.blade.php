{{-- Refonte 2026-11-12 — « Maintenant » : la pose la plus urgente, en
     grand (TechSpaceController::$nextTask : en cours, sinon en retard,
     sinon du jour). C'est une ligne de pose à part entière (.pose-line) :
     le tiroir, « Y aller », la photo et « Refaire » fonctionnent dessus.
     Elle n'est pas répétée plus bas dans la liste. --}}
@php
    $d = \App\Support\TechCarte::donnees($task);
    $ref = $task->panel?->reference ?? '—';
    $statut = (string) $task->status;
@endphp
<div class="pose pose-line tx-maintenant tx-hero {{ $d['hasProblem'] ? 'has-problem' : '' }} {{ $d['rejPige'] ? 'has-reject' : '' }}"
     {!! \App\Support\TechCarte::attributs($task, $d) !!}>
    @include('public.tech.partials._banner_rejected_photo', ['rejPige' => $d['rejPige']])
    @if($d['hasProblem'])
        <div class="pose-reported-banner" data-problem-banner>
            ⚠ Tu as déjà dit : <strong data-problem-label>{{ $d['problemLabel'] ?: '—' }}</strong>
            <span class="reported-when" data-problem-when>{{ $d['problemAgo'] ? 'il y a ' . $d['problemAgo'] : '' }}</span>
        </div>
    @endif
    <div class="pose-thumb tx-hero-photo" @if($d['photoUrl']) style="background-image: url('{{ $d['photoUrl'] }}')" @endif>
        <span class="tx-hero-ref">{{ $ref }}</span>
        @if($statut === 'en_cours')<span class="tx-pill tx-pill-place">Sur place</span>
        @elseif($statut === 'en_route')<span class="tx-pill tx-pill-route">En route</span>
        @elseif($d['isLate'])<span class="tx-pill tx-pill-retard">En retard</span>@endif
    </div>
    <div class="tx-hero-corps">
        @include('public.tech.partials._tx_badges', ['task' => $task, 'retard' => false])
        <span class="pose-ref" hidden>{{ $ref }}</span>
        <strong class="pose-name tx-hero-nom">{{ $task->panel?->name }}</strong>
        <div class="tx-ligne-sous">
            @if($task->panel?->commune?->name)<span>{{ $task->panel->commune->name }}</span>@endif
            @if($task->scheduled_at)<span>{{ \Carbon\Carbon::parse($task->scheduled_at)->locale('fr')->isoFormat('ddd D MMM, HH[h]mm') }}</span>@endif
            @if($task->campaign?->name)<span>{{ $task->campaign->name }}</span>@endif
        </div>
        <span class="pose-dot" hidden style="background: {{ $d['statusColor'] }}"></span>
        <div class="tx-actions">
            <a class="tx-btn" data-go-maps href="{{ $d['goUrl'] }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>Y aller</a>
            <label class="tx-btn tx-btn-orange">
                <input type="file" accept="image/*" data-photo-input>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>Photo
            </label>
        </div>
    </div>
</div>
