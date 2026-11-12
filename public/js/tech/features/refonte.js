// public/js/tech/features/refonte.js — Refonte de l'espace technicien
// (2026-11-12, maquette validée) : « Ma journée » et les axes.
//
//   1. Recherche sur les poses affichées (data-search + texte visible).
//   2. Carte d'axe : plier / déplier.
//   3. « Je suis arrivé » sur un axe : progression 50 % sur son premier pied
//      à faire — le serveur démarre tous ses pieds (PoseTask::idsFreresDAxe).
//   4. « Y aller » sur un axe : l'axe s'affiche « En route ».
//   5. « Terminé » sur un axe entier (contrat d'avant le découpage, choix 4b).
//   6. Photo envoyée (événement « tx:photo-envoyee » d'upload.js) : compteur
//      du jour (renvoyé par le serveur, TechCarte::journee), compteur de la
//      commune et, pour un pied ou un axe entier, la carte de l'axe — sans
//      rechargement.
//
// Aucun calcul métier ici : statuts et compteurs viennent du serveur.

import { postJson, urlForTask } from '../core/api.js';
import { toast } from '../core/ui-helpers.js';

const CFG = () => window.TECH_CONFIG || {};
const sansAccents = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

// Pilule de statut d'un axe (même libellés que _tx_axe.blade.php).
const PILULES = {
    planifiee: ['tx-pill-planifiee', 'Planifiée'],
    en_route:  ['tx-pill-route', 'En route'],
    en_cours:  ['tx-pill-place', 'Sur place'],
    realisee:  ['tx-pill-fait', 'Terminé'],
};

function poserPilule(el, statut) {
    if (!el) return;
    const [cls, libelle] = PILULES[statut] || PILULES.planifiee;
    el.classList.remove('tx-pill-planifiee', 'tx-pill-route', 'tx-pill-place', 'tx-pill-fait');
    el.classList.add(cls);
    el.textContent = libelle;
}

// ── 1. Recherche ─────────────────────────────────────────────────────
function initRecherche() {
    const champ = document.getElementById('tx-recherche');
    if (!champ) return;
    const vide = document.getElementById('tx-vide-recherche');
    const memo = new WeakMap();
    const texteDe = (el) => {
        if (!memo.has(el)) {
            const morceaux = [el.dataset.search, el.textContent,
                ...Array.from(el.querySelectorAll('[data-search]'), (x) => x.dataset.search)];
            memo.set(el, sansAccents(morceaux.join(' ')));
        }
        return memo.get(el);
    };
    const filtrer = () => {
        const mots = sansAccents(champ.value).split(/\s+/).filter(Boolean);
        let visibles = 0;
        document.querySelectorAll('.tx-commune .tx-liste > *').forEach((el) => {
            const garde = !mots.length || mots.every((m) => texteDe(el).includes(m));
            el.hidden = !garde;
            if (garde) visibles++;
        });
        document.querySelectorAll('.tx-commune').forEach((section) => {
            section.hidden = !section.querySelector('.tx-liste > :not([hidden])');
        });
        if (vide) vide.hidden = !mots.length || visibles > 0;
    };
    let minuteur;
    champ.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(filtrer, 120); });
    champ.addEventListener('search', filtrer);
}

// ── Compteurs ────────────────────────────────────────────────────────
// En-tête : valeurs du serveur (réponse de la photo ou de « Terminé »).
function majJournee(jour) {
    const bloc = document.querySelector('.tx-jour[data-jour-faites]');
    if (!bloc || !jour) return;
    bloc.dataset.jourFaites = jour.faites;
    bloc.dataset.jourTotal = jour.total;
    const compte = bloc.querySelector('[data-tx-jour-compte]');
    if (compte) compte.textContent = `${jour.faites} / ${jour.total} aujourd'hui`;
    const message = bloc.querySelector('[data-tx-jour-message]');
    if (message && jour.message) message.textContent = jour.message;
    const barre = bloc.querySelector('.tx-barre i');
    if (barre) barre.style.width = `${jour.pct ?? 0}%`;
}

