<x-admin-layout title="Tarifs communaux">

{{-- Fix : .modal-overlay a `display:flex` permanent dans app.css.
     Sans :not(.show), nos modaux restent ouverts au chargement. --}}
<style>
    #modal-history:not(.show),
    #modal-schedule:not(.show) {
        display: none !important;
    }
</style>

<x-slot:topbarLeft>
    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Réglages
    </a>
</x-slot:topbarLeft>

<div style="max-width:1100px;margin:0 auto">

    <div style="font-size:12px;color:var(--text3);margin-bottom:14px">
        <a href="{{ route('admin.settings.index') }}" style="color:var(--text3);text-decoration:none">Réglages</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Tarifs communaux ODP / TM</span>
    </div>

    {{-- Intro --}}
    <div style="background:linear-gradient(135deg,rgba(58,168,53,.05),rgba(232,160,32,.05));border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px">
        <div style="display:flex;align-items:flex-start;gap:14px">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:22px">💰</div>
            <div style="flex:1">
                <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Tarifs ODP / TM par commune</div>
                <div style="font-size:12.5px;color:var(--text3);line-height:1.55">
                    Référentiel utilisé pour la facturation FNE. La <strong>TM est fixe</strong> (1 000 F/m²/mois nationale).
                    L'<strong>ODP varie</strong> selon la commune (15 000 Plateau, 5 000 Cocody, etc.).
                    Les tarifs sont <strong>historisés automatiquement</strong> à chaque modification — les factures passées gardent leurs valeurs d'origine.
                    Tu peux aussi <strong>programmer un nouveau tarif à l'avance</strong> (ex: voté pour 2026), il s'appliquera automatiquement à la date d'effet.
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:10px;padding:12px 16px;margin-bottom:14px;color:#15803d;font-size:13px">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:12px 16px;margin-bottom:14px;color:#b91c1c;font-size:13px">{{ session('error') }}</div>
    @endif

    {{-- Table tarifs --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <div class="card-title">📋 Référentiel tarifaire ({{ $communes->count() }} communes)</div>
            <input type="search" id="filter-input" placeholder="🔍 Filtrer une commune…"
                   style="padding:7px 11px;border:1px solid var(--border);border-radius:8px;font-size:12px;width:240px">
        </div>
        <div class="card-body" style="padding:0">
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px" id="tariffs-table">
                    <thead>
                        <tr style="background:var(--surface2);text-align:left;color:var(--text3)">
                            <th style="padding:10px 14px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;font-weight:700">Commune</th>
                            <th style="padding:10px 14px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;text-align:right">ODP courant</th>
                            <th style="padding:10px 14px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;text-align:right">TM courant</th>
                            <th style="padding:10px 14px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;font-weight:700">Dernier changement</th>
                            <th style="padding:10px 14px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;font-weight:700">Tarif futur</th>
                            <th style="padding:10px 14px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;font-weight:700;text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($communes as $c)
                        <tr class="tariff-row" data-name="{{ strtolower($c->name) }}" style="border-top:1px solid var(--border)">
                            <td style="padding:10px 14px">
                                <div style="font-weight:700;color:var(--text)">{{ $c->name }}</div>
                                @if($c->rate_history_count > 0)
                                    <div style="font-size:10.5px;color:var(--text3);margin-top:1px">{{ $c->rate_history_count }} entrée(s) d'historique</div>
                                @endif
                            </td>
                            <td style="padding:10px 14px;text-align:right;font-family:ui-monospace,monospace;font-weight:700;color:var(--accent)">{{ number_format($c->odp_rate, 0, ',', ' ') }}</td>
                            <td style="padding:10px 14px;text-align:right;font-family:ui-monospace,monospace">{{ number_format($c->tm_rate, 0, ',', ' ') }}</td>
                            <td style="padding:10px 14px;color:var(--text2)">
                                @if($c->last_change_date)
                                    <div style="font-size:11.5px">{{ $c->last_change_date->format('d/m/Y') }}</div>
                                @else
                                    <span style="color:var(--text3);font-size:11px">—</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px">
                                @if($c->next_change)
                                    <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.25);border-radius:8px;padding:5px 9px;font-size:11px;color:#1d4ed8">
                                        <strong>📅 {{ $c->next_change['date'] }}</strong><br>
                                        ODP {{ number_format($c->next_change['odp_rate'], 0, ',', ' ') }} · TM {{ number_format($c->next_change['tm_rate'], 0, ',', ' ') }}
                                    </div>
                                @else
                                    <span style="color:var(--text3);font-size:11px">Aucun</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;text-align:center;white-space:nowrap">
                                <button type="button" class="btn btn-ghost btn-sm" onclick="openHistory({{ $c->id }}, '{{ addslashes($c->name) }}')"
                                        style="font-size:11px;padding:5px 9px">📜 Historique</button>
                                <button type="button" class="btn btn-ghost btn-sm" onclick="openSchedule({{ $c->id }}, '{{ addslashes($c->name) }}', {{ $c->odp_rate }}, {{ $c->tm_rate }})"
                                        style="font-size:11px;padding:5px 9px;color:var(--accent-dark)">📅 Programmer</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- ════ MODAL HISTORIQUE ════ --}}
