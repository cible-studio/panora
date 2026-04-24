@extends('client.layout')
@section('title', 'Proposition ' . $reservation->reference)
@section('page-title', 'Détail de la proposition')

@section('content')

{{-- ══ BREADCRUMB ══ --}}
<div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text3);margin-bottom:20px;">
    <a href="{{ route('client.dashboard') }}" style="color:var(--text3);text-decoration:none;transition:color .15s;" onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text3)'">Accueil</a>
    <span>›</span>
    <a href="{{ route('client.propositions') }}" style="color:var(--text3);text-decoration:none;transition:color .15s;" onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text3)'">Propositions</a>
    <span>›</span>
    <span style="color:var(--text2);">{{ $reservation->reference }}</span>
</div>

{{-- ══ HEADER ══ --}}
@php
    $sc = match($reservation->status->value) {
        'en_attente' => ['bg'=>'rgba(250,184,11,.1)', 'color'=>'#fab80b', 'label'=>'En attente de réponse'],
        'confirme'   => ['bg'=>'rgba(34,197,94,.1)',  'color'=>'#22c55e', 'label'=>'Proposition acceptée'],
        'refuse'     => ['bg'=>'rgba(239,68,68,.1)',  'color'=>'#ef4444', 'label'=>'Proposition refusée'],
        'annule'     => ['bg'=>'rgba(239,68,68,.1)',  'color'=>'#ef4444', 'label'=>'Annulée'],
        default      => ['bg'=>'rgba(148,163,184,.1)','color'=>'#94a3b8', 'label'=>ucfirst($reservation->status->value)],
    };
@endphp

<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 24px;margin-bottom:16px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:var(--text);margin-bottom:4px;">Proposition {{ $reservation->reference }}</h1>
        <div style="font-size:12px;color:var(--text3);">Envoyée le {{ $reservation->proposition_sent_at?->format('d/m/Y à H:i') ?? '—' }}</div>
    </div>
    <span style="font-size:12px;font-weight:600;padding:6px 16px;border-radius:20px;background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">
        {{ $sc['label'] }}
    </span>
</div>

{{-- ══ ALERTES ══ --}}
@if($reservation->status->value === 'confirme')
<div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#22c55e;display:flex;align-items:center;gap:8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    Vous avez confirmé cette proposition. Merci pour votre confiance !
</div>
@elseif(in_array($reservation->status->value, ['annule','refuse']))
<div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#60a5fa;display:flex;align-items:center;gap:8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    Cette proposition a été refusée ou annulée.
</div>
@elseif($joursRestants < 0)
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#ef4444;display:flex;align-items:center;gap:8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    Cette proposition est expirée — vous ne pouvez plus y répondre.
</div>
@elseif($joursRestants <= 3)
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#ef4444;display:flex;align-items:center;gap:8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Action urgente — plus que {{ max(0, $joursRestants) }} jour(s) pour répondre.
</div>
@elseif($joursRestants <= 7)
<div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#60a5fa;display:flex;align-items:center;gap:8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    Plus que {{ $joursRestants }} jour(s) pour répondre.
</div>
@endif

