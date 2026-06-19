{{-- _done_section.blade.php — SM2a Lot 1.4.
     Section "🟢 Déjà faites" du carnet T1 §3.5 :
       - bandeau vert avec compteur
       - pliée par défaut (élément <details> natif HTML5 = toggle accessible)
       - liste compacte des 30 dernières poses réalisées du tech
       - persistence localStorage du flag "déplié" (optionnel, défaut plié)

     Pas d'inline JS — un mini-handler dans tech-app.js (Phase 6 extraction)
     écoutera l'event `toggle` du <details> et persistera le flag. Pour
     l'instant : open/close sans persistence — UX acceptable.

     Variables consommées :
       - $donePosesRecent (Collection<PoseTask>) — terminées, ordre DESC
       - $totalDone (int) — total réel des poses faites du tech (peut être >30) --}}
@if(($totalDone ?? 0) > 0)
<details class="sm2-done-section" data-section="done-poses">
    <summary class="sm2-done-summary">
        <span class="sm2-done-icon" aria-hidden="true">🟢</span>
        <span class="sm2-done-label">
            <strong>{{ $totalDone }}</strong> panneau{{ $totalDone > 1 ? 'x' : '' }} fait{{ $totalDone > 1 ? 's' : '' }}
        </span>
        <span class="sm2-done-toggle" aria-hidden="true">▾</span>
    </summary>

    @if($donePosesRecent->isNotEmpty())
        <div class="sm2-done-list">
            @foreach($donePosesRecent as $dp)
                <div class="sm2-done-row">
                    <span class="sm2-done-row-dot" aria-hidden="true">✓</span>
                    <div class="sm2-done-row-info">
                        <div class="sm2-done-row-ref">{{ $dp->panel?->reference ?? '—' }}</div>
                        @if($dp->panel?->name)
                            <div class="sm2-done-row-name">{{ \Illuminate\Support\Str::limit($dp->panel->name, 32) }}</div>
                        @endif
                        <div class="sm2-done-row-meta">
                            @if($dp->panel?->commune?->name)📍 {{ $dp->panel->commune->name }}@endif
                            · <span>{{ optional($dp->updated_at)->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if(($totalDone ?? 0) > $donePosesRecent->count())
            <a href="{{ route('tech.space.piges', $token) }}" class="sm2-done-all">
                Voir tout l'historique →
            </a>
        @endif
    @else
        <div class="sm2-done-empty">Aucune trace récente — les anciennes poses sont dans <a href="{{ route('tech.space.piges', $token) }}">Mes photos</a>.</div>
    @endif
</details>
@endif
