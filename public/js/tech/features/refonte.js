// public/js/tech/features/refonte.js — Refonte de l'espace technicien
// (2026-11-12, maquette validée) : « Ma journée » et les axes.
//
// Étape 1
//   1. Recherche sur les poses affichées (data-search + texte visible).
//   2. Carte d'axe : plier / déplier.
//   3. « Je suis arrivé » sur un axe : progression 50 % sur son premier pied
//      à faire — le serveur démarre tous ses pieds (PoseTask::idsFreresDAxe).
//   4. « Y aller » / « Je suis arrivé », d'où qu'ils viennent (carte, tiroir) :
//      la carte de l'axe suit (événement « tx:statut »).
//   5. « Terminé » sur un axe entier (contrat d'avant le découpage, choix 4b).
//   6. Photo envoyée (événement « tx:photo-envoyee » d'upload.js) : compteur
//      du jour (TechCarte::journee), compteur de la commune, carte de l'axe.
// Étape 2
//   7. Plus de rechargement après une photo : la pose faite quitte la liste,
//      « Maintenant » est remplacée par celle que choisit le serveur (même
//      règle, même gabarit : TechSpaceController::poseSuivante), et la
//      feuille « Photo envoyée » propose de continuer.
//   8. Le menu de l'initiale (feuille #tx-menu).
//   9. Une alerte touchée mène à sa pose (événement « tx:alerte », sm2c.js).
//
// Aucun calcul métier ici : statuts, compteurs et pose suivante viennent du
// serveur.

import { postJson, urlForTask } from '../core/api.js';
import { toast } from '../core/ui-helpers.js';

const CFG = () => window.TECH_CONFIG || {};
const sansAccents = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

// Pilule de statut (mêmes libellés que les partials _tx_*).
const PILULES = {
    planifiee: ['tx-pill-planifiee', 'Planifiée'],
    en_route:  ['tx-pill-route', 'En route'],
    en_cours:  ['tx-pill-place', 'Sur place'],
    realisee:  ['tx-pill-fait', 'Terminé'],
};

function poserPilule(el, statut) {
    if (!el) return;
    const [cls, libelle] = PILULES[statut] || PILULES.planifiee;
    el.classList.remove('tx-pill-planifiee', 'tx-pill-route', 'tx-pill-place', 'tx-pill-fait', 'tx-pill-retard');
    el.classList.add(cls);
    el.textContent = libelle;
}

// ── Feuilles du bas ──────────────────────────────────────────────────
function ouvrirFeuille(id) {
    const fond = document.getElementById(id);
    if (!fond) return null;
    fond.hidden = false;
    fond.querySelector('[data-tx-envoi-continuer], [data-tx-fermer]')?.focus({ preventScroll: true });
    return fond;
}

function fermerFeuille(fond) {
    if (fond) fond.hidden = true;
}

// ── 1. Recherche ─────────────────────────────────────────────────────
let filtrerRecherche = () => {};

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
    filtrerRecherche = () => {
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
    champ.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(filtrerRecherche, 120); });
    champ.addEventListener('search', () => filtrerRecherche());
}

