{{-- Refonte 2026-11-12 — badges d'une pose : rechange / retouche, visuel
     (vague), équipe créditée, retard. Mêmes règles que _pose_card. --}}
@php $kind = $task->pose_kind ?? 'initial'; @endphp
<span class="tx-badges">
    @if(!empty($retard))<span class="tx-badge tx-badge-retard">En retard</span>@endif
    @if($kind === 'rechange')<span class="tx-badge tx-badge-rechange">Rechange</span>
    @elseif($kind === 'retouche')<span class="tx-badge tx-badge-retouche">Retouche</span>@endif
    @if($libelleVague = $task->libelleVague())
        <span class="tx-badge tx-badge-vague" title="Visuel à poser">{{ \Illuminate\Support\Str::limit($libelleVague, 26) }}</span>
    @endif
    @if($task->poseTeam)
        <span class="tx-badge" style="background: {{ $task->poseTeam->colorHex() }}; color: #fff"
              title="Pose créditée à l'équipe {{ $task->poseTeam->name }}">{{ $task->poseTeam->name }}</span>
    @endif
</span>
