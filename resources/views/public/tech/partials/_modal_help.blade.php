{{-- _modal_help.blade.php — SM2a Lot 5.1.
     Modale T8 "Besoin d'aide ?" (spec §3 T8). S'ouvre au tap sur le
     bouton "?" jaune du header (data-action="open-help" ajouté en
     Phase 1 Lot 1.1) OU automatiquement à la PREMIÈRE visite (flag
     localStorage tech_first_use = true).

     3 cards horizontales expliquant les 3 actions principales :
       📍 "Y aller" → ouvre Google Maps
       📸 "Photo"   → ouvre la caméra
       ⚠️ "Souci"   → liste des motifs

     Boutons en bas :
       ▶ Voir le tutoriel (1 min) → URL externe configurable (TECH_CONFIG.contacts.tutorialVideoUrl)
       📞 Appeler mon chef → tel: avec TECH_CONFIG.contacts.chiefPhone

     Pas d'inline JS — piloté par features/help.js. --}}
<div id="sm2-t8-overlay" class="sm2-t8-overlay" hidden aria-hidden="true">
    <div class="sm2-t8-modal" role="dialog" aria-modal="true" aria-labelledby="sm2-t8-title">
        <header class="sm2-t8-head">
            <div class="sm2-t8-head-icon" aria-hidden="true">💡</div>
            <div class="sm2-t8-head-text">
                <h2 id="sm2-t8-title" class="sm2-t8-title">Besoin d'aide ?</h2>
                <div class="sm2-t8-subtitle">3 choses simples à savoir</div>
            </div>
            <button type="button" class="sm2-t8-close" data-action="close-help" aria-label="Fermer">✕</button>
        </header>

        <div class="sm2-t8-body">
            <div class="sm2-t8-card">
                <div class="sm2-t8-card-circle sm2-t8-card-circle-go" aria-hidden="true">📍</div>
                <div class="sm2-t8-card-text">
                    <div class="sm2-t8-card-title">Pour aller au panneau</div>
                    <div class="sm2-t8-card-desc">Tape <strong>Y aller</strong>. Google Maps s'ouvre et t'emmène.</div>
                </div>
            </div>

            <div class="sm2-t8-card">
                <div class="sm2-t8-card-circle sm2-t8-card-circle-cam" aria-hidden="true">📸</div>
                <div class="sm2-t8-card-text">
                    <div class="sm2-t8-card-title">Pour envoyer la photo</div>
                    <div class="sm2-t8-card-desc">Devant le panneau, tape <strong>Photo</strong>. La caméra s'ouvre.</div>
                </div>
            </div>

            <div class="sm2-t8-card">
                <div class="sm2-t8-card-circle sm2-t8-card-circle-warn" aria-hidden="true">⚠️</div>
                <div class="sm2-t8-card-text">
                    <div class="sm2-t8-card-title">Si tu as un souci</div>
                    <div class="sm2-t8-card-desc">Tape <strong>Souci</strong> et choisis le motif. Le bureau est prévenu.</div>
                </div>
            </div>

            <div class="sm2-t8-actions">
                <a class="sm2-t8-btn sm2-t8-btn-primary" data-field="tutorial-link" href="#" target="_blank" rel="noopener" hidden>
                    ▶ Voir le tutoriel (1 min)
                </a>
                <a class="sm2-t8-btn sm2-t8-btn-ghost" data-field="chief-call" href="#" hidden>
                    📞 Appeler mon chef
                </a>
                <button type="button" class="sm2-t8-btn sm2-t8-btn-ghost" data-action="close-help">
                    OK, j'ai compris
                </button>
            </div>
        </div>
    </div>
</div>