<div id="modal-history" class="modal-overlay" role="dialog">
    <div class="modal" style="max-width:720px">
        <div class="modal-header">
            <h3>📜 Historique tarifaire — <span id="hist-commune-name">…</span></h3>
            <button type="button" onclick="document.getElementById('modal-history').classList.remove('show')" class="modal-close">×</button>
        </div>
        <div class="modal-body">
            <div id="hist-body" style="font-size:12.5px;color:var(--text2)">Chargement…</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-history').classList.remove('show')">Fermer</button>
        </div>
    </div>
</div>

{{-- ════ MODAL PROGRAMMER NOUVEAU TARIF ════ --}}
<div id="modal-schedule" class="modal-overlay" role="dialog">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h3>📅 Programmer un nouveau tarif</h3>
            <button type="button" onclick="document.getElementById('modal-schedule').classList.remove('show')" class="modal-close">×</button>
        </div>
        <form method="POST" action="" id="schedule-form">
            @csrf
            <div class="modal-body">
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:11.5px;color:var(--text3)">
                    <strong>Commune :</strong> <span id="sched-commune-name">…</span><br>
                    <strong>Tarif courant :</strong> ODP <span id="sched-current-odp">—</span> · TM <span id="sched-current-tm">—</span>
                </div>
                <div class="mfg">
                    <label>Date d'effet <span style="color:var(--red)">*</span> <span style="font-weight:400;color:var(--text3);font-size:11px">(doit être future)</span></label>
                    <input type="date" name="effective_from" id="sched-date" required
                           min="{{ now()->addDay()->format('Y-m-d') }}"
                           value="{{ now()->addYear()->startOfYear()->format('Y-m-d') }}">
                </div>
                <div class="form-2col">
                    <div class="mfg">
                        <label>Nouveau tarif ODP (F/m²/mois) <span style="color:var(--red)">*</span></label>
                        <input type="number" name="odp_rate" id="sched-odp" min="0" step="100" required
                               style="font-family:ui-monospace,monospace;text-align:right">
                    </div>
                    <div class="mfg">
                        <label>Nouveau tarif TM (F/m²/mois) <span style="color:var(--red)">*</span></label>
                        <input type="number" name="tm_rate" id="sched-tm" min="0" step="100" required
                               style="font-family:ui-monospace,monospace;text-align:right">
                    </div>
                </div>
                <div class="mfg">
                    <label>Notes / motif <span style="color:var(--text3);font-weight:400">(facultatif)</span></label>
                    <textarea name="notes" rows="2" maxlength="500" placeholder="Ex: voté en conseil municipal le 15/11/2025"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-schedule').classList.remove('show')">Annuler</button>
                <button type="submit" class="btn btn-primary">✅ Programmer le tarif</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// ─── Filtre live ──────────────────────────────────────────────
