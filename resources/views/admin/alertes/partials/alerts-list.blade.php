{{-- resources/views/admin/alertes/partials/alerts-list.blade.php
     Liste des alertes — utilise la meta du catalogue AlertService::TYPES
     pour l'icon, la couleur et le label. Actions par alerte : marquer lu,
     archiver, supprimer. --}}

@if ($alertes->isEmpty())
    <div style="text-align:center;padding:80px 20px;color:var(--text3)">
        <div style="font-size:56px;margin-bottom:16px;opacity:.6">🎉</div>
        <div style="font-size:17px;font-weight:700;color:var(--text2);margin-bottom:6px">Aucune alerte</div>
        <div style="font-size:13px;max-width:420px;margin:0 auto;line-height:1.6">
            Tout est en ordre. Les nouvelles alertes apparaîtront ici dès qu'un évènement le déclenche.
        </div>
    </div>
@else
    <div style="display:flex;flex-direction:column">
        @foreach ($alertes as $alerte)
            @php
                $meta = $alerte->meta; // accessor → AlertService::TYPES[$type] ou DEFAULT_META
                $niveauCfg = match ($alerte->niveau) {
                    'danger'  => ['c' => '#ef4444', 'bg' => 'rgba(239,68,68,.08)',  'bd' => 'rgba(239,68,68,.25)'],
                    'warning' => ['c' => '#f97316', 'bg' => 'rgba(249,115,22,.08)', 'bd' => 'rgba(249,115,22,.25)'],
                    default   => ['c' => '#3b82f6', 'bg' => 'rgba(59,130,246,.08)', 'bd' => 'rgba(59,130,246,.25)'],
                };
                $isUnread = !$alerte->is_read;
            @endphp
            <div id="alert-{{ $alerte->id }}"
                 class="alert-row"
                 data-niveau="{{ $alerte->niveau }}"
                 data-type="{{ $alerte->type }}"
                 style="display:flex;align-items:flex-start;gap:14px;padding:14px 18px;border-bottom:1px solid var(--border);transition:background .15s;
                    {{ $isUnread ? 'background:' . $niveauCfg['bg'] . ';border-left:3px solid ' . $niveauCfg['c'] . ';' : '' }}">

                {{-- Icon module (couleur du type, depuis catalogue) --}}
                <div style="flex-shrink:0;width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:{{ $meta['color'] }}1a;border:1px solid {{ $meta['color'] }}33;border-radius:10px;font-size:18px">
                    {{ $meta['icon'] }}
                </div>

                <div style="flex:1;min-width:0">
                    {{-- Titre + badges --}}
                    <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:5px">
                        <span style="font-size:13px;font-weight:700;color:var(--text)">{{ $alerte->title }}</span>

                        @if ($isUnread)
                            <span style="padding:1px 7px;border-radius:20px;font-size:9px;font-weight:800;background:{{ $niveauCfg['c'] }};color:#fff;text-transform:uppercase;letter-spacing:.4px">Nouveau</span>
                        @endif

                        <span style="padding:2px 9px;border-radius:20px;font-size:9px;font-weight:700;background:{{ $meta['color'] }}1a;color:{{ $meta['color'] }};letter-spacing:.3px">{{ $meta['label'] }}</span>
                    </div>

                    <div style="font-size:12px;color:var(--text2);line-height:1.55;margin-bottom:7px">
                        {{ $alerte->message }}
                    </div>

                    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                        <span style="font-size:11px;color:var(--text3)" title="{{ $alerte->triggered_at?->format('d/m/Y H:i:s') ?? '—' }}">
                            ⏱ {{ $alerte->triggered_at?->diffForHumans() ?? $alerte->created_at->diffForHumans() }}
                        </span>
                        @if ($alerte->lien)
                            <a href="{{ $alerte->lien }}"
                               style="font-size:11px;color:{{ $meta['color'] }};text-decoration:none;font-weight:600">
                                Voir le détail →
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div style="display:flex;gap:6px;flex-shrink:0;align-items:flex-start">
                    @if ($isUnread)
                        <button data-id="{{ $alerte->id }}"
                                class="mark-read-btn alert-action-btn alert-action-success"
                                title="Marquer comme lu">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <span class="action-label">Lu</span>
                        </button>
                    @endif
                    <button data-id="{{ $alerte->id }}"
                            class="archive-alert-btn alert-action-btn alert-action-neutral"
                            title="Archiver (garde l'historique)">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/></svg>
                    </button>
                    <button data-id="{{ $alerte->id }}"
                            class="delete-alert-btn alert-action-btn alert-action-danger"
                            title="Supprimer définitivement">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Footer pagination --}}
    @if ($alertes->hasPages())
        <div style="padding:14px 18px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;background:var(--surface2);font-size:12px;color:var(--text3)">
            <span>
                Page {{ $alertes->currentPage() }} / {{ $alertes->lastPage() }}
                · {{ $alertes->total() }} alerte(s)
            </span>
            <div>{{ $alertes->links() }}</div>
        </div>
    @endif
@endif

<style>
.alert-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text2);
}
.alert-action-btn:hover { transform: translateY(-1px); }
.alert-action-success { background: rgba(34,197,94,.08); border-color: rgba(34,197,94,.3); color: #22c55e; }
.alert-action-success:hover { background: rgba(34,197,94,.16); }
.alert-action-neutral:hover { background: var(--surface3); color: var(--text); border-color: var(--text3); }
.alert-action-danger { background: rgba(239,68,68,.06); border-color: rgba(239,68,68,.2); color: #ef4444; }
.alert-action-danger:hover { background: rgba(239,68,68,.14); }
.alert-action-btn:disabled { opacity: .5; cursor: not-allowed; }
.alert-action-btn .action-label { display: inline-block; }
@media (max-width: 640px) { .alert-action-btn .action-label { display: none; } }
.alert-row:hover { background: var(--surface2) !important; }
</style>
