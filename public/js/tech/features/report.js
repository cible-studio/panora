// public/js/tech/features/report.js — SM2a Phase 4 (refonte T5 + T6).
//
// Modale de signalement terrain refondue en 2 étapes sur la même modale
// physique (cf. _modal_report.blade.php) :
//   - T5 (data-step="motif")   : le tech voit les 9 motifs et en tape un
//   - T6 (data-step="details") : motif rappelé, photo optionnelle, textarea
//                                 avec placeholder dynamique, envoi POST
//
// Garde-fou ascendant compatibilité : on conserve les IDs/classes/comportement
// POST de la version SM1.5 (Lot 1). Seul le flow d'interaction et la
// présentation changent. Les routes serveur sont strictement inchangées.
//
// Dépendances :
//   - core/api.js : urlForTask
//   - core/ui-helpers.js : flashSuccess, toast, compressImage
//   - window.TECH_CONFIG.routes.reportTpl + motifLabels + csrfToken

import { urlForTask } from '../core/api.js';
import { flashSuccess, toast, compressImage } from '../core/ui-helpers.js';

// Placeholder dynamique du textarea T6 selon motif choisi (cf. spec T6).
// Tableau aligné sur DelayReason::cases() — 9 valeurs.
const MOTIF_PLACEHOLDERS = {
    panneau_casse:         "Ex: le côté droit est arraché par le vent…",
    acces_bloque:          "Ex: chantier en cours, retour demain…",
    mauvaise_adresse:      "Ex: numéro introuvable, je suis allé là…",
    technicien_absent:     "Ex: je n'ai pas pu y aller aujourd'hui…",
    materiel_indisponible: "Ex: il manque les agrafes / la colle…",
    meteo:                 "Ex: trop de pluie, j'y vais demain…",
    retard_impression:     "Ex: l'imprimeur n'a pas livré la bâche…",
    retard_client:         "Ex: le client n'a pas validé le visuel…",
    autre:                 "Décris-nous le problème en quelques mots…",
};

