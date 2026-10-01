<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des clients — CIBLE CI</title>
    {{-- 2026-10-01 — charte graphique : styles communs (polices, ch-*) puis styles propres. --}}
    @include('pdf.partials.charte-styles')
    <style>
        /* 2026-10-01 — charte graphique : vraies marges de page (!important,
           sinon « * { margin:0 } » les annule sous DomPDF) ; marge basse
           réservée au pied commun charte-footer. */
        @page { margin: 12mm 12mm 20mm 12mm !important; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            color: {{ $charte['noir'] }};
            font-size: 9px;
            line-height: 1.2;
        }

        .container { padding: 0; }

        /* ── BANNER FILTRES ── */
        .ch-info strong.accent { color: {{ $charte['rouge'] }}; }

        /* ── TABLE ── */
        table.ch-table thead th { white-space: nowrap; padding: 7px 5px; }
        table.ch-table tbody td {
            padding: 6px 5px;
            vertical-align: top;
            color: {{ $charte['noir'] }};
        }

        .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .ncc { font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['rouge'] }}; font-weight: 700; }
        .truncate { max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pale { color: {{ $charte['texte_pale'] }}; }

        .empty {
            text-align: center;
            padding: 60px 20px;
            color: {{ $charte['texte_pale'] }};
        }
    </style>
</head>
<body>

{{-- 2026-10-01 — charte graphique : en-tête commun clair (liseré + logo
     CIBLE pour fond clair + titre + méta), remplace le bandeau sombre. --}}
@include('pdf.partials.charte-header', [
    'docTitle' => 'Liste des clients',
    'docMeta'  => [
        'Généré le ' . $generated,
        count($clients) . ' client' . (count($clients) > 1 ? 's' : ''),
    ],
])

<div class="container">

    {{-- Banner filtres actifs — n'apparaît que si au moins un filtre est posé,
         pour rappeler à l'admin (ou au lecteur) le scope du document. --}}
    @if(!empty($filters['search']) || !empty($filters['sector']) || !empty($filters['active_only']))
        <div class="ch-info">
            <strong class="accent">Filtres appliqués :</strong>
            @if(!empty($filters['active_only']))
                <span>Avec campagne active</span>
            @endif
            @if(!empty($filters['sector']))
                @if(!empty($filters['active_only'])) · @endif
                <span>Secteur : <strong class="accent">{{ $filters['sector'] }}</strong></span>
            @endif
            @if(!empty($filters['search']))
                @if(!empty($filters['active_only']) || !empty($filters['sector'])) · @endif
                <span>Recherche : <strong class="accent">« {{ $filters['search'] }} »</strong></span>
            @endif
        </div>
    @endif

    @if(count($clients) === 0)
        <div class="empty">
            Aucun client ne correspond aux filtres actifs.
        </div>
    @else
        <table class="ch-table">
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
                        <td>@if($c->ncc)<span class="ncc">{{ $c->ncc }}</span>@else <span class="pale">—</span>@endif</td>
                        <td class="truncate" title="{{ $c->sector }}">{{ $c->sector ?? '—' }}</td>
                        <td class="truncate" title="{{ $c->contact_name }}">{{ $c->contact_name ?? '—' }}</td>
                        <td class="truncate" title="{{ $c->email }}">{{ $c->email ?? '—' }}</td>
                        <td>{{ $c->phone ?? '—' }}</td>
                        <td class="num">{{ (int) ($c->campaigns_count ?? 0) }}</td>
                        <td class="num">
                            @if($active > 0)
                                <span class="ch-badge ch-badge-vert">{{ $active }}</span>
                            @else
                                <span class="pale">0</span>
                            @endif
                        </td>
                        <td class="num">{{ (int) ($c->reservations_count ?? 0) }}</td>
                        <td>
                            @if($hasAcc)
                                <span class="ch-badge ch-badge-bleu">Actif</span>
                            @else
                                <span class="ch-badge ch-badge-gris">Non actif</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</div>

{{-- 2026-10-01 — charte graphique : pied commun ; l'ancien texte de pied est conservé en footerHint. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => "CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel",
])

</body>
</html>
