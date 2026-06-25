<x-admin-layout>
<x-slot name="title">Import campagne — Prévisualisation</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.campaigns.import-pdf.form') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Re-uploader un PDF
    </a>
</x-slot:topbarLeft>

<div style="max-width:1000px;margin:0 auto">

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--text3);margin-bottom:16px">
        <a href="{{ route('admin.campaigns.index') }}" style="color:var(--text3);text-decoration:none">Campagnes</a>
        <span style="margin:0 6px">›</span>
        <a href="{{ route('admin.campaigns.import-pdf.form') }}" style="color:var(--text3);text-decoration:none">Import PDF</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Prévisualisation</span>
    </div>

    {{-- Bandeau récap --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 24px;margin-bottom:18px">
        <div style="font-size:10px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:1.2px;margin-bottom:8px">Données extraites du PDF</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Client</div>
                <div style="font-size:15px;font-weight:700;color:var(--text);margin-top:2px">
                    {{ $data['client'] }}
                </div>
                @if($existingClient)
                    <div style="font-size:11px;color:#22c55e;margin-top:3px">✓ Trouvé en base <span style="color:var(--text3)">(#{{ $existingClient->id }})</span></div>
                @else
                    <div style="font-size:11px;color:#3b82f6;margin-top:3px">✨ Sera créé automatiquement</div>
                @endif
            </div>
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Campagne</div>
                <div style="font-size:15px;font-weight:700;color:var(--text);margin-top:2px">{{ $data['campaign'] }}</div>
                @if($existingCampaign)
                    <div style="font-size:11px;color:#f59e0b;margin-top:3px">⚠️ Une campagne homonyme existe déjà sur cette période (#{{ $existingCampaign->id }})</div>
                @else
                    <div style="font-size:11px;color:#22c55e;margin-top:3px">✓ Nom disponible</div>
                @endif
            </div>
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Période</div>
                <div style="font-size:15px;font-weight:700;color:var(--text);margin-top:2px">
                    {{ $data['start_date']->format('d/m/Y') }} → {{ $data['end_date']->format('d/m/Y') }}
                </div>
                @php
                    // diffInDays renvoie un float quand start=00:00 et end=23:59:59
                    // → on arrondit pour afficher proprement.
                    $days = (int) round($data['start_date']->copy()->startOfDay()->diffInDays($data['end_date']->copy()->startOfDay()));
                    // RÈGLE PATRONNE 2026-06-25 — identique à Campaign::billableMonths() :
                    //   mois = jours / 30, arrondi au demi-mois le plus proche, plancher 0.5
                    $billableMonths = $days <= 0 ? 0.5 : max(0.5, round(($days / 30) * 2) / 2);
                @endphp
                <div style="font-size:11px;color:var(--text3);margin-top:3px">{{ $days }} jour(s) — {{ rtrim(rtrim(number_format($billableMonths, 1, ',', ''), '0'), ',') }} mois</div>
            </div>
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Panneaux détectés</div>
                <div style="font-size:15px;font-weight:700;color:var(--text);margin-top:2px">{{ count($data['codes']) }} code(s)</div>
                <div style="font-size:11px;margin-top:3px">
                    <span style="color:#22c55e">✓ {{ $foundPanels->count() }} trouvé(s)</span>
                    @if(count($missingCodes) > 0)
                        <span style="color:#ef4444">· ❌ {{ count($missingCodes) }} introuvable(s)</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Alerte panneaux manquants (bloquante) --}}
    @if(count($missingCodes) > 0)
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:18px 22px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="font-size:18px">❌</span>
            <span style="font-size:14px;font-weight:700;color:#ef4444">Import bloqué — codes panneaux introuvables en base</span>
        </div>
        <div style="font-size:12px;color:var(--text2);line-height:1.6;margin-bottom:10px">
            Les codes suivants n'existent pas dans le catalogue Panora. Ajoute-les via
            <a href="{{ route('admin.panels.create') }}" style="color:var(--accent);text-decoration:none">l'inventaire</a>
            puis re-tente l'import :
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
            @foreach($missingCodes as $code)
                <span style="font-family:monospace;font-size:12px;padding:4px 10px;background:rgba(239,68,68,.10);border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#ef4444;font-weight:600">
                    {{ $code }}
                </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Tableau panneaux trouvés --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">🪧 Panneaux qui seront liés à la campagne ({{ $foundPanels->count() }})</div>
        </div>
        @if($foundPanels->isEmpty())
            <div style="padding:40px;text-align:center;color:var(--text3);font-size:13px">Aucun panneau à lier.</div>
        @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Commune</th>
                        <th style="text-align:right">Tarif mensuel</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($foundPanels as $panel)
                    <tr>
                        <td>
                            <span style="font-family:monospace;font-weight:700;color:var(--accent)">{{ $panel->reference }}</span>
                        </td>
                        <td>{{ $panel->name }}</td>
                        <td style="color:var(--text2);font-size:12px">{{ $panel->commune?->name ?? '—' }}</td>
                        <td style="text-align:right;color:var(--text2);font-size:12px">
                            @if($panel->monthly_rate > 0)
                                {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                            @else
                                <span style="color:var(--text3)">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Action bar --}}
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding:18px 22px;background:var(--surface);border:1px solid var(--border);border-radius:14px">
        <a href="{{ route('admin.campaigns.import-pdf.form') }}" class="btn btn-ghost">Annuler</a>
        @if($canImport)
            <form method="POST" action="{{ route('admin.campaigns.import-pdf.apply') }}"
                  onsubmit="return confirm('Créer la campagne {{ $data['campaign'] }} avec {{ $foundPanels->count() }} panneau(x) ?');"
                  style="display:inline">
                @csrf
                <button type="submit" class="btn btn-primary">
                    ✅ Confirmer l'import
                </button>
            </form>
        @else
            <button type="button" class="btn btn-primary" disabled
                    style="opacity:.5;cursor:not-allowed"
                    title="Ajoute d'abord les panneaux manquants au catalogue">
                ❌ Import bloqué
            </button>
        @endif
    </div>

    <div style="margin-top:14px;font-size:11px;color:var(--text3);text-align:center;line-height:1.6">
        À la validation : création client (si absent) → création campagne → liaison des panneaux → calcul du total tarif catalogue × durée.
        Tu pourras éditer le total et les statuts ensuite depuis la fiche campagne.
    </div>
</div>

</x-admin-layout>
