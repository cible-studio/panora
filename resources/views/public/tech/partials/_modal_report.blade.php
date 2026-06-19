{{-- _modal_report.blade.php — SM2a Phase 4 (refonte T5 + T6).
     Modale signalement en DEUX ÉTATS sur la même modale physique :
       - T5 (data-step="motif")   : choix du motif (liste verticale)
       - T6 (data-step="details") : motif sélectionné mis en avant +
                                    photo optionnelle + textarea avec
                                    placeholder dynamique + envoyer.

     Pilote : features/report.js (bind sélection motif → switch T6,
     "← Changer de motif" → retour T5, envoi POST signalement).

     CLAUDE.md règle 1 : on conserve les IDs et classes legacy (ts-report-*)
     pour ne pas casser l'enchainement avec le JS existant, et on ajoute
     un attribut data-step + 2 panneaux (.ts-report-step-{motif,details}).

     Source unique des motifs : App\Enums\DelayReason::cases() (9 motifs).
     L'ordre des cases dans l'enum détermine l'ordre d'affichage. --}}
<div id="ts-report-modal" aria-hidden="true" data-step="motif">
    <div class="ts-report-card">

        {{-- ═══ ÉTAT T5 — Choix du motif ═══ --}}
        <section class="ts-report-step ts-report-step-motif">
            <button type="button" class="sm2-t5-close" id="ts-report-cancel-top" aria-label="Annuler">
                ✗ <span>Annuler</span>
            </button>
            <h3 class="sm2-t5-title">C'est quoi le souci ?</h3>
            <p class="ts-report-sub" id="ts-report-ref">Touche le motif. Le bureau sera prévenu tout de suite.</p>
            <div class="ts-report-opts">
                @foreach(\App\Enums\DelayReason::cases() as $motif)
                    <button type="button" class="ts-report-opt"
                            data-type="{{ $motif->value }}"
                            data-icon="{{ $motif->icon() }}"
                            data-label="{{ $motif->label() }}">
                        <span class="ts-report-opt-icon" aria-hidden="true">{{ $motif->icon() }}</span>
                        <span class="ts-report-opt-label">{{ $motif->label() }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        {{-- ═══ ÉTAT T6 — Détails du signalement ═══ --}}
        <section class="ts-report-step ts-report-step-details" hidden>
            <button type="button" class="sm2-t6-back" id="ts-report-change-motif" aria-label="Changer de motif">
                ← Changer de motif
            </button>

            {{-- Rappel visuel du motif choisi (rempli dynamiquement par JS) --}}
            <div class="sm2-t6-motif-recap">
                <span class="sm2-t6-motif-icon" data-field="motif-icon">📝</span>
                <div class="sm2-t6-motif-text">
                    <div class="sm2-t6-motif-hint">Tu as choisi</div>
                    <div class="sm2-t6-motif-label" data-field="motif-label">—</div>
                </div>
            </div>

            <div class="sm2-t6-section-label">📷 Une photo du souci ?</div>
            <label class="ts-report-photo-btn" id="ts-report-photo-label">
                <input type="file" id="ts-report-photo" accept="image/*" capture="environment" hidden>
                <span id="ts-report-photo-label-text">Ajouter une photo (facultatif)</span>
            </label>

            <div class="sm2-t6-section-label">💬 Un commentaire ?</div>
            <textarea id="ts-report-note"
                      placeholder="Tu peux écrire des détails (pas obligé)…"></textarea>

            <button type="button" id="ts-report-send"
                    class="sm2-t6-send" disabled>
                📤 Envoyer le signalement
            </button>
        </section>

        {{-- Ancien bouton "Annuler" du bas (utilisé en T5 + T6) — masqué
             visuellement mais préservé pour compatibilité JS (report.js
             écoute son ID). Position absolue hors-écran. --}}
        <button type="button" id="ts-report-cancel" class="ts-report-legacy-cancel" aria-hidden="true">Annuler</button>

    </div>
</div>

<style>
    /* UX "sans lecture" : actions plus grosses (préservé legacy) */
    .actions .btn { min-height: 52px; font-size: 16px; }
    .btn-report-sm {
        width:100%; margin-top:8px; min-height:46px;
        background:rgba(217,119,6,.10); color:#b45309;
        border:1px solid rgba(217,119,6,.30); border-radius:12px;
        font-weight:700; cursor:pointer;
    }
    .btn-report-sm:active { transform: translateY(1px); }

    /* Overlay succès (préservé legacy) */
    #ts-success {
        position:fixed; inset:0; z-index:9999; display:none;
        flex-direction:column; align-items:center; justify-content:center; gap:16px;
        background:rgba(22,163,74,.97); color:#fff;
    }
    #ts-success.show { display:flex; animation:tsFade .2s ease; }
    @keyframes tsFade { from{opacity:0} to{opacity:1} }
    .ts-check svg { width:120px; height:120px; }
    .ts-check circle { stroke:#fff; stroke-width:3; stroke-dasharray:151; stroke-dashoffset:151; animation:tsC .5s ease forwards; }
    .ts-check path { stroke:#fff; stroke-width:4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:40; stroke-dashoffset:40; animation:tsK .35s .35s ease forwards; }
    @keyframes tsC { to{stroke-dashoffset:0} }
    @keyframes tsK { to{stroke-dashoffset:0} }
    .ts-msg { font-size:23px; font-weight:800; }

    /* ═══ Modal report — refonte SM2 Phase 4 ═══ */
    #ts-report-modal {
        position:fixed; inset:0; z-index:9998; display:none;
        align-items:flex-end; justify-content:center; background:rgba(15,23,42,.55); padding:0;
    }
    #ts-report-modal.show { display:flex; }
    .ts-report-card {
        position: relative;
        background:#fff; width:100%; max-width:520px; border-radius:18px 18px 0 0;
        padding:16px 18px calc(18px + env(safe-area-inset-bottom)); animation:tsUp .25s ease;
        max-height: 92vh; overflow-y: auto;
    }
    @keyframes tsUp { from{transform:translateY(40px);opacity:.5} to{transform:translateY(0);opacity:1} }

    /* Étapes T5 / T6 — visibilité contrôlée par data-step sur la modale */
    .ts-report-step { display: block; }
    #ts-report-modal[data-step="motif"]   .ts-report-step-details { display: none; }
    #ts-report-modal[data-step="details"] .ts-report-step-motif   { display: none; }
    #ts-report-modal[data-step="details"] .ts-report-step-details { display: block; }

    /* ── T5 — Choix du motif ── */
    .sm2-t5-close {
        display: inline-flex; align-items: center; gap: 6px;
        background: transparent; border: none;
        font-size: 13px; font-weight: 700; color: var(--text2);
        padding: 4px 6px; border-radius: 8px;
        cursor: pointer; font-family: inherit;
    }
    .sm2-t5-close:active { background: var(--surface2); }
    .sm2-t5-title { font-size: 18px; margin: 6px 0 4px; color: var(--text); font-weight: 800; }
    .ts-report-sub { font-size: 13px; color: var(--text2); margin: 0 0 14px; }
    .ts-report-opts { display: flex; flex-direction: column; gap: 8px; }
    .ts-report-opt {
        display: flex; align-items: center; gap: 12px;
        text-align: left; padding: 12px 14px; min-height: 56px;
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 14px;
        font-size: 14px; font-weight: 600; color: var(--text);
        cursor: pointer; font-family: inherit;
        transition: transform .08s, border-color .15s, background .15s;
    }
    .ts-report-opt:active { transform: scale(.99); }
    .ts-report-opt:hover { border-color: var(--c-orange-border); background: var(--c-orange-bg); }
    .ts-report-opt-icon {
        flex: 0 0 32px; font-size: 22px; line-height: 1;
        display: flex; align-items: center; justify-content: center;
    }
    .ts-report-opt-label { flex: 1; line-height: 1.3; }
    .ts-report-opt.sel { border-color: var(--c-orange-action); background: var(--c-orange-bg); color: var(--c-orange-text); }

    /* ── T6 — Détails ── */
    .sm2-t6-back {
        display: inline-flex; align-items: center; gap: 6px;
        background: transparent; border: none;
        font-size: 13px; font-weight: 700; color: var(--text2);
        padding: 4px 6px; border-radius: 8px;
        cursor: pointer; font-family: inherit;
        margin-bottom: 10px;
    }
    .sm2-t6-back:active { background: var(--surface2); }

    .sm2-t6-motif-recap {
        display: flex; align-items: center; gap: 12px;
        padding: 14px 16px;
        background: var(--c-yellow-bg);
        border: 1.5px solid var(--c-yellow-border);
        border-radius: 14px;
        margin-bottom: 14px;
    }
    .sm2-t6-motif-icon {
        flex: 0 0 36px;
        font-size: 32px; line-height: 1;
    }
    .sm2-t6-motif-hint { font-size: 11px; color: var(--c-yellow-text); opacity: .85; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
    .sm2-t6-motif-label { font-size: 15px; color: var(--c-yellow-text); font-weight: 800; line-height: 1.25; margin-top: 2px; }

    .sm2-t6-section-label {
        font-size: 12px; font-weight: 800;
        color: var(--text2);
        margin: 10px 0 6px;
    }
    .ts-report-photo-btn {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        min-height: 50px; padding: 0 14px;
        background: rgba(59,130,246,.06);
        border: 1.5px dashed rgba(59,130,246,.45);
        color: #1d4ed8; border-radius: 12px;
        font-size: 13.5px; font-weight: 700; cursor: pointer;
    }
    .ts-report-photo-btn.has-file {
        background: var(--c-green-bg); border-color: var(--c-green-success); color: var(--c-green-text);
        border-style: solid;
    }
    #ts-report-note {
        width: 100%; min-height: 76px; padding: 10px 12px;
        border: 1.5px solid var(--border); border-radius: 12px;
        font: inherit; font-size: 14px; resize: vertical;
    }
    #ts-report-note:focus {
        border-color: var(--c-orange-action);
        outline: none;
    }
    .sm2-t6-send {
        margin-top: 14px;
        width: 100%; min-height: 52px;
        background: linear-gradient(135deg, #b91c1c, #7f1d1d);
        color: #fff; border: none; border-radius: 14px;
        font-size: 15px; font-weight: 800;
        cursor: pointer; font-family: inherit;
        box-shadow: 0 6px 16px -4px rgba(185, 28, 28, .42);
        transition: transform .08s;
    }
    .sm2-t6-send:active { transform: scale(.98); }
    .sm2-t6-send:disabled { opacity: .55; box-shadow: none; cursor: not-allowed; }

    .ts-report-legacy-cancel {
        position: absolute; left: -9999px; opacity: 0; pointer-events: none;
    }
</style>
