<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1a1a2e; }

        .header {
            background: #0a0c10;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo { font-size: 22px; font-weight: 800; color: #e8a020; }
        .logo-sub { font-size: 10px; color: #8a90a2; margin-top: 2px; }
        .ref { font-size: 14px; font-weight: 700; color: #e8a020; }

        .content { padding: 25px 30px; }

        .title { font-size: 18px; font-weight: 700; margin-bottom: 20px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }

        .section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
        }
        .section-title {
            font-size: 10px;
            font-weight: 700;
            color: #e8a020;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
        }

        .field { margin-bottom: 8px; }
        .field-label { font-size: 9px; color: #64748b; text-transform: uppercase; }
        .field-value { font-size: 12px; font-weight: 600; margin-top: 2px; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }
        .badge-green  { background: #dcfce7; color: #16a34a; }
        .badge-orange { background: #fef3c7; color: #d97706; }
        .badge-blue   { background: #dbeafe; color: #2563eb; }
        .badge-red    { background: #fee2e2; color: #dc2626; }
        .badge-gray   { background: #f1f5f9; color: #475569; }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 30px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }

        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; padding: 8px; text-align: left; font-size: 9px; color: #64748b; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div>
            <div class="logo">CIBLE CI</div>
            <div class="logo-sub">RÉGIE OOH — FICHE PANNEAU</div>
        </div>
        <div class="ref">{{ $panel->reference }}</div>
    </div>

    <div class="content">

        {{-- TITRE --}}
        <div style="margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #e8a020;">
            <div class="title">{{ $panel->name }}</div>
            <div style="display:flex; gap:10px; align-items:center;">
                @if($panel->status->value === 'libre')
                    <span class="badge badge-green">Libre</span>
                @elseif($panel->status->value === 'option')
                    <span class="badge badge-orange">Option</span>
                @elseif($panel->status->value === 'confirme')
                    <span class="badge badge-blue">Confirmé</span>
                @elseif($panel->status->value === 'occupe')
                    <span class="badge badge-blue">Occupé</span>
                @else
                    <span class="badge badge-red">Maintenance</span>
                @endif
                @if($panel->is_lit)
                    <span class="badge badge-orange">💡 Éclairé</span>
                @endif
            </div>
        </div>

        {{-- GRID INFOS --}}
        <div class="grid-2">

            {{-- LOCALISATION --}}
            <div class="section">
                <div class="section-title">📍 Localisation</div>
                <div class="field">
                    <div class="field-label">Commune</div>
                    <div class="field-value">{{ $panel->commune->name }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Zone</div>
                    <div class="field-value">{{ $panel->zone?->name ?? '—' }}</div>
                </div>
                @if($panel->zone_description)
                <div class="field">
                    <div class="field-label">Description emplacement</div>
                    <div class="field-value" style="font-weight:400;">{{ $panel->zone_description }}</div>
                </div>
                @endif
                @if($panel->latitude && $panel->longitude)
                <div class="field">
                    <div class="field-label">Coordonnées GPS</div>
                    <div class="field-value" style="font-family:monospace; font-size:11px;">
                        {{ $panel->latitude }}, {{ $panel->longitude }}
                    </div>
                </div>
                @endif
            </div>

            {{-- CARACTÉRISTIQUES --}}
            <div class="section">
                <div class="section-title">📐 Caractéristiques</div>
                <div class="field">
                    <div class="field-label">Format</div>
                    <div class="field-value">{{ $panel->format->name }}</div>
                </div>
                @if($panel->format->surface)
                <div class="field">
                    <div class="field-label">Surface</div>
                    <div class="field-value">{{ $panel->format->surface }} m²</div>
                </div>
                @endif
                <div class="field">
                    <div class="field-label">Catégorie</div>
                    <div class="field-value">{{ $panel->category?->name ?? '—' }}</div>
                </div>
                @if($panel->daily_traffic)
                <div class="field">
                    <div class="field-label">Trafic journalier</div>
                    <div class="field-value">{{ number_format($panel->daily_traffic, 0, ',', ' ') }} véhicules/jour</div>
                </div>
                @endif
            </div>

        </div>

        {{-- TARIFICATION --}}
        <div class="section" style="margin-bottom:20px;">
            <div class="section-title">💰 Tarification</div>
            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:15px;">
                <div class="field">
                    <div class="field-label">Tarif mensuel</div>
                    <div class="field-value" style="color:#e8a020; font-size:16px;">
                        {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                    </div>
                </div>
                <div class="field">
                    <div class="field-label">Tarif trimestriel</div>
                    <div class="field-value">
                        {{ number_format($panel->monthly_rate * 3, 0, ',', ' ') }} FCFA
                    </div>
                </div>
                <div class="field">
                    <div class="field-label">Tarif annuel</div>
                    <div class="field-value">
                        {{ number_format($panel->monthly_rate * 12, 0, ',', ' ') }} FCFA
                    </div>
                </div>
            </div>
        </div>

        {{-- HISTORIQUE MAINTENANCES --}}
        @if($panel->maintenances->count() > 0)
        <div class="section">
            <div class="section-title">🔧 Historique maintenances</div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type de panne</th>
                        <th>Priorité</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($panel->maintenances->take(5) as $maintenance)
                    <tr>
                        <td>{{ $maintenance->date_signalement->format('d/m/Y') }}</td>
                        <td>{{ $maintenance->type_panne }}</td>
                        <td>{{ ucfirst($maintenance->priorite) }}</td>
                        <td>{{ ucfirst($maintenance->statut) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>

    {{-- FOOTER --}}
    <div class="footer">
        <div>CIBLE CI — Régie OOH — Document confidentiel</div>
        <div>Généré le {{ now()->format('d/m/Y à H:i') }}</div>
        <div>{{ $panel->reference }}</div>
    </div>

</body>
</html>
