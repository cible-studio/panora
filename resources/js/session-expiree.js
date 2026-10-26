// Session expirée (2026-10-26). Quand la session a expiré, ou que Panora a été
// mis à jour pendant que la page était ouverte, les appels en arrière-plan
// reçoivent 401 (déconnecté) ou 419 (jeton périmé). La plupart des pages les
// laissaient échouer sans rien dire. On propose de recharger : le serveur
// renvoie alors vers la connexion si besoin, puis ramène sur cette page.

const fetchOrigine = window.fetch.bind(window);
let affichee = false;

function memeOrigine(entree) {
    try {
        const url = entree instanceof Request ? entree.url : String(entree);
        return new URL(url, window.location.href).origin === window.location.origin;
    } catch (e) {
        return false;
    }
}

function afficherSessionExpiree() {
    if (affichee) return;
    affichee = true;

    const voile = document.createElement('div');
    voile.setAttribute('role', 'alertdialog');
    voile.setAttribute('aria-modal', 'true');
    voile.setAttribute('aria-labelledby', 'session-expiree-titre');
    voile.style.cssText = 'position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;'
        + 'padding:16px;background:rgba(8,10,14,.72);';
    voile.innerHTML = `
        <div style="width:100%;max-width:420px;padding:26px 24px;border-radius:14px;text-align:center;
                    background:var(--surface,#12151c);color:var(--text,#eaedf5);border:1px solid var(--border2,#2a303f);
                    box-shadow:0 20px 60px rgba(0,0,0,.45);font-family:var(--font-body,'DM Sans',sans-serif);">
            <div style="font-size:30px;margin-bottom:8px;">⏱</div>
            <div id="session-expiree-titre" style="font-size:18px;font-weight:700;margin-bottom:8px;">Votre session a expiré</div>
            <p style="margin:0 0 6px;font-size:14px;line-height:1.55;color:var(--text2,#8a90a2);">
                Panora vous a déconnecté après une période d'inactivité, ou vient d'être mis à jour.
            </p>
            <p style="margin:0 0 18px;font-size:14px;line-height:1.55;color:var(--text2,#8a90a2);">
                Rechargez la page : après vous être reconnecté, vous reviendrez ici.
                Ce qui n'était pas enregistré sur cette page sera perdu.
            </p>
            <button type="button" style="width:100%;padding:12px 16px;border:0;border-radius:10px;cursor:pointer;
                    font:700 14.5px/1.2 inherit;color:#14100a;
                    background:linear-gradient(135deg,var(--accent,#fab80b),var(--accent-dark,#c97d10));">
                Recharger la page
            </button>
        </div>`;
    const bouton = voile.querySelector('button');
    bouton.addEventListener('click', () => window.location.reload());
    document.body.appendChild(voile);
    bouton.focus();
}

window.fetch = async (...args) => {
    const reponse = await fetchOrigine(...args);
    if ((reponse.status === 401 || reponse.status === 419) && memeOrigine(args[0])) {
        afficherSessionExpiree();
    }
    return reponse;
};
