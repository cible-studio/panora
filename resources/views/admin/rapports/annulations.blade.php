<x-admin-layout title="Rapport — Motifs d'annulation">
    <x-slot:topbarLeft>
        <a href="{{ route('admin.rapports.index') }}" class="btn btn-ghost btn-sm">← Retour aux rapports</a>
    </x-slot:topbarLeft>

    {{-- Filtre année --}}
    <form method="GET" action="{{ route('admin.rapports.annulations') }}" style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <label style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;">Année</label>
        <select name="annee" onchange="this.form.submit()"
                style="height:36px;padding:0 12px;background:var(--surface);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);">
            @foreach($anneesDisponibles as $y)
                <option value="{{ $y }}" {{ $y === $annee ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
        <span style="font-size:13px;color:var(--text3);">{{ $total }} annulation(s)</span>
    </form>

    {{-- Résumé --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px;">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px;">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);margin-bottom:6px;">Total annulées</div>
            <div style="font-size:28px;font-weight:800;color:#ef4444;">{{ $total }}</div>
            <div style="font-size:11px;color:var(--text3);">en {{ $annee }}</div>
        </div>
        @if($byReason->isNotEmpty())
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px;">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);margin-bottom:6px;">Motif principal</div>
            <div style="font-size:16px;font-weight:700;color:var(--text);">{{ $byReason->first()['label'] }}</div>
            <div style="font-size:11px;color:var(--text3);">{{ $byReason->first()['count'] }} cas ({{ $byReason->first()['pct'] }}%)</div>
        </div>
        @endif
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px;">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);margin-bottom:6px;">Renseignées</div>
            @php $renseignees = $cancelledAll->whereNotNull('cancellation_reason')->count(); @endphp
            <div style="font-size:28px;font-weight:800;color:var(--text);">{{ $renseignees }}</div>
            <div style="font-size:11px;color:var(--text3);">{{ $total > 0 ? round($renseignees/$total*100) : 0 }}% documentées</div>
        </div>
    </div>

    @if($byReason->isNotEmpty())
    {{-- Graphique barres --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;margin-bottom:24px;">
        <h2 style="font-size:15px;font-weight:600;color:var(--text);margin:0 0 18px;">Répartition par motif</h2>
        <div style="display:grid;gap:10px;">
            @foreach($byReason as $r)
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                    <span style="font-size:13px;font-weight:500;color:var(--text);">{{ $r['label'] }}</span>
                    <span style="font-size:12px;font-weight:700;color:{{ $r['color'] }};">{{ $r['count'] }} ({{ $r['pct'] }}%)</span>
                </div>
                <div style="background:var(--surface2);border-radius:4px;height:8px;overflow:hidden;">
                    <div style="height:100%;border-radius:4px;background:{{ $r['color'] }};width:{{ $r['pct'] }}%;transition:width 0.6s ease;"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Tableau détaillé --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;">
        <div style="padding:14px 20px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:15px;font-weight:600;color:var(--text);">Détail des campagnes annulées</span>
            <span style="font-size:12px;color:var(--text3);">{{ $total }} entrée(s)</span>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:var(--surface2);">
                        <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);border-bottom:1px solid var(--border);">Campagne</th>
                        <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);border-bottom:1px solid var(--border);">Client</th>
                        <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);border-bottom:1px solid var(--border);">Date annulation</th>
                        <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);border-bottom:1px solid var(--border);">Motif</th>
                        <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);border-bottom:1px solid var(--border);">Précision</th>
                        <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);border-bottom:1px solid var(--border);">Annulé par</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cancelledAll as $c)
                    @php
                        $rKey   = $c->cancellation_reason ?? '';
                        $rLabel = $reasonLabels[$rKey] ?? 'Non renseigné';
                        $rColor = $reasonColors[$rKey] ?? '#6b7280';
                    @endphp
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:11px 14px;">
                            <a href="{{ route('admin.campaigns.show', $c) }}"
                               style="font-weight:600;color:var(--text);text-decoration:none;">{{ $c->name }}</a>
                        </td>
                        <td style="padding:11px 14px;font-size:13px;color:var(--text2);">
                            {{ $c->client?->name ?? '—' }}
                        </td>
                        <td style="padding:11px 14px;font-size:12px;color:var(--text3);white-space:nowrap;">
                            {{ $c->updated_at->format('d/m/Y H:i') }}
                        </td>
                        <td style="padding:11px 14px;">
                            @if($rKey)
                            <span style="padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:{{ $rColor }}18;color:{{ $rColor }};border:1px solid {{ $rColor }}44;">
                                {{ $rLabel }}
                            </span>
                            @else
                            <span style="font-size:12px;color:var(--text3);">—</span>
                            @endif
                        </td>
                        <td style="padding:11px 14px;font-size:12px;color:var(--text2);max-width:200px;">
                            {{ $c->cancellation_notes ?: '—' }}
                        </td>
                        <td style="padding:11px 14px;font-size:12px;color:var(--text3);">
                            {{ $c->user?->name ?? '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:48px;color:var(--text3);">
                            <div style="font-size:40px;margin-bottom:12px;">✅</div>
                            <div>Aucune campagne annulée en {{ $annee }}</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