// Commune : « N / M faites » — une pose de plus faite, le total ne bouge pas.
function compterCommune(pose) {
    const nom = pose?.dataset.commune || 'Sans commune';
    const section = Array.from(document.querySelectorAll('.tx-commune[data-commune]'))
        .find((s) => s.dataset.commune === nom);
    const compte = section?.querySelector('[data-tx-commune-compte]');
    if (!compte) return;
    const faites = (parseInt(compte.dataset.faites, 10) || 0) + 1;
    const total = Math.max(parseInt(compte.dataset.total, 10) || 0, faites);
    compte.dataset.faites = faites;
    compte.textContent = `${faites} / ${total} faites`;
}

// ── 2. Plier / déplier un axe ────────────────────────────────────────
function basculerAxe(tete) {
    const carte = tete.closest('.tx-axe');
    if (!carte) return;
    const ouvert = carte.classList.toggle('ouvert');
    tete.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
}

// Statut affiché d'un axe et de ses pieds encore à faire.
function statutAxe(carte, statut, pct) {
    if (!carte) return;
    const maintenant = new Date().toISOString();
    carte.querySelectorAll('.tx-pied.pose-line[data-task-id], .tx-axe-actions[data-task-id]').forEach((el) => {
        el.dataset.taskStatus = statut;
        if (pct != null) el.dataset.progress = Math.max(parseInt(el.dataset.progress, 10) || 0, pct);
        if (statut === 'en_cours' && !el.dataset.arrivedAt) el.dataset.arrivedAt = maintenant;
        if (!el.dataset.startedAt) el.dataset.startedAt = maintenant;
    });
    poserPilule(carte.querySelector('[data-tx-axe-statut]'), statut);
    const arrive = carte.querySelector('[data-tx-arrive]');
    if (arrive && statut === 'en_cours') {
        arrive.disabled = true;
        arrive.lastChild.textContent = 'Sur place';
    }
}

// ── 3. « Je suis arrivé » (axe) ──────────────────────────────────────
async function arriver(bouton) {
    if (bouton.disabled) return;
    const porteur = bouton.closest('[data-task-id]');
    const carte = bouton.closest('[data-axe-carte]');
    const taskId = porteur?.dataset.taskId;
    const modele = CFG().routes?.progressTpl;
    if (!taskId || !modele) return;

    const avant = bouton.innerHTML;
    bouton.disabled = true;
    bouton.textContent = '⏳ …';
    try {
        const r = await postJson(urlForTask(modele, taskId), { percent: 50 });
        const data = await r.json().catch(() => ({}));
        if (!r.ok || !data.ok) {
            bouton.disabled = false;
            bouton.innerHTML = avant;
            toast(data.error || data.message || `Erreur ${r.status}`, 'error');
            return;
        }
        bouton.innerHTML = avant;
        // Une autre pose est repassée « à faire » (un seul lieu à la fois) :
        // le serveur détient la vérité, on recharge (comme le tiroir).
        if (Array.isArray(data.reverted_ids) && data.reverted_ids.length) {
            toast('Tu es sur place. Ta pose précédente repasse « à faire ».', 'success');
            setTimeout(() => window.location.reload(), 700);
            return;
        }
        statutAxe(carte, 'en_cours', 50);
        toast('Tu es sur place : tous les pieds de l\'axe sont démarrés.', 'success');
    } catch (e) {
        bouton.disabled = false;
        bouton.innerHTML = avant;
        toast('Pas de réseau — réessaie quand ça revient', 'error');
    }
}

// ── 5. « Terminé » (axe entier) ──────────────────────────────────────
// Petite feuille de confirmation (au lieu du confirm() natif).
function demander({ titre, texte, oui, non = 'Annuler' }) {
    return new Promise((resolve) => {
        const fond = document.createElement('div');
        fond.className = 'tx-feuille-fond';
        fond.innerHTML = `
            <div class="tx-feuille" role="alertdialog" aria-modal="true" aria-labelledby="tx-feuille-titre">
                <h2 id="tx-feuille-titre"></h2>
                <p></p>
                <div class="tx-actions">
                    <button type="button" class="tx-btn" data-non></button>
                    <button type="button" class="tx-btn tx-btn-vert" data-oui></button>
                </div>
            </div>`;
        fond.querySelector('h2').textContent = titre;
        fond.querySelector('p').textContent = texte;
        fond.querySelector('[data-non]').textContent = non;
        fond.querySelector('[data-oui]').textContent = oui;
        document.body.appendChild(fond);
        const finir = (valeur) => {
            fond.remove();
            document.removeEventListener('keydown', echap);
            resolve(valeur);
        };
        const echap = (e) => { if (e.key === 'Escape') finir(false); };
        fond.addEventListener('click', (e) => {
            if (e.target.closest('[data-oui]')) finir(true);
            else if (e.target === fond || e.target.closest('[data-non]')) finir(false);
        });
        document.addEventListener('keydown', echap);
        fond.querySelector('[data-oui]').focus();
    });
}

