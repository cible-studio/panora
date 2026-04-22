<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        /* ═══════════════════════════════════════════════════════════════
           CIBLE CI — Liste des Disponibilités
           Version optimisée DomPDF : footer fixe, sans prix
           ═══════════════════════════════════════════════════════════════ */
        * { margin:0; padding:0; box-sizing:border-box; }
        
        @page { 
            margin: 12mm 10mm; 
            size: A4 landscape;
        }
        
        body { 
            font-family: 'DejaVu Sans', Arial, sans-serif; 
            font-size: 9px; 
            color: #1e293b; 
            background: #fff;
            line-height: 1.4;
        }
        
        /* ── PAGE ── */
        .page {
            position: relative;
            min-height: 190mm;
        }
        
        /* ── HEADER ── */
        .header { 
            background: #0f172a; 
            color: #fff; 
            padding: 10px 14px; 
            margin-bottom: 12px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .header h1 { 
            font-size: 13px; 
            font-weight: bold; 
            color: #e8a020; 
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
            padding: 5px 5px; 
            text-align: left; 
            font-size: 7px; 
            font-weight: 700; 
            text-transform: uppercase; 
            color: #64748b; 
            border-bottom: 2px solid #e2e8f0; 
        }
        td { 
            padding: 5px 5px; 
            border-bottom: 1px solid #f1f5f9; 
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
            padding: 2px 6px; 
            border-radius: 10px; 
            font-size: 7px; 
            font-weight: 600; 
        }
        .badge-libre       { background: #dcfce7; color: #166534; }
        .badge-occupe      { background: #fee2e2; color: #991b1b; }
        .badge-option      { background: #fef3c7; color: #92400e; }
        .badge-maintenance { background: #f1f5f9; color: #475569; }
        
        .lit { 
            color: #fab80b; 
            font-size: 9px; 
        }
        .non-lit { 
            color: #94a3b8; 
            font-size: 9px; 
        }
        
        /* ── TOTAUX ── */
        .totals {
            margin-top: 10px;
            padding: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-align: right;
            font-size: 8px;
            font-weight: bold;
        }
        .totals span {
            color: #e20613;
            font-size: 10px;
        }
        
        /* ── FOOTER FIXE EN BAS ── */
        .footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            margin-top: 10px;
            text-align: center;
            font-size: 6px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
    </style>
</head>
<body>

<div class="page">
    {{-- HEADER --}}
    <div class="header">
        <div>
            <h1>CIBLE CI — Sélection de panneaux</h1>
            <p>Généré le {{ $generated }} · {{ count($panels) }} panneau(x)</p>
            @if($startDate && $endDate)
            <p style="margin-top:2px;">Période : {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>
            @endif
        </div>
        <div class="header-right">
            Régie Publicitaire OOH
        </div>
    </div>

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
                <th>Éclairage</th>
                <th>Trafic/j</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($panels as $p)
            @php
                $statusLabel = match($p->status->value) {
                    'libre'          => ['label' => 'Disponible', 'class' => 'badge-libre'],
                    'occupe'         => ['label' => 'Occupé',     'class' => 'badge-occupe'],
                    'option'         => ['label' => 'En option',  'class' => 'badge-option'],
                    'maintenance'    => ['label' => 'Maintenance','class' => 'badge-maintenance'],
                    default          => ['label' => ucfirst($p->status->value), 'class' => 'badge-libre'],
                };
                $traffic = (int)($p->daily_traffic ?? 0);
                $isLit = (bool)($p->is_lit ?? false);
                
                $dims = null;
                if ($p->format?->width && $p->format?->height) {
                    $w = rtrim(rtrim(number_format($p->format->width, 2, '.', ''), '0'), '.');
                    $h = rtrim(rtrim(number_format($p->format->height, 2, '.', ''), '0'), '.');
                    $dims = "{$w}x{$h}m";
                }
            @endphp
            <tr>
                <td><span class="ref">{{ $p->reference }}</span></td>
                <td>{{ $p->name }}</td>
                <td>{{ $p->commune?->name ?? '—' }}</td>
                <td>{{ $p->zone?->name ?? '—' }}</td>
                <td>{{ $p->format?->name ?? '—' }}</td>
                <td>{{ $dims ?? '—' }}</td>
                <td>{{ $p->category?->name ?? '—' }}</td>
                <td>
                    @if($isLit)
                        <span class="lit">LED</span>
                    @else
                        <span class="non-lit">Non</span>
                    @endif
                </td>
                <td>{{ $traffic > 0 ? number_format($traffic, 0, ',', ' ') : '—' }}</td>
                <td><span class="badge {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTAUX (optionnel) --}}
    @if($totalMensuel > 0)
    <div class="totals">
        Total mensuel : <span>{{ number_format($totalMensuel, 0, ',', ' ') }} FCFA</span>
        @if($startDate && $endDate)
        | Total sur {{ $dureeEnMois }} mois : <span>{{ number_format($totalPeriode, 0, ',', ' ') }} FCFA</span>
        @endif
    </div>
    @endif

    {{-- FOOTER FIXE --}}
    <div class="footer">
        CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel
    </div>
</div>

</body>
</html>