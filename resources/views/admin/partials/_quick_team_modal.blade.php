{{-- ════ Modale "Création rapide d'une équipe de pose" — réutilisable ════
     2026-06-19 — Calque sur _quick_tech_modal.blade.php.
     Permet de créer une PoseTeam SANS quitter le formulaire de pose en cours.
     L'équipe créée est ajoutée à TOUS les selects "team_name" portant l'id
     cible (target_select_id) et automatiquement sélectionnée.

     Paramètres @include :
       target_select_id : string — id du <select name="team_name"> à enrichir
                                    après création (option append + selection).

     Hook JS exposé : window.openQuickTeamModal({target_select_id})
     Définition @once → 1 seule instance même si inclus plusieurs fois.
--}}
@php
    $qtTargetSelectId = $target_select_id ?? 'qt-team-target-select';
    $palette          = \App\Models\PoseTeam::COLOR_PALETTE;
    // Liste de techniciens proposée à la sélection. Inclut TOUS les techniciens
    // (avec et sans équipe). Cocher un tech déjà rattaché à une équipe Y
    // le DÉPLACE vers la nouvelle équipe — l'observer User::pose_team_id
    // gère ce transfert via un simple UPDATE bulk dans PoseTeamController::store.
    // L'info de l'équipe actuelle est affichée en badge informatif.
    $availableTechs   = $available_techs ?? collect();
@endphp

{{-- 2026-06-19 — z-index 10500 : doit rester AU-DESSUS de Select2 (10000 dans app.css)
     sinon les dropdowns Select2 (ex : champ Campagne, Client) dépassent par-dessus. --}}
