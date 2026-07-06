// public/js/tech/features/status-changes.js — SM1.5 Lot 2.
//
// Transitions de statut PoseTask depuis la card :
//   1. Y aller   : bump planifiee → en_route au tap [data-go-maps]
//                  (sans preventDefault — Google Maps s'ouvre à part)
//   2. J'y suis  : bump en_route → en_cours au tap [data-action="arrive"]
//   3. Générique : transition arbitraire via [data-action="status"][data-status-value=X]
//   4. Justifier : modale askContradictionReason() pour piges contradictoires
//                  (consommée par upload.js lot 6)
//
// Source : blocs 2, 3, 8, 9 du <script> inline pré-SM1.5 (lignes 235-292,
// 368-440, 442-511). Migration 1:1 comportement-identique.
//
// Dépendances :
//   - core/api.js : urlForTask
//   - core/ui-helpers.js : toast
//   - window.TECH_CONFIG.routes.statusTpl (avec __TASK__)
//   - window.TECH_CONFIG.csrfToken

import { urlForTask } from '../core/api.js';
import { toast } from '../core/ui-helpers.js';

const STATUS_TARGET = {
    en_route: { color: '#8b5cf6' }, // violet
    en_cours: { color: '#3b82f6' }, // bleu
};

function postStatus(taskId, body) {
    const url  = urlForTask(window.TECH_CONFIG.routes.statusTpl, taskId);
    const csrf = window.TECH_CONFIG.csrfToken;
    const isJson = typeof body === 'object';
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Content-Type': isJson ? 'application/json' : 'application/x-www-form-urlencoded',
        },
        body: isJson ? JSON.stringify(body) : body,
    });
}

// Convertit "#RRGGBB" en "rgba(r,g,b,alpha)" pour styliser le badge.
function hexToRgba(hex, alpha) {
    const m = hex.match(/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i);
    if (!m) return hex;
    return `rgba(${parseInt(m[1],16)},${parseInt(m[2],16)},${parseInt(m[3],16)},${alpha})`;
}

// ── 1. "Y aller" : bump planifiee → en_route + ouvre Maps ────────
function bindGoMaps() {
    document.addEventListener('click', async (e) => {
        const goBtn = e.target.closest('[data-go-maps]');
        if (!goBtn) return;
        const pose = goBtn.closest('[data-task-id]');
        if (!pose) return;
        const taskId = pose.dataset.taskId;
        if (!taskId) return;
        const currentStatus = pose.dataset.taskStatus;
        // FIX 2026-07-01 (feedback patronne) : autrefois le fetch n'était
        // envoyé QUE si status === 'planifiee'. Sur les poses déjà en_route
        // (2ème clic Y aller, ou pose ouverte depuis le drawer avec un
        // ancien statut), rien ne partait → sensation de bug côté tech
        // ("ça marche sur MAINTENANT mais pas sur les autres").
        // Aujourd'hui : on tente le bump sur tout statut non-terminal ;
        // le serveur répond {ok:true, noop:true} si same-status → propre
        // et cohérent entre focus card et drawer.
        if (currentStatus === 'realisee' || currentStatus === 'annulee') return;
        try {
            const r = await postStatus(taskId, 'status=en_route');
            if (r.ok) {
                pose.dataset.taskStatus = 'en_route';
                const dot = pose.querySelector('.pose-dot');
                if (dot) dot.style.background = STATUS_TARGET.en_route.color;
            }
        } catch (e) { /* silencieux, on n'empêche pas Maps */ }
        // Le lien suit son cours (target=_blank) — pas de preventDefault.
    });
}

// ── 2. "J'y suis" : bump en_route → en_cours ─────────────────────
function bindArrive() {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="arrive"]');
        if (!btn || btn.disabled) return;
        const pose = btn.closest('[data-task-id]');
        if (!pose) return;
        const taskId = pose.dataset.taskId;
        const original = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '⏳ …';
        try {
            const r = await postStatus(taskId, 'status=en_cours');
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.ok) {
                pose.dataset.taskStatus = 'en_cours';
                btn.innerHTML = '✓ Sur place';
                const dot = pose.querySelector('.pose-dot');
                if (dot) dot.style.background = STATUS_TARGET.en_cours.color;
                toast('Tu es bien sur place — bonne pose !', 'success');
            } else {
                btn.disabled = false; btn.innerHTML = original;
                toast(data.error || 'Erreur', 'error');
            }
        } catch (err) {
            btn.disabled = false; btn.innerHTML = original;
            toast('Pas de réseau — réessaie quand ça revient', 'error');
        }
    });
}

