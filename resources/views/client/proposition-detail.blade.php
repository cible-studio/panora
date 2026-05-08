<!-- resources/views/client/proposition-detail.blade.php -->
@extends('client.layout')
@section('title', 'Proposition ' . $reservation->reference)
@section('page-title', 'Détail de la proposition')

@section('content')

{{-- ══ RETOUR ══ --}}
<a href="{{ route('client.propositions') }}"
   style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text3);text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);transition:all .15s;margin-bottom:18px;"
   onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--border2)';this.style.background='var(--surface2)'"
   onmouseout="this.style.color='var(--text3)';this.style.borderColor='var(--border)';this.style.background='var(--surface)'">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Mes propositions
</a>

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
        <div style="font-size:14px;font-weight:600;color:var(--text);">{{ $days }} jour{{ $days > 1 ? 's' : '' }}</div>
        <div style="font-size:10px;color:var(--text3);margin-top:2px;">{{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }}</div>
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
                    <span style="color:var(--text2);font-weight:500;">Prix mensuel</span>
                    <span style="color:var(--text2);font-weight:600;">{{ number_format($panel['monthly_rate'], 0, ',', ' ') }} FCFA</span>
                </div>
                @if(!empty($panel['total']) && $panel['total'] > 0)
                <div style="display:flex;justify-content:space-between;font-size:13px;padding-top:6px;">
                    <span style="color:var(--text);font-weight:600;">Total ({{ $monthsLabel }} mois)</span>
                    <span style="color:#e20613;font-weight:800;">{{ number_format($panel['total'], 0, ',', ' ') }} FCFA</span>
                </div>
                @endif
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
@php $totalAmount = (float) $reservation->total_amount; @endphp
@if($totalAmount > 0)
<div style="background:linear-gradient(135deg,rgba(226,6,19,.08),transparent);border:1px solid rgba(226,6,19,.2);border-radius:14px;padding:24px;margin-bottom:20px;">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;margin-bottom:12px;">
        <div>
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;">Montant total estimé (HT)</div>
            <div style="font-size:28px;font-weight:800;color:#e20613;line-height:1;">
                {{ number_format($totalAmount, 0, ',', ' ') }}
                <span style="font-size:14px;font-weight:400;color:var(--text3);"> FCFA</span>
            </div>
            <div style="font-size:11px;color:var(--text3);margin-top:4px;">Pour {{ $days }} jour{{ $days > 1 ? 's' : '' }} ({{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }}) · {{ count($panels) }} emplacement(s)</div>
        </div>
    </div>
    <div style="font-size:11px;color:var(--text3);padding-top:12px;border-top:1px solid rgba(226,6,19,.15);">
        Devis définitif établi lors de la confirmation. Tarifs nets hors taxes et frais techniques.
    </div>
</div>
@endif

{{-- ══ INTERLOCUTEUR ══ --}}
@if($reservation->user)
@php
    $interlocuteur = $reservation->user;
    $initials = collect(explode(' ', $interlocuteur->name))
        ->map(fn($w) => strtoupper($w[0] ?? ''))->filter()->take(2)->implode('');
@endphp
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#e20613,#fab80b);display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:800;color:#fff;flex-shrink:0;letter-spacing:-.5px;">
        {{ $initials }}
    </div>
    <div style="flex:1;min-width:140px;">
        <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Votre interlocuteur</div>
        <div style="font-size:15px;font-weight:700;color:var(--text);">{{ $interlocuteur->name }}</div>
        <div style="font-size:12px;color:var(--text3);margin-top:2px;">{{ $interlocuteur->role?->label() ?? '—' }}</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;min-width:0;">
        <a href="mailto:{{ $interlocuteur->email }}"
           style="display:inline-flex;align-items:center;gap:7px;font-size:12px;color:var(--text2);text-decoration:none;transition:color .15s;white-space:nowrap;"
           onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text2)'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            {{ $interlocuteur->email }}
        </a>
        @if($interlocuteur->whatsapp_number)
        <a href="https://wa.me/{{ preg_replace('/\D/', '', $interlocuteur->whatsapp_number) }}"
           target="_blank" rel="noopener"
           style="display:inline-flex;align-items:center;gap:7px;font-size:12px;color:var(--text2);text-decoration:none;transition:color .15s;white-space:nowrap;"
           onmouseover="this.style.color='#22c55e'" onmouseout="this.style.color='var(--text2)'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            {{ $interlocuteur->whatsapp_number }}
        </a>
        @endif
    </div>
