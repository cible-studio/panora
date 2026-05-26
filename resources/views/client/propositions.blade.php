<!-- resources/views/client/propositions.blade.php -->
@extends('client.layout')
@section('title', 'Mes propositions')
@section('page-title', 'Propositions commerciales')

@section('content')

{{-- ══ STATS ══ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        <div style="font-size:24px;font-weight:800;color:#e20613;line-height:1;">{{ $propositions->total() }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:5px;">Total propositions</div>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2" style="margin-bottom:10px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div style="font-size:24px;font-weight:800;color:#fab80b;line-height:1;">{{ $propositions->where('proposition_viewed_at', null)->where('end_date', '>=', now())->count() }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:5px;">Nouvelles</div>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" style="margin-bottom:10px;"><polyline points="20 6 9 17 4 12"/></svg>
        <div style="font-size:24px;font-weight:800;color:#22c55e;line-height:1;">{{ $propositions->where('status', 'confirme')->count() }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:5px;">Acceptées</div>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="margin-bottom:10px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div style="font-size:24px;font-weight:800;color:var(--text2);line-height:1;">{{ $propositions->where('end_date', '<', now())->count() }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:5px;">Expirées</div>
    </div>

</div>

{{-- ══ FILTRES ══ --}}
<div style="margin-bottom:20px;">
    <form method="GET" action="{{ route('client.propositions') }}" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div>
            <label style="display:block;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px;">Recherche</label>
            <input type="text" name="search"
                   style="background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:8px 14px;font-size:13px;color:var(--text);width:220px;outline:none;transition:border-color .15s;"
                   onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'"
                   placeholder="Référence..." value="{{ request('search') }}">
        </div>
        <div>
            <label style="display:block;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px;">Statut</label>
            <select name="status"
                    style="background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:8px 14px;font-size:13px;color:var(--text);outline:none;cursor:pointer;transition:border-color .15s;"
                    onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
                <option value="">Tous</option>
                <option value="en_attente" {{ request('status') == 'en_attente' ? 'selected' : '' }}>En attente</option>
                <option value="confirme"   {{ request('status') == 'confirme'   ? 'selected' : '' }}>Confirmée</option>
                <option value="refuse"     {{ request('status') == 'refuse'     ? 'selected' : '' }}>Refusée</option>
            </select>
        </div>
        <button type="submit"
                style="padding:8px 18px;background:#e20613;color:#fff;font-weight:600;border-radius:9px;font-size:13px;border:none;cursor:pointer;transition:opacity .15s;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            Filtrer
        </button>
        @if(request('search') || request('status'))
        <a href="{{ route('client.propositions') }}"
           style="padding:8px 16px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;font-size:13px;color:var(--text2);text-decoration:none;transition:all .15s;"
           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
            ↺ Effacer
        </a>
        @endif
    </form>
</div>

{{-- ══ LISTE PROPOSITIONS ══ --}}
@forelse($propositions as $res)
@php
    // Compte unifié interne + externe. total_amount fait foi côté finance —
    // y compris quand il vaut 0 (campagne offerte / package gratuit). On ne
    // tombe sur la somme catalogue QUE si total_amount est null (pas
    // renseigné), sinon le client voit les tarifs catalogue alors que la
    // proposition est à 0.
    $totalInternal = $res->panels->sum(fn($p) => (float)($p->monthly_rate ?? 0));
    $totalExternal = $res->externalPanels->sum(fn($p) => (float)($p->monthly_rate ?? 0));
    $total    = $res->total_amount !== null
                ? (float) $res->total_amount
                : $totalInternal + $totalExternal;
    $panelCount = $res->panels->count() + $res->externalPanels->count();
    $expired  = $res->end_date < now();
    $viewed   = !is_null($res->proposition_viewed_at);
    $status   = $res->status->value;
    $daysLeft = now()->startOfDay()->diffInDays($res->end_date->startOfDay(), false);
    $isNew    = !$viewed && !$expired && $status === 'en_attente';
@endphp

<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;margin-bottom:10px;overflow:hidden;transition:border-color .2s;opacity:{{ $expired ? '.6' : '1' }};{{ $isNew ? 'border-left:3px solid #e20613;' : '' }}"
     onmouseover="this.style.borderColor='rgba(226,6,19,.25)'" onmouseout="this.style.borderColor='var(--border)'">
    <div style="padding:16px 20px;">
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px;">

            <div style="flex:1;min-width:0;">
                {{-- Référence + badge --}}
                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                    <span style="font-family:monospace;font-size:12px;font-weight:700;color:#e20613;background:rgba(226,6,19,.08);padding:3px 10px;border-radius:6px;">{{ $res->reference }}</span>

                    {{-- Lot 12.4 — badge statut unifié 4 couleurs --}}
                    @if($isNew)
                        <span style="font-size:10px;font-weight:700;padding:3px 10px;border-radius:14px;background:rgba(250,184,11,.12);color:#c2570d;border:1px solid rgba(250,184,11,.3);">Nouvelle</span>
                    @elseif($expired && $status === 'en_attente')
                        <span style="font-size:10px;font-weight:700;padding:3px 10px;border-radius:14px;background:rgba(148,163,184,.12);color:#64748b;border:1px solid rgba(148,163,184,.25);">Expirée</span>
                    @else
                        @include('client.partials._status-badge', ['status' => $status])
                        @if($viewed && $status === 'en_attente')
                            <span style="font-size:10px;font-weight:700;padding:3px 10px;border-radius:14px;background:rgba(59,130,246,.12);color:#1d4ed8;border:1px solid rgba(59,130,246,.3);">Consultée</span>
                        @endif
                    @endif
                </div>

                {{-- Dates --}}
                <div style="font-size:13px;color:var(--text2);margin-bottom:6px;">
                    📅 {{ $res->start_date->format('d/m/Y') }} → {{ $res->end_date->format('d/m/Y') }}
                    @if(!$expired && $status === 'en_attente')
                        <span style="font-size:11px;color:{{ $daysLeft <= 3 ? '#ef4444' : '#fab80b' }};margin-left:10px;">
                            ⏳ {{ $daysLeft }}j restant(s)
                        </span>
                    @endif
                </div>

                {{-- Infos --}}
                <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:11px;color:var(--text3);">
                    <span>{{ $panelCount }} panneau(x)</span>
                    @if($total > 0)
                        <span>{{ number_format($total, 0, ',', ' ') }} FCFA</span>
                    @elseif($res->total_amount !== null)
                        {{-- 0 FCFA explicitement saisi (campagne offerte) --}}
                        <span style="color:#16a34a;font-weight:600;">0 FCFA · Offert</span>
                    @endif
                    @if($res->proposition_sent_at)
                        <span>Reçue {{ $res->proposition_sent_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            @php
                // Une proposition est ACTIONNABLE seulement si :
                //   - statut = en_attente
                //   - pas expirée
                //   - token + slug présents
                $isActionable = !$expired
                    && $status === 'en_attente'
                    && $res->proposition_token
                    && $res->proposition_slug;
            @endphp
            <div style="display:flex;gap:8px;flex-shrink:0;align-items:center;">
                @if($isActionable)
                    {{-- Bouton primaire : aller à la fiche client connecté pour répondre --}}
                    <a href="{{ route('client.proposition.detail', $res->proposition_token) }}"
                       style="padding:8px 16px;background:#e20613;color:#fff;font-weight:600;border-radius:9px;font-size:12px;text-decoration:none;transition:opacity .15s;"
                       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                        Voir et répondre
                    </a>

                    {{-- Lien public (utile pour partager, ex. par email/WhatsApp) --}}
                    <a href="{{ route('proposition.show', [$res->reference, $res->proposition_slug]) }}"
                       target="_blank" rel="noopener"
                       title="Ouvrir la version publique (utile pour la partager)"
                       style="padding:8px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:12px;color:var(--text2);text-decoration:none;transition:all .15s;"
                       onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
                        Lien public
                    </a>
                @else
                    {{-- Non actionnable (déjà confirmée/refusée/expirée) :
                         on garde uniquement la consultation, pas le lien public
                         (qui pourrait laisser croire qu'on peut encore agir). --}}
                    <a href="{{ route('client.proposition.detail', $res->proposition_token) }}"
                       style="padding:8px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:12px;color:var(--text2);text-decoration:none;transition:all .15s;"
                       onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
                        Consulter
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@empty
<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:60px;text-align:center;">
    <div style="font-size:48px;margin-bottom:12px;opacity:.4;">📭</div>
    <div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:6px;">Aucune proposition reçue</div>
    <div style="font-size:13px;color:var(--text3);">Vos propositions commerciales apparaîtront ici dès qu'elles vous seront envoyées.</div>
</div>
@endforelse

<div style="margin-top:20px;">
    {{ $propositions->appends(request()->query())->links() }}
</div>

@endsection
