{{-- _modal_photo_preview.blade.php — SM2a Lot 3.1.
     Modale T3 "Photo prise — validation GPS" (spec §3 T3). Apparaît
     après que le tech a capturé la photo et AVANT l'envoi. Le tech
     voit :
       - sa photo en grand (aspect 3:4 portrait)
       - un bandeau noir GPS + heure overlay sur la photo
       - une boîte de validation colorée selon distance haversine :
           VERT  (< 100 m)   → "✓ Position bien enregistrée"
           ORANGE (< 500 m)  → "⚠ Tu es à X m du panneau, vérifie"
           ROUGE (≥ 500 m)   → "✗ Tu es à Y m du panneau, c'est pas le bon ?"
       - 2 boutons : Refaire (1/3) + Envoyer la photo (2/3)

     Pas d'inline JS — piloté par features/upload.js qui remplit les
     data-field et écoute data-action="t3-redo"/"t3-send".

     Variables consommées : aucune. --}}
<div id="sm2-t3-overlay" class="sm2-t3-overlay" hidden aria-hidden="true">
    <div class="sm2-t3-modal" role="dialog" aria-modal="true" aria-labelledby="sm2-t3-title">
        <div class="sm2-t3-photo-wrap">
            <img class="sm2-t3-photo" data-field="photo" alt="Aperçu de ta photo">
            {{-- Overlay noir en bas de la photo avec GPS + heure --}}
            <div class="sm2-t3-photo-overlay" aria-hidden="true">
                <span class="sm2-t3-overlay-icon">📍</span>
                <span data-field="gps-text">Position en cours…</span>
                <span class="sm2-t3-overlay-sep">·</span>
                <span data-field="time-text">—</span>
            </div>
        </div>

        <div class="sm2-t3-body">
            <h2 id="sm2-t3-title" class="sm2-t3-title">Voilà ta photo</h2>
            <div class="sm2-t3-pose" data-field="pose-ref">—</div>

            {{-- Boîte de validation GPS : couleur définie en JS via
                 data-verdict="ok|warn|bad|pending". --}}
            <div class="sm2-t3-verdict" data-field="verdict" data-verdict="pending">
                <span class="sm2-t3-verdict-icon" data-field="verdict-icon">⏳</span>
                <div class="sm2-t3-verdict-text">
                    <strong data-field="verdict-title">Calcul de la position…</strong>
                    <span data-field="verdict-sub"></span>
                </div>
            </div>

            <div class="sm2-t3-actions">
                <button type="button" class="sm2-t3-btn sm2-t3-btn-redo" data-action="t3-redo">
                    📷 Refaire
                </button>
                <button type="button" class="sm2-t3-btn sm2-t3-btn-send" data-action="t3-send">
                    ✅ Envoyer la photo
                </button>
            </div>
        </div>
    </div>
</div>
