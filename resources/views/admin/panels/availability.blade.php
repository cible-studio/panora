<x-admin-layout :title="'Disponibilité — ' . $panel->reference">

<x-slot:topbarLeft>
    <a href="{{ route('admin.panels.show', $panel) }}" class="btn btn-ghost btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour au panneau
    </a>
</x-slot:topbarLeft>

<x-slot:topbarActions>
    <a href="{{ route('admin.reservations.disponibilites', ['statut' => 'libre']) }}"
       class="btn btn-ghost btn-sm">
        🔍 Recherche dispo
    </a>
    <a href="{{ route('admin.panels.pdf', $panel) }}" class="btn btn-ghost btn-sm">
        📄 Fiche PDF
    </a>
</x-slot:topbarActions>

@php
    // Le panneau est-il libre maintenant ?
    $now           = now()->startOfDay();
    $currentBooking = $reservations->first(function ($r) use ($now) {
        $start = $r->start_date instanceof \Carbon\Carbon
            ? $r->start_date : \Carbon\Carbon::parse($r->start_date);
        $end   = $r->end_date instanceof \Carbon\Carbon
            ? $r->end_date : \Carbon\Carbon::parse($r->end_date);
        return $start->lte($now) && $end->gte($now);
    });
    $upcomingBookings = $reservations->reject(fn($r) => $r === $currentBooking);
@endphp