</div>
@endif

{{-- ══ ACTIONS ══ --}}
@php
    // Conditions pour pouvoir agir (cohérentes avec PropositionController::assertActionable)
    $canAct = $reservation->status->value === 'en_attente'
        && $joursRestants >= 0
        && !empty($reservation->proposition_token)
        && !empty($reservation->proposition_slug);
@endphp

@if($canAct)
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
@else
    {{-- Action impossible — affichage explicite pour ne pas laisser le client perplexe --}}
    @php
        $blockReason = match(true) {
            $reservation->status->value === 'confirme' => [
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
                'color' => '#22c55e',
                'title' => 'Proposition déjà acceptée',
                'msg'   => 'Vous avez confirmé cette proposition. Une campagne a été créée dans votre espace. Notre équipe commerciale prendra contact avec vous pour la suite.',
            ],
            $reservation->status->value === 'refuse' => [
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                'color' => '#94a3b8',
                'title' => 'Proposition refusée',
                'msg'   => 'Cette proposition a été refusée. Pour recevoir une nouvelle sélection adaptée à vos besoins, contactez votre interlocuteur commercial CIBLE CI.',
            ],
            $reservation->status->value === 'annule' => [
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
                'color' => '#94a3b8',
                'title' => 'Proposition annulée',
                'msg'   => 'Cette proposition a été annulée par notre équipe. Contactez votre commercial pour plus de détails.',
            ],
            $joursRestants < 0 => [
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
                'color' => '#ef4444',
                'title' => 'Proposition expirée',
                'msg'   => 'Le délai de validité de cette proposition est dépassé. Contactez votre commercial pour recevoir une nouvelle proposition adaptée à vos besoins actuels.',
            ],
            default => [
                'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
                'color' => '#94a3b8',
                'title' => 'Action impossible',
                'msg'   => 'Cette proposition n\'est plus modifiable. Contactez votre commercial pour plus d\'informations.',
            ],
        };
    @endphp
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:28px;text-align:center;">
        <div style="width:52px;height:52px;border-radius:50%;background:rgba(148,163,184,0.08);border:1px solid rgba(148,163,184,0.2);display:inline-flex;align-items:center;justify-content:center;color:{{ $blockReason['color'] }};margin-bottom:14px;">
            {!! $blockReason['icon'] !!}
        </div>
        <h3 style="font-size:17px;font-weight:600;color:var(--text);margin-bottom:8px;">{{ $blockReason['title'] }}</h3>
        <p style="font-size:13px;color:var(--text2);max-width:500px;margin:0 auto 18px;line-height:1.7;">
            {{ $blockReason['msg'] }}
        </p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <a href="{{ route('client.propositions') }}"
               style="padding:10px 22px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:13px;color:var(--text2);text-decoration:none;font-weight:500;transition:all .15s;"
               onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--text3)'"
               onmouseout="this.style.color='var(--text2)';this.style.borderColor='var(--border2)'">
                ← Mes propositions
            </a>
            @if($reservation->status->value === 'confirme')
                <a href="{{ route('client.campagnes') }}"
                   style="padding:10px 22px;background:#e20613;color:#fff;font-weight:600;border-radius:9px;font-size:13px;text-decoration:none;transition:opacity .15s;"
                   onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    Voir mes campagnes →
                </a>
            @endif
        </div>
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

{{-- ══ MODALES — uniquement si la proposition est encore actionnable
     ($canAct est défini plus haut dans le fichier).
     Cela évite qu'un client puisse les ouvrir via la console JS sur une
     proposition expirée ou déjà traitée. La garde côté controller fait
     office de double rempart serveur. ══ --}}