document.getElementById('filter-input')?.addEventListener('input', (e) => {
    const q = e.target.value.trim().toLowerCase();
    document.querySelectorAll('.tariff-row').forEach(row => {
        row.style.display = (!q || row.dataset.name.includes(q)) ? '' : 'none';
    });
});

// ─── Modal historique ────────────────────────────────────────
async function openHistory(communeId, name) {
    document.getElementById('hist-commune-name').textContent = name;
    document.getElementById('hist-body').textContent = 'Chargement…';
    document.getElementById('modal-history').classList.add('show');

    try {
        const r = await fetch(`/admin/communes/${communeId}/tariffs/history`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const d = await r.json();
        renderHistory(d.history);
    } catch (e) {
        document.getElementById('hist-body').innerHTML = '<div style="color:#b91c1c">Erreur de chargement</div>';
    }
}

function renderHistory(history) {
    const body = document.getElementById('hist-body');
    if (!history.length) {
        body.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text3)">Aucun historique tarifaire.</div>';
        return;
    }
    const fmt = (n) => Number(n).toLocaleString('fr-FR');
    const rows = history.map(h => {
        const badge = h.is_future
            ? '<span style="background:rgba(59,130,246,.12);color:#1d4ed8;padding:2px 7px;border-radius:6px;font-size:9.5px;font-weight:700">📅 PROGRAMMÉ</span>'
            : (h.is_current
                ? '<span style="background:rgba(34,197,94,.12);color:#16a34a;padding:2px 7px;border-radius:6px;font-size:9.5px;font-weight:700">✓ COURANT</span>'
                : '<span style="background:rgba(107,114,128,.10);color:#6b7280;padding:2px 7px;border-radius:6px;font-size:9.5px;font-weight:700">PASSÉ</span>');
        return `
            <tr style="border-top:1px solid var(--border)">
                <td style="padding:8px 12px">
                    <div style="font-weight:700">${h.effective_from_h} →  ${h.effective_to_h || '∞'}</div>
                    <div style="margin-top:3px">${badge}</div>
                    ${h.notes ? '<div style="font-size:10.5px;color:var(--text3);margin-top:3px;font-style:italic">' + h.notes + '</div>' : ''}
                </td>
                <td style="padding:8px 12px;text-align:right;font-family:ui-monospace,monospace;color:var(--accent);font-weight:700">${fmt(h.odp_rate)}</td>
                <td style="padding:8px 12px;text-align:right;font-family:ui-monospace,monospace">${fmt(h.tm_rate)}</td>
                <td style="padding:8px 12px;text-align:right;font-size:10.5px;color:var(--text3)">
                    ${h.created_by || '—'}<br>${h.created_at || ''}
                </td>
            </tr>
        `;
    }).join('');

    body.innerHTML = `
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:var(--surface2);color:var(--text3);text-align:left">
                    <th style="padding:8px 12px;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Période</th>
                    <th style="padding:8px 12px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;text-align:right">ODP</th>
                    <th style="padding:8px 12px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;text-align:right">TM</th>
                    <th style="padding:8px 12px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;text-align:right">Par / le</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

// ─── Modal programmer ────────────────────────────────────────
function openSchedule(communeId, name, currentOdp, currentTm) {
    document.getElementById('sched-commune-name').textContent = name;
    document.getElementById('sched-current-odp').textContent = Number(currentOdp).toLocaleString('fr-FR');
    document.getElementById('sched-current-tm').textContent  = Number(currentTm).toLocaleString('fr-FR');
    document.getElementById('sched-odp').value = currentOdp;
    document.getElementById('sched-tm').value  = currentTm;
    document.getElementById('schedule-form').action = `/admin/communes/${communeId}/tariffs/schedule`;
    document.getElementById('modal-schedule').classList.add('show');
}

// ESC ferme les modales
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.getElementById('modal-history').classList.remove('show');
        document.getElementById('modal-schedule').classList.remove('show');
    }
});
</script>
@endpush

</x-admin-layout>
