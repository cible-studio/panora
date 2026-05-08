<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Pige — {{ $campaign->name }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #f1f5f9; color: #0f172a;
        font-size: 14px; line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }

    .wrap { max-width: 760px; margin: 0 auto; padding: 0 16px 80px; }

    /* HEADER */
    .header {
        background: #0a0c10; color: #fff;
        padding: 18px 16px; margin-bottom: 14px;
    }
    .header-inner { max-width: 760px; margin: 0 auto; display: flex; align-items: center; gap: 12px; }
    .logo {
        font-size: 20px; font-weight: 800; color: #e8a020;
        letter-spacing: -.5px;
    }
    .logo-sub {
        font-size: 9px; color: #8a90a2;
        text-transform: uppercase; letter-spacing: 1.5px;
        margin-top: 2px;
    }
    .header h1 { font-size: 14px; font-weight: 600; color: #fff; margin-top: 6px; }

    /* CAMPAGNE INFO */
    .campaign-card {
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 12px; padding: 16px;
        margin-bottom: 14px;
        border-left: 4px solid #e8a020;
    }
    .campaign-card .lbl {
        font-size: 9px; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 1.5px;
    }
    .campaign-card h2 {
        font-size: 17px; font-weight: 800; color: #0f172a;
        margin: 4px 0 6px;
    }
    .campaign-card .meta {
        font-size: 12px; color: #64748b;
        display: flex; flex-wrap: wrap; gap: 12px;
    }

    .closed-banner {
        background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;
        border-radius: 10px; padding: 14px 16px;
        margin-bottom: 14px; font-size: 13px; font-weight: 600;
    }

    /* TECH NAME (saisie 1 fois, mémorisée localStorage) */
    .tech-card {
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 12px; padding: 14px 16px;
        margin-bottom: 14px;
        display: flex; align-items: center; gap: 10px;
    }
    .tech-card label {
        font-size: 11px; font-weight: 700; color: #64748b;
        text-transform: uppercase; letter-spacing: 1px;
        flex-shrink: 0;
    }
    .tech-card input {
        flex: 1; padding: 8px 12px;
        background: #f8fafc; border: 1px solid #e2e8f0;
        border-radius: 8px; font-size: 13px; color: #0f172a;
        outline: none;
    }
    .tech-card input:focus { border-color: #e8a020; }

    /* PROGRESS GLOBALE */
    .progress-card {
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 12px; padding: 14px 16px;
        margin-bottom: 14px;
    }
    .progress-card .head { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px; }
    .progress-bar {
        height: 8px; background: #f1f5f9; border-radius: 6px; overflow: hidden;
    }
    .progress-bar > div {
        height: 100%; background: linear-gradient(90deg, #e8a020, #f97316);
        transition: width .4s ease;
    }

    /* PANEL CARD */
    .panel-card {
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 12px; margin-bottom: 12px;
        overflow: hidden;
    }
    .panel-head {
        padding: 12px 14px; border-bottom: 1px solid #f1f5f9;
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
    }
    .panel-ref {
        font-family: monospace, 'Courier New', sans-serif;
        font-weight: 700; color: #c2570d; font-size: 12px;
    }
    .panel-name {
        font-weight: 600; color: #0f172a; font-size: 13px;
        margin-top: 2px;
    }
    .panel-meta { font-size: 11px; color: #64748b; }

    .pige-status {
        display: inline-block; padding: 2px 8px;
        border-radius: 12px; font-size: 10px; font-weight: 700;
    }
    .pige-status-todo    { background: #fef3c7; color: #b45309; }
    .pige-status-pending { background: #dbeafe; color: #1d4ed8; }
    .pige-status-done    { background: #dcfce7; color: #166534; }
    .pige-status-rejected{ background: #fee2e2; color: #b91c1c; }

    .panel-body { padding: 14px; }

    .upload-btn {
        display: block; width: 100%;
        padding: 12px 14px;
        background: #e8a020; color: #0a0c10;
        font-weight: 700; font-size: 14px;
        border: none; border-radius: 10px;
        cursor: pointer; text-align: center;
        transition: background .15s;
    }
    .upload-btn:hover { background: #d4910f; }
    .upload-btn:disabled { background: #cbd5e1; color: #fff; cursor: not-allowed; }
    .upload-btn-secondary {
        background: transparent; color: #64748b;
        border: 1px solid #e2e8f0;
    }
    .upload-btn-secondary:hover { background: #f8fafc; color: #0f172a; }

    /* PIGES déjà prises */
    .pige-list {
        display: flex; gap: 8px; overflow-x: auto;
        padding: 4px 0; margin-top: 10px;
    }
    .pige-thumb {
        position: relative; flex-shrink: 0;
        width: 72px; height: 72px; border-radius: 8px;
        overflow: hidden; background: #f1f5f9;
        border: 1px solid #e2e8f0;
    }
    .pige-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .pige-thumb .badge {
        position: absolute; top: 4px; right: 4px;
        font-size: 9px; padding: 1px 5px; border-radius: 6px;
        background: rgba(255,255,255,.95); color: #0f172a;
        font-weight: 700;
    }

    /* MODAL UPLOAD */
    .modal {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.7); z-index: 1000;
        align-items: center; justify-content: center; padding: 16px;
    }
    .modal.open { display: flex; }
    .modal-content {
        background: #fff; border-radius: 14px; max-width: 460px; width: 100%;
        padding: 20px; max-height: 92vh; overflow-y: auto;
    }
    .modal h3 { font-size: 15px; font-weight: 700; margin-bottom: 14px; }
    .modal .field { margin-bottom: 12px; }
    .modal .field label {
        display: block; font-size: 11px; font-weight: 700;
        color: #64748b; text-transform: uppercase;
        letter-spacing: .8px; margin-bottom: 4px;
    }
    .modal .field input,
    .modal .field textarea {
        width: 100%; padding: 10px 12px;
        background: #f8fafc; border: 1px solid #e2e8f0;
        border-radius: 8px; font-size: 13px; color: #0f172a;
        outline: none; font-family: inherit;
    }
    .modal .field input:focus,
    .modal .field textarea:focus { border-color: #e8a020; }

    .preview {
        width: 100%; max-height: 220px; object-fit: contain;
        background: #f8fafc; border-radius: 8px;
        margin-bottom: 10px; display: none;
    }
    .preview.shown { display: block; }

    .gps-info {
        font-size: 11px; color: #64748b;
        background: #f8fafc; padding: 6px 10px;
        border-radius: 6px; margin-bottom: 10px;
    }
    .gps-info.ok { color: #16a34a; }

    .modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; }
    .btn-cancel {
        padding: 10px 16px; background: #f1f5f9; color: #475569;
        border: 1px solid #e2e8f0; border-radius: 8px;
        cursor: pointer; font-size: 13px; font-weight: 600;
    }

    .toast-host {
        position: fixed; top: 16px; left: 16px; right: 16px;
        z-index: 9999; pointer-events: none;
    }
    .toast {
        background: #dcfce7; color: #166534; border: 1px solid #86efac;
        padding: 12px 16px; border-radius: 10px;
        font-size: 13px; font-weight: 600; margin-bottom: 8px;
        max-width: 760px; margin-left: auto; margin-right: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,.1);
        pointer-events: auto;
    }
    .toast.error { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }

    .footer {
        text-align: center; font-size: 11px; color: #94a3b8;
        margin-top: 30px; padding: 16px;
    }
</style>
</head>
<body>

<div class="header">
    <div class="header-inner">
        <div>
            <div class="logo">CIBLE CI</div>
            <div class="logo-sub">Régie OOH · Pige terrain</div>
        </div>
    </div>
</div>

<div class="wrap">
    <div class="campaign-card">
        <div class="lbl">Campagne</div>
        <h2>{{ $campaign->name }}</h2>
        <div class="meta">
            <span>📅 {{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}</span>
            <span>👥 {{ $campaign->client?->name ?? '—' }}</span>
            <span>🪧 {{ $campaign->panels->count() }} panneau(x)</span>
        </div>
    </div>

    @if($isClosed)
        <div class="closed-banner">
            ⚠️ Cette campagne est <strong>{{ $campaign->status->label() }}</strong>.
            La prise de pige est désactivée.
        </div>
    @else
        <div class="tech-card">
            <label for="tech-name">Technicien</label>
            <input type="text" id="tech-name" placeholder="Votre prénom et nom" maxlength="100">
        </div>

        @php
            $totalPiges = $existingPiges->flatten()->count();
            $panelsWithPige = $existingPiges->keys()->count();
            $totalPanels = $campaign->panels->count();
            $progressPct = $totalPanels > 0 ? round(($panelsWithPige / $totalPanels) * 100) : 0;
        @endphp
        <div class="progress-card">
            <div class="head">
                <span><strong>{{ $panelsWithPige }}</strong> / {{ $totalPanels }} panneaux pigés</span>
                <span style="color:#e8a020;font-weight:700;">{{ $progressPct }}%</span>
            </div>
            <div class="progress-bar"><div style="width:{{ $progressPct }}%"></div></div>
        </div>
    @endif

    @forelse($campaign->panels as $panel)
        @php
            $panelPiges = $existingPiges[$panel->id] ?? collect();
            $hasVerified = $panelPiges->contains(fn($p) => $p->status === 'verifie');
            $hasPending  = $panelPiges->contains(fn($p) => $p->status === 'en_attente');
            $statusClass = $hasVerified ? 'pige-status-done' : ($hasPending ? 'pige-status-pending' : 'pige-status-todo');
            $statusLabel = $hasVerified ? '✓ Pigé' : ($hasPending ? '⏳ En attente' : '📷 À piger');
        @endphp
        <div class="panel-card" data-panel-id="{{ $panel->id }}">
            <div class="panel-head">
                <div style="min-width:0;">
                    <div class="panel-ref">{{ $panel->reference }}</div>
                    <div class="panel-name">{{ \Illuminate\Support\Str::limit($panel->name, 60) }}</div>
                    <div class="panel-meta">
                        {{ $panel->commune?->name ?? '—' }}
                        @if($panel->format?->name) · {{ $panel->format->name }}@endif
                    </div>
                </div>
                <span class="pige-status {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>

            <div class="panel-body">
                @if(!$isClosed)
                    <button type="button" class="upload-btn"
                            onclick="PigeCollect.openUpload({{ $panel->id }}, '{{ addslashes($panel->reference) }}', '{{ addslashes(\Illuminate\Support\Str::limit($panel->name, 50)) }}')">
                        📷 Prendre une photo
                    </button>
                @endif

                @if($panelPiges->isNotEmpty())
                    <div class="pige-list" data-panel-piges="{{ $panel->id }}">
                        @foreach($panelPiges as $p)
                            <div class="pige-thumb">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($p->photo_path) }}" alt="">
                                <span class="badge">
                                    @if($p->status === 'verifie') ✓
                                    @elseif($p->status === 'rejete') ✕
                                    @else ⏳
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align:center;padding:30px;color:#94a3b8;">Aucun panneau associé à cette campagne.</div>
    @endforelse

    <div class="footer">
        <strong>CIBLE CI</strong> — Lien sécurisé attribué à cette campagne.
    </div>
</div>

{{-- ─── MODAL UPLOAD ─── --}}
<div class="modal" id="upload-modal" onclick="if(event.target===this)PigeCollect.close()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <h3 id="upload-modal-title">Prendre une photo</h3>

        <img id="upload-preview" class="preview">
        <div id="upload-gps" class="gps-info">📍 GPS désactivé — la photo sera envoyée sans coordonnées.</div>

        <div class="field">
            <label>Photo</label>
            <input type="file" id="upload-photo" accept="image/*" capture="environment">
        </div>
        <div class="field">
            <label>Notes (optionnel)</label>
            <textarea id="upload-notes" rows="2" placeholder="Conditions du panneau, remarques…" maxlength="500"></textarea>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="PigeCollect.close()">Annuler</button>
            <button type="button" class="upload-btn" id="upload-submit" onclick="PigeCollect.submit()" style="width:auto;">
                Envoyer
            </button>
        </div>
    </div>
</div>

<div id="toast-host" class="toast-host"></div>

<script>
window.PigeCollect = (function () {
    const token = '{{ $token }}';
    const csrf  = document.querySelector('meta[name="csrf-token"]').content;
    const modal = document.getElementById('upload-modal');
    let currentPanelId = null;
    let currentGps = { lat: null, lng: null };

    // Demande GPS dès l'arrivée sur la page (avec consentement implicite navigateur)
    function requestGps() {
        if (!('geolocation' in navigator)) return;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                currentGps.lat = pos.coords.latitude.toFixed(6);
                currentGps.lng = pos.coords.longitude.toFixed(6);
                const el = document.getElementById('upload-gps');
                if (el) {
                    el.textContent = `📍 GPS détecté : ${currentGps.lat}, ${currentGps.lng}`;
                    el.classList.add('ok');
                }
            },
            (err) => { console.warn('GPS refusé/indisponible:', err.message); },
            { enableHighAccuracy: true, timeout: 6000, maximumAge: 60000 }
        );
    }

    function showToast(message, type = 'success') {
        const host = document.getElementById('toast-host');
        const t = document.createElement('div');
        t.className = 'toast ' + (type === 'error' ? 'error' : '');
        t.textContent = message;
        host.appendChild(t);
        setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; }, 2700);
        setTimeout(() => t.remove(), 3100);
    }

    // Mémorise le nom du technicien dans localStorage pour ne pas avoir à le retaper
    const techInput = document.getElementById('tech-name');
    if (techInput) {
        techInput.value = localStorage.getItem('pige_tech_name') || '';
        techInput.addEventListener('change', () => {
            localStorage.setItem('pige_tech_name', techInput.value.trim());
        });
    }

    // Auto-preview à la sélection du fichier
    const fileInput = document.getElementById('upload-photo');
    fileInput?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const preview = document.getElementById('upload-preview');
        preview.src = URL.createObjectURL(file);
        preview.classList.add('shown');
    });

    return {
        openUpload(panelId, ref, name) {
            currentPanelId = panelId;
            document.getElementById('upload-modal-title').textContent = `Photo — ${ref}`;
            document.getElementById('upload-photo').value = '';
            document.getElementById('upload-notes').value = '';
            document.getElementById('upload-preview').classList.remove('shown');
            document.getElementById('upload-preview').src = '';
            modal.classList.add('open');

            // Demande GPS à l'ouverture si pas encore obtenu
            if (currentGps.lat === null) requestGps();
        },
        close() {
            modal.classList.remove('open');
            currentPanelId = null;
        },
        async submit() {
            const file = document.getElementById('upload-photo').files[0];
            if (!file) {
                showToast('Sélectionnez une photo.', 'error');
                return;
            }
            if (!currentPanelId) return;

            const btn = document.getElementById('upload-submit');
            btn.disabled = true;
            btn.textContent = 'Envoi…';

            const fd = new FormData();
            fd.append('_token',   csrf);
            fd.append('panel_id', String(currentPanelId));
            fd.append('photo',    file);
            fd.append('notes',    document.getElementById('upload-notes').value);
            fd.append('tech_name',techInput?.value || '');
            if (currentGps.lat !== null) {
                fd.append('gps_lat', currentGps.lat);
                fd.append('gps_lng', currentGps.lng);
            }

            try {
                const r = await fetch(`/pige/${token}/upload`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd,
                });

                if (r.status === 422) {
                    const data = await r.json().catch(() => ({}));
                    const first = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Données invalides.');
                    showToast(first, 'error');
                    return;
                }

                const data = await r.json();
                if (!data.ok) {
                    showToast(data.message || 'Erreur.', 'error');
                    return;
                }

                this.close();
                showToast(data.message || 'Pige envoyée.');

                // Mise à jour visuelle in-place : ajoute une miniature dans la
                // pige-list du panneau et passe le statut à "en attente".
                const card = document.querySelector(`.panel-card[data-panel-id="${currentPanelId}"]`);
                if (card) {
                    let list = card.querySelector('[data-panel-piges]');
                    if (!list) {
                        list = document.createElement('div');
                        list.className = 'pige-list';
                        list.dataset.panelPiges = currentPanelId;
                        card.querySelector('.panel-body').appendChild(list);
                    }
                    const thumb = document.createElement('div');
                    thumb.className = 'pige-thumb';
                    thumb.innerHTML = `<img src="${data.photo_url}" alt=""><span class="badge">⏳</span>`;
                    list.appendChild(thumb);

                    const statusEl = card.querySelector('.pige-status');
                    if (statusEl) {
                        statusEl.className = 'pige-status pige-status-pending';
                        statusEl.textContent = '⏳ En attente';
                    }
                }
            } catch (e) {
                console.error(e);
                showToast('Erreur réseau. Réessayez.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Envoyer';
            }
        },
    };
})();
</script>
</body>
</html>