{{-- ══ STATS ══ --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2" style="margin:0 auto 8px;display:block;"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Début</div>
        <div style="font-size:14px;font-weight:600;color:var(--text);">{{ $reservation->start_date->format('d/m/Y') }}</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="margin:0 auto 8px;display:block;"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Fin</div>
        <div style="font-size:14px;font-weight:600;color:var(--text);">{{ $reservation->end_date->format('d/m/Y') }}</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2" style="margin:0 auto 8px;display:block;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Durée</div>
        <div style="font-size:14px;font-weight:600;color:var(--text);">{{ round($months) }} mois</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2" style="margin:0 auto 8px;display:block;"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Panneaux</div>
        <div style="font-size:18px;font-weight:700;color:#e20613;">{{ count($panels) }}</div>
    </div>
</div>

{{-- ══ EMPLACEMENTS ══ --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
    <h2 style="font-size:15px;font-weight:700;color:var(--text);">Emplacements sélectionnés</h2>
    <span style="font-size:11px;color:var(--text3);">({{ count($panels) }})</span>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    @foreach($panels as $index => $panel)
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:border-color .2s;position:relative;"
         onmouseover="this.style.borderColor='rgba(226,6,19,.25)'" onmouseout="this.style.borderColor='var(--border)'">

        {{-- Numéro --}}
        <div style="position:absolute;top:10px;left:10px;z-index:10;width:26px;height:26px;border-radius:50%;background:#e20613;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3);">
            {{ $index + 1 }}
        </div>

        {{-- Photo --}}
        <div style="cursor:pointer;position:relative;overflow:hidden;" onclick="openPanelModal({{ $index }})">
            @if($panel['photo_url'])
                <img src="{{ $panel['photo_url'] }}" style="width:100%;height:160px;object-fit:cover;display:block;transition:transform .3s;" alt="{{ $panel['reference'] }}" loading="lazy"
                     onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                <div style="position:absolute;inset:0;background:rgba(0,0,0,.5);opacity:0;transition:opacity .2s;display:flex;align-items:center;justify-content:center;"
                     onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
                    <span style="background:#e20613;color:#fff;padding:6px 14px;border-radius:20px;font-size:11px;font-weight:600;">Voir en détail</span>
                </div>
            @else
                <div style="width:100%;height:160px;background:var(--surface2);display:flex;align-items:center;justify-content:center;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </div>
            @endif
        </div>

        {{-- Infos --}}
        <div style="padding:14px;">
            <div style="font-family:monospace;font-size:11px;font-weight:700;color:#e20613;background:rgba(226,6,19,.08);padding:2px 8px;border-radius:5px;display:inline-block;margin-bottom:6px;">{{ $panel['reference'] }}</div>
            <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $panel['name'] }}</div>

            <div style="border-top:1px solid var(--border);padding-top:10px;display:flex;flex-direction:column;gap:6px;">
                @foreach([
                    ['label'=>'Commune', 'val'=>$panel['commune']],
                    ['label'=>'Zone', 'val'=>($panel['zone'] ?? '') !== '—' ? ($panel['zone'] ?? '') : ''],
                    ['label'=>'Format', 'val'=>$panel['format'] ?? ''],
                    ['label'=>'Dimensions', 'val'=>$panel['dimensions'] ?? ''],
                ] as $row)
                @if(!empty($row['val']))
                <div style="display:flex;justify-content:space-between;font-size:11px;">
                    <span style="color:var(--text3);">{{ $row['label'] }}</span>
                    <span style="color:var(--text2);font-weight:500;">{{ $row['val'] }}</span>
                </div>
                @endif
                @endforeach
                <div style="display:flex;justify-content:space-between;font-size:11px;">
                    <span style="color:var(--text3);">Éclairage</span>
                    <span style="color:{{ $panel['is_lit'] ? '#fab80b' : 'var(--text2)' }};font-weight:500;">{{ $panel['is_lit'] ? 'Éclairé' : 'Non éclairé' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;border-top:1px dashed var(--border);padding-top:8px;margin-top:2px;">
                    <span style="color:var(--text2);font-weight:500;">Tarif mensuel</span>
                    <span style="color:#e20613;font-weight:700;">{{ number_format($panel['monthly_rate'], 0, ',', ' ') }} FCFA</span>
                </div>
            </div>

            <button onclick="openPanelModal({{ $index }})"
                    style="width:100%;margin-top:10px;padding:6px;font-size:11px;color:var(--text3);border:none;background:none;cursor:pointer;border-top:1px solid var(--border);padding-top:8px;transition:color .15s;"
                    onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text3)'">
                Voir tous les détails →
            </button>
        </div>
    </div>
    @endforeach
</div>

{{-- ══ TOTAL ══ --}}
@php $total = collect($panels)->sum('total'); @endphp
@if($total > 0)
<div style="background:linear-gradient(135deg,rgba(226,6,19,.08),transparent);border:1px solid rgba(226,6,19,.2);border-radius:14px;padding:24px;margin-bottom:20px;">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;margin-bottom:12px;">
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;">Montant total estimé (HT)</div>
            <div style="font-size:28px;font-weight:800;color:#e20613;line-height:1;">
                {{ number_format($total, 0, ',', ' ') }}
                <span style="font-size:14px;font-weight:400;color:var(--text3);"> FCFA</span>
            </div>
            <div style="font-size:11px;color:var(--text3);margin-top:4px;">Pour {{ round($months) }} mois · {{ count($panels) }} emplacement(s)</div>
        </div>
    </div>
    <div style="font-size:11px;color:var(--text3);padding-top:12px;border-top:1px solid rgba(226,6,19,.15);">
        Devis définitif établi lors de la confirmation. Tarifs nets hors taxes et frais techniques.
    </div>
</div>
@endif

{{-- ══ ACTIONS ══ --}}
@if($joursRestants >= 0 && $reservation->status->value === 'en_attente')
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:32px;text-align:center;">
    <h3 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px;">Quelle est votre décision ?</h3>
    <p style="font-size:13px;color:var(--text2);max-width:500px;margin:0 auto 24px;line-height:1.7;">
        En confirmant, les panneaux vous seront attribués immédiatement et une campagne sera créée dans votre espace.
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-bottom:16px;">
        <button onclick="openConfirmModal()"
                style="padding:12px 28px;background:#e20613;color:#fff;font-weight:700;border-radius:10px;font-size:14px;border:none;cursor:pointer;transition:opacity .15s;display:flex;align-items:center;gap:8px;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Accepter la proposition
        </button>
        <button onclick="openRefuseModal()"
                style="padding:12px 24px;background:rgba(239,68,68,.08);color:#ef4444;font-weight:600;border-radius:10px;font-size:14px;border:1px solid rgba(239,68,68,.25);cursor:pointer;transition:all .15s;"
                onmouseover="this.style.background='rgba(239,68,68,.15)'" onmouseout="this.style.background='rgba(239,68,68,.08)'">
            Refuser
        </button>
    </div>
    <div style="font-size:11px;color:var(--text3);">Réponse sécurisée · CIBLE CI · Abidjan</div>
</div>
@endif

{{-- ══ MODAL PANNEAU ══ --}}
<div id="modal-panel" class="fixed inset-0 bg-black/90 backdrop-blur-md z-50 hidden"
     style="display:none;align-items:center;justify-content:center;padding:16px;"
     onclick="if(event.target===this)closePanelModal()">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;max-width:800px;width:100%;max-height:90vh;overflow-y:auto;"
         onclick="event.stopPropagation()">
        <div style="position:sticky;top:0;background:var(--surface);border-bottom:1px solid var(--border);padding:16px 24px;display:flex;justify-content:space-between;align-items:center;z-index:1;">
            <div id="modal-panel-title" style="font-size:15px;font-weight:700;color:var(--text);"></div>
            <button onclick="closePanelModal()" style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:20px;line-height:1;transition:color .15s;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'">✕</button>
        </div>
        <div style="padding:24px;">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <div style="border-radius:10px;overflow:hidden;border:1px solid var(--border);margin-bottom:10px;">
                        <img id="modal-main-image" src="" alt="" style="width:100%;max-height:280px;object-fit:cover;display:block;">
                    </div>
                    <div id="modal-thumbnails" style="display:flex;gap:6px;overflow-x:auto;padding-bottom:4px;"></div>
                    <div id="modal-no-image" style="display:none;text-align:center;padding:40px;background:var(--surface2);border-radius:10px;">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.5" style="margin:0 auto 10px;display:block;opacity:.4;"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                        <div style="font-size:13px;color:var(--text3);">Aucune photo disponible</div>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <div id="modal-ref" style="font-family:monospace;font-size:11px;font-weight:700;color:#e20613;background:rgba(226,6,19,.08);padding:3px 10px;border-radius:6px;display:inline-block;margin-bottom:6px;"></div>
                        <div id="modal-name" style="font-size:16px;font-weight:700;color:var(--text);"></div>
                    </div>
                    <div style="background:var(--surface2);border-radius:10px;padding:14px;">
                        <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px;">Localisation</div>
                        <div style="display:flex;flex-direction:column;gap:6px;" id="modal-location"></div>
                    </div>
                    <div style="background:var(--surface2);border-radius:10px;padding:14px;">
                        <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px;">Caractéristiques</div>
                        <div style="display:flex;flex-direction:column;gap:6px;" id="modal-specs"></div>
                    </div>
                    <div style="background:rgba(226,6,19,.06);border:1px solid rgba(226,6,19,.2);border-radius:10px;padding:14px;">
                        <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px;">Tarification</div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:13px;color:var(--text2);">Tarif mensuel</span>
                            <span id="modal-price" style="font-size:22px;font-weight:800;color:#e20613;"></span>
                        </div>
                        <div style="font-size:10px;color:var(--text3);margin-top:6px;">Tarif net hors taxes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══ MODAL CONFIRMATION ══ --}}
<div id="modal-confirm" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;"
     onclick="if(event.target===this)closeConfirmModal()">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;max-width:420px;width:100%;padding:32px;text-align:center;position:relative;"
         onclick="event.stopPropagation()">
        <button onclick="closeConfirmModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;color:var(--text3);cursor:pointer;font-size:18px;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'">✕</button>
        <div style="width:56px;height:56px;border-radius:14px;background:rgba(34,197,94,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h3 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px;">Confirmer la proposition</h3>
        <p style="font-size:13px;color:var(--text2);margin-bottom:16px;line-height:1.6;">Souhaitez-vous confirmer cette proposition ? Les panneaux vous seront attribués et une campagne sera créée.</p>
        <div style="background:rgba(250,184,11,.08);border:1px solid rgba(250,184,11,.2);border-radius:10px;padding:10px 14px;margin-bottom:20px;font-size:12px;color:#fab80b;">
            Cette action est définitive — elle déclenche la création de votre campagne.
        </div>
        <form method="POST" action="{{ route('proposition.confirmer', $token) }}">
            @csrf
            <div style="display:flex;gap:10px;justify-content:center;">
                <button type="button" onclick="closeConfirmModal()"
                        style="padding:10px 20px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:13px;color:var(--text2);cursor:pointer;transition:all .15s;"
                        onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">Annuler</button>
                <button type="submit"
                        style="padding:10px 24px;background:#22c55e;color:#fff;font-weight:700;border-radius:9px;font-size:13px;border:none;cursor:pointer;transition:opacity .15s;"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'"
                        onclick="this.disabled=true;this.textContent='En cours…';this.closest('form').submit()">
                    Confirmer
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ MODAL REFUS ══ --}}
<div id="modal-refus" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;"
     onclick="if(event.target===this)closeRefuseModal()">
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;max-width:420px;width:100%;padding:32px;position:relative;"
         onclick="event.stopPropagation()">
        <button onclick="closeRefuseModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;color:var(--text3);cursor:pointer;font-size:18px;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'">✕</button>
        <div style="text-align:center;margin-bottom:20px;">
            <div style="width:56px;height:56px;border-radius:14px;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
            <h3 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:6px;">Refuser la proposition</h3>
            <p style="font-size:13px;color:var(--text2);line-height:1.6;">Un motif aide notre équipe à mieux adapter les futures propositions.</p>
        </div>
        <form method="POST" action="{{ route('proposition.refuser', $token) }}">
            @csrf
            <textarea name="motif" rows="3"
                      style="width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:10px 14px;font-size:13px;color:var(--text);resize:vertical;outline:none;transition:border-color .15s;margin-bottom:16px;font-family:inherit;"
                      onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'"
                      placeholder="Motif optionnel — ex: budget, zones, période..."></textarea>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button type="button" onclick="closeRefuseModal()"
                        style="padding:10px 20px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:13px;color:var(--text2);cursor:pointer;transition:all .15s;"
                        onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">Annuler</button>
                <button type="submit"
                        style="padding:10px 24px;background:rgba(239,68,68,.1);color:#ef4444;font-weight:600;border-radius:9px;font-size:13px;border:1px solid rgba(239,68,68,.25);cursor:pointer;transition:all .15s;"
                        onmouseover="this.style.background='rgba(239,68,68,.2)'" onmouseout="this.style.background='rgba(239,68,68,.1)'">
                    Confirmer le refus
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const panelsData = @json($panels);
let currentPhotoIndex = 0;
let currentPhotos = [];