// ── 3. Changement de statut générique [data-action="status"][data-status-value=X] ──
function bindStatusGeneric() {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="status"]');
        if (!btn) return;
        e.preventDefault();

        const pose = btn.closest('[data-task-id]');
        const taskId = pose?.dataset.taskId;
        const newStatus = btn.dataset.statusValue;
        if (!taskId || !newStatus) return;

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ ...';

        try {
            const res = await postStatus(taskId, { status: newStatus });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                toast(data.error || 'Erreur', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
                return;
            }

            // Mise à jour DOM locale (pas de reload qui ferait remonter
            // en haut de page et perdrait le contexte de scroll du tech).
            const badge = pose.querySelector('[data-status]');
            if (badge) {
                badge.textContent = data.status_icon + ' ' + data.status_label;
                badge.style.color       = data.status_color;
                badge.style.background  = hexToRgba(data.status_color, 0.10);
                badge.style.borderColor = hexToRgba(data.status_color, 0.30);
            }

            // Cache les boutons d'action sauf "Photo + Terminer" qui doit
            // rester accessible quel que soit le statut intermédiaire.
            const actions = pose.querySelector('.actions');
            if (actions && newStatus === 'en_route') {
                actions.querySelector('[data-status-value="en_route"]')?.remove();
            }
            if (actions && newStatus === 'en_cours') {
                actions.querySelector('[data-status-value="en_route"]')?.remove();
                actions.querySelector('[data-status-value="en_cours"]')?.remove();
            }

            btn.disabled = false;
            btn.innerHTML = originalText;
            toast(data.message, 'success');
        } catch (err) {
            toast('Pas de réseau — réessaie quand ça revient', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
}

// ── 4. Modale "justifier la pige malgré signalement" ─────────────
// Le tech a signalé un problème non résolu sur cette pose mais tente
// d'envoyer une pige : on impose une justification écrite (min 10 chars)
// qui sera tracée dans pige.notes côté admin.
// Retourne une Promise<string|null> — null si annulation.
//
// Exporté pour consommation par upload.js (lot 6).
export function askContradictionReason(signalLabel) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.style.cssText = `position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.6);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;padding:16px`;
        overlay.innerHTML = `
            <div style="background:#fff;border-radius:14px;max-width:440px;width:100%;box-shadow:0 30px 80px -20px rgba(0,0,0,.4);overflow:hidden">
                <div style="padding:16px 20px;background:linear-gradient(180deg,#fff7ed,#fff);border-bottom:1px solid #fed7aa;display:flex;align-items:flex-start;gap:10px">
                    <div style="font-size:22px;line-height:1">⚠️</div>
                    <div>
                        <div style="font-size:15px;font-weight:800;color:#9a3412;margin-bottom:2px">Tu envoies une photo malgré le souci</div>
                        <div style="font-size:12.5px;color:#b45309;line-height:1.45">
                            Tu as déjà dit : <strong>« ${signalLabel} »</strong>.
                            Si tu envoies quand même la photo, dis pourquoi (le bureau le verra).
                        </div>
                    </div>
                </div>
                <div style="padding:16px 20px">
                    <label style="display:block;font-size:12.5px;font-weight:700;color:#1f2937;margin-bottom:6px">
                        Dis pourquoi <span style="color:#ef4444">*</span>
                    </label>
                    <textarea id="contradiction-reason-input" rows="3"
                              maxlength="1000"
                              placeholder="Ex : le panneau a été réparé, ou l'affiche est encore lisible…"
                              style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13.5px;resize:vertical;font-family:inherit"></textarea>
                    <div id="contradiction-reason-counter" style="font-size:11px;color:#6b7280;text-align:right;margin-top:4px">0 / 10 lettres mini</div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
                        <button type="button" data-action="cancel"
                                style="padding:9px 16px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;color:#4b5563">
                            Annuler
                        </button>
                        <button type="button" data-action="confirm" disabled
                                style="padding:9px 18px;background:#f97316;border:none;color:#fff;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;opacity:.5">
                            Envoyer quand même
                        </button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        const ta      = overlay.querySelector('#contradiction-reason-input');
        const counter = overlay.querySelector('#contradiction-reason-counter');
        const btnOk   = overlay.querySelector('[data-action="confirm"]');
        const btnNo   = overlay.querySelector('[data-action="cancel"]');
        ta.focus();
        ta.addEventListener('input', () => {
            const n = ta.value.trim().length;
            counter.textContent = `${n} / 10 lettres mini`;
            const ok = n >= 10;
            btnOk.disabled = !ok;
            btnOk.style.opacity = ok ? '1' : '.5';
            btnOk.style.cursor  = ok ? 'pointer' : 'not-allowed';
        });
        function close(val) { overlay.remove(); resolve(val); }
        btnOk.addEventListener('click', () => {
            const v = ta.value.trim();
            if (v.length >= 10) close(v);
        });
        btnNo.addEventListener('click', () => close(null));
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(null); });
        document.addEventListener('keydown', function esc(ev) {
            if (ev.key === 'Escape') { close(null); document.removeEventListener('keydown', esc); }
        });
    });
}

export function init() {
    bindGoMaps();
    bindArrive();
    bindStatusGeneric();
    // askContradictionReason est exporté, pas auto-bindé — consommé à la
    // demande par upload.js lot 6.
}
