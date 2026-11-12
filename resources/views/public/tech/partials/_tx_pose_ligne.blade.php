{{-- Refonte 2026-11-12 — ligne d'une pose ordinaire dans « Ma journée ».
     Garde le contrat des modules JS : .pose.pose-line + data-* (via
     TechCarte::attributs), .pose-row (ouvre le tiroir), .pose-ref,
     .pose-name, .pose-dot. Nouveau : .pose-thumb porte la photo du
     panneau — le tiroir et l'écran « pose suivante » la retrouvent (elle
     n'était plus affichée depuis la refonte des cartes). Une case photo
     discrète sert à « Refaire la photo » depuis les photos refusées. --}}
@php
    $d = \App\Support\TechCarte::donnees($task);
    $ref = $task->panel?->reference ?? '—';
@endphp
<div class="pose pose-line tx-ligne {{ $d['hasProblem'] ? 'has-problem' : '' }} {{ $d['rejPige'] ? 'has-reject' : '' }}"
     {!! \App\Support\TechCarte::attributs($task, $d) !!}>
    @include('public.tech.partials._banner_rejected_photo', ['rejPige' => $d['rejPige']])
    @if($d['hasProblem'])
        <div class="pose-reported-banner" data-problem-banner>
            ⚠ Tu as déjà dit : <strong data-problem-label>{{ $d['problemLabel'] ?: '—' }}</strong>
            <span class="reported-when" data-problem-when>{{ $d['problemAgo'] ? 'il y a ' . $d['problemAgo'] : '' }}</span>
        </div>
    @endif
    <div class="pose-row tx-ligne-corps" role="button" tabindex="0" aria-label="Voir la pose {{ $ref }}">
        <span class="pose-thumb tx-vignette" @if($d['photoUrl']) style="background-image: url('{{ $d['photoUrl'] }}')" @endif></span>
        <div class="tx-ligne-inf">
            <div class="tx-ligne-l1"><span class="pose-ref">{{ $ref }}</span>@include('public.tech.partials._tx_badges', ['task' => $task, 'retard' => false])</div>
            <div class="pose-name">{{ $task->panel?->name }}</div>
            <div class="tx-ligne-sous">
                @if($d['isLate'])<span class="tx-tard">En retard</span>@endif
                @if($task->scheduled_at)<span>{{ \Carbon\Carbon::parse($task->scheduled_at)->locale('fr')->isoFormat('ddd D MMM, HH[h]mm') }}</span>@endif
            </div>
        </div>
        <span class="pose-dot tx-pastille" style="background: {{ $d['statusColor'] }}" title="{{ $d['status']->label() }}"></span>
    </div>
    <label class="tx-photo-discrete" aria-label="Photo de {{ $ref }}"><input type="file" accept="image/*" data-photo-input></label>
</div>