async function terminer(bouton) {
    if (bouton.disabled) return;
    const ligne = bouton.closest('[data-axe-entier]');
    const taskId = ligne?.dataset.taskId;
    const modele = CFG().routes?.statusTpl;
    if (!taskId || !modele) return;

    const photos = parseInt(ligne.dataset.photos, 10) || 0;
    const pieds = parseInt(ligne.dataset.piedsTotal, 10) || 0;
    if (photos === 0) {
        toast('Envoie au moins une photo avant de terminer.', 'error');
        return;
    }
    if (pieds > photos) {
        const manque = pieds - photos;
        const ok = await demander({
            titre: `Il manque ${pluriel(manque, 'photo')}`,
            texte: `${pluriel(photos, 'photo')} envoyée${photos > 1 ? 's' : ''} pour ${pieds} pieds. Terminer quand même ?`,
            oui: 'Terminer',
            non: 'Continuer les photos',
        });
        if (!ok) return;
    }

    const avant = bouton.innerHTML;
    bouton.disabled = true;
    bouton.textContent = '⏳ …';
    try {
        const r = await postJson(urlForTask(modele, taskId), { status: 'realisee' });
        const data = await r.json().catch(() => ({}));
        if (!r.ok || !data.ok) {
            bouton.disabled = false;
            bouton.innerHTML = avant;
            toast(data.error || data.message || `Erreur ${r.status}`, 'error');
            return;
        }
        ligne.dataset.taskStatus = 'realisee';
        poserPilule(ligne.querySelector('.tx-pill'), 'realisee');
        ligne.classList.add('tx-envoyee');
        majJournee(data.jour);
        compterCommune(ligne);
        toast('Axe terminé — merci !', 'success');
    } catch (e) {
        bouton.disabled = false;
        bouton.innerHTML = avant;
        toast('Pas de réseau — réessaie quand ça revient', 'error');
    }
}

// ── 6. Après une photo ───────────────────────────────────────────────
const heureCourte = () => {
    const d = new Date();
    return `${d.getHours()}h${String(d.getMinutes()).padStart(2, '0')}`;
};