<div id="qtt-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:10500;align-items:center;justify-content:center;padding:16px"
     onclick="if(event.target===this)closeQuickTeamModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:480px;display:flex;flex-direction:column;overflow:hidden"
         onclick="event.stopPropagation()">
        <div style="padding:14px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <h3 style="font-size:15px;font-weight:700;margin:0">👥 Nouvelle équipe de pose</h3>
            <button type="button" onclick="closeQuickTeamModal()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3)">✕</button>
        </div>
        <div style="padding:18px 22px">
            <div style="background:rgba(232,160,32,.06);border:1px solid rgba(232,160,32,.20);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:11.5px;color:var(--text2);line-height:1.5">
                💡 Création rapide : l'équipe sera <strong>immédiatement disponible</strong> dans le select de cette pose et de toutes les autres.
            </div>

            <div style="margin-bottom:12px">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-bottom:5px">Nom de l'équipe *</label>
                <input type="text" id="qtt-name" placeholder="Ex : Équipe Cocody" maxlength="80"
                       style="width:100%;height:38px;padding:0 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text);box-sizing:border-box">
            </div>

            <div style="margin-bottom:14px">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-bottom:6px">Couleur *</label>
                <div id="qtt-palette" style="display:flex;gap:8px;flex-wrap:wrap">
                    @foreach($palette as $slug => $hex)
                        <button type="button" data-slug="{{ $slug }}"
                                class="qtt-color {{ $loop->first ? 'is-selected' : '' }}"
                                style="width:30px;height:30px;border-radius:8px;background:{{ $hex }};border:2px solid transparent;cursor:pointer;transition:transform .12s, border-color .12s"
                                title="{{ ucfirst($slug) }}"></button>
                    @endforeach
                </div>
                <small style="display:block;color:var(--text3);font-size:10.5px;margin-top:4px">8 couleurs prédéfinies, cohérentes avec le design system Panora.</small>
            </div>

            @if($availableTechs->isNotEmpty())
                {{-- Liste de tous les techniciens. Ceux déjà dans une équipe sont
                     affichés avec un badge "actuellement dans X". Les cocher les
                     DÉPLACE vers la nouvelle équipe (1 tech = 1 équipe, simple
                     transfert via update pose_team_id). --}}
                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-bottom:5px">Membres à rattacher (optionnel)</label>
                    <div style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;background:var(--surface2);padding:8px 10px">
                        @foreach($availableTechs as $tech)
                            @php $currentTeam = $tech->poseTeam?->name; @endphp
                            <label class="qtt-tech-row" style="display:flex;align-items:center;gap:8px;padding:5px 6px;border-radius:6px;cursor:pointer;font-size:12.5px">
                                <input type="checkbox" class="qtt-member" value="{{ $tech->id }}"
                                       data-current-team="{{ $currentTeam ?? '' }}"
                                       style="margin:0;accent-color:var(--accent);width:15px;height:15px;flex-shrink:0">
                                <span style="font-weight:600;color:var(--text)">{{ $tech->name }}</span>
                                @if($tech->agent_code)
                                    <span style="font-family:ui-monospace,monospace;font-size:10.5px;color:var(--text3)">({{ $tech->agent_code }})</span>
                                @endif
                                @if($currentTeam)
                                    <span style="margin-left:auto;font-size:10.5px;background:rgba(245,158,11,.14);color:#b45309;padding:1px 8px;border-radius:999px;font-weight:600"
                                          title="Le cocher le transférera vers la nouvelle équipe">
                                        ↻ {{ $currentTeam }}
                                    </span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    <small style="display:block;color:var(--text3);font-size:10.5px;margin-top:4px">
                        Cocher les techniciens à inclure dans l'équipe. Ceux ayant un badge <span style="background:rgba(245,158,11,.14);color:#b45309;padding:0 6px;border-radius:999px;font-size:9.5px;font-weight:600">↻ équipe</span> seront <strong>transférés</strong> depuis leur équipe actuelle.
                    </small>
                </div>
            @endif

            <div style="margin-bottom:12px">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text2);margin-bottom:5px">Description (optionnel)</label>
                <textarea id="qtt-description" rows="2" maxlength="500"
                          placeholder="Ex : équipe basée à Cocody, intervient sur le Sud d'Abidjan…"
                          style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface);color:var(--text);box-sizing:border-box;resize:vertical;font-family:inherit"></textarea>
            </div>

            <div id="qtt-error"   style="display:none;background:rgba(239,68,68,.10);border:1px solid #ef4444;border-radius:8px;padding:9px 12px;margin-top:8px;color:#b91c1c;font-size:12px"></div>
            <div id="qtt-success" style="display:none;background:rgba(34,197,94,.10);border:1px solid #22c55e;border-radius:8px;padding:10px 12px;margin-top:8px;font-size:12px;color:#15803d"></div>
        </div>
        <div style="padding:13px 22px;border-top:1px solid var(--border);background:var(--surface2);display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="btn btn-ghost" onclick="closeQuickTeamModal()">Annuler</button>
            <button type="button" id="qtt-submit" class="btn btn-primary">✓ Créer l'équipe</button>
        </div>
    </div>
</div>

@once
<style>
    .qtt-color.is-selected { transform: scale(1.1); border-color: var(--text) !important; box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--accent); }
    .qtt-color:hover       { transform: scale(1.05); }
    .qtt-tech-row:hover    { background: var(--surface); }
