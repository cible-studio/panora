{{-- _drawer_t9_rejected.blade.php — SM2a Lot 5.2.
     Drawer T9 "Photo refusée" (spec §3 T9). Liste les piges actuellement
     refusées qui attendent une re-prise. Chaque entrée affiche :
       - Bandeau rouge "À REFAIRE" avec ref panneau
       - Bulle "MESSAGE DU CHEF" style WhatsApp avec rejection_reason
       - Mini photo refusée + bloc "ENVOYÉE Hier à 9:53"
       - Bouton "📷 Refaire la photo" → re-déclenche l'input photo de la
         .pose-line correspondante (ouvre la caméra)
       - Bouton tertiaire "📞 Appeler le chef pour comprendre" (si dispo)

     Variables consommées :
       - $rejectedPiges (Collection<Pige>) — pré-chargé par TechSpaceController. --}}
<div id="sm2-t9-overlay" class="sm2-t9-overlay" hidden aria-hidden="true">
    <aside id="sm2-t9-drawer" class="sm2-t9-drawer"
           role="dialog" aria-modal="true" aria-labelledby="sm2-t9-title">
        <header class="sm2-t9-head">
            <button type="button" class="sm2-t9-back" data-action="close-t9" aria-label="Revenir au carnet">
                <span aria-hidden="true">←</span> Retour
            </button>
            <h2 id="sm2-t9-title" class="sm2-t9-title">📷 Photos à refaire</h2>
            <button type="button" class="sm2-t9-close" data-action="close-t9" aria-label="Fermer">✕</button>
        </header>

        <div class="sm2-t9-body">
            @forelse($rejectedPiges ?? [] as $pige)
                @php
                    $photoUrl = $pige->photo_path ? asset('storage/' . $pige->photo_path) : null;
                @endphp
                <article class="sm2-t9-card" data-task-id="{{ $pige->pose_task_id }}">
                    {{-- Bandeau rouge épinglé "À REFAIRE" --}}
                    <div class="sm2-t9-banner">
                        <div class="sm2-t9-banner-icon" aria-hidden="true">⚠</div>
                        <div class="sm2-t9-banner-text">
                            <strong>À REFAIRE</strong>
                            <span>Photo refusée</span>
                        </div>
                    </div>
                    <div class="sm2-t9-which">
                        Le chef a refusé ta photo de
                        <strong>{{ $pige->panel?->name ?? ($pige->panel?->reference ?? 'ce panneau') }}</strong>
                        @if($pige->panel?->commune?->name)
                            ({{ $pige->panel->commune->name }})
                        @endif.
                    </div>

                    {{-- Bulle message du chef style WhatsApp --}}
                    @if($pige->rejection_reason)
                        <div class="sm2-t9-bubble">
                            <div class="sm2-t9-bubble-head">
                                <span class="sm2-t9-bubble-avatar" aria-hidden="true">💬</span>
                                <span class="sm2-t9-bubble-label">MESSAGE DU CHEF{{ $pige->verificateur?->name ? ' · '.$pige->verificateur->name : '' }}</span>
                            </div>
                            <div class="sm2-t9-bubble-text">{{ $pige->rejection_reason }}</div>
                        </div>
                    @endif

                    {{-- Mini photo refusée + métadonnées envoi --}}
                    <div class="sm2-t9-photo-row">
                        @if($photoUrl)
                            <div class="sm2-t9-photo-wrap">
                                <img class="sm2-t9-photo" src="{{ $photoUrl }}" alt="Photo refusée">
                                <span class="sm2-t9-photo-badge">REFUSÉE</span>
                            </div>
                        @else
                            <div class="sm2-t9-photo-wrap sm2-t9-photo-wrap-empty" aria-hidden="true">🪧</div>
                        @endif
                        <div class="sm2-t9-meta">
                            <div class="sm2-t9-meta-row">
                                <span class="sm2-t9-meta-label">ENVOYÉE</span>
                                <span class="sm2-t9-meta-value">{{ optional($pige->taken_at)->diffForHumans() ?? '—' }}</span>
                            </div>
                            @if($pige->gps_lat && $pige->gps_lng && $pige->panel?->latitude && $pige->panel?->longitude)
                                @php
                                    $R = 6371000;
                                    $lat1 = deg2rad((float) $pige->gps_lat);
                                    $lat2 = deg2rad((float) $pige->panel->latitude);
                                    $dLat = $lat2 - $lat1;
                                    $dLng = deg2rad(((float) $pige->panel->longitude) - ((float) $pige->gps_lng));
                                    $a = sin($dLat/2)**2 + cos($lat1)*cos($lat2)*sin($dLng/2)**2;
                                    $distM = (int) round($R * 2 * atan2(sqrt($a), sqrt(1-$a)));
                                    $distStr = $distM >= 950 ? round($distM/1000, 1) . ' km' : $distM . ' m';
                                @endphp
                                <div class="sm2-t9-meta-row">
                                    <span class="sm2-t9-meta-label">DISTANCE PANNEAU</span>
                                    <span class="sm2-t9-meta-value">À {{ $distStr }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="sm2-t9-actions">
                        @if($pige->pose_task_id)
                            <button type="button" class="sm2-t9-btn sm2-t9-btn-primary"
                                    data-action="t9-redo" data-task-id="{{ $pige->pose_task_id }}">
                                📷 Refaire la photo
                            </button>
                        @else
                            <button type="button" class="sm2-t9-btn sm2-t9-btn-primary" disabled
                                    title="Tâche associée introuvable">
                                📷 Refaire la photo (indisponible)
                            </button>
                        @endif
                        <a class="sm2-t9-btn sm2-t9-btn-ghost"
                           data-field="chief-call" hidden href="#">
                            📞 Appeler le chef pour comprendre
                        </a>
                    </div>
                </article>
            @empty
                <div class="sm2-t9-empty">
                    <div class="sm2-t9-empty-icon" aria-hidden="true">🎉</div>
                    <div>Aucune photo refusée. Continue comme ça !</div>
                </div>
            @endforelse
        </div>
    </aside>
</div>
