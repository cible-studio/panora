<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size:10px; color:#1e293b; }

        .header { background:#0f172a; color:#fff; padding:12px 18px; margin-bottom:12px; }
        .header h1 { font-size:14px; font-weight:bold; color:#e8a020; }
        .header .sub { font-size:9px; color:#94a3b8; margin-top:2px; }

        /* DomPDF : table HTML classique sans CSS display:table */
        .grid-table { width:100%; border-collapse:collapse; border-spacing:0; }
        .grid-table td { width:33.33%; vertical-align:top; padding:4px; }

        .card { border:1px solid #e2e8f0; border-radius:4px; overflow:hidden; }
        .card-img { width:100%; height:85px; display:block; }
        .card-body { padding:6px 7px; }
        .card-ref { font-family:monospace; font-weight:bold; font-size:11px; color:#e8a020; }
        .card-name { font-size:9.5px; font-weight:600; color:#1e293b; margin-top:1px; }
        .card-meta { font-size:8px; color:#64748b; margin-top:2px; line-height:1.4; }
        .card-price { font-weight:bold; color:#e8a020; font-size:10px; margin-top:3px; }
        .badge { display:inline; padding:1px 5px; border-radius:3px; font-size:8px; font-weight:bold; }
        .b-libre    { background:#dcfce7; color:#166534; }
        .b-occupe   { background:#fee2e2; color:#991b1b; }
        .b-option   { background:#fef3c7; color:#92400e; }
        .b-maint    { background:#f1f5f9; color:#475569; }
        .b-confirme { background:#ede9fe; color:#5b21b6; }

        .footer { margin-top:12px; text-align:center; font-size:8px; color:#94a3b8;
                  border-top:1px solid #e2e8f0; padding-top:6px; }
    </style>
</head>
<body>

<div class="header">
    <h1>CIBLE CI — Disponibilités avec visuels</h1>
    <div class="sub">Généré le {{ $generated }} · {{ count($panels) }} panneau(x)</div>
</div>

{{--
    DomPDF ne supporte pas float/flex/grid.
    On utilise une <table> HTML pure avec des cellules fixes à 33%.
    Chaque ligne de la table = 3 panneaux.
--}}
<table class="grid-table">
    @php
        $items  = $panels->values();
        $chunks = $items->chunk(3);
    @endphp

    @foreach($chunks as $row)
    <tr>
        @foreach($row as $p)
        @php
            $status    = $p['display_status'] ?? 'libre';
            $badgeCls  = match($status) {
                'libre'                    => 'b-libre',
                'occupe'                   => 'b-occupe',
                'option_periode','option'  => 'b-option',
                'maintenance'              => 'b-maint',
                'confirme'                 => 'b-confirme',
                default                    => 'b-libre',
            };
            $badgeLbl  = match($status) {
                'libre'                    => 'Disponible',
                'occupe'                   => 'Occupé',
                'option_periode','option'  => 'En option',
                'maintenance'              => 'Maintenance',
                'confirme'                 => 'Confirmé',
                default                    => ucfirst($status),
            };
            $photoPath = $p['photo_path'] ?? null;
            $hasPhoto  = $photoPath && file_exists($photoPath);
            $tarif     = isset($p['monthly_rate']) && $p['monthly_rate']
                ? number_format((float)$p['monthly_rate'], 0, ',', ' ') . ' FCFA/mois'
                : '—';
            $name = \Illuminate\Support\Str::limit($p['name'] ?? '', 30);
            $meta = ($p['commune'] ?? '') .
                    (($p['zone'] ?? '—') !== '—' ? ' · ' . $p['zone'] : '') .
                    (($p['dimensions'] ?? '') ? ' · ' . $p['dimensions'] : '') .
                    (($p['is_lit'] ?? false) ? ' · Éclairé' : '');
        @endphp
        <td>
            <div class="card">
                @if($hasPhoto)
                    {{-- Chemin absolu local — obligatoire pour DomPDF --}}
                    <img class="card-img" src="{{ $photoPath }}" alt="">
                @else
                    {{-- Placeholder texte quand pas de photo --}}
                    <div style="width:100%;height:85px;background:#1e293b;
                                text-align:center;line-height:85px;
                                font-family:monospace;font-size:12px;
                                font-weight:bold;color:#e8a020;">
                        {{ $p['reference'] ?? '' }}
                    </div>
                @endif
                <div class="card-body">
                    <div class="card-ref">{{ $p['reference'] ?? '' }}</div>
                    <div class="card-name">{{ $name }}</div>
                    <div class="card-meta">{{ $meta }}</div>
                    <div class="card-price">{{ $tarif }}</div>
                    <span class="badge {{ $badgeCls }}">{{ $badgeLbl }}</span>
                    @if(!empty($p['release_info']['label']))
                        <div style="font-size:7.5px;color:#64748b;margin-top:2px;">
                            {{ $p['release_info']['label'] }}
                        </div>
                    @endif
                </div>
            </div>
        </td>
        @endforeach

        {{-- Compléter la dernière ligne avec des cellules vides --}}
        @for($i = $row->count(); $i < 3; $i++)
            <td></td>
        @endfor
    </tr>
    @endforeach
</table>

<div class="footer">
    CIBLE CI · Régie Publicitaire · Document confidentiel · {{ $generated }}
</div>

</body>
</html>