// Un pied photographié : sa ligne devient « Envoyée », la route avance,
// le pied suivant passe devant. Sans rechargement.
function piedEnvoye(carte, ligne, taskId) {
    const n = ligne.dataset.txPied || '';
    const fait = document.createElement('div');
    fait.className = 'tx-pied tx-pied-fait envoyee tx-vient';
    fait.innerHTML = `
        <span class="tx-pied-num tx-pied-num-ok">✓</span>
        <span class="tx-pied-lib"><strong></strong><span class="tx-att"></span></span>
        <span></span>`;
    fait.querySelector('strong').textContent = `Pied ${n}`;
    fait.querySelector('.tx-att').textContent = `Envoyée, à valider · ${heureCourte()}`;
    ligne.replaceWith(fait);

    const segment = carte.querySelector(`[data-tx-segment="${taskId}"]`);
    if (segment) segment.className = 'envoyee';
    const jalon = carte.querySelector(`[data-tx-jalon="${taskId}"]`);
    if (jalon) jalon.className = 'tx-jalon envoyee';

    const segments = carte.querySelectorAll('[data-tx-segment]');
    const faits = carte.querySelectorAll('[data-tx-segment].envoyee, [data-tx-segment].validee').length;
    const compte = carte.querySelector('[data-tx-axe-compte]');
    if (compte) compte.textContent = `${faits} / ${segments.length}`;

    // Le pied suivant (dans l'ordre du terrain) passe devant.
    carte.querySelectorAll('.suivant').forEach((el) => el.classList.remove('suivant'));
    const suivant = carte.querySelector('.tx-pied.pose-line[data-task-id]');
    const actions = carte.querySelector('.tx-axe-actions[data-task-id]');
    if (!suivant) {
        carte.classList.add('tx-axe-fini');
        if (actions) actions.hidden = true;
        poserPilule(carte.querySelector('[data-tx-axe-statut]'), 'realisee');
        toast('Axe terminé : toutes ses photos sont parties. Bravo !', 'success');
        return;
    }
    suivant.classList.add('suivant');
    carte.querySelector(`[data-tx-jalon="${suivant.dataset.taskId}"]`)?.classList.add('suivant');
    // « Y aller » / « Je suis arrivé » s'appuyaient sur ce pied : ils passent
    // au suivant (mêmes attributs que la ligne, cf. TechCarte::attributs).
    if (actions && actions.dataset.taskId === String(taskId)) {
        Array.from(suivant.attributes)
            .filter((a) => a.name.startsWith('data-') && a.name !== 'data-tx-pied')
            .forEach((a) => actions.setAttribute(a.name, a.value));
        const lien = actions.querySelector('[data-go-maps]');
        if (lien && suivant.dataset.goUrl) lien.href = suivant.dataset.goUrl;
    }
    const reste = carte.querySelectorAll('.tx-pied.pose-line[data-task-id]').length;
    toast(`Pied ${n} envoyé · plus que ${pluriel(reste, 'pied')}`, 'success');
    suivant.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Axe entier : la tâche reste ouverte (choix 4b), on compte ses photos.
function photoAxeEntier(ligne, data, photo) {
    const photos = data.photos_count ?? ((parseInt(ligne.dataset.photos, 10) || 0) + 1);
    ligne.dataset.photos = photos;
    ligne.dataset.taskStatus = 'en_cours';
    poserPilule(ligne.querySelector('.tx-pill'), 'en_cours');
    const compte = ligne.querySelector('[data-tx-photos-compte]');
    if (compte) compte.textContent = photos;

    if (photo instanceof Blob) {
        let galerie = ligne.querySelector('.tx-photos-envoyees');
        if (!galerie) {
            galerie = document.createElement('div');
            galerie.className = 'tx-photos-envoyees';
            ligne.querySelector('.tx-axe-actions')?.before(galerie);
        }
        const vignette = document.createElement('i');
        vignette.style.backgroundImage = `url('${URL.createObjectURL(photo)}')`;
        galerie.prepend(vignette);
    }

    const pieds = parseInt(ligne.dataset.piedsTotal, 10) || 0;
    const terminer = ligne.querySelector('[data-tx-terminer]');
    if (pieds > 0 && photos >= pieds) {
        terminer?.classList.add('tx-a-toucher');
        toast('Chaque pied a sa photo : touche « Terminé ».', 'success');
    } else {
        toast(pieds > 0 ? `Photo ${photos} sur ${pieds} envoyée. Continue !` : 'Photo envoyée. Continue !', 'success');
    }
}

function surPhotoEnvoyee(e) {
    const { taskId, data = {}, pose, photo } = e.detail || {};
    majJournee(data.jour);
    if (data.axe_entier) {
        const ligne = pose?.closest('[data-axe-entier]');
        if (ligne) photoAxeEntier(ligne, data, photo);
        return;
    }
    if (data.task_done === false) return;
    compterCommune(pose);
    const carte = pose?.closest('[data-axe-carte]');
    if (carte && pose.matches('.tx-pied.pose-line')) piedEnvoye(carte, pose, taskId);
}

export function init() {
    if (!document.body.classList.contains('tx')) return;
    initRecherche();

    document.addEventListener('click', (e) => {
        const tete = e.target.closest('[data-tx-axe-toggle]');
        if (tete) { basculerAxe(tete); return; }
        const arrive = e.target.closest('[data-tx-arrive]');
        if (arrive) { e.preventDefault(); arriver(arrive); return; }
        const fin = e.target.closest('[data-tx-terminer]');
        if (fin) { e.preventDefault(); terminer(fin); return; }
        // « Y aller » d'un axe (après la confirmation T7, qui rejoue le clic) :
        // status-changes.js passe le pied en route, le serveur entraîne les
        // autres — on l'affiche sur la carte.
        const go = e.target.closest('.tx-axe-actions [data-go-maps]');
        if (go) {
            const carte = go.closest('[data-axe-carte]');
            const pilule = carte?.querySelector('[data-tx-axe-statut]');
            if (pilule?.classList.contains('tx-pill-planifiee')) statutAxe(carte, 'en_route', 25);
        }
    });

    document.addEventListener('tx:photo-envoyee', surPhotoEnvoyee);
}
