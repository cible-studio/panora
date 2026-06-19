{{-- SM2b Phase 5 — Modale validation photo (A4).
     S'ouvre via data-action="open-validate-pige" data-pige-id=X depuis
     n'importe où dans le dashboard. Charge les détails via le endpoint
     admin.piges.detail-json puis affiche photo + comparaison + actions.

     Pas d'inline JS — piloté par public/js/admin/pige-validate.js.
     Variables consommées : aucune (shell vide). --}}
<div id="pige-validate-overlay" class="pige-validate-overlay" hidden aria-hidden="true">
    <div class="pige-validate-modal" role="dialog" aria-modal="true" aria-labelledby="pige-validate-title">
        <header class="pige-validate-head">
            <h2 id="pige-validate-title">✅ Valider la photo</h2>
            <button type="button" class="pige-validate-close" data-action="close-validate-pige" aria-label="Fermer">✕</button>
        </header>

        <div class="pige-validate-body" data-field="body-state-loading">
            Chargement…
        </div>

        <div class="pige-validate-body" data-field="body-state-error" hidden>
            <div class="pige-validate-error">
                Impossible de charger les détails. <button type="button" data-action="retry-validate">Réessayer</button>
            </div>
        </div>

        <div class="pige-validate-body" data-field="body-state-ok" hidden>
            <div class="pige-validate-grid">
                <figure class="pige-validate-photo-wrap">
                    <img class="pige-validate-photo" data-field="pv-photo" alt="Photo envoyée par le tech">
                    <figcaption class="pige-validate-photo-cap">Photo envoyée</figcaption>
                </figure>
                <figure class="pige-validate-photo-wrap pige-validate-photo-wrap--ref">
                    <img class="pige-validate-photo" data-field="pv-ref-photo" alt="Photo de référence du panneau">
                    <figcaption class="pige-validate-photo-cap">Référence panneau</figcaption>
                </figure>
            </div>

            <div class="pige-validate-info">
                <div class="pige-validate-row">
                    <span class="pige-validate-key">Panneau</span>
                    <span class="pige-validate-val">
                        <strong data-field="pv-panel-ref">—</strong> · <span data-field="pv-panel-name">—</span>
                        · <span data-field="pv-commune">—</span>
                    </span>
                </div>
                <div class="pige-validate-row">
                    <span class="pige-validate-key">Tech</span>
                    <span class="pige-validate-val" data-field="pv-tech">—</span>
                </div>
                <div class="pige-validate-row">
                    <span class="pige-validate-key">Campagne</span>
                    <span class="pige-validate-val" data-field="pv-campaign">—</span>
                </div>
                <div class="pige-validate-row">
                    <span class="pige-validate-key">Distance au panneau</span>
                    <span class="pige-validate-val" data-field="pv-dist">—</span>
                </div>
                <div class="pige-validate-row">
                    <span class="pige-validate-key">Envoyée</span>
                    <span class="pige-validate-val" data-field="pv-taken">—</span>
                </div>
            </div>

            <div class="pige-validate-actions">
                <button type="button" class="pige-validate-btn pige-validate-btn--reject" data-action="show-reject">
                    ✗ Refuser
                </button>
                <button type="button" class="pige-validate-btn pige-validate-btn--validate" data-action="do-validate">
                    ✓ Valider
                </button>
            </div>

            {{-- Sous-formulaire refus, caché par défaut --}}
            <div class="pige-validate-reject" data-field="reject-form" hidden>
                <div class="pige-validate-reject-title">Pourquoi refuser ?</div>
                <div class="pige-validate-reject-quick">
                    <button type="button" data-reason-prefix="[Photo floue]">📷 Floue</button>
                    <button type="button" data-reason-prefix="[Mauvais panneau]">🚫 Mauvais panneau</button>
                    <button type="button" data-reason-prefix="[GPS trop loin]">📍 GPS trop loin</button>
                    <button type="button" data-reason-prefix="">📝 Autre</button>
                </div>
                <textarea data-field="reject-reason" rows="3" placeholder="Détails (visible au tech)"></textarea>
                <div class="pige-validate-reject-actions">
                    <button type="button" class="pige-validate-btn pige-validate-btn--cancel" data-action="cancel-reject">Annuler</button>
                    <button type="button" class="pige-validate-btn pige-validate-btn--reject" data-action="do-reject">📤 Envoyer le refus</button>
                </div>
            </div>
        </div>
    </div>
</div>
