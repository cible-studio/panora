<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        /* ═══════════════════════════════════════════════════════════════
           CIBLE CI — Liste des Disponibilités
           Chartre graphique : Rouge (#e20613) + Doré (#e8a020)
           ═══════════════════════════════════════════════════════════════ */
        * { margin:0; padding:0; box-sizing:border-box; }

        @page {
            margin: 12mm 10mm;
            size: A4 landscape;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8px;
            color: #1e293b;
            background: #fff;
            line-height: 1.4;
        }

        /* ── PAGE ── */
        .page {
            position: relative;
            min-height: 190mm;
        }

        /* ── HEADER AVEC LOGO ── */
        .header {
            background: #0f172a;
            padding: 10px 14px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e8a020;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-img {
            width: 35px;
            height: auto;
        }

        .header h1 {
            font-size: 12px;
            font-weight: 700;
            color: #e8a020;
            margin: 0;
        }

        .header p {
            font-size: 7px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .header-right {
            text-align: right;
            font-size: 7px;
            color: #94a3b8;
        }

        /* ── TABLEAU ── */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: #f1f5f9;
        }

        th {
            padding: 6px 6px;
            text-align: left;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            color: #1e293b;
            border-bottom: 2px solid #e20613;
        }

        td {
            padding: 5px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        tr:nth-child(even) td {
            background: #f8fafc;
        }

        /* ── STYLES ── */
        .ref {
            font-family: monospace;
            font-weight: 700;
            font-size: 9px;
            color: #e20613;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 7px;
            font-weight: 600;
        }
        .badge-libre       { background: #dcfce7; color: #166534; }
        .badge-occupe      { background: #fee2e2; color: #991b1b; }
        .badge-option      { background: #fef3c7; color: #92400e; }
        .badge-confirme    { background: #dbeafe; color: #1e40af; }
        .badge-maintenance { background: #f1f5f9; color: #475569; }

        .lit {
            color: #e8a020;
            font-weight: 600;
            font-size: 8px;
        }
        .non-lit {
            color: #94a3b8;
            font-size: 8px;
        }

        /* ── TOTAUX ── */
        .totals {
            margin-top: 12px;
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-align: right;
            font-size: 8px;
            font-weight: 700;
        }
        .totals span {
            color: #e20613;
            font-size: 10px;
        }

        /* ── FOOTER ── */
        .footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            margin-top: 12px;
            text-align: center;
            font-size: 6px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }

        /* ── MENTIONS ── */
        .client-ref {
            margin-top: 8px;
            padding: 6px 10px;
            background: #fefce8;
            border-left: 3px solid #e8a020;
            font-size: 7px;
            color: #475569;
        }
    </style>
</head>
<body>

@php
    $logoPath = public_path('images/logon.png');
    $logoBase64 = null;
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
    }
@endphp

<div class="page">
    {{-- HEADER --}}
    <div class="header">
        <div class="logo-container">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" class="logo-img" alt="CIBLE CI">
            @endif
            <div>
                <h1>CIBLE CI — Sélection de panneaux</h1>
                <p>Généré le {{ $generated ?? now()->format('d/m/Y H:i') }} · {{ count($panels) }} panneau(x)</p>
                @if($startDate && $endDate)
                    <p>Période : {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>
                @endif
            </div>
        </div>
        <div class="header-right">
            @if(isset($reservation_ref))
                Réservation : <strong>{{ $reservation_ref }}</strong><br>
            @endif
            @if(isset($client_name))
                Client : {{ $client_name }}
            @endif
        </div>
    </div>

    {{-- Mention réservation (si présente) --}}
    @if(isset($reservation_ref) || isset($client_name))
    <div class="client-ref">
        📄 Document confidentiel — Proposition commerciale
        @if(isset($reservation_ref))
            · Réf. réservation : <strong>{{ $reservation_ref }}</strong>
        @endif
        @if(isset($client_name))
            · Client : <strong>{{ $client_name }}</strong>
        @endif
    </div>
    @endif

    {{-- TABLEAU --}}
    <table>
        <thead>
            <tr>
                <th>Réf.</th>
                <th>Emplacement</th>
                <th>Commune</th>
                <th>Zone</th>
                <th>Format</th>
                <th>Dimensions</th>
                <th>Catégorie</th>
                <th>💡</th>
                <th>Trafic/j</th>
                @unless($hideStatus ?? false)
                <th>Statut</th>
                @endunless
            </tr>
        </thead>
        <tbody>
            @foreach($panels as $p)
            @php
                $statusLabel = match($p->status->value ?? $p['status'] ?? 'libre') {
                    'libre'          => ['label' => 'Disponible', 'class' => 'badge-libre'],
                    'occupe'         => ['label' => 'Occupé',     'class' => 'badge-occupe'],
                    'option'         => ['label' => 'En option',  'class' => 'badge-option'],
                    'confirme'       => ['label' => 'Confirmé',   'class' => 'badge-confirme'],
                    'maintenance'    => ['label' => 'Maintenance','class' => 'badge-maintenance'],
                    default          => ['label' => ucfirst($p->status->value ?? '—'), 'class' => 'badge-libre'],
                };
                $traffic = (int)($p->daily_traffic ?? 0);
                $isLit = (bool)($p->is_lit ?? false);
                $reference = $p->reference ?? ($p['reference'] ?? '—');
                $name = $p->name ?? ($p['name'] ?? '—');
                $commune = $p->commune?->name ?? ($p['commune'] ?? '—');
                $zone = $p->zone?->name ?? ($p['zone'] ?? '—');
                $format = $p->format?->name ?? ($p['format'] ?? '—');
                $category = $p->category?->name ?? ($p['category'] ?? '—');

                $dims = null;
                if (isset($p->format) && $p->format?->width && $p->format?->height) {
                    $w = rtrim(rtrim(number_format($p->format->width, 2, '.', ''), '0'), '.');
                    $h = rtrim(rtrim(number_format($p->format->height, 2, '.', ''), '0'), '.');
                    $dims = "{$w}x{$h}m";
                } elseif (isset($p['dimensions']) && $p['dimensions']) {
                    $dims = $p['dimensions'];
                }
            @endphp

            <tr>
                <td><span class="ref">{{ $reference }}</span></td>
                <td>{{ $name }}</td>
                <td>{{ $commune }}</td>
                <td>{{ $zone }}</td>
                <td>{{ $format }}</td>
                <td>{{ $dims ?? '—' }}</td>
                <td>{{ $category }}</td>
                <td>
                    @if($isLit)
                        <span class="lit">💡 LED</span>
                    @else
                        <span class="non-lit">Non éclairé</span>
                    @endif
                </td>
                <td>{{ $traffic > 0 ? number_format($traffic, 0, ',', ' ') : '—' }}</td>
                @unless($hideStatus ?? false)
                <td><span class="badge {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span></td>
                @endunless
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTAUX --}}
    @if(isset($totalMensuel) && $totalMensuel > 0)
    <div class="totals">
        Total mensuel : <span>{{ number_format($totalMensuel, 0, ',', ' ') }} FCFA</span>
        @if(isset($startDate) && isset($endDate) && $startDate && $endDate)
            | Total sur {{ $dureeEnMois ?? 1 }} mois : <span>{{ number_format($totalPeriode ?? 0, 0, ',', ' ') }} FCFA</span>
        @endif
    </div>
    @endif

    {{-- FOOTER --}}
    <div class="footer">
        CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel - Tous droits réservés
    </div>
</div>

</body>
</html>