{{-- ── HEADER ──────────────────────────────────────────────── --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;
            padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;
            justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:14px;">
        @if($panel->photos->first())
            <img src="{{ asset('storage/' . $panel->photos->first()->path) }}"
                 alt="{{ $panel->reference }}"
                 style="width:72px;height:54px;object-fit:cover;border-radius:10px;
                        border:1px solid var(--border);">
        @else
            <div style="width:72px;height:54px;border-radius:10px;background:var(--surface2);
                        display:flex;align-items:center;justify-content:center;
                        color:var(--text3);font-size:24px;">🪧</div>
        @endif
        <div>
            <div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:14px;
                        color:var(--accent);font-weight:700;">
                {{ $panel->reference }}
            </div>
            <div style="font-size:16px;font-weight:600;color:var(--text);margin-top:2px;">
                {{ $panel->name }}
            </div>
            <div style="font-size:12px;color:var(--text3);margin-top:2px;">
                📍 {{ $panel->commune?->name ?? '—' }}
                @if($panel->format) · 📐 {{ $panel->format->name }} @endif
                @if($panel->category) · {{ $panel->category->name }} @endif
            </div>
        </div>
    </div>

    <div style="text-align:right;">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;
                    letter-spacing:.5px;margin-bottom:4px;">Statut actuel</div>
        @php $s = $panel->status?->value ?? $panel->status; @endphp
        @if($s === 'libre')
            <span class="badge badge-green" style="font-size:13px;padding:4px 12px;">✅ Libre</span>
        @elseif($s === 'option')
            <span class="badge badge-orange" style="font-size:13px;padding:4px 12px;">⏳ Option</span>
        @elseif($s === 'confirme')
            <span class="badge badge-blue" style="font-size:13px;padding:4px 12px;">🔒 Confirmé</span>
        @elseif($s === 'occupe')
            <span class="badge badge-purple" style="font-size:13px;padding:4px 12px;">📡 Occupé</span>
        @elseif($s === 'maintenance')
            <span class="badge badge-red" style="font-size:13px;padding:4px 12px;">🔧 Maintenance</span>
        @else
            <span class="badge badge-gray" style="font-size:13px;padding:4px 12px;">{{ $s ?? '—' }}</span>
        @endif
    </div>
</div>

{{-- ── BLOC OCCUPATION COURANTE ────────────────────────────── --}}
@if($currentBooking)
    <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);
                border-radius:14px;padding:18px 22px;margin-bottom:18px;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
                    color:#b45309;margin-bottom:8px;">
            🟠 Actuellement occupé
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--text);">
                    {{ $currentBooking->client?->name ?? '—' }}
                </div>
                <div style="font-size:12px;color:var(--text3);margin-top:3px;">
                    Réf. {{ $currentBooking->reference }}
                    · {{ \Carbon\Carbon::parse($currentBooking->start_date)->format('d/m/Y') }}
                    → {{ \Carbon\Carbon::parse($currentBooking->end_date)->format('d/m/Y') }}
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px;color:var(--text3);">Se libère le</div>
                <div style="font-size:14px;font-weight:700;color:#b45309;">
                    {{ \Carbon\Carbon::parse($currentBooking->end_date)->format('d/m/Y') }}
                </div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px;">
                    {{ \Carbon\Carbon::parse($currentBooking->end_date)->diffForHumans() }}
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ── LISTE RÉSERVATIONS À VENIR ─────────────────────────── --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;
            overflow:hidden;">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);
                display:flex;align-items:center;justify-content:space-between;">
        <div style="font-size:13px;font-weight:700;color:var(--text);">
            📅 Réservations à venir
        </div>
        <span style="font-size:11px;color:var(--text3);">
            {{ $upcomingBookings->count() }} programmée(s)
        </span>
    </div>

    @if($upcomingBookings->isEmpty())
        <div style="padding:48px 24px;text-align:center;color:var(--text3);">
            <div style="font-size:42px;margin-bottom:10px;opacity:.5;">📭</div>
            <div style="font-size:14px;font-weight:600;color:var(--text2);">
                Aucune réservation programmée
            </div>
            <div style="font-size:12px;margin-top:4px;">
                Ce panneau est disponible pour une nouvelle proposition.
            </div>
        </div>
    @else
        <table style="width:100%;border-collapse:collapse;">
            <thead style="background:var(--surface2);">
                <tr style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);">
                    <th style="padding:10px 18px;text-align:left;">Client</th>
                    <th style="padding:10px 18px;text-align:left;">Réf.</th>
                    <th style="padding:10px 18px;text-align:left;">Début</th>
                    <th style="padding:10px 18px;text-align:left;">Fin</th>
                    <th style="padding:10px 18px;text-align:left;">Durée</th>
                    <th style="padding:10px 18px;text-align:center;">Statut</th>
                    <th style="padding:10px 18px;text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($upcomingBookings as $r)
                    @php
                        $start = \Carbon\Carbon::parse($r->start_date);
                        $end   = \Carbon\Carbon::parse($r->end_date);
                        $days  = max(1, (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
                        $status = $r->status?->value ?? $r->status;
                    @endphp
                    <tr style="border-top:1px solid var(--border);font-size:13px;">
                        <td style="padding:12px 18px;font-weight:600;color:var(--text);">
                            {{ $r->client?->name ?? '—' }}
                        </td>
                        <td style="padding:12px 18px;font-family:ui-monospace,Menlo,Consolas,monospace;
                                   font-size:12px;color:var(--accent);">
                            <a href="{{ route('admin.reservations.show', $r) }}"
                               style="color:var(--accent);text-decoration:none;">
                                {{ $r->reference }}
                            </a>
                        </td>
                        <td style="padding:12px 18px;color:var(--text2);">{{ $start->format('d/m/Y') }}</td>
                        <td style="padding:12px 18px;color:var(--text2);">{{ $end->format('d/m/Y') }}</td>
                        <td style="padding:12px 18px;color:var(--text3);">
                            {{ $days }} j{{ $days > 1 ? '.' : '' }}
                        </td>
                        <td style="padding:12px 18px;text-align:center;">
                            @if($status === 'confirme')
                                <span class="badge badge-blue">🔒 Confirmée</span>
                            @elseif($status === 'en_attente')
                                <span class="badge badge-orange">⏳ Option</span>
                            @else
                                <span class="badge badge-gray">{{ $status }}</span>
                            @endif
                        </td>
                        <td style="padding:12px 18px;text-align:right;">
                            <a href="{{ route('admin.reservations.show', $r) }}"
                               class="btn btn-ghost btn-sm">Voir →</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

</x-admin-layout>
