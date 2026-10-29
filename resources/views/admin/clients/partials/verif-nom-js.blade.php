{{-- Vérification du nom d'entreprise pendant la saisie (2026-10-29).
     La règle est côté serveur (App\Support\NomEntreprise, route
     admin.clients.verifier-nom) : ce script ne fait qu'afficher sa réponse.
     Utilisé par les formulaires client et les fenêtres « Nouveau client »
     (réservation, campagne). --}}
@once
<script>
window.PanoraNomClient = (function () {
    const URL_VERIF = @json(route('admin.clients.verifier-nom'));
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const BOITE = 'border-radius:10px;padding:10px 12px;font-size:12px;line-height:1.45;color:var(--text);margin-top:8px;';
    const ROUGE = BOITE + 'background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.35);';
    const AMBRE = BOITE + 'background:rgba(245,158,11,.10);border:1px solid rgba(245,158,11,.40);';
    const BOUTON = 'flex-shrink:0;background:var(--accent);color:#fff;border:0;border-radius:8px;padding:5px 10px;font-size:11.5px;font-weight:700;cursor:pointer;white-space:nowrap;text-decoration:none;';

    async function verifier(nom, sauf) {
        const p = new URLSearchParams({ nom: nom || '' });
        if (sauf) p.set('sauf', sauf);
        try {
            const r = await fetch(URL_VERIF + '?' + p, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            return r.ok ? await r.json() : null;
        } catch (e) {
            return null;
        }
    }

    function ligne(f, utiliser) {
        const camp = f.campagnes ? ' · ' + f.campagnes + ' campagne' + (f.campagnes > 1 ? 's' : '') : ' · aucune campagne';
        const action = utiliser
            ? `<button type="button" data-utiliser="${esc(f.id)}" style="${BOUTON}">Utiliser cette fiche</button>`
            : `<a href="${esc(f.url)}" target="_blank" rel="noopener" style="${BOUTON}">Ouvrir la fiche ↗</a>`;
        return `<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 0 0;margin-top:7px;border-top:1px solid rgba(127,127,127,.18)">
                    <div style="min-width:0"><strong>${esc(f.name)}</strong> <span style="color:var(--text3)">${esc(f.statut)}${camp}</span></div>${action}
                </div>`;
    }

    /**
     * Affiche la réponse du serveur dans `boite` et renvoie 'libre',
     * 'identique' ou 'proche'. options.utiliser(fiche) : bouton « Utiliser
     * cette fiche » (fenêtres rapides) ; sans lui, lien « Ouvrir la fiche ».
     */
    function afficher(boite, data, options = {}) {
        if (!boite) return 'libre';
        if (data?.identique) {
            boite.innerHTML = `<div style="${ROUGE}">❌ <strong>Ce nom existe déjà dans Panora.</strong> Utilisez cette fiche plutôt que d'en créer une seconde.${ligne(data.identique, options.utiliser)}</div>`;
        } else if (data?.proches?.length) {
            boite.innerHTML = `<div style="${AMBRE}">⚠️ <strong>Ça ressemble à une fiche existante.</strong>${data.proches.map(f => ligne(f, options.utiliser)).join('')}
                    <label style="display:flex;gap:7px;align-items:center;margin-top:10px;cursor:pointer;font-weight:600">
                        <input type="checkbox" name="nom_proche_confirme" value="1" data-nom-confirme> C'est une autre entreprise
                    </label></div>`;
        } else {
            boite.innerHTML = '';
            boite.style.display = 'none';
            return 'libre';
        }
        boite.style.display = 'block';
        boite.querySelectorAll('[data-utiliser]').forEach(b => b.addEventListener('click', () => {
            const f = [data.identique, ...(data.proches || [])].find(x => x && String(x.id) === b.dataset.utiliser);
            if (f && options.utiliser) options.utiliser(f);
        }));
        return data.identique ? 'identique' : 'proche';
    }

    /**
     * Vérifie le champ à chaque pause de saisie. options.sauf : fiche
     * renommée ; options.initial : nom actuel (pas d'alerte tant qu'il
     * n'est pas modifié).
     */
    function brancher(champ, boite, options = {}) {
        if (!champ || !boite || champ.dataset.nomVerifie) return;
        champ.dataset.nomVerifie = '1';
        let minuteur = null, jeton = 0;
        const lancer = () => {
            clearTimeout(minuteur);
            minuteur = setTimeout(async () => {
                const nom = champ.value.trim(), n = ++jeton;
                const inchange = options.initial !== undefined && nom.toUpperCase() === String(options.initial).trim().toUpperCase();
                if (nom.length < 2 || inchange) { afficher(boite, null, options); return; }
                const data = await verifier(nom, options.sauf);
                if (n === jeton && data) afficher(boite, data, options); // réponse dépassée : ignorée
            }, 350);
        };
        champ.addEventListener('input', lancer);
        if (champ.value.trim()) lancer();
    }

    /** « C'est une autre entreprise » coché dans la boîte ? */
    const confirme = boite => !!boite?.querySelector('[data-nom-confirme]')?.checked;

    /** Vide la boîte (réouverture d'une fenêtre). */
    const vider = boite => afficher(boite, null);

    return { verifier, afficher, brancher, confirme, vider };
})();
</script>
@endonce
