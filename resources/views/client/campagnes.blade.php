@extends('client.layout')
@section('title', 'Mes campagnes')
@section('page-title', 'Mes campagnes')

@section('content')

{{-- ══ FILTRES ══ --}}
<div style="margin-bottom:20px;">
    <form method="GET" action="{{ route('client.campagnes') }}" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div style="flex:1;min-width:180px;">
            <input type="text" name="search" placeholder="Rechercher une campagne..."
                   value="{{ request('search') }}"
                   style="width:100%;background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:9px 14px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                   onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
        </div>
        <div>
            <select name="status"
                    style="background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:9px 14px;font-size:13px;color:var(--text);outline:none;cursor:pointer;transition:border-color .15s;"
                    onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'">
                <option value="">Tous les statuts</option>
                <option value="actif"   {{ request('status') == 'actif'   ? 'selected' : '' }}>Actif</option>
                <option value="pose"    {{ request('status') == 'pose'    ? 'selected' : '' }}>En pose</option>
                <option value="termine" {{ request('status') == 'termine' ? 'selected' : '' }}>Terminé</option>
            </select>
        </div>
        <button type="submit"
                style="padding:9px 20px;background:#e20613;color:#fff;font-weight:600;border-radius:9px;font-size:13px;border:none;cursor:pointer;transition:opacity .15s;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            Filtrer
        </button>
        @if(request('search') || request('status'))
        <a href="{{ route('client.campagnes') }}"
           style="padding:9px 16px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;font-size:13px;color:var(--text2);text-decoration:none;transition:all .15s;"
           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text2)'">
            ↺ Effacer
        </a>
        @endif
    </form>
</div>

{{-- ══ TABLEAU ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid var(--border2);">
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Nom</th>
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Période</th>
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Panneaux</th>
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Montant</th>
                    <th style="text-align:left;padding:12px 16px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">Statut</th>
                    <th style="padding:12px 16px;width:80px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($campagnes as $camp)
                @php
                    $s = $camp->status->value;
                    $badge = match($s) {
                        'actif'   => ['bg'=>'rgba(34,197,94,.1)',  'color'=>'#22c55e',  'label'=>'Actif'],
                        'pose'    => ['bg'=>'rgba(59,130,246,.1)', 'color'=>'#60a5fa',  'label'=>'En pose'],
                        'termine' => ['bg'=>'rgba(250,184,11,.1)', 'color'=>'#fab80b',  'label'=>'Terminé'],
                        default   => ['bg'=>'rgba(148,163,184,.1)','color'=>'#94a3b8',  'label'=>ucfirst($s)],
                    };
                @endphp
                <tr style="border-bottom:1px solid var(--border);transition:background .1s;"
                    onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
                    <td style="padding:14px 16px;">
                        <span style="font-weight:600;font-size:14px;color:var(--text);">{{ $camp->name }}</span>
                    </td>
                    <td style="padding:14px 16px;font-size:12px;color:var(--text2);white-space:nowrap;">
                        {{ $camp->start_date->format('d/m/Y') }} → {{ $camp->end_date->format('d/m/Y') }}
                    </td>
                    <td style="padding:14px 16px;font-size:13px;color:var(--text2);">
                        {{ $camp->panels->count() }}
                    </td>
                    <td style="padding:14px 16px;font-size:13px;font-weight:700;color:#e20613;white-space:nowrap;">
                        {{ number_format($camp->total_amount ?? 0, 0, ',', ' ') }} FCFA
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;background:{{ $badge['bg'] }};color:{{ $badge['color'] }};">
                            {{ $badge['label'] }}
                        </span>
                    </td>
                    <td style="padding:14px 16px;">
                        <a href="{{ route('client.campagne.detail', $camp) }}"
                           style="font-size:12px;font-weight:600;color:#e20613;text-decoration:none;white-space:nowrap;transition:opacity .15s;"
                           onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                            Voir →
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding:60px;text-align:center;color:var(--text3);">
                        <div style="font-size:40px;margin-bottom:10px;opacity:.4;">📭</div>
                        <div style="font-size:14px;font-weight:600;color:var(--text2);margin-bottom:4px;">Aucune campagne trouvée</div>
                        <div style="font-size:12px;">Modifiez vos filtres pour afficher plus de résultats</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($campagnes->hasPages())
<div style="margin-top:20px;">
    {{ $campagnes->appends(request()->query())->links() }}
</div>
@endif

<style>
.pagination { display:flex;justify-content:center;gap:6px;flex-wrap:wrap; }
.pagination .page-link { display:flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 10px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text2);font-size:13px;transition:all .15s;text-decoration:none; }
.pagination .page-link:hover { background:rgba(226,6,19,.08);border-color:rgba(226,6,19,.25);color:#e20613; }
.pagination .active .page-link { background:#e20613;border-color:#e20613;color:#fff;font-weight:600; }
.pagination .disabled .page-link { opacity:.4;cursor:not-allowed; }
</style>

@endsection
