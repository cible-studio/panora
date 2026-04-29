<x-admin-layout title="Pige — {{ $pige->panel?->reference }}">

<x-slot:topbarActions>
    <a href="{{ route('admin.piges.index', array_filter(['campaign_id'=>$pige->campaign_id,'panel_id'=>$pige->panel_id])) }}"
       class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Retour
    </a>
    @if($pige->isEnAttente())
    <button type="button"
        onclick="PigeDetail.verify({{ $pige->id }}, '{{ $pige->panel?->reference }}')"
        class="btn btn-primary btn-sm" style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Vérifier
    </button>
    <button type="button"
            onclick="PigeDetail.showRejectModal()"
            class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:5px;color:#ef4444;border-color:rgba(239,68,68,.3)">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Rejeter
    </button>
    @endif
</x-slot:topbarActions>

@php
$sc = $pige->getStatusConfig();
$sIcon = match($pige->status) {
    'en_attente' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    'verifie'    => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
    'rejete'     => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    default      => '',
};
@endphp

<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start">

    {{-- ══ GAUCHE : PHOTO + INFOS ══ --}}
    <div style="display:flex;flex-direction:column;gap:14px">

        {{-- Photo principale --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div style="padding:12px 16px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
                <div style="font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Pige #{{ $pige->id }}
                </div>
                <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;background:{{ $sc['bg'] }};color:{{ $sc['color'] }};border:1px solid {{ $sc['bd'] }}">
                    {!! $sIcon !!} {{ $sc['label'] }}
                </span>
            </div>

            {{-- Motif rejet --}}
            @if($pige->isRejetee() && $pige->rejection_reason)
            <div style="padding:12px 16px;background:rgba(239,68,68,.06);border-bottom:1px solid rgba(239,68,68,.2);display:flex;align-items:flex-start;gap:10px">
                <svg width="14" height="14" style="flex-shrink:0;margin-top:1px" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                <div>
                    <div style="font-size:12px;font-weight:700;color:#ef4444">Motif de rejet</div>
                    <div style="font-size:12px;color:#ef4444;opacity:.85;margin-top:2px;line-height:1.5">{{ $pige->rejection_reason }}</div>
                </div>
            </div>
            @endif

            {{-- Image --}}
            <div style="background:#000;min-height:280px;display:flex;align-items:center;justify-content:center;position:relative">
                <a href="{{ $pige->getPhotoUrl() }}" target="_blank"
                   style="display:block;width:100%;text-align:center">
                    <img src="{{ $pige->getPhotoUrl() }}"
                         alt="Pige {{ $pige->panel?->reference }}"
                         style="max-width:100%;max-height:500px;object-fit:contain;display:block;margin:0 auto"
                         onerror="this.closest('div').innerHTML='<div style=\'color:var(--text3);font-size:13px;padding:40px;text-align:center\'>Photo indisponible</div>'">
                </a>
                {{-- GPS overlay --}}
                @if($pige->hasGps())
                <a href="{{ $pige->getGoogleMapsUrl() }}" target="_blank"
                   style="position:absolute;bottom:10px;right:10px;padding:5px 10px;background:rgba(0,0,0,.7);color:#fff;border-radius:8px;font-size:11px;text-decoration:none;display:flex;align-items:center;gap:5px;backdrop-filter:blur(4px)">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    {{ number_format($pige->gps_lat,4) }}, {{ number_format($pige->gps_lng,4) }}
                </a>
                @endif
            </div>

            {{-- Actions photo --}}
            <div style="padding:10px 16px;display:flex;gap:8px;align-items:center;border-top:1px solid var(--border)">
                <a href="{{ $pige->getPhotoUrl() }}" target="_blank"
                   style="font-size:12px;color:var(--accent);text-decoration:none;display:flex;align-items:center;gap:5px;padding:6px 12px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:8px">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    Ouvrir en plein écran
                </a>
                @if($pige->hasGps())
                <a href="{{ $pige->getGoogleMapsUrl() }}" target="_blank"
                   style="font-size:12px;color:var(--text2);text-decoration:none;display:flex;align-items:center;gap:5px;padding:6px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Voir sur Google Maps
                </a>
                @endif
            </div>
        </div>

        {{-- Informations détaillées --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div style="padding:12px 16px;background:var(--surface2);border-bottom:1px solid var(--border);font-size:13px;font-weight:700;color:var(--text)">
                Informations
            </div>
            <div style="padding:18px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
                @php
                $details = [
                    ['PANNEAU',      $pige->panel?->reference.' — '.($pige->panel?->name ?? '—'), null, route('admin.panels.show', $pige->panel)],
                    ['COMMUNE',      $pige->panel?->commune?->name ?? '—', null, null],
                    ['CAMPAGNE',     $pige->campaign?->name ?? 'Sans campagne', null, $pige->campaign ? route('admin.campaigns.show', $pige->campaign) : null],
                    ['CLIENT',       $pige->campaign?->client?->name ?? '—', null, null],
                    ['TECHNICIEN',   $pige->technicien?->name ?? 'Non renseigné', null, null],
                    ['PRISE DE VUE', $pige->taken_at?->format('d/m/Y à H:i') ?? $pige->created_at->format('d/m/Y à H:i'), null, null],
                    ['VÉRIFIÉE PAR', $pige->verificateur?->name ?? '—', $pige->isVerifiee() ? '#22c55e' : null, null],
                    ['DATE VÉRIF.',  $pige->verified_at?->format('d/m/Y à H:i') ?? '—', $pige->isVerifiee() ? '#22c55e' : null, null],
                ];
                @endphp
                @foreach($details as [$label, $value, $color, $link])
                <div>
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:4px">{{ $label }}</div>
                    @if($link)
                    <a href="{{ $link }}" style="font-size:13px;font-weight:500;color:{{ $color ?? 'var(--accent)' }};text-decoration:none">{{ $value }}</a>
                    @else
                    <div style="font-size:13px;font-weight:500;color:{{ $color ?? 'var(--text)' }}">{{ $value }}</div>
                    @endif
                </div>
                @endforeach
            </div>

            @if($pige->notes)
            <div style="padding:0 18px 18px">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:6px">NOTES</div>
                <div style="font-size:12px;color:var(--text2);background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;line-height:1.6;white-space:pre-wrap">{{ $pige->notes }}</div>
            </div>
            @endif

            @if($pige->hasGps())
            <div style="padding:0 18px 18px">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:6px">COORDONNÉES GPS</div>
                <div style="font-size:12px;font-family:monospace;color:var(--text2);display:flex;align-items:center;gap:10px">
                    <span>{{ $pige->gps_lat }}, {{ $pige->gps_lng }}</span>
                    <a href="{{ $pige->getGoogleMapsUrl() }}" target="_blank"
                       style="font-size:11px;color:var(--accent);text-decoration:none;padding:2px 8px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:6px">
                        Maps →
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ DROITE : ACTIONS + SIDEBAR ══ --}}
    <div style="display:flex;flex-direction:column;gap:12px">

        {{-- Actions --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div style="padding:12px 16px;background:var(--surface2);border-bottom:1px solid var(--border);font-size:13px;font-weight:600">Actions</div>
            <div style="padding:14px 16px;display:flex;flex-direction:column;gap:6px">

                @if($pige->isEnAttente())
                {{-- Bouton Vérifier (corrigé) --}}
                <button type="button"
                        onclick="PigeDetail.verify({{ $pige->id }}, '{{ $pige->panel?->reference }}')"
                        style="width:100%;padding:9px 12px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;text-align:left;display:flex;align-items:center;gap:8px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Marquer comme vérifiée
                </button>

                {{-- Bouton Rejeter --}}
                <button type="button" onclick="PigeDetail.showRejectModal()"
                        style="width:100%;padding:9px 12px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);color:#ef4444;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;text-align:left;display:flex;align-items:center;gap:8px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Rejeter la pige
                </button>
                @endif

                {{-- Liens panel / campagne --}}
                @if($pige->panel)
                <a href="{{ route('admin.panels.show', $pige->panel) }}" class="sb-link">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    Voir le panneau
                </a>
                @endif

                @if($pige->campaign)
                <a href="{{ route('admin.campaigns.show', $pige->campaign) }}" class="sb-link">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
                    Voir la campagne
                </a>
                <a href="{{ route('admin.piges.index', ['campaign_id'=>$pige->campaign_id,'panel_id'=>$pige->panel_id]) }}" class="sb-link">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Autres piges ce panneau
                </a>
                @endif

                {{-- Suppression (uniquement si non vérifiée) --}}
                @if(!$pige->isVerifiee())
                <div style="border-top:1px solid var(--border);padding-top:6px;margin-top:2px">
                    <button type="button"
                            onclick="PigeDetail.destroy({{ $pige->id }}, '{{ $pige->panel?->reference }}')"
                            class="sb-link sb-link-danger" style="width:100%;text-align:left;cursor:pointer">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        Supprimer la pige
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- Statut & Timeline --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:12px">Workflow</div>
            @php
            $steps = [
                ['Uploadée', $pige->created_at->format('d/m/Y H:i'), true, '#e8a020'],
                ['En attente de vérification', $pige->status === 'en_attente' ? 'Maintenant' : null, $pige->status !== 'en_attente', '#f97316'],
                ['Vérifiée', $pige->verified_at?->format('d/m/Y H:i'), $pige->isVerifiee(), '#22c55e'],
                ['Rejetée', $pige->isRejetee() ? $pige->verified_at?->format('d/m/Y H:i') : null, $pige->isRejetee(), '#ef4444'],
            ];
            @endphp
            <div style="display:flex;flex-direction:column;gap:0">
                @foreach($steps as [$label, $date, $done, $color])
                @if($label === 'Rejetée' && !$pige->isRejetee()) @continue @endif
                <div style="display:flex;align-items:flex-start;gap:10px;padding:6px 0">
                    <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0">
                        <div style="width:16px;height:16px;border-radius:50%;background:{{ $done ? $color : 'var(--surface2)' }};border:2px solid {{ $done ? $color : 'var(--border)' }};display:flex;align-items:center;justify-content:center">
                            @if($done)
                            <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            @endif
                        </div>
                    </div>
                    <div style="flex:1;min-width:0;padding-top:1px">
                        <div style="font-size:12px;font-weight:{{ $done ? '600' : '400' }};color:{{ $done ? 'var(--text)' : 'var(--text3)' }}">{{ $label }}</div>
                        @if($date)
                        <div style="font-size:10px;color:var(--text3);margin-top:1px">{{ $date }}</div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Métadonnées --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:10px">Infos système</div>
            @foreach([['ID','#'.$pige->id],['Créée le',$pige->created_at->format('d/m/Y H:i')],['Modifiée le',$pige->updated_at->format('d/m/Y H:i')]] as [$l,$v])
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <span style="font-size:11px;color:var(--text3)">{{ $l }}</span>
                <span style="font-size:11px;color:var(--text2);font-family:monospace">{{ $v }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ════ MODAL REJET ════ --}}
<div id="modal-reject" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:18px;width:100%;max-width:440px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.4)">
        <div style="padding:18px 20px;background:rgba(239,68,68,.06);border-bottom:1px solid rgba(239,68,68,.2);display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;background:rgba(239,68,68,.12);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
            <div>
                <div style="font-size:14px;font-weight:700;color:#ef4444">Rejeter la pige</div>
                <div style="font-size:11px;color:rgba(239,68,68,.7)">Panneau {{ $pige->panel?->reference }}</div>
            </div>
        </div>
        <div style="padding:18px 20px">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:6px">Motif de rejet *</label>
            <textarea id="reject-input" rows="3"
                      placeholder="Ex: Photo floue, mauvais panneau, visuel non conforme…"
                      style="width:100%;padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);resize:none;outline:none;box-sizing:border-box;transition:border-color .2s"
                      onfocus="this.style.borderColor='#ef4444'"
                      onblur="this.style.borderColor='var(--border)'"></textarea>
            <div id="reject-err" style="font-size:11px;color:#ef4444;margin-top:4px;display:none">Le motif est obligatoire.</div>
            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:5px">
                @foreach(['Photo floue','Mauvais panneau','Visuel non conforme','Date incorrecte','Photo trop sombre','Angle incorrect','Panneau absent'] as $m)
                <button type="button" onclick="document.getElementById('reject-input').value='{{ $m }}'"
                        style="font-size:10px;padding:3px 9px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;cursor:pointer;color:var(--text3)">
                    {{ $m }}
                </button>
                @endforeach
            </div>
        </div>
        <div style="padding:12px 20px 18px;display:flex;gap:8px;justify-content:flex-end">
            <button onclick="PigeDetail.closeReject()" style="padding:8px 18px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text2);cursor:pointer">Annuler</button>
            <button onclick="PigeDetail.submitReject()" style="padding:8px 20px;background:#ef4444;border:none;border-radius:10px;font-size:13px;font-weight:700;color:#fff;cursor:pointer">Rejeter</button>
        </div>
    </div>
</div>

{{-- ════ MODAL CONFIRM ════ --}}
<div id="modal-confirm" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:18px;width:100%;max-width:400px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.4)">
        <div style="padding:20px 22px 16px">
            <div id="modal-confirm-icon" style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:14px"></div>
            <div id="modal-confirm-title" style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:8px"></div>
            <div id="modal-confirm-body"  style="font-size:13px;color:var(--text2);line-height:1.5"></div>
        </div>
        <div style="padding:14px 22px 20px;display:flex;gap:8px;justify-content:flex-end">
            <button onclick="Confirm.cancel()" style="padding:8px 18px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text2);cursor:pointer">Annuler</button>
            <button id="modal-confirm-btn" style="padding:8px 20px;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer"></button>
        </div>
    </div>
</div>

<style>
.sb-link { display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;font-size:12px;color:var(--text2);text-decoration:none;background:var(--surface2);border:1px solid var(--border);transition:border-color .15s }
.sb-link:hover { border-color:var(--accent);color:var(--accent) }
.sb-link-danger { background:rgba(239,68,68,.04);border-color:rgba(239,68,68,.15);color:#ef4444 }
.sb-link-danger:hover { border-color:rgba(239,68,68,.4) }
</style>

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
const PIGE_ID = {{ $pige->id }};

// ── Confirm modal ──────────────────────────────────────────
window.Confirm = {
    _cb: null,
    show(body, type = 'confirm', cb) {
        this._cb = cb;
        const cfg = {
            confirm: { icon: '<svg width="20"...', btnBg: '#3b82f6', btnTxt: 'Confirmer', title: 'Confirmer' },
            danger: { icon: '<svg width="20"...', btnBg: '#ef4444', btnTxt: 'Supprimer', title: 'Confirmer la suppression' }
        };
        const c = cfg[type] || cfg.confirm;
        document.getElementById('modal-confirm-icon').innerHTML = c.icon;
        document.getElementById('modal-confirm-title').textContent = c.title;
        document.getElementById('modal-confirm-body').innerHTML = body;
        const btn = document.getElementById('modal-confirm-btn');
        btn.textContent = c.btnTxt;
        btn.style.background = c.btnBg;
        btn.onclick = () => { Confirm.cancel(); cb?.(); };
        document.getElementById('modal-confirm').style.display = 'flex';
    },
    cancel() { document.getElementById('modal-confirm').style.display = 'none'; this._cb = null; }
};

// ── PigeDetail actions ────────────────────────────────────
window.PigeDetail = {
    async verify() {
        Confirm.show('Marquer cette pige comme vérifiée ?', 'confirm', async () => {
            try {
                const res = await fetch(`/admin/piges/${PIGE_ID}/verify`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this._showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => window.location.reload(), 800);
            } catch { this._showToast('Erreur de connexion.', 'error'); }
        });
    },
    
    async destroy() {
        Confirm.show('Supprimer définitivement cette pige ? Action irréversible.', 'danger', async () => {
            try {
                const res = await fetch(`/admin/piges/${PIGE_ID}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this._showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => window.location.href = '{{ route("admin.piges.index") }}', 800);
            } catch { this._showToast('Erreur de connexion.', 'error'); }
        });
    },
    
    showRejectModal() {
        document.getElementById('reject-input').value = '';
        document.getElementById('reject-err').style.display = 'none';
        document.getElementById('modal-reject').style.display = 'flex';
    },
    
    closeReject() { document.getElementById('modal-reject').style.display = 'none'; },
    
    async submitReject() {
        const reason = document.getElementById('reject-input').value.trim();
        if (!reason) { document.getElementById('reject-err').style.display = 'block'; return; }
        document.getElementById('reject-err').style.display = 'none';
        try {
            const res = await fetch(`/admin/piges/${PIGE_ID}/reject`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ rejection_reason: reason })
            });
            const data = await res.json();
            if (data.success) { this.closeReject(); window.location.reload(); }
            else alert(data.message || 'Erreur.');
        } catch { alert('Erreur de connexion.'); }
    },
    
    _showToast(msg, type) {
        const colors = { success: '#22c55e', error: '#ef4444' };
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 18px;background:var(--surface);border-left:3px solid ${colors[type] || '#22c55e'};border-radius:10px;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.25)`;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
    }
};

// Gestionnaires
document.getElementById('modal-confirm')?.addEventListener('click', e => { if (e.target === e.currentTarget) Confirm.cancel(); });
document.getElementById('modal-reject')?.addEventListener('click', e => { if (e.target === e.currentTarget) PigeDetail.closeReject(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { Confirm.cancel(); PigeDetail.closeReject(); } });
</script>
@endpush
</x-admin-layout>