@if($canAct ?? false)
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
        <p style="font-size:13px;color:var(--text2);margin-bottom:14px;line-height:1.6;">Souhaitez-vous confirmer cette proposition ? Les panneaux vous seront attribués et une campagne sera créée.</p>

        {{-- Récap clair : durée réelle + montant total --}}
        @php $totalAmount = (float) $reservation->total_amount; @endphp
        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:14px;text-align:left;">
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:6px;">
                <span style="color:var(--text3)">Période</span>
                <span style="color:var(--text);font-weight:600">{{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:6px;">
                <span style="color:var(--text3)">Durée</span>
                <span style="color:var(--text);font-weight:600">{{ $days }} jour{{ $days > 1 ? 's' : '' }} · {{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:6px;">
                <span style="color:var(--text3)">Emplacements</span>
                <span style="color:var(--text);font-weight:600">{{ count($panels) }} panneau{{ count($panels) > 1 ? 'x' : '' }}</span>
            </div>
            @if($totalAmount > 0)
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:14px;padding-top:8px;border-top:1px solid var(--border);margin-top:6px;">
                <span style="color:var(--text);font-weight:700">Total à payer (HT)</span>
                <span style="color:#e20613;font-weight:800;font-size:16px">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
        </div>

        <div style="background:rgba(250,184,11,.08);border:1px solid rgba(250,184,11,.2);border-radius:10px;padding:10px 14px;margin-bottom:20px;font-size:12px;color:#fab80b;">
            Cette action est définitive — elle déclenche la création de votre campagne.
        </div>
        <form method="POST" action="{{ route('proposition.confirmer', [$reservation->reference, $reservation->proposition_slug]) }}">
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
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;max-width:460px;width:100%;padding:28px;position:relative;max-height:90vh;overflow-y:auto;"
         onclick="event.stopPropagation()">
        <button onclick="closeRefuseModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;color:var(--text3);cursor:pointer;font-size:18px;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'">✕</button>
        <div style="text-align:center;margin-bottom:18px;">
            <div style="width:52px;height:52px;border-radius:14px;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
            <h3 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:6px;">Refuser la proposition</h3>
            <p style="font-size:12.5px;color:var(--text2);line-height:1.6;">Indiquez le motif principal — cela nous aide à mieux ajuster nos futures propositions.</p>
        </div>
        <form method="POST" action="{{ route('proposition.refuser', [$reservation->reference, $reservation->proposition_slug]) }}" id="form-refuser-client">
            @csrf
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px;" role="radiogroup" aria-label="Motif du refus">
                @foreach(\App\Models\Reservation::REFUS_REASONS as $code => $label)
                    <label class="refus-option" data-checked="false"
                           style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border2);border-radius:9px;cursor:pointer;font-size:13px;color:var(--text);transition:all .15s;background:var(--surface2);">
                        <input type="radio" name="reason_code" value="{{ $code }}" required
                               style="accent-color:#e20613;cursor:pointer;flex-shrink:0;"
                               onchange="document.querySelectorAll('.refus-option').forEach(o=>{o.dataset.checked='false';o.style.borderColor='var(--border2)';o.style.background='var(--surface2)';});this.closest('.refus-option').dataset.checked='true';this.closest('.refus-option').style.borderColor='#e20613';this.closest('.refus-option').style.background='rgba(226,6,19,0.05)';">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <textarea name="motif" rows="2"
                      style="width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:10px 14px;font-size:13px;color:var(--text);resize:vertical;outline:none;transition:border-color .15s;margin-bottom:16px;font-family:inherit;"
                      onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'"
                      placeholder="Précisions optionnelles (commentaire libre)…"></textarea>
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
@endif {{-- /canAct (modales) --}}

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
function openConfirmModal()  { const el = document.getElementById('modal-confirm'); if (!el) return; el.style.display='flex'; document.body.style.overflow='hidden'; }
function closeConfirmModal() { const el = document.getElementById('modal-confirm'); if (!el) return; el.style.display='none'; document.body.style.overflow=''; }
function openRefuseModal()   { const el = document.getElementById('modal-refus');   if (!el) return; el.style.display='flex'; document.body.style.overflow='hidden'; }
function closeRefuseModal()  { const el = document.getElementById('modal-refus');   if (!el) return; el.style.display='none'; document.body.style.overflow=''; }

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closePanelModal(); closeConfirmModal(); closeRefuseModal(); }
    if (document.getElementById('modal-panel').style.display !== 'none') {
        if (e.key === 'ArrowLeft'  && currentPhotos.length) { currentPhotoIndex=(currentPhotoIndex-1+currentPhotos.length)%currentPhotos.length; updateModalImage(); }
        if (e.key === 'ArrowRight' && currentPhotos.length) { currentPhotoIndex=(currentPhotoIndex+1)%currentPhotos.length; updateModalImage(); }
    }
});
</script>

@endsection
