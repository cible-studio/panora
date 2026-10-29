{{--
    Confirmation avant envoi au client (2026-10-29) : adresses exactes +
    aperçu du mail, fournis par admin.apercu-envoi (mêmes règles et même
    mail que l'envoi réel). Inclure une fois par page, puis :
      formulaire : onsubmit="return ApercuEnvoi.formulaire(event, this, '{{ url }}', 'Titre', 'Envoyer')"
      code JS    : ApercuEnvoi.ouvrir({ url, titre, bouton, note, onConfirm })
--}}
@once
<div id="apercu-envoi" onclick="if (event.target === this) ApercuEnvoi.fermer()"
     style="display:none;position:fixed;inset:0;z-index:10030;background:rgba(15,23,42,.5);backdrop-filter:blur(3px);align-items:center;justify-content:center;padding:16px">
    <div role="dialog" aria-modal="true" aria-labelledby="apercu-envoi-titre"
         style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:720px;max-height:calc(100vh - 32px);display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,.3);overflow:hidden">
        <div style="padding:18px 22px 12px;border-bottom:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                <div id="apercu-envoi-titre" style="font-size:16px;font-weight:800;color:var(--text)">📧 Envoyer au client</div>
                <button type="button" onclick="ApercuEnvoi.fermer()" aria-label="Fermer"
                        style="background:none;border:0;font-size:20px;line-height:1;color:var(--text3);cursor:pointer">×</button>
            </div>
            <div data-chargement style="margin-top:12px;font-size:13px;color:var(--text3)">Préparation de l'aperçu…</div>
            <div data-entete style="display:none;margin-top:12px;font-size:13px;color:var(--text2);line-height:1.6">
                <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap">
                    <span style="color:var(--text3);min-width:52px">À</span>
                    <span data-destinataires style="display:flex;gap:6px;flex-wrap:wrap"></span>
                </div>
                <div data-ligne-copie style="display:none;gap:8px;align-items:flex-start;flex-wrap:wrap;margin-top:4px">
                    <span style="color:var(--text3);min-width:52px">Copie</span>
                    <span data-copie style="display:flex;gap:6px;flex-wrap:wrap"></span>
                </div>
                <div style="display:flex;gap:8px;margin-top:4px">
                    <span style="color:var(--text3);min-width:52px">Objet</span>
                    <strong data-sujet style="color:var(--text)"></strong>
                </div>
                <div data-note style="display:none;margin-top:8px;font-size:12px;color:var(--text3)"></div>
            </div>
            <div data-erreur style="display:none;margin-top:12px;padding:10px 12px;border-radius:8px;background:rgba(239,68,68,.1);color:#ef4444;font-size:13px"></div>
        </div>
        <div style="flex:1;min-height:0;background:#F4F4F4">
            <iframe data-cadre title="Aperçu du mail" sandbox="allow-same-origin"
                    style="display:block;width:100%;height:min(460px, calc(100vh - 300px));border:0;background:#F4F4F4"></iframe>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end">
            <button type="button" class="btn btn-ghost btn-sm" onclick="ApercuEnvoi.fermer()">Annuler</button>
            <button type="button" class="btn btn-primary btn-sm" data-envoyer disabled>Envoyer</button>
        </div>
    </div>
</div>
<script>
window.ApercuEnvoi = (function () {
    var suite = null;
    function el(sel) { return document.querySelector('#apercu-envoi ' + sel); }
    function pastilles(cible, adresses) {
        cible.innerHTML = '';
        adresses.forEach(function (a) {
            var s = document.createElement('span');
            s.textContent = a;
            s.style.cssText = 'padding:2px 9px;border-radius:999px;background:var(--surface2);border:1px solid var(--border);color:var(--text);font-size:12px;';
            cible.appendChild(s);
        });
    }
    function fermer() {
        var f = document.getElementById('apercu-envoi');
        if (f) f.style.display = 'none';
        suite = null;
    }
    function ouvrir(o) {
        var f = document.getElementById('apercu-envoi');
        if (f.parentNode !== document.body) document.body.appendChild(f); // jamais enfermée dans un bloc
        suite = o.onConfirm || null;
        el('#apercu-envoi-titre').textContent = o.titre || '📧 Envoyer au client';
        var envoyer = el('[data-envoyer]');
        envoyer.textContent = o.bouton || 'Envoyer';
        envoyer.disabled = true;
        el('[data-chargement]').style.display = 'block';
        el('[data-entete]').style.display = 'none';
        el('[data-erreur]').style.display = 'none';
        el('[data-cadre]').srcdoc = '';
        var note = el('[data-note]');
        note.textContent = o.note || '';
        note.style.display = o.note ? 'block' : 'none';
        f.style.display = 'flex';

        fetch(o.url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (d) {
                el('[data-chargement]').style.display = 'none';
                el('[data-entete]').style.display = 'block';
                pastilles(el('[data-destinataires]'), d.destinataires);
                pastilles(el('[data-copie]'), d.copie || []);
                el('[data-ligne-copie]').style.display = (d.copie || []).length ? 'flex' : 'none';
                el('[data-sujet]').textContent = d.sujet || '—';
                el('[data-cadre]').srcdoc = d.html || '';
                if (!d.destinataires.length) {
                    var e = el('[data-erreur]');
                    e.textContent = "Aucune adresse : ni interlocuteur, ni contact coché, ni adresse sur la fiche client. Le mail ne peut pas partir.";
                    e.style.display = 'block';
                    return;
                }
                envoyer.disabled = false;
            })
            .catch(function () {
                el('[data-chargement]').style.display = 'none';
                var e = el('[data-erreur]');
                e.textContent = "L'aperçu n'a pas pu être préparé. Rechargez la page et réessayez.";
                e.style.display = 'block';
            });
    }
    document.addEventListener('click', function (ev) {
        if (ev.target && ev.target.matches && ev.target.matches('#apercu-envoi [data-envoyer]') && suite) {
            ev.target.disabled = true;
            ev.target.textContent = 'Envoi…';
            var action = suite;
            suite = null;
            action();
        }
    });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') fermer(); });
    return {
        ouvrir: ouvrir,
        fermer: fermer,
        /** Pour un <form> : bloque l'envoi direct, montre l'aperçu, envoie à la confirmation. */
        formulaire: function (event, form, url, titre, bouton, note) {
            event.preventDefault();
            ouvrir({ url: url, titre: titre, bouton: bouton, note: note, onConfirm: function () { form.submit(); } });
            return false;
        },
    };
})();
</script>
@endonce