function row(label, val, color) {
    if (!val || val === '—') return '';
    return `<div style="display:flex;justify-content:space-between;font-size:12px;"><span style="color:var(--text3);">${label}</span><span style="color:${color||'var(--text2)'};font-weight:500;">${val}</span></div>`;
}

function openPanelModal(index) {
    const panel = panelsData[index];
    if (!panel) return;
    currentPhotos = panel.photos || [];
    currentPhotoIndex = 0;

    document.getElementById('modal-panel-title').textContent = panel.reference;
    document.getElementById('modal-ref').textContent = panel.reference;
    document.getElementById('modal-name').textContent = panel.name;
    document.getElementById('modal-price').textContent = new Intl.NumberFormat('fr-FR').format(panel.monthly_rate) + ' FCFA';

    document.getElementById('modal-location').innerHTML =
        row('Commune', panel.commune) +
        row('Zone', panel.zone !== '—' ? panel.zone : '');

    document.getElementById('modal-specs').innerHTML =
        row('Format', panel.format) +
        row('Dimensions', panel.dimensions) +
        row('Éclairage', panel.is_lit ? 'Éclairé' : 'Non éclairé', panel.is_lit ? '#fab80b' : null) +
        row('Orientation', panel.orientation) +
        row('Hauteur', panel.height) +
        (panel.daily_traffic ? row('Trafic/jour', new Intl.NumberFormat('fr-FR').format(panel.daily_traffic) + ' véhicules') : '');

    updateModalImage();
    const m = document.getElementById('modal-panel');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function updateModalImage() {
    const img = document.getElementById('modal-main-image');
    const thumbs = document.getElementById('modal-thumbnails');
    const noImg = document.getElementById('modal-no-image');
    if (currentPhotos.length > 0) {
        img.src = currentPhotos[currentPhotoIndex].url;
        img.style.display = 'block';
        noImg.style.display = 'none';
        thumbs.innerHTML = currentPhotos.map((p, i) => `
            <button onclick="currentPhotoIndex=${i};updateModalImage()" style="width:56px;height:56px;border-radius:8px;overflow:hidden;border:2px solid ${i===currentPhotoIndex?'#e20613':'var(--border2)'};cursor:pointer;flex-shrink:0;padding:0;background:none;">
                <img src="${p.url}" style="width:100%;height:100%;object-fit:cover;">
            </button>`).join('');
    } else {
        img.style.display = 'none';
        noImg.style.display = 'block';
        thumbs.innerHTML = '';
    }
}

function closePanelModal() {
    document.getElementById('modal-panel').style.display = 'none';
    document.body.style.overflow = '';
}
function openConfirmModal()  { document.getElementById('modal-confirm').style.display='flex'; document.body.style.overflow='hidden'; }
function closeConfirmModal() { document.getElementById('modal-confirm').style.display='none'; document.body.style.overflow=''; }
function openRefuseModal()   { document.getElementById('modal-refus').style.display='flex';  document.body.style.overflow='hidden'; }
function closeRefuseModal()  { document.getElementById('modal-refus').style.display='none';  document.body.style.overflow=''; }

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closePanelModal(); closeConfirmModal(); closeRefuseModal(); }
    if (document.getElementById('modal-panel').style.display !== 'none') {
        if (e.key === 'ArrowLeft'  && currentPhotos.length) { currentPhotoIndex=(currentPhotoIndex-1+currentPhotos.length)%currentPhotos.length; updateModalImage(); }
        if (e.key === 'ArrowRight' && currentPhotos.length) { currentPhotoIndex=(currentPhotoIndex+1)%currentPhotos.length; updateModalImage(); }
    }
});
</script>

@endsection
