{{--
    Aperçu du devis pendant sa saisie (2026-10-30).

    Rendu par QuoteController::apercu à chaque modification du formulaire.
    Les montants viennent d'InvoiceCalculator, côté serveur : ce qu'on voit
    ici est ce qui sera enregistré, au franc près. Aucune formule n'est
    recopiée en JavaScript (CLAUDE.md, règle n°1).

    Maquette volontairement sobre et réduite : c'est un aperçu de
    vérification, pas le document final — celui-ci reste resources/views/
    pdf/quote.blade.php, à la charte complète.
--}}
@php
    $f = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $dateFr = function ($d) {
        if (!$d) return null;
        try { return \Carbon\Carbon::parse($d)->format('d/m/Y'); } catch (\Throwable) { return null; }
    };
    $periode = $dateFr($periodeDebut) && $dateFr($periodeFin)
        ? 'du ' . $dateFr($periodeDebut) . ' au ' . $dateFr($periodeFin)
        : null;
@endphp

<div class="qa-doc">
    <div class="qa-entete">
        <div>
            <div class="qa-marque">{{ config('charte.nom', 'CIBLE') }}</div>
            <div class="qa-sous">{{ config('charte.coordonnees.activite') }}</div>
        </div>
        <div style="text-align:right">
            <div class="qa-type">DEVIS</div>
            <div class="qa-ref">{{ $reference }}</div>
        </div>
    </div>

    <div class="qa-bloc">
        <div class="qa-titre-devis">{{ $titre !== '' ? $titre : 'Titre à renseigner' }}</div>
        <div class="qa-meta">
            <span>{{ $client?->name ?? 'Client à choisir' }}</span>
            @if($campagne)<span> · {{ $campagne->name }}</span>@endif
            @if($periode)<span> · {{ $periode }}</span>@endif
        </div>
    </div>

    @if($lignes)
        <table class="qa-table">
            <thead>
                <tr>
                    <th style="text-align:left">Emplacement</th>
                    <th style="text-align:right">Qté</th>
                    <th style="text-align:right">Durée</th>
                    <th style="text-align:right">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lignes as $l)
                    <tr>
                        <td>
                            {{ \Illuminate\Support\Str::limit($l['designation'] ?: '—', 44) }}
                            @if(!empty($l['snapshot_commune_name']))
                                <div class="qa-commune">{{ $l['snapshot_commune_name'] }}</div>
                            @endif
                        </td>
                        <td style="text-align:right">{{ $l['quantite'] }}</td>
                        <td style="text-align:right">{{ rtrim(rtrim(number_format($l['duree_mois'], 1, ',', ''), '0'), ',') }} m</td>
                        <td style="text-align:right;white-space:nowrap">{{ $f($l['montant_ht_ligne'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="qa-vide">Ajoutez un emplacement pour voir le devis se dessiner.</div>
    @endif

    @if($services)
        <div class="qa-section">Services annexes</div>
        <table class="qa-table">
            <tbody>
                @foreach($services as $s)
                    <tr>
                        <td>
                            {{ \Illuminate\Support\Str::limit($s['label'], 40) }}
                            <span class="qa-tva-pastille {{ $s['tva_applicable'] ? 'qa-avec' : 'qa-sans' }}">
                                {{ $s['tva_applicable'] ? 'TVA' : 'HT' }}
                            </span>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            {{ $f($s['tva_applicable'] ? round($s['prix_ht'] * (1 + $tva / 100)) : $s['prix_ht']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="qa-totaux">
        <div class="qa-ligne"><span>Total HT brut</span><strong>{{ $f($totaux['amount'] ?? 0) }}</strong></div>
        @if(($totaux['remise_pct'] ?? 0) > 0)
            <div class="qa-ligne qa-remise">
                <span>Remise {{ rtrim(rtrim(number_format($totaux['remise_pct'], 2, ',', ''), '0'), ',') }} %</span>
                <strong>− {{ $f(($totaux['amount'] ?? 0) - ($totaux['net_ht'] ?? 0)) }}</strong>
            </div>
        @endif
        <div class="qa-ligne qa-fort"><span>Total HT</span><strong>{{ $f($totaux['net_ht'] ?? 0) }}</strong></div>
        <div class="qa-ligne"><span>TVA ({{ rtrim(rtrim(number_format($tva, 2, ',', ''), '0'), ',') }} %)</span><strong>{{ $f($totaux['tva_amount'] ?? 0) }}</strong></div>

        @if(($totaux['tsp_amount'] ?? 0) > 0)
            <div class="qa-ligne"><span>TSP</span><strong>{{ $f($totaux['tsp_amount']) }}</strong></div>
        @endif
        @if(($totaux['tm_total'] ?? 0) > 0)
            <div class="qa-ligne"><span>Taxe municipale</span><strong>{{ $f($totaux['tm_total']) }}</strong></div>
        @endif
        @if(($totaux['odp_total'] ?? 0) > 0)
            <div class="qa-ligne"><span>Occupation du domaine public</span><strong>{{ $f($totaux['odp_total']) }}</strong></div>
        @endif
        @if(($totaux['services_ttc_total'] ?? 0) > 0)
            <div class="qa-ligne"><span>Services annexes TTC</span><strong>{{ $f($totaux['services_ttc_total']) }}</strong></div>
        @endif

        <div class="qa-total-final">
            <span>TOTAL À PAYER</span>
            <strong>{{ $f($totaux['total_a_payer'] ?? 0) }} FCFA</strong>
        </div>
    </div>
</div>
