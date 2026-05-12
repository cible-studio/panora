<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des clients — CIBLE CI</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #1f2937;
            font-size: 9px;
            line-height: 1.4;
        }

        .container { padding: 12px 16px 60px; }

        /* ── HEADER UNIFORME (cohérent avec les autres PDF du projet) ── */
        .pdf-header {
            background: #0d1117;
            color: #ffffff;
            padding: 12px 22px;
            display: table;
            width: 100%;
            border-bottom: 3px solid #e8a020;
            margin-bottom: 12px;
        }
        .pdf-header > div { display: table-cell; vertical-align: middle; }
        .pdf-header .logo-cell  { width: 30%; text-align: left; }
        .pdf-header .title-cell { width: 40%; text-align: center; }
        .pdf-header .meta-cell  { width: 30%; text-align: right; font-size: 8.5px; color: #9ca3af; }
        .pdf-header img {
            height: 34px;
            width: auto;
            background: #ffffff;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .pdf-header h1 {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #fff;
            text-transform: uppercase;
        }
        .pdf-header .accent { color: #e8a020; }

        /* ── BANNER FILTRES ── */
        .filter-banner {
            background: #fff7ed;
            border-left: 3px solid #e8a020;
            padding: 8px 14px;
            margin-bottom: 12px;
            font-size: 9px;
            color: #4b5563;
        }
        .filter-banner strong { color: #c2570d; }

        /* ── TABLE ── */
        table.list {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }
        table.list thead th {
            background: #f3f4f6;
            color: #374151;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7.5px;
            letter-spacing: 0.4px;
            padding: 7px 5px;
            border-bottom: 2px solid #e5e7eb;
            text-align: left;
            white-space: nowrap;
        }
        table.list tbody td {
            padding: 6px 5px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
            color: #1f2937;
        }
        table.list tbody tr:nth-child(even) { background: #fafafa; }

        .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .ncc { font-family: 'Courier New', monospace; color: #c2570d; font-weight: 700; }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: 700;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-account { background: #dbeafe; color: #1e40af; }
        .badge-no { background: #f3f4f6; color: #6b7280; }
        .truncate { max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        /* ── FOOTER FIXE ── */
        .pdf-footer {
            position: fixed;
            bottom: 12px;
            left: 22px;
            right: 22px;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 8px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="pdf-header">
    <div class="logo-cell">
        <img src="{{ $logoSrc }}" alt="CIBLE CI">
    </div>
    <div class="title-cell">
        <h1>Liste des <span class="accent">clients</span></h1>
    </div>
    <div class="meta-cell">
        Généré le {{ $generated }}<br>
        {{ count($clients) }} client{{ count($clients) > 1 ? 's' : '' }}
    </div>
</div>

<div class="container">

    {{-- Banner filtres actifs — n'apparaît que si au moins un filtre est posé,
         pour rappeler à l'admin (ou au lecteur) le scope du document. --}}
    @if(!empty($filters['search']) || !empty($filters['sector']) || !empty($filters['active_only']))
        <div class="filter-banner">
            <strong>Filtres appliqués :</strong>
            @if(!empty($filters['active_only']))
                <span>Avec campagne active</span>
            @endif
            @if(!empty($filters['sector']))
                @if(!empty($filters['active_only'])) · @endif
                <span>Secteur : <strong>{{ $filters['sector'] }}</strong></span>
            @endif
            @if(!empty($filters['search']))
                @if(!empty($filters['active_only']) || !empty($filters['sector'])) · @endif
                <span>Recherche : <strong>« {{ $filters['search'] }} »</strong></span>
            @endif
        </div>
    @endif

    @if(count($clients) === 0)
        <div class="empty">
            Aucun client ne correspond aux filtres actifs.
        </div>
    @else
        <table class="list">
            <thead>
                <tr>
                    <th style="width:18%">Nom</th>
                    <th style="width:9%">NCC</th>
                    <th style="width:11%">Secteur</th>
                    <th style="width:13%">Contact</th>
                    <th style="width:14%">Email</th>
                    <th style="width:10%">Téléphone</th>
                    <th class="num" style="width:6%">Camp.</th>
                    <th class="num" style="width:6%">Actives</th>
                    <th class="num" style="width:6%">Réserv.</th>
                    <th style="width:7%">Compte</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clients as $c)
                    @php
                        $hasAcc = method_exists($c, 'hasAccount') ? $c->hasAccount() : false;
                        $active = (int) ($c->active_campaigns_count ?? 0);
                    @endphp
                    <tr>
                        <td><strong>{{ $c->name }}</strong></td>
                        <td>@if($c->ncc)<span class="ncc">{{ $c->ncc }}</span>@else <span style="color:#9ca3af">—</span>@endif</td>
                        <td class="truncate" title="{{ $c->sector }}">{{ $c->sector ?? '—' }}</td>
                        <td class="truncate" title="{{ $c->contact_name }}">{{ $c->contact_name ?? '—' }}</td>
                        <td class="truncate" title="{{ $c->email }}">{{ $c->email ?? '—' }}</td>
                        <td>{{ $c->phone ?? '—' }}</td>
                        <td class="num">{{ (int) ($c->campaigns_count ?? 0) }}</td>
                        <td class="num">
                            @if($active > 0)
                                <span class="badge badge-active">{{ $active }}</span>
                            @else
                                <span style="color:#9ca3af">0</span>
                            @endif
                        </td>
                        <td class="num">{{ (int) ($c->reservations_count ?? 0) }}</td>
                        <td>
                            @if($hasAcc)
                                <span class="badge badge-account">Actif</span>
                            @else
                                <span class="badge badge-no">Non actif</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</div>

<div class="pdf-footer">
    CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel
</div>

</body>
</html>
