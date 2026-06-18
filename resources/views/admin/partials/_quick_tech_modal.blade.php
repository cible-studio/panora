{{-- ════ Modale "Création rapide d'un technicien" — réutilisable ════
     Bloc pose tasks (2026-06-18). Calque sur la modale existante du module
     maintenance pour réutiliser l'endpoint AJAX admin.maintenances.quick-tech
     (qui valide name + email/whatsapp/phone, génère le code agent TT-XXX
     auto via User::generateAgentCode('technique'), et renvoie un JSON
     {ok, technician, plain_password}).

     Paramètres @include :
       target_select_id : string  — id du <select name="assigned_user_id">
                                    à enrichir après création (option append
                                    + selection).

     Hook JS exposé : window.openQuickTechModal()  (idempotent — défini une
     seule fois grâce à @once).
--}}
@php
    $targetSelectId = $target_select_id ?? 'qt-target-select';
@endphp

<div id="qt-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9000;align-items:center;justify-content:center;padding:16px"
     onclick="if(event.target===this)closeQuickTechModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:480px;display:flex;flex-direction:column;overflow:hidden"
         onclick="event.stopPropagation()">
        <div style="padding:14px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <h3 style="font-size:15px;font-weight:700;margin:0">🔧 Nouveau technicien</h3>
            <button type="button" onclick="closeQuickTechModal()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3)">✕</button>
        </div>
        <div style="padding:18px 22px">
            <div style="background:rgba(232,160,32,.06);border:1px solid rgba(232,160,32,.20);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:11.5px;color:var(--text2);line-height:1.5">
                💡 Création rapide : un code agent <strong>TT-XXX</strong> est généré automatiquement.
                Le mot de passe temporaire est affiché 1 fois pour transmission au technicien.
            </div>
            <div style="margin-bottom:12px">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-bottom:5px">Nom complet *</label>
                <input type="text" id="qt-name" placeholder="Prénom Nom" maxlength="100"
                       style="width:100%;height:38px;padding:0 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text);box-sizing:border-box">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-bottom:5px">Téléphone</label>
                    <input type="text" id="qt-phone" placeholder="07 07 07 07 07" maxlength="30"
                           style="width:100%;height:38px;padding:0 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text);box-sizing:border-box">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-bottom:5px">WhatsApp</label>
                    <input type="text" id="qt-whatsapp" placeholder="0707070707" maxlength="30"
                           style="width:100%;height:38px;padding:0 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text);box-sizing:border-box">
                </div>
            </div>
            <div style="margin-bottom:12px">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-bottom:5px">Email (optionnel)</label>
                <input type="email" id="qt-email" placeholder="prenom.nom@cibleci.ci" maxlength="150"
                       style="width:100%;height:38px;padding:0 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text);box-sizing:border-box">
                <small style="display:block;color:var(--text3);font-size:11px;margin-top:4px">
                    Si vide, un email temporaire <em>@tech.local</em> est généré automatiquement.
                </small>
            </div>
            <div id="qt-error"   style="display:none;background:rgba(239,68,68,.10);border:1px solid #ef4444;border-radius:8px;padding:9px 12px;margin-top:8px;color:#b91c1c;font-size:12px"></div>
            <div id="qt-success" style="display:none;background:rgba(34,197,94,.10);border:1px solid #22c55e;border-radius:8px;padding:10px 12px;margin-top:8px;font-size:12px;color:#15803d"></div>
        </div>
        <div style="padding:13px 22px;border-top:1px solid var(--border);background:var(--surface2);display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="btn btn-ghost" onclick="closeQuickTechModal()">Annuler</button>
            <button type="button" id="qt-submit" class="btn btn-primary">✓ Créer le technicien</button>
        </div>
    </div>
</div>

@once
<script>
(function () {
    // Cible : id du <select> à mettre à jour après création. Surchargé à
    // chaque ouverture (cf. window.openQuickTechModal(opts)).
    var QT_TARGET = @json($targetSelectId);
    var CSRF      = '{{ csrf_token() }}';
    var URL       = '{{ route('admin.maintenances.quick-tech') }}';

    window.openQuickTechModal = function (opts) {
        opts = opts || {};
        if (opts.target_select_id) QT_TARGET = opts.target_select_id;
        ['qt-name', 'qt-phone', 'qt-whatsapp', 'qt-email'].forEach(function (id) {
            var el = document.getElementById(id); if (el) el.value = '';
        });
        var err = document.getElementById('qt-error');
        var ok  = document.getElementById('qt-success');
        if (err) err.style.display = 'none';
        if (ok)  ok.style.display  = 'none';
        var btn = document.getElementById('qt-submit');
        btn.disabled = false;
        btn.textContent = '✓ Créer le technicien';
        document.getElementById('qt-modal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(function () { document.getElementById('qt-name').focus(); }, 50);
    };

    window.closeQuickTechModal = function () {
        document.getElementById('qt-modal').style.display = 'none';
        document.body.style.overflow = '';
    };

    function showErr(msg) {
        var err = document.getElementById('qt-error');
        err.textContent = msg;
        err.style.display = 'block';
    }

    document.getElementById('qt-submit').addEventListener('click', async function () {
        var name = document.getElementById('qt-name').value.trim();
        if (!name) { showErr('Le nom est obligatoire.'); return; }
        var btn = this; btn.disabled = true; btn.textContent = 'Création…';
        document.getElementById('qt-error').style.display = 'none';

        var fd = new URLSearchParams({
            _token:          CSRF,
            name:            name,
            phone:           document.getElementById('qt-phone').value,
            whatsapp_number: document.getElementById('qt-whatsapp').value,
            email:           document.getElementById('qt-email').value,
        });

        try {
            var r = await fetch(URL, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: fd,
            });
            if (r.status === 422) {
                var d = await r.json().catch(function () { return {}; });
                var first = d.errors ? Object.values(d.errors).flat()[0] : (d.message || 'Données invalides.');
                showErr(first); return;
            }
            var data = await r.json();
            if (!r.ok || !data.ok) { showErr(data.message || 'Erreur lors de la création.'); return; }

            // Inject dans le select cible + sélection auto
            var sel = document.getElementById(QT_TARGET);
            if (sel) {
                var label = data.technician.name + (data.technician.agent_code ? ' (' + data.technician.agent_code + ')' : '');
                var opt = new Option(label, data.technician.id, true, true);
                sel.appendChild(opt);
                sel.value = String(data.technician.id);
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Affiche le mot de passe temporaire pour transmission
            var ok = document.getElementById('qt-success');
            ok.innerHTML = '✅ Technicien <strong>' + data.technician.name + '</strong> créé. '
                         + 'Code agent : <code>' + data.technician.agent_code + '</code>.<br>'
                         + (data.plain_password
                             ? '🔑 Mot de passe temporaire : <strong style="font-family:monospace">' + data.plain_password + '</strong> — copie-le tout de suite, il ne sera plus affiché.'
                             : '');
            ok.style.display = 'block';
            btn.textContent = '✓ Créé — fermer';
            btn.onclick = function () { closeQuickTechModal(); };
            btn.disabled = false;
        } catch (e) {
            showErr('Erreur réseau : ' + (e.message || e));
            btn.disabled = false; btn.textContent = '✓ Créer le technicien';
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var m = document.getElementById('qt-modal');
            if (m && m.style.display === 'flex') closeQuickTechModal();
        }
    });
})();
</script>
@endonce
