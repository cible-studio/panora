<x-admin-layout title="Conflits détectés">

<x-slot:topbarLeft>
    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Retour
    </a>
</x-slot:topbarLeft>

{{-- ── BANDEAU SOMMAIRE ────────────────────────────────────── --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div style="width:48px;height:48px;border-radius:12px;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div style="flex:1;min-width:200px">
            <div style="font-size:18px;font-weight:800;color:var(--text)">
                @if($total === 0)
                    Aucun conflit détecté
                @else
                    {{ $total }} conflit{{ $total > 1 ? 's' : '' }} actif{{ $total > 1 ? 's' : '' }} — résolution nécessaire
                @endif
            </div>
            <div style="font-size:13px;color:var(--text2);margin-top:4px">
                @if($total === 0)
                    L'inventaire est cohérent — aucun panneau n'est engagé sur 2 entités chevauchantes.
                @else
                    {{ $resaInvolved }} réservation{{ $resaInvolved > 1 ? 's' : '' }} ·
                    {{ $campInvolved }} campagne{{ $campInvolved > 1 ? 's' : '' }} impliquée{{ ($resaInvolved + $campInvolved) > 1 ? 's' : '' }}.
                    Chaque ligne ci-dessous = 1 panneau engagé simultanément à plusieurs endroits.
                @endif
            </div>
        </div>
        @if($total > 0)
        <div style="font-size:11px;color:var(--text3);max-width:260px;text-align:right">
            Choisissez quelle entité garder pour chaque panneau — les autres engagements seront retirés.
        </div>
        @endif
    </div>
</div>

@if(session('success'))
<div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:10px;padding:12px 16px;margin-bottom:14px;font-size:13px;color:#16a34a">
    ✓ {{ session('success') }}
</div>
@endif

@if($total === 0)
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:48px 24px;text-align:center">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(34,197,94,.1);display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px">Tout est en ordre</div>
        <div style="font-size:13px;color:var(--text3)">Le filet de protection anti double-booking fonctionne. Cette page se mettra à jour automatiquement si un conflit apparaît.</div>
    </div>
@else
    @foreach($conflicts as $conflict)
    <div style="background:var(--surface);border:1.5px solid rgba(239,68,68,.35);border-radius:14px;padding:18px 22px;margin-bottom:14px">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:14px">
            <div>
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#ef4444;margin-bottom:4px">⚠️ Panneau en double-booking</div>
                <div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:18px;font-weight:800;color:var(--accent)">{{ $conflict['reference'] }}</div>
                <a href="{{ route('admin.panels.availability', $conflict['panel_id']) }}" target="_blank"
                   style="font-size:11px;color:var(--text3);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-top:6px">
                    Voir le calendrier de ce panneau
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
            </div>
            <span style="font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;background:rgba(239,68,68,.1);color:#ef4444">
                {{ count($conflict['engagements']) }} engagements
            </span>
        </div>

        <form method="POST" action="{{ route('admin.conflicts.resolve') }}"
              onsubmit="return confirm('Confirmer la résolution ? Les engagements non choisis seront retirés (le panneau sera detaché de leur résa/campagne).')"
              style="display:flex;flex-direction:column;gap:8px">
            @csrf
            <input type="hidden" name="panel_id" value="{{ $conflict['panel_id'] }}">

            @foreach($conflict['engagements'] as $i => $e)
            <label style="display:flex;align-items:center;gap:14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;cursor:pointer;background:var(--surface2);transition:all .15s"
                   onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                <input type="radio" name="keep" value="{{ $e['type'] }}:{{ $e['id'] }}"
                       data-type="{{ $e['type'] }}" data-id="{{ $e['id'] }}"
                       @if($i === 0) checked @endif
                       style="width:18px;height:18px;cursor:pointer;accent-color:#22c55e">
                <span style="font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px;{{ $e['type'] === 'campaign' ? 'background:rgba(59,130,246,.12);color:#3b82f6' : 'background:rgba(232,160,32,.12);color:var(--accent)' }};text-transform:uppercase;letter-spacing:.4px;flex-shrink:0">
                    {{ $e['type'] === 'campaign' ? '📢 Campagne' : '📋 Réservation' }}
                </span>
                <div style="flex:1;min-width:0">
                    @if($e['type'] === 'reservation')
                        <a href="{{ route('admin.reservations.show', $e['id']) }}" target="_blank"
                           style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;font-weight:700;color:var(--text);text-decoration:none">{{ $e['ref'] }}</a>
                    @else
                        <a href="{{ route('admin.campaigns.show', $e['id']) }}" target="_blank"
                           style="font-size:13px;font-weight:700;color:var(--text);text-decoration:none">{{ $e['ref'] }}</a>
                    @endif
                    <div style="font-size:11px;color:var(--text2);margin-top:3px">
                        @if($e['client'])👤 {{ $e['client'] }} · @endif
                        📅 {{ \Carbon\Carbon::parse($e['start'])->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($e['end'])->format('d/m/Y') }}
                        · Statut : <span style="font-weight:600">{{ $e['status'] }}</span>
                    </div>
                </div>
            </label>
            @endforeach

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px">
                @if(auth()->user()?->role?->value === 'admin')
                <button type="submit" class="btn btn-sm btn-primary"
                        onclick="prepareResolve(this)"
                        style="display:inline-flex;align-items:center;gap:6px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Garder cet engagement, retirer les autres
                </button>
                @else
                <span style="font-size:11px;color:var(--text3);font-style:italic">Résolution réservée aux administrateurs.</span>
                @endif
            </div>
        </form>
    </div>
    @endforeach
@endif

<script>
function prepareResolve(btn) {
    const form = btn.closest('form');
    const checked = form.querySelector('input[name=keep]:checked');
    if (!checked) {
        alert('Choisissez un engagement à conserver.');
        return false;
    }
    // Injecte les inputs hidden : keep_type / keep_id et remove[idx][...]
    // Index explicite pour que PHP regroupe type+id par item dans le array.
    form.querySelectorAll('.resolve-extra').forEach(n => n.remove());
    const inputs = [];
    inputs.push(`<input type="hidden" class="resolve-extra" name="keep_type" value="${checked.dataset.type}">`);
    inputs.push(`<input type="hidden" class="resolve-extra" name="keep_id" value="${checked.dataset.id}">`);
    let idx = 0;
    form.querySelectorAll('input[name=keep]').forEach(r => {
        if (r === checked) return;
        inputs.push(`<input type="hidden" class="resolve-extra" name="remove[${idx}][type]" value="${r.dataset.type}">`);
        inputs.push(`<input type="hidden" class="resolve-extra" name="remove[${idx}][id]" value="${r.dataset.id}">`);
        idx++;
    });
    btn.insertAdjacentHTML('beforebegin', inputs.join(''));
    return true;
}
</script>

</x-admin-layout>
