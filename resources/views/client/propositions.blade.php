<!-- resources/views/client/propositions.blade.php -->
@extends('client.layout')
@section('title', 'Mes propositions')
@section('page-title', 'Propositions commerciales')

@section('content')

{{-- ══ STATS ══ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2" style="margin-bottom:10px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.18 2 2 0 0 1 3.58 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.56a16 16 0 0 0 5.55 5.55l1.63-1.84a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 14.92z"/></svg>
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
    $total    = $res->panels->sum(fn($p) => (float)($p->monthly_rate ?? 0));
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

                    @if($isNew)
                        <span style="font-size:10px;font-weight:700;background:rgba(250,184,11,.1);color:#fab80b;padding:2px 8px;border-radius:20px;">Nouvelle</span>
                    @elseif($expired)
                        <span style="font-size:10px;font-weight:700;background:rgba(148,163,184,.1);color:#94a3b8;padding:2px 8px;border-radius:20px;">Expirée</span>
                    @elseif($status === 'confirme')
                        <span style="font-size:10px;font-weight:700;background:rgba(34,197,94,.1);color:#22c55e;padding:2px 8px;border-radius:20px;">Confirmée</span>
                    @elseif(in_array($status, ['annule','refuse']))
                        <span style="font-size:10px;font-weight:700;background:rgba(239,68,68,.1);color:#ef4444;padding:2px 8px;border-radius:20px;">Refusée</span>
                    @elseif($viewed)
                        <span style="font-size:10px;font-weight:700;background:rgba(59,130,246,.1);color:#60a5fa;padding:2px 8px;border-radius:20px;">Consultée</span>
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
                    <span>{{ $res->panels->count() }} panneau(x)</span>
                    @if($total > 0)
                        <span>{{ number_format($total, 0, ',', ' ') }} FCFA/mois</span>
                    @endif
                    @if($res->proposition_sent_at)
                        <span>Reçue {{ $res->proposition_sent_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:8px;flex-shrink:0;align-items:center;">
                @if(!$expired && $status === 'en_attente')
                    <a href="{{ route('client.proposition.detail', $res->proposition_token) }}"
                       style="padding:8px 16px;background:#e20613;color:#fff;font-weight:600;border-radius:9px;font-size:12px;text-decoration:none;transition:opacity .15s;"
                       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                        Voir et répondre
                    </a>
                @else
                    <a href="{{ route('client.proposition.detail', $res->proposition_token) }}"
                       style="padding:8px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:12px;color:var(--text2);text-decoration:none;transition:all .15s;"
                       onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
                        Consulter
                    </a>
                @endif
                @php
                    // Route 'proposition.show' attend [reference, slug].
                    // Si le slug n'a pas été généré (anciennes propositions), fallback sur la
                    // route legacy par token qui redirige automatiquement.
                    $publicLink = $res->proposition_slug
                        ? route('proposition.show', [$res->reference, $res->proposition_slug])
                        : route('proposition.show.legacy', $res->proposition_token);
                @endphp
                <a href="{{ $publicLink }}" target="_blank" rel="noopener"
                   style="padding:8px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;font-size:12px;color:var(--text2);text-decoration:none;transition:all .15s;"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
                    Lien public
                </a>
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