function effacerRecherche() {
    const champ = document.getElementById('tx-recherche');
    if (champ && champ.value) {
        champ.value = '';
        filtrerRecherche();
    }
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
    const carte = tete?.closest('.tx-axe');
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

// ── 4. Statut changé ailleurs (carte, tiroir, « Maintenant ») ────────
function surStatut(e) {
    const { taskId, status } = e.detail || {};
    if (!taskId || !PILULES[status]) return;
    const ligne = document.querySelector(`.pose-line[data-task-id="${taskId}"]`);
    const carte = ligne?.closest('[data-axe-carte]')
        || document.querySelector(`.tx-axe-actions[data-task-id="${taskId}"]`)?.closest('[data-axe-carte]');
    if (carte) {
        // Les pieds ne reculent jamais : « en route » n'efface pas « sur place ».
        const pilule = carte.querySelector('[data-tx-axe-statut]');
        if (status === 'en_route' && !pilule?.classList.contains('tx-pill-planifiee')) return;
        statutAxe(carte, status, status === 'en_cours' ? 50 : 25);
        return;
    }
    // « Maintenant » : sa pilule sur la photo.
    const hero = ligne?.closest('.tx-hero');
    if (hero) {
        let pilule = hero.querySelector('.tx-hero-photo .tx-pill');
        if (!pilule) {
            pilule = document.createElement('span');
            pilule.className = 'tx-pill';
            hero.querySelector('.tx-hero-photo')?.append(pilule);
        }
        poserPilule(pilule, status);
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
        majJournee(data.jour);
        compterCommune(ligne);
        poseFaite(ligne, data, 'Axe terminé');
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

// Un pied photographié : sa ligne devient « Envoyée », la route avance, le
// pied suivant passe devant. Rend true quand l'axe n'a plus de pied à faire.
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
        return true;
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
    return false;
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
    const fin = ligne.querySelector('[data-tx-terminer]');
    if (pieds > 0 && photos >= pieds) {
        fin?.classList.add('tx-a-toucher');
        toast('Chaque pied a sa photo : touche « Terminé ».', 'success');
    } else {
        toast(pieds > 0 ? `Photo ${photos} sur ${pieds} envoyée. Continue !` : 'Photo envoyée. Continue !', 'success');
    }
}

// ── 7. Une pose faite : elle quitte la liste, « Maintenant » change ──
// Retire une entrée de sa commune (après le fondu), et la commune si vide.
function retirerEntree(entree, delai = 450) {
    if (!entree) return;
    entree.classList.add('tx-envoyee');
    setTimeout(() => {
        const section = entree.closest('.tx-commune');
        entree.remove();
        if (section && !section.querySelector('.tx-liste > *')) section.remove();
    }, delai);
}

// La nouvelle « Maintenant », rendue par le serveur (_tx_maintenant_section).
function majMaintenant(suivante) {
    const ancienne = document.querySelector('[data-tx-maintenant]');
    if (!suivante) { ancienne?.remove(); return; }
    if (!suivante.html) return; // au-delà du plafond d'affichage : on garde la liste
    const gabarit = document.createElement('template');
    gabarit.innerHTML = suivante.html.trim();
    const neuve = gabarit.content.firstElementChild;
    if (!neuve) return;
    // Elle passe en tête : on la retire de sa commune.
    const cible = suivante.cle
        ? `[data-axe-carte="${suivante.cle}"]`
        : `.pose-line[data-task-id="${suivante.task_id}"]`;
    document.querySelectorAll(`.tx-commune:not([data-tx-maintenant]) .tx-liste > ${cible}`).forEach((el) => {
        const section = el.closest('.tx-commune');
        el.remove();
        if (section && !section.querySelector('.tx-liste > *')) section.remove();
    });
    if (ancienne) ancienne.replaceWith(neuve);
    else (document.querySelector('.tx-commune') || document.getElementById('tx-vide-recherche'))?.before(neuve);
}

const distanceLisible = (m) => (m < 950
    ? `${Math.max(10, Math.round(m / 10) * 10)} m`
    : `${(m / 1000).toFixed(1).replace('.', ',')} km`);

// Feuille « Photo envoyée » : le compteur du jour et la pose suivante.
let suivanteProposee = null;

function feuilleEnvoi(data, titre) {
    const fond = document.getElementById('tx-envoi');
    if (!fond) return;
    const s = data.suivante || null;
    suivanteProposee = s;
    fond.querySelector('[data-tx-envoi-titre]').textContent = titre;
    fond.querySelector('[data-tx-envoi-jour]').textContent = data.jour
        ? `${data.jour.faites} / ${data.jour.total} poses faites aujourd'hui` : '';
    const bloc = fond.querySelector('[data-tx-envoi-suivante]');
    bloc.hidden = !s;
    fond.querySelector('[data-tx-envoi-fini]').hidden = !!s;
    fond.querySelector('[data-tx-envoi-continuer]').hidden = !s;
    if (s) {
        fond.querySelector('[data-tx-envoi-ref]').textContent = `${s.reference || '—'} · pose suivante`;
        fond.querySelector('[data-tx-envoi-nom]').textContent = s.nom || '';
        fond.querySelector('[data-tx-envoi-sous]').textContent = [s.commune,
            s.distance_m != null ? `à ${distanceLisible(s.distance_m)}` : null].filter(Boolean).join(' · ');
        fond.querySelector('[data-tx-envoi-photo]').style.backgroundImage = s.photo_url ? `url('${s.photo_url}')` : '';
    }
    ouvrirFeuille('tx-envoi');
}

// Pose faite (photo, dernier pied d'un axe, « Terminé ») : même suite.
function poseFaite(entreeOuLigne, data, titre) {
    retirerEntree(entreeOuLigne?.closest('.tx-liste > *'));
    if (!('suivante' in data)) { toast(data.message || 'Photo envoyée.', 'success'); return; }
    majMaintenant(data.suivante);
    // Dernière pose du jour : sm2c.js affiche l'écran « fin de journée ».
    if (!data.is_last_pose_of_day) feuilleEnvoi(data, titre);
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
    if (carte && pose.matches('.tx-pied.pose-line')) {
        if (piedEnvoye(carte, pose, taskId)) poseFaite(carte, data, 'Axe terminé');
        return;
    }
    poseFaite(pose, data, 'Photo envoyée');
}

// ── Ouvrir une pose (feuille « Continuer », alertes) ─────────────────
function ouvrirPose(taskId) {
    const ligne = document.querySelector(`.pose-line[data-task-id="${taskId}"]`);
    if (!ligne) return false;
    effacerRecherche();
    const carte = ligne.closest('[data-axe-carte]');
    if (carte) {
        // Un pied : on déplie son axe et on montre le pied (la carte porte
        // les gestes de l'axe).
        if (!carte.classList.contains('ouvert')) basculerAxe(carte.querySelector('[data-tx-axe-toggle]'));
        ligne.scrollIntoView({ behavior: 'smooth', block: 'center' });
        ligne.classList.add('tx-vient');
        setTimeout(() => ligne.classList.remove('tx-vient'), 1300);
        return true;
    }
    ligne.scrollIntoView({ behavior: 'smooth', block: 'center' });
    // Le tiroir s'ouvre au toucher de la ligne (pose-drawer.js).
    ligne.click();
    return true;
}

// ── 9. Alertes ───────────────────────────────────────────────────────
function surAlerte(e) {
    const { type, taskId } = e.detail || {};
    const pagePhotos = CFG().routes?.piges;
    if (type === 'photo_validated') {
        if (pagePhotos) window.location.href = pagePhotos;
        return;
    }
    if (type === 'photo_rejected') {
        // Le tiroir « Photos à refaire » (t9-rejected.js), s'il y en a encore.
        const t9 = document.querySelector('[data-action="open-t9"]');
        if (t9) { t9.click(); return; }
    }
    if (taskId && ouvrirPose(taskId)) return;
    toast('Cette pose n\'est plus dans ta liste.', 'info');
}

// ── 10. Liens d'autres pages (étape 3) ───────────────────────────────
//   ?axe=<axe>-<campagne> : « Voir » sur un arrêt d'axe de la carte → la
//                            carte de l'axe dépliée ;
//   ?alertes=1            : « Alertes » depuis Carte / Mes photos → le tiroir ;
//   ?refaire=1            : « Refaire » depuis Mes photos → « Photos à refaire ».
// (?focus=task_<id> reste géré par pose-drawer.js.)
function liensEntrants() {
    const params = new URLSearchParams(location.search);
    const cle = params.get('axe');
    const alertes = params.get('alertes');
    const refaire = params.get('refaire');
    if (!cle && !alertes && !refaire) return;
    params.delete('axe');
    params.delete('alertes');
    params.delete('refaire');
    // Un rechargement ne doit pas rouvrir.
    history.replaceState(null, '', location.pathname + (params.toString() ? `?${params}` : '') + location.hash);

    if (cle) {
        const carte = document.querySelector(`[data-axe-carte="${CSS.escape(cle)}"]`);
        if (carte) {
            if (!carte.classList.contains('ouvert')) basculerAxe(carte.querySelector('[data-tx-axe-toggle]'));
            setTimeout(() => carte.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
            carte.classList.add('tx-vient');
        } else {
            toast('Cet axe n\'est plus dans ta liste.', 'info');
        }
    }
    if (alertes) document.querySelector('.tx-cloche[data-action="open-b3"]')?.click();
    if (refaire) {
        const t9 = document.querySelector('[data-action="open-t9"]');
        if (t9) t9.click();
        else toast('Plus aucune photo à refaire : elles sont toutes reparties.', 'success');
    }
}

// ── 8. Menu ──────────────────────────────────────────────────────────
function ouvrirMenu() {
    const fond = document.getElementById('tx-menu');
    if (!fond) return;
    // Les interrupteurs reprennent les préférences enregistrées (mêmes clés
    // que sm2c.js, qui les bascule au toucher).
    fond.querySelectorAll('[data-pref-key]').forEach((b) => {
        let actif = false;
        try { actif = localStorage.getItem(b.dataset.prefKey) === 'true'; } catch (err) { /* navigation privée */ }
        b.classList.toggle('is-on', actif);
        b.setAttribute('aria-checked', actif ? 'true' : 'false');
    });
    ouvrirFeuille('tx-menu');
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
        if (e.target.closest('[data-action="open-tx-menu"]')) { e.preventDefault(); ouvrirMenu(); return; }

        // Feuilles : « Continuer », fermer, fond.
        if (e.target.closest('[data-tx-envoi-continuer]')) {
            fermerFeuille(document.getElementById('tx-envoi'));
            if (suivanteProposee && !ouvrirPose(suivanteProposee.task_id)) {
                document.querySelector('[data-tx-maintenant]')?.scrollIntoView({ behavior: 'smooth' });
            }
            return;
        }
        const fond = e.target.closest('.tx-feuille-fond[id]');
        if (fond && (e.target === fond || e.target.closest('[data-tx-fermer]'))) {
            fermerFeuille(fond);
            // Une préférence vient de basculer (sm2c.js) : l'interrupteur suit.
        }
        const pref = e.target.closest('#tx-menu [data-pref-key]');
        if (pref) {
            setTimeout(() => pref.setAttribute('aria-checked', pref.classList.contains('is-on') ? 'true' : 'false'));
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.tx-feuille-fond[id]:not([hidden])').forEach(fermerFeuille);
    });

    document.addEventListener('tx:statut', surStatut);
    document.addEventListener('tx:photo-envoyee', surPhotoEnvoyee);
    document.addEventListener('tx:alerte', surAlerte);

    liensEntrants();
}
