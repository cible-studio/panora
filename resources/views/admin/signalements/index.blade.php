<x-admin-layout title="Signalements terrain">

<x-slot:topbarLeft>
    <h1 style="font-size:20px;font-weight:800;margin:0;display:flex;align-items:center;gap:8px">
        ⚠️ Signalements terrain
    </h1>
</x-slot:topbarLeft>

{{-- ── KPI cards ────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px">
    <div class="kpi-card" style="--kpi-color:#ef4444">
        <div class="kpi-card__top-bar" style="background:#ef4444"></div>
        <div class="kpi-card__icon" style="color:#ef4444">⚠️</div>
        <div class="kpi-card__value" style="color:#ef4444">{{ $kpi['pending'] }}</div>
        <div class="kpi-card__label">À traiter</div>
        <div class="kpi-card__sub">Signalements en attente</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#f97316">
        <div class="kpi-card__top-bar" style="background:#f97316"></div>
        <div class="kpi-card__icon" style="color:#f97316">🔧</div>
        <div class="kpi-card__value" style="color:#f97316">{{ $kpi['maintenance'] }}</div>
        <div class="kpi-card__label">Mis en maintenance</div>
        <div class="kpi-card__sub">Panneau sorti de dispo</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#6b7280">
        <div class="kpi-card__top-bar" style="background:#6b7280"></div>
        <div class="kpi-card__icon" style="color:#6b7280">✓</div>
        <div class="kpi-card__value" style="color:#6b7280">{{ $kpi['dismissed'] }}</div>
        <div class="kpi-card__label">Dismissed</div>
        <div class="kpi-card__sub">Clôturés sans action</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--accent)">
        <div class="kpi-card__top-bar" style="background:var(--accent)"></div>
        <div class="kpi-card__icon" style="color:var(--accent)">📊</div>
        <div class="kpi-card__value" style="color:var(--accent)">{{ $kpi['total'] }}</div>
        <div class="kpi-card__label">Total</div>
        <div class="kpi-card__sub">Tous statuts</div>
    </div>
</div>

{{-- ── Filtres ─────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:18px">
    <form method="GET" action="{{ route('admin.signalements.index') }}"
          style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <div style="display:flex;gap:4px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:4px">
            @foreach(['pending' => '⚠️ À traiter', 'all' => 'Tous', 'resolved' => '✓ Traités'] as $key => $label)
            <a href="{{ route('admin.signalements.index', ['status' => $key]) }}"
               style="padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      {{ $status === $key ? 'background:var(--surface);color:var(--text);box-shadow:0 1px 2px rgba(0,0,0,.04)' : 'color:var(--text2)' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>

        <select name="type" onchange="this.form.submit()"
                style="padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:var(--surface);font-size:13px">
            <option value="">Tous les types</option>
            @foreach($problemLabels as $key => $label)
                <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        @if(request('type'))
            <a href="{{ route('admin.signalements.index', ['status' => $status]) }}"
               style="font-size:12px;color:var(--text3);text-decoration:none">✕ Réinitialiser</a>
        @endif
    </form>
</div>

{{-- ── Bandeau "nouveaux signalements" — visible quand le polling
     détecte de nouveaux signaux pendant que tu es sur cette page.
     Clic = rechargement (full reload, le plus simple et fiable). ── --}}
<div id="new-signals-banner"
     onclick="window.location.reload()"
     style="display:none;cursor:pointer;margin-bottom:14px;padding:12px 16px;border-radius:10px;background:linear-gradient(180deg,#ef4444,#dc2626);color:#fff;font-weight:700;text-align:center;box-shadow:0 8px 24px -4px rgba(239,68,68,.4);animation:pulseRed 1.6s ease-in-out infinite">
    <span id="new-signals-banner-text">⚠️ Nouveaux signalements — clique pour actualiser</span>
</div>
<style>@keyframes pulseRed{0%,100%{transform:scale(1);box-shadow:0 8px 24px -4px rgba(239,68,68,.4)}50%{transform:scale(1.015);box-shadow:0 12px 32px -4px rgba(239,68,68,.55)}}</style>

{{-- ── Liste ───────────────────────────────────────────── --}}
<div data-signalements-list>
@forelse($signalements as $sig)
    @php
        $type      = $sig->payload['type'] ?? 'autre';
        $note      = $sig->payload['note'] ?? null;
        // Motif effectif (= dernier amendement éventuel ou motif d'origine).
        $effective = $sig->effectiveMotif();
        $label     = $effective?->label() ?? ($problemLabels[$type] ?? 'Problème');
        $typeColor = $effective?->color() ?? '#6b7280';
        $hasAmend  = $effective && $type !== $effective->value;
        $panel     = $sig->task?->panel;
        $firstPhoto= $panel?->photos?->sortBy('ordre')->first();
        $thumbUrl  = $firstPhoto ? asset('storage/' . $firstPhoto->path) : null;
    @endphp
    <div class="card" style="margin-bottom:10px;padding:14px;border-left:3px solid {{ $sig->resolved_at ? 'var(--border)' : $typeColor }};overflow:hidden">
        <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap">
            {{-- Vignette du panneau --}}
            <div style="flex:0 0 48px">
                @if($thumbUrl)
                    <div style="width:48px;height:48px;border-radius:8px;background:url('{{ $thumbUrl }}') center/cover no-repeat;border:1px solid var(--border)"></div>
                @else
                    <div style="width:48px;height:48px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--text3)">🪧</div>
                @endif
            </div>

            {{-- Infos (prend toute la place restante) --}}
            <div style="flex:1 1 280px;min-width:0">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                    <span style="font-family:monospace;font-size:14px;font-weight:800;color:var(--accent-dark)">
                        {{ $panel?->reference ?? '—' }}
                    </span>
                    <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;background:rgba(0,0,0,.06);color:{{ $typeColor }};border:1px solid {{ $typeColor }}40">
                        {{ $label }}
                    </span>
                    @if($sig->resolved_at)
                        @if($sig->resolution_action === 'maintenance')
                            <span style="font-size:11px;font-weight:700;color:#f97316;background:rgba(249,115,22,.10);padding:2px 8px;border-radius:999px">🔧 En maintenance</span>
                        @else
                            <span style="font-size:11px;font-weight:700;color:var(--text3);background:var(--surface2);padding:2px 8px;border-radius:999px">✓ Traité</span>
                        @endif
                    @endif
                </div>
                <div style="font-size:13px;color:var(--text);margin-bottom:4px">
                    {{ $panel?->name ?? '—' }} · 📍 {{ $panel?->commune?->name ?? '—' }}
                    @if($panel?->adresse) · {{ $panel->adresse }} @endif
                </div>
                @if($note)
                    <div style="font-size:13px;color:var(--text2);background:var(--surface2);border-left:3px solid var(--border);padding:8px 12px;border-radius:6px;margin:6px 0;white-space:pre-wrap">{{ $note }}</div>
                @endif
                @php $photoPath = $sig->payload['photo_path'] ?? null; @endphp
                @if($photoPath)
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($photoPath) }}" target="_blank" rel="noopener"
                       style="display:inline-block;margin:6px 0;border:1px solid var(--border);border-radius:8px;overflow:hidden">
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($photoPath) }}"
                             alt="Photo signalement"
                             style="display:block;width:140px;height:100px;object-fit:cover;cursor:zoom-in">
                    </a>
                    <div style="font-size:10.5px;color:var(--text3);margin-top:-4px">📷 Photo jointe par le tech — cliquer pour zoomer</div>
                @endif
                <div style="font-size:12px;color:var(--text3);display:flex;gap:12px;flex-wrap:wrap;margin-top:4px">
                    <span>👷 {{ $sig->actor ?? '—' }}</span>
                    <span>🕐 {{ $sig->created_at?->diffForHumans() }}</span>
                    @if($sig->task?->campaign)
                        <span>📢 {{ $sig->task->campaign->name }}</span>
                    @endif
                    @if($sig->task)
                        <a href="{{ route('admin.pose-tasks.show', $sig->task->id) }}" style="color:var(--accent);text-decoration:none">Ouvrir la pose →</a>
                    @endif
                </div>
                @if($sig->resolved_at)
                    <div style="font-size:11px;color:var(--text3);margin-top:6px;font-style:italic">
                        Traité par {{ $sig->resolvedBy?->name ?? 'admin' }} {{ $sig->resolved_at->diffForHumans() }}
                        @if($sig->maintenance_id)
                            · <a href="{{ route('admin.maintenances.show', $sig->maintenance_id) }}" style="color:var(--accent)">Voir la maintenance →</a>
                        @endif
                    </div>
                @endif
            </div>

        </div>

        {{-- Actions compactes — alignées à droite, dimensions raisonnables. --}}
        @if(!$sig->isResolved())
        <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
            {{-- M3 SLA : édition motif a posteriori (admin/MP only via Policy) --}}
            @can('amend', $sig)
            <button type="button"
                    onclick="slaOpenModal({{ $sig->id }})"
                    style="padding:7px 12px;min-height:34px;background:var(--surface);color:#a16207;border:1px solid #f59e0b;border-radius:8px;font-weight:600;font-size:12.5px;cursor:pointer;display:inline-flex;align-items:center;gap:5px"
                    title="Modifier le motif de ce signalement — l'historique d'origine sera conservé en audit">
                ✎ Modifier le motif
            </button>
            @endcan
            <form method="POST" action="{{ route('admin.signalements.dismiss', $sig->id) }}"
                  onsubmit="return confirm('Marquer le signalement comme traité, sans toucher au panneau ?')"
                  style="margin:0">
                @csrf
                <button type="submit"
                        style="padding:7px 14px;min-height:34px;background:var(--surface);color:var(--text2);border:1px solid var(--border);border-radius:8px;font-weight:600;font-size:12.5px;cursor:pointer;display:inline-flex;align-items:center;gap:5px"
                        title="Clôturer sans toucher au panneau">
                    ✓ Marquer traité
                </button>
            </form>
            <button type="button"
                    class="js-open-maintenance"
                    data-url="{{ route('admin.signalements.maintenance', $sig->id) }}"
                    data-csrf="{{ csrf_token() }}"
                    data-panel-ref="{{ $panel?->reference }}"
                    data-panel-name="{{ $panel?->name }}"
                    style="padding:7px 14px;min-height:34px;background:#f97316;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:12.5px;cursor:pointer;display:inline-flex;align-items:center;gap:5px"
                    title="Bascule le panneau en MAINTENANCE + crée la fiche + informe le client">
                🔧 Mettre en maintenance
            </button>
        </div>
        @endif

        {{-- Badge "motif modifié" si amendement présent --}}
        @if($hasAmend)
            <div style="margin-top:6px;padding:6px 10px;background:rgba(245,158,11,.10);border-left:3px solid #f59e0b;border-radius:6px;font-size:11.5px;color:#a16207">
                ✎ Motif modifié a posteriori — d'origine : <strong>{{ \App\Enums\DelayReason::tryFrom($type)?->label() ?? 'Inconnu' }}</strong>
            </div>
        @endif

        {{-- Modale d'édition (incluse seulement si le user a le droit) --}}
        @can('amend', $sig)
            @include('admin.sla._modal_edit_motif', ['action' => $sig])
        @endcan
    </div>
@empty
    <div class="card" style="text-align:center;padding:60px 20px;color:var(--text3)">
        <div style="font-size:48px;margin-bottom:12px">🎉</div>
        <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px">
            @if($status === 'pending')
                Aucun signalement en attente
            @else
                Aucun signalement
            @endif
        </div>
        <div style="font-size:13px">Le terrain n'a rien remonté pour ce filtre.</div>
    </div>
@endforelse
</div>

<script>
// Écoute l'event du polling global (admin layout) : quand un nouveau
// signalement arrive pendant qu'on est sur cette page, on affiche le
// bandeau rouge pulsé. Pas de fetch/diff côté JS — un reload est plus
// fiable (filtres, pagination, droits préservés).
window.addEventListener('signalements:new', (e) => {
    const banner = document.getElementById('new-signals-banner');
    const text   = document.getElementById('new-signals-banner-text');
    if (!banner || !text) return;
    const count = e.detail?.count;
    text.textContent = count
        ? `⚠️ ${count} signalement(s) en attente — clique pour actualiser`
        : `⚠️ Nouveau signalement — clique pour actualiser`;
    banner.style.display = 'block';
    banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
</script>

{{ $signalements->links() }}

{{-- ─────────────────────────────────────────────────────────
     Modal "Mettre en maintenance" — collecte la durée prévue
     que le client recevra par mail (combien de temps son
     panneau est down + date de retour estimée).
   ───────────────────────────────────────────────────────── --}}
<div id="maintenanceModal"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);backdrop-filter:blur(2px);align-items:center;justify-content:center;padding:16px">
    <div style="background:var(--surface);border-radius:14px;max-width:480px;width:100%;box-shadow:0 30px 80px -20px rgba(0,0,0,.4);overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fff7ed,#fff);display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:10px;background:#f97316;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px">🔧</div>
            <div style="flex:1;min-width:0">
                <div style="font-size:15px;font-weight:800;color:var(--text)">Mettre en maintenance</div>
                <div id="maintenanceModalPanel" style="font-size:12.5px;color:var(--text2);font-family:monospace"></div>
            </div>
            <button type="button" onclick="closeMaintenanceModal()"
                    style="background:transparent;border:none;color:var(--text3);font-size:22px;cursor:pointer;line-height:1">×</button>
        </div>

        <form id="maintenanceModalForm" method="POST" action="" style="padding:18px 20px 16px">
            <input type="hidden" name="_token" id="maintenanceModalCsrf" value="">

            <div style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;padding:10px 12px;border-radius:8px;font-size:12.5px;margin-bottom:14px;line-height:1.45">
                ℹ️ Le panneau sortira des disponibilités. <strong>Les clients
                des campagnes en cours seront prévenus par email</strong> avec
                la durée que tu saisis ci-dessous.
            </div>

            <label style="display:block;font-size:12.5px;font-weight:700;color:var(--text);margin-bottom:6px">
                Durée prévue d'indisponibilité (en jours) <span style="color:#ef4444">*</span>
            </label>
            <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
                <input type="number" name="duree_prevue_jours" id="maintenanceModalDuree"
                       min="1" max="365" value="7" required
                       style="flex:1;min-width:120px;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-weight:600">
                @foreach([1 => '1j', 3 => '3j', 7 => '1 sem', 14 => '2 sem', 30 => '1 mois'] as $val => $lbl)
                    <button type="button"
                            onclick="document.getElementById('maintenanceModalDuree').value={{ $val }}"
                            style="padding:6px 10px;border:1px solid var(--border);background:var(--surface2);border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;color:var(--text2)">
                        {{ $lbl }}
                    </button>
                @endforeach
            </div>

            <label style="display:block;font-size:12.5px;font-weight:700;color:var(--text);margin-bottom:6px">
                Priorité
            </label>
            <select name="priorite"
                    style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13.5px;margin-bottom:14px;background:var(--surface)">
                <option value="basse">Basse</option>
                <option value="normale" selected>Normale</option>
                <option value="haute">Haute</option>
                <option value="urgente">Urgente</option>
            </select>

            <label style="display:block;font-size:12.5px;font-weight:700;color:var(--text);margin-bottom:6px">
                Note interne (facultatif)
            </label>
            <textarea name="description" rows="2" maxlength="1000" placeholder="Précisions techniques pour l'équipe maintenance…"
                      style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;resize:vertical;margin-bottom:18px"></textarea>

            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" onclick="closeMaintenanceModal()"
                        style="padding:9px 16px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;color:var(--text2)">
                    Annuler
                </button>
                <button type="submit"
                        style="padding:9px 18px;background:#f97316;border:none;color:#fff;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer">
                    🔧 Mettre en maintenance
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function closeMaintenanceModal() {
    document.getElementById('maintenanceModal').style.display = 'none';
}
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-open-maintenance');
    if (!btn) return;
    const modal = document.getElementById('maintenanceModal');
    document.getElementById('maintenanceModalForm').action = btn.dataset.url;
    document.getElementById('maintenanceModalCsrf').value  = btn.dataset.csrf;
    const ref  = btn.dataset.panelRef || '—';
    const name = btn.dataset.panelName || '';
    document.getElementById('maintenanceModalPanel').textContent =
        ref + (name ? ' · ' + name : '');
    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('maintenanceModalDuree').focus(), 50);
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMaintenanceModal();
});
document.getElementById('maintenanceModal').addEventListener('click', (e) => {
    if (e.target.id === 'maintenanceModal') closeMaintenanceModal();
});