export function init() {
    const modal  = document.getElementById('ts-report-modal');
    if (!modal) return;

    const refEl       = document.getElementById('ts-report-ref');
    const noteEl      = document.getElementById('ts-report-note');
    const sendBtn     = document.getElementById('ts-report-send');
    const cancelTop   = document.getElementById('ts-report-cancel-top');
    const cancelLeg   = document.getElementById('ts-report-cancel');
    const changeBtn   = document.getElementById('ts-report-change-motif');
    const photoInp    = document.getElementById('ts-report-photo');
    const photoLbl    = document.getElementById('ts-report-photo-label');
    const photoTxt    = document.getElementById('ts-report-photo-label-text');
    const motifIconEl = modal.querySelector('[data-field="motif-icon"]');
    const motifLblEl  = modal.querySelector('[data-field="motif-label"]');

    let attachedPhoto = null;
    let currentTaskId = null;
    let selectedType  = null;

    function setStep(step) {
        modal.dataset.step = step;
    }

    function resetState() {
        selectedType = null;
        attachedPhoto = null;
        if (noteEl) { noteEl.value = ''; noteEl.placeholder = ''; }
        if (sendBtn) sendBtn.disabled = true;
        if (photoInp) photoInp.value = '';
        photoLbl?.classList.remove('has-file');
        if (photoTxt) photoTxt.textContent = 'Ajouter une photo (facultatif)';
        modal.querySelectorAll('.ts-report-opt').forEach(o => o.classList.remove('sel'));
        setStep('motif');
    }

    function closeModal() {
        modal.classList.remove('show');
        // 2026-07-07 : bug console signalé par la patronne — un descendant
        // du modal avait le focus alors que le modal restait aria-hidden.
        // On synchronise aria-hidden avec l'état visible.
        modal.setAttribute('aria-hidden', 'true');
        // Retire le focus des éléments internes pour ne pas garder un
        // focus fantôme quand le modal se cache.
        if (document.activeElement && modal.contains(document.activeElement)) {
            document.activeElement.blur();
        }
        resetState();
    }

    photoInp?.addEventListener('change', async () => {
        const f = photoInp.files?.[0];
        if (!f) {
            attachedPhoto = null;
            photoLbl?.classList.remove('has-file');
            if (photoTxt) photoTxt.textContent = 'Ajouter une photo (facultatif)';
            return;
        }
        try {
            attachedPhoto = await compressImage(f);
            photoLbl?.classList.add('has-file');
            if (photoTxt) photoTxt.textContent = '✓ Photo prête';
        } catch (e) {
            attachedPhoto = f;
            photoLbl?.classList.add('has-file');
            if (photoTxt) photoTxt.textContent = '✓ Photo prête (non compressée)';
        }
    });

    // Ouverture depuis un bouton data-action="report" (carnet T1 ou drawer T2)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="report"]');
        if (!btn) return;
        e.preventDefault();
        const pose = btn.closest('[data-task-id]');
        currentTaskId = pose?.dataset.taskId || null;
        if (!currentTaskId) return;

        resetState();

        const ref  = pose.querySelector('.pose-ref')?.textContent?.trim()
                  || pose.querySelector('[data-field="ref"]')?.textContent?.trim();
        const name = pose.querySelector('.pose-name')?.textContent?.trim()
                  || pose.querySelector('[data-field="name"]')?.textContent?.trim();
        if (refEl) {
            refEl.textContent = ref
                ? `Panneau ${ref}${name ? ' — ' + name : ''}`
                : 'Touche le motif. Le bureau sera prévenu.';
        }
        modal.classList.add('show');
    });

    // T5 → T6 : sélection d'un motif → bascule
    modal.querySelectorAll('.ts-report-opt').forEach(opt => {
        opt.addEventListener('click', () => {
            selectedType = opt.dataset.type;
            modal.querySelectorAll('.ts-report-opt').forEach(o => o.classList.toggle('sel', o === opt));

            // Peuple le rappel motif T6
            if (motifIconEl) motifIconEl.textContent = opt.dataset.icon || '📝';
            if (motifLblEl)  motifLblEl.textContent  = opt.dataset.label || opt.querySelector('.ts-report-opt-label')?.textContent?.trim() || '—';

            // Placeholder dynamique du textarea
            if (noteEl) noteEl.placeholder = MOTIF_PLACEHOLDERS[selectedType] || MOTIF_PLACEHOLDERS.autre;

            // Active le bouton envoyer
            if (sendBtn) sendBtn.disabled = false;

            // Switch vers T6
            setStep('details');
        });
    });

    // T6 → T5 : "Changer de motif"
    changeBtn?.addEventListener('click', () => {
        selectedType = null;
        if (sendBtn) sendBtn.disabled = true;
        if (noteEl) noteEl.placeholder = '';
        modal.querySelectorAll('.ts-report-opt').forEach(o => o.classList.remove('sel'));
        setStep('motif');
    });

    // Annuler (T5 top OR legacy bottom)
    cancelTop?.addEventListener('click', closeModal);
    cancelLeg?.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    // Envoi POST (inchangé fonctionnellement vs SM1.5)
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
            closeModal();
            if (res.ok && data.ok) {
                // 2026-07-07 (feedback patronne) : l'injection dynamique du
                // bandeau sur la ligne pose problème (rectangle blanc dans
                // certains cas, incohérence avec le rendu Blade). On simplifie :
                // le tech voit UNIQUEMENT le flashSuccess plein écran (retour
                // fort et clair). Le signalement est bien enregistré serveur,
                // et au prochain reload de la page, Blade rendra proprement
                // le bandeau via @if($lastProblem) — chemin unique et fiable.
                flashSuccess('Souci envoyé au bureau&nbsp;!');
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
