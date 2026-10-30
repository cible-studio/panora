{{--
    Regrouper deux fiches client (2026-10-30) — Direction seulement.
    La fenêtre n'applique aucune règle elle-même : elle affiche l'aperçu
    calculé par le serveur (App\Services\Clients\RegroupementClients) et
    renvoie les choix ; le serveur revérifie tout au moment de regrouper.
--}}
<div id="modal-regrouper" class="modal-overlay" style="display:none"
     onclick="if(event.target===this) Regrouper.fermer()">
    <div class="modal" style="max-width:680px;width:100%;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title">🔀 Regrouper avec une autre fiche
                <span title="Pour une même entreprise enregistrée deux fois : tout ce qui appartient à la fiche retirée (campagnes, réservations, factures, interlocuteurs…) passe sur la fiche gardée, puis la fiche retirée va à la corbeille. Aucun mail n'est envoyé."
                      style="cursor:help;font-size:13px;color:var(--text3);">ⓘ</span>
            </div>
            <button class="modal-close" onclick="Regrouper.fermer()">✕</button>
        </div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto;font-size:13px;">
            {{-- Étape 1 : l'autre fiche --}}
            <div id="rg-etape-choix">
                <input type="search" id="rg-recherche" class="filter-select" style="width:100%;margin-bottom:10px;"
                       placeholder="Chercher une fiche (nom, NCC, e-mail…)" autocomplete="off"
                       oninput="Regrouper.chercher(this.value)">
                <div id="rg-titre-liste" style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;"></div>
                <div id="rg-fiches" style="display:flex;flex-direction:column;gap:6px;"></div>
            </div>

            {{-- Étape 2 : aperçu --}}
            <div id="rg-etape-apercu" style="display:none;">
                <button type="button" class="btn btn-ghost btn-sm" style="margin-bottom:10px;" onclick="Regrouper.retourChoix()">‹ Autre fiche</button>
                <div style="font-weight:700;margin-bottom:6px;">Quelle fiche garder ?</div>
                <div id="rg-garder" style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px;"></div>
                <div id="rg-apercu"></div>
            </div>
            <div id="rg-erreur" style="display:none;margin-top:10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:8px;padding:10px;color:var(--red);"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="Regrouper.fermer()">Annuler</button>
            <button class="btn btn-primary" id="rg-valider" disabled onclick="Regrouper.valider()">🔀 Regrouper</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.Regrouper = (function () {
    const urls = {
        fiches:    @js(route('admin.clients.regrouper.fiches', $client)),
        apercu:    @js(route('admin.clients.regrouper.apercu', $client)),
        regrouper: @js(route('admin.clients.regrouper', $client)),
    };
    const ici = { id: {{ $client->id }}, name: @js($client->name), campagnes: {{ (int) ($client->campaigns_count ?? 0) }} };
    const jeton = @js(csrf_token());
    let autre = null, garder = 'cette', apercu = null, minuteur = null, demande = 0;

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const pluriel = (n, mot) => n + ' ' + mot + (n > 1 ? 's' : '');

    async function lire(url, options) {
        const r = await fetch(url, Object.assign({ headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }, options || {}));
        const d = await r.json().catch(() => ({}));
        if (!r.ok) {
            throw new Error(d.errors ? Object.values(d.errors).flat().join(' ') : (d.message || 'Erreur du serveur.'));
        }
        return d;
    }

    function erreur(message) {
        $('rg-erreur').style.display = message ? 'block' : 'none';
        $('rg-erreur').textContent = message || '';
    }

    function ouvrir() {
        autre = null; apercu = null; garder = 'cette';
        $('rg-recherche').value = '';
        $('rg-etape-choix').style.display = 'block';
        $('rg-etape-apercu').style.display = 'none';
        $('rg-valider').disabled = true;
        erreur('');
        $('modal-regrouper').style.display = 'flex';
        chercher('', true);
        setTimeout(() => $('rg-recherche').focus(), 50);
    }

    function fermer() { $('modal-regrouper').style.display = 'none'; }

    function chercher(q, tout_de_suite) {
        clearTimeout(minuteur);
        minuteur = setTimeout(async () => {
            const n = ++demande;
            try {
                const d = await lire(urls.fiches + '?q=' + encodeURIComponent(q.trim()));
                if (n !== demande) return;
                $('rg-titre-liste').textContent = d.suggestions
                    ? (d.fiches.length ? 'Fiches qui ressemblent' : 'Aucune fiche ne ressemble : cherchez-la')
                    : (d.fiches.length ? 'Résultats' : 'Aucune fiche trouvée');
                $('rg-fiches').innerHTML = d.fiches.map((f, i) => `
                    <button type="button" class="btn btn-ghost" data-i="${i}" style="justify-content:space-between;text-align:left;width:100%;">
                        <span><strong>${esc(f.name)}</strong> <span style="color:var(--text3);">· ${esc(f.statut)}</span></span>
                        <span style="color:var(--text3);font-size:12px;">${pluriel(f.campagnes, 'campagne')} · ${pluriel(f.reservations, 'réservation')}${f.cree ? ' · créée le ' + esc(f.cree) : ''}</span>
                    </button>`).join('');
                $('rg-fiches').querySelectorAll('button').forEach(b => b.onclick = () => choisir(d.fiches[b.dataset.i]));
            } catch (e) { erreur(e.message); }
        }, tout_de_suite ? 0 : 300);
    }

    function choisir(f) {
        autre = f;
        // Par défaut, on garde la fiche qui porte le plus de campagnes.
        garder = f.campagnes > ici.campagnes ? 'autre' : 'cette';
        $('rg-etape-choix').style.display = 'none';
        $('rg-etape-apercu').style.display = 'block';
        $('rg-garder').innerHTML = [['cette', ici.name], ['autre', f.name]].map(([v, nom]) => `
            <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                <input type="radio" name="rg-garder" value="${v}" ${v === garder ? 'checked' : ''}>
                <span>Garder <strong>${esc(nom)}</strong>${v === 'cette' ? ' (cette fiche)' : ''}</span>
            </label>`).join('');
        $('rg-garder').querySelectorAll('input').forEach(r => r.onchange = () => { garder = r.value; charger(); });
        charger();
    }

    function retourChoix() {
        $('rg-etape-apercu').style.display = 'none';
        $('rg-etape-choix').style.display = 'block';
        $('rg-valider').disabled = true;
        erreur('');
    }

    async function charger() {
        erreur('');
        $('rg-valider').disabled = true;
        $('rg-apercu').innerHTML = '<div style="color:var(--text3);">Calcul…</div>';
        const n = ++demande;
        try {
            const a = await lire(urls.apercu + '?avec=' + autre.id + '&garder=' + garder);
            if (n !== demande) return;
            apercu = a;
            afficher(a);
        } catch (e) { $('rg-apercu').innerHTML = ''; erreur(e.message); }
    }

    function bloc(couleur, html) {
        const c = { rouge: ['rgba(239,68,68,.08)', 'rgba(239,68,68,.25)'], ambre: ['rgba(232,160,32,.10)', 'rgba(232,160,32,.35)'], gris: ['var(--surface2)', 'var(--border)'] }[couleur];
        return `<div style="background:${c[0]};border:1px solid ${c[1]};border-radius:8px;padding:10px;margin-bottom:10px;">${html}</div>`;
    }

    function afficher(a) {
        const R = esc(a.retirer.name), G = esc(a.garder.name);
        let h = `<div style="margin-bottom:10px;">« ${R} » passe sur « ${G} », puis va à la corbeille. Statut final : <strong>${esc(a.statut)}</strong>.</div>`;

        if (a.refus.length) {
            h += bloc('rouge', '<strong>Regroupement impossible</strong><ul style="margin:6px 0 0 18px;">' + a.refus.map(r => `<li>${esc(r)}</li>`).join('') + '</ul>');
        }

        h += bloc('gris', '<strong>Ce qui passe sur « ' + G + ' »</strong><br>' + (a.compteurs.length
            ? a.compteurs.map(c => `${c.nombre} ${esc(c.libelle)}`).join(' · ')
            : 'Rien : la fiche est vide.'));

        const ct = a.contacts;
        if (ct.deplaces || ct.fusionnes.length) {
            h += bloc('gris', '<strong>Interlocuteurs</strong><br>'
                + (ct.deplaces ? pluriel(ct.deplaces, 'interlocuteur') + ' déplacé' + (ct.deplaces > 1 ? 's' : '') + '. ' : '')
                + (ct.fusionnes.length ? 'Déjà présents (même adresse), gardés une seule fois : ' + ct.fusionnes.map(esc).join(', ') + '. ' : '')
                + (ct.decoches ? `<span title="Règle du 29/10 : seul l'interlocuteur d'un dossier reçoit les mails de suivi.">${pluriel(ct.decoches, 'interlocuteur')} ne recevr${ct.decoches > 1 ? 'ont' : 'a'} plus les mails de suivi ⓘ</span>` : ''));
        }

        if (a.champs.length) {
            h += bloc('gris', '<strong>Informations de la fiche</strong>'
                + '<table style="width:100%;margin-top:6px;border-collapse:collapse;font-size:12px;">'
                + `<tr style="color:var(--text3);text-align:left;"><th style="padding:3px 4px;">Champ</th><th style="padding:3px 4px;">${G}</th><th style="padding:3px 4px;">${R}</th><th style="padding:3px 4px;">Résultat</th></tr>`
                + a.champs.map(c => `<tr style="border-top:1px solid var(--border);">
                        <td style="padding:3px 4px;">${esc(c.libelle)}</td>
                        <td style="padding:3px 4px;">${c.garde ? esc(c.garde) : '<span style="color:var(--text3);">vide</span>'}</td>
                        <td style="padding:3px 4px;">${esc(c.retire)}</td>
                        <td style="padding:3px 4px;">${c.action === 'complete' ? '✅ repris' : '<span title="La fiche gardée garde sa valeur ; celle de l\'autre fiche est notée au journal du regroupement.">conservé ⓘ</span>'}</td>
                    </tr>`).join('')
                + '</table>');
        }

        if (a.factures_emises.length) {
            h += bloc('ambre', `<strong>${pluriel(a.factures_emises.length, 'facture')} déjà émise${a.factures_emises.length > 1 ? 's' : ''}</strong> : ${a.factures_emises.map(esc).join(', ')}<br>`
                + `Elles passent sur « ${G} » ; leur PDF garde le nom et le NCC de leur émission.`
                + `<label style="display:flex;gap:8px;align-items:center;margin-top:8px;cursor:pointer;"><input type="checkbox" id="rg-factures"> J'ai compris</label>`);
        }

        if (a.liaisons.length) {
            h += bloc('gris', '<strong>Réservations et campagnes sur les mêmes panneaux</strong>'
                + a.liaisons.map(l => l.possible
                    ? `<label style="display:flex;gap:8px;align-items:flex-start;margin-top:8px;cursor:pointer;">
                           <input type="checkbox" class="rg-liaison" value="${l.reservation_id}:${l.campagne_id}" style="margin-top:3px;">
                           <span>C'est la même affaire : relier la réservation <strong>${esc(l.reservation)}</strong> à la campagne <strong>${esc(l.campagne)}</strong>
                           <span title="Une fois reliées, annuler cette réservation annulera aussi la campagne, comme pour toute campagne créée depuis une réservation." style="cursor:help;color:var(--text3);">ⓘ</span></span>
                       </label>`
                    : `<div style="margin-top:8px;color:var(--text3);">${esc(l.reservation)} et ${esc(l.campagne)} restent séparées : ${l.raisons.map(esc).join(' ; ')}.</div>`).join(''));
        }

        $('rg-apercu').innerHTML = h;
        const maj = () => {
            $('rg-valider').disabled = a.refus.length > 0 || (a.factures_emises.length > 0 && !$('rg-factures')?.checked);
        };
        $('rg-factures')?.addEventListener('change', maj);
        maj();
    }

    async function valider() {
        if (!apercu || !autre) return;
        if (!confirm(`Regrouper « ${apercu.retirer.name} » dans « ${apercu.garder.name} » ? « ${apercu.retirer.name} » ira à la corbeille.`)) return;
        const fd = new FormData();
        fd.append('_token', jeton);
        fd.append('avec', autre.id);
        fd.append('garder', garder);
        fd.append('factures_comprises', $('rg-factures')?.checked ? '1' : '0');
        document.querySelectorAll('.rg-liaison:checked').forEach(c => fd.append('liaisons[]', c.value));
        $('rg-valider').disabled = true;
        erreur('');
        try {
            const d = await lire(urls.regrouper, { method: 'POST', body: fd });
            window.location = d.url;
        } catch (e) {
            erreur(e.message);
            $('rg-valider').disabled = false;
        }
    }

    return { ouvrir, fermer, chercher, retourChoix, valider };
})();
document.addEventListener('keydown', e => { if (e.key === 'Escape') Regrouper.fermer(); });
</script>
@endpush