// ─── M3 SLA enrichi — modale d'édition motif a posteriori ─────────────
function slaOpenModal(actionId) {
    const m = document.getElementById('modal-edit-motif-' + actionId);
    if (m) {
        m.style.display = 'flex';
        setTimeout(() => m.querySelector('select[name="motif"]')?.focus(), 50);
    }
}
function slaCloseModal(actionId) {
    const m = document.getElementById('modal-edit-motif-' + actionId);
    if (m) m.style.display = 'none';
}
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.sla-modal-overlay').forEach(el => el.style.display = 'none');
});
</script>

<style>
/* M3 SLA modale d'édition motif */
.sla-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, .55);
    backdrop-filter: blur(2px);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
.sla-modal {
    background: var(--surface);
    border-radius: 14px;
    max-width: 480px;
    width: 100%;
    box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .4);
    overflow: hidden;
}
.sla-modal-head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px;
}
.sla-modal-body { padding: 18px; }
.sla-modal-body .fne-field { margin-bottom: 12px; }
.sla-modal-body label {
    display: block; font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--text2); margin-bottom: 6px;
}
.sla-modal-body label .req { color: #ef4444; }
.sla-modal-body label .opt { font-size: 10px; color: var(--text3); font-weight: 500; text-transform: none; letter-spacing: 0; }
.sla-modal-body select, .sla-modal-body textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    background: var(--surface);
    color: var(--text);
    font-family: inherit;
    outline: none;
    box-sizing: border-box;
}
.sla-modal-body select { height: 38px; }
.sla-modal-body textarea { min-height: 70px; resize: vertical; line-height: 1.5; }
.sla-modal-body select:focus, .sla-modal-body textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(232, 160, 32, .15);
}
</style>

</x-admin-layout>