</style>
<script>
(function () {
    var QTT_TARGET = @json($qtTargetSelectId);
    var CSRF       = '{{ csrf_token() }}';
    var URL        = '{{ route('admin.teams.store') }}';
    // Si true, recharge la page après création réussie (utile pour pages liste).
    // Si false (défaut), injecte juste dans le select cible (formulaires).
    var QTT_RELOAD = false;

    // Slug couleur actuellement sélectionnée (1ère par défaut).
    var QTT_COLOR = (function () {
        var first = document.querySelector('#qtt-palette .qtt-color');
        return first ? first.dataset.slug : 'red';
    })();

    // Délégation : un clic sur une pastille met à jour la sélection
    var paletteEl = document.getElementById('qtt-palette');
    if (paletteEl) {
        paletteEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.qtt-color');
            if (!btn) return;
            paletteEl.querySelectorAll('.qtt-color').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            QTT_COLOR = btn.dataset.slug;
        });
    }

    window.openQuickTeamModal = function (opts) {
        opts = opts || {};
        if (opts.target_select_id)  QTT_TARGET = opts.target_select_id;
        QTT_RELOAD = !!opts.reload_on_success;
        // Ferme les dropdowns Select2 ouverts pour éviter qu'ils dépassent
        // visuellement par-dessus la modale (cf. capture 2026-06-19).
        if (window.jQuery && jQuery.fn.select2) {
            try { jQuery('select.select2-hidden-accessible').select2('close'); } catch (e) {}
        }
        document.getElementById('qtt-name').value = '';
        document.getElementById('qtt-description').value = '';
        // Reset des cases membres (sinon les coches précédentes persistent).
        document.querySelectorAll('.qtt-member').forEach(function (cb) { cb.checked = false; });
        var err = document.getElementById('qtt-error');
        var ok  = document.getElementById('qtt-success');
        if (err) err.style.display = 'none';
        if (ok)  ok.style.display  = 'none';
        var btn = document.getElementById('qtt-submit');
        btn.disabled = false;
        btn.textContent = '✓ Créer l\'équipe';
        // Reset palette à la 1ère couleur
        var first = paletteEl ? paletteEl.querySelector('.qtt-color') : null;
        if (first) {
            paletteEl.querySelectorAll('.qtt-color').forEach(function (b) { b.classList.remove('is-selected'); });
            first.classList.add('is-selected');
            QTT_COLOR = first.dataset.slug;
        }
        document.getElementById('qtt-modal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(function () { document.getElementById('qtt-name').focus(); }, 50);
    };

    window.closeQuickTeamModal = function () {
        document.getElementById('qtt-modal').style.display = 'none';
        document.body.style.overflow = '';
    };

    function showErr(msg) {
        var err = document.getElementById('qtt-error');
        err.textContent = msg;
        err.style.display = 'block';
    }

    document.getElementById('qtt-submit').addEventListener('click', async function () {
        var name = document.getElementById('qtt-name').value.trim();
        if (!name || name.length < 2) { showErr('Le nom de l\'équipe est obligatoire (min. 2 caractères).'); return; }
        var btn = this; btn.disabled = true; btn.textContent = 'Création…';
        document.getElementById('qtt-error').style.display = 'none';

        var fd = new URLSearchParams({
            _token:      CSRF,
            name:        name,
            color_slug:  QTT_COLOR,
            description: document.getElementById('qtt-description').value,
        });
        // Membres cochés → envoyés en member_ids[] (le controller PoseTeamController
        // les rattachera via User::pose_team_id en une seule requête UPDATE).
        document.querySelectorAll('.qtt-member:checked').forEach(function (cb) {
            fd.append('member_ids[]', cb.value);
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
                showErr(first); btn.disabled = false; btn.textContent = '✓ Créer l\'équipe'; return;
            }
            var data = await r.json();
            if (!r.ok || !data.ok) {
                showErr(data.message || 'Erreur lors de la création.');
                btn.disabled = false; btn.textContent = '✓ Créer l\'équipe';
                return;
            }

            // Inject dans le select cible — le champ team_name attend le NOM
            // (string), pas l'id. On insère donc value=name + selection auto.
            var sel = document.getElementById(QTT_TARGET);
            if (sel) {
                var opt = new Option(data.team.name, data.team.name, true, true);
                sel.appendChild(opt);
                sel.value = data.team.name;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }

            var ok = document.getElementById('qtt-success');
            ok.innerHTML = '✅ Équipe <strong>' + data.team.name + '</strong> créée'
                         + (QTT_RELOAD ? ', rechargement de la page…' : ' et sélectionnée.');
            ok.style.display = 'block';
            btn.textContent = QTT_RELOAD ? '⏳ Rechargement…' : '✓ Créée — fermer';
            btn.onclick = function () { closeQuickTeamModal(); };
            btn.disabled = false;

            // Mode page liste (perf équipes, gestion équipes) : on reload pour
            // que la nouvelle équipe apparaisse dans le classement/tableau.
            if (QTT_RELOAD) {
                setTimeout(function () { window.location.reload(); }, 700);
            }
        } catch (e) {
            showErr('Erreur réseau : ' + (e.message || e));
            btn.disabled = false; btn.textContent = '✓ Créer l\'équipe';
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var m = document.getElementById('qtt-modal');
            if (m && m.style.display === 'flex') closeQuickTeamModal();
        }
    });
})();
</script>
@endonce
