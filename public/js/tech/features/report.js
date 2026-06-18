// public/js/tech/features/report.js — SM1.5 Lot 1.
//
// Modale de signalement terrain (1 tap depuis une card → "⚠ Souci").
// 9 motifs DelayReason (centralisés App\Enums\DelayReason — Module 3 SLA).
// Photo optionnelle compressée client-side. Note libre optionnelle.
// POST vers /tech/{token}/poses/{taskId}/report. À succès :
//   - flashSuccess overlay
//   - bandeau "déjà signalé" injecté sur la card sans reload
//   - label motif via window.TECH_CONFIG.motifLabels[type]
//
// Source : bloc 13 du <script> inline pré-SM1.5 (lignes 763-884).
// Migration 1:1 comportement-identique.
//
// Dépendances :
//   - core/api.js : postJson + urlForTask
//   - core/ui-helpers.js : flashSuccess, toast, compressImage
//   - window.TECH_CONFIG.routes.reportTpl (avec __TASK__)
//   - window.TECH_CONFIG.motifLabels (9 motifs FR)
//   - window.TECH_CONFIG.csrfToken (pour multipart upload)
//   - DOM modale #ts-report-modal (rendue par _modal_report.blade.php)

import { urlForTask } from '../core/api.js';
import { flashSuccess, toast, compressImage } from '../core/ui-helpers.js';

export function init() {
    const modal  = document.getElementById('ts-report-modal');
    if (!modal) return;
    const refEl    = document.getElementById('ts-report-ref');
    const noteEl   = document.getElementById('ts-report-note');
    const sendBtn  = document.getElementById('ts-report-send');
    const cancel   = document.getElementById('ts-report-cancel');
    const photoInp = document.getElementById('ts-report-photo');
    const photoLbl = document.getElementById('ts-report-photo-label');
    const photoTxt = document.getElementById('ts-report-photo-label-text');
    let attachedPhoto = null;
    let currentTaskId = null;
    let selectedType  = null;

    photoInp?.addEventListener('change', async () => {
        const f = photoInp.files?.[0];
        if (!f) {
            attachedPhoto = null;
            photoLbl?.classList.remove('has-file');
            if (photoTxt) photoTxt.textContent = '📷 Joindre une photo (facultatif)';
            return;
        }
        try {
            attachedPhoto = await compressImage(f);
            photoLbl?.classList.add('has-file');
            if (photoTxt) photoTxt.textContent = '✓ Photo prête';
        } catch (e) {
            attachedPhoto = f; // fallback original
            photoLbl?.classList.add('has-file');
            if (photoTxt) photoTxt.textContent = '✓ Photo prête (non compressée)';
        }
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="report"]');
        if (!btn) return;
        e.preventDefault();
        const pose = btn.closest('[data-task-id]');
        currentTaskId = pose?.dataset.taskId || null;
        if (!currentTaskId) return;
        selectedType = null;
        if (noteEl) noteEl.value = '';
        sendBtn.disabled = true;
        modal.querySelectorAll('.ts-report-opt').forEach(o => o.classList.remove('sel'));
        const ref = pose.querySelector('.pose-ref')?.textContent?.trim();
        if (refEl) refEl.textContent = ref ? ('Panneau ' + ref + ' — choisis le problème.') : 'Choisis ce qui ne va pas.';
        attachedPhoto = null;
        if (photoInp) photoInp.value = '';
        photoLbl?.classList.remove('has-file');
        if (photoTxt) photoTxt.textContent = '📷 Joindre une photo (facultatif)';
        modal.classList.add('show');
    });

    modal.querySelectorAll('.ts-report-opt').forEach(opt => {
        opt.addEventListener('click', () => {
            selectedType = opt.dataset.type;
            modal.querySelectorAll('.ts-report-opt').forEach(o => o.classList.toggle('sel', o === opt));
            sendBtn.disabled = false;
        });
    });
    cancel?.addEventListener('click', () => modal.classList.remove('show'));
    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });

    sendBtn?.addEventListener('click', async () => {
        if (!currentTaskId || !selectedType) return;
        sendBtn.disabled = true;
        try {
            const url = urlForTask(window.TECH_CONFIG.routes.reportTpl, currentTaskId);
            const csrf = window.TECH_CONFIG.csrfToken;
            let res;
            if (attachedPhoto) {
                const fd = new FormData();
                fd.append('type', selectedType);
                fd.append('note', (noteEl?.value || '').trim());
                fd.append('photo', attachedPhoto, 'signalement.jpg');
                res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin',
                    body: fd,
                });
            } else {
                res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ type: selectedType, note: (noteEl?.value || '').trim() }),
                });
            }
            const data = await res.json();
            modal.classList.remove('show');
            if (res.ok && data.ok) {
                flashSuccess('Souci envoyé au bureau&nbsp;!');

                // Bandeau "déjà signalé" injecté sur la card sans reload.
                // Labels motifs lus depuis TECH_CONFIG (9 DelayReason FR).
                const TYPE_LABELS = window.TECH_CONFIG?.motifLabels || {};
                const pose = document.querySelector(`.pose-line[data-task-id="${currentTaskId}"]`);
                if (pose) {
                    pose.classList.add('has-problem');
                    const banner = pose.querySelector('[data-problem-banner]');
                    const lbl = pose.querySelector('[data-problem-label]');
                    const whn = pose.querySelector('[data-problem-when]');
                    if (banner) banner.style.display = '';
                    if (lbl) lbl.textContent = TYPE_LABELS[selectedType] || 'Problème signalé';
                    if (whn) whn.textContent = "à l'instant";
                }
            } else {
                toast(data.error || data.message || 'Erreur', 'error');
                sendBtn.disabled = false;
            }
        } catch (err) {
            toast('Pas de réseau — réessaie quand ça revient', 'error');
            sendBtn.disabled = false;
        }
    });
}
