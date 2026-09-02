<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport taxes communes{{ $commune ? ' — ' . $commune->name : '' }}</title>

    {{-- Même charte que pdf/taxes-details.blade.php (partial commun).
         Marges identiques, seule l'orientation change : ce rapport reste
         en portrait (7 colonnes), le détail est en paysage (8 colonnes). --}}
    <style>@page { size: A4 portrait; margin: 14mm 14mm 18mm 14mm; }</style>
    @include('pdf.partials.tax-doc-styles')
</head>
<body>

@include('pdf.partials.tax-doc-header', [
    'docTitle'  => 'Rapport des taxes communales',
    'docPeriod' => null,
])

@php
    $metaCols = array_filter([
        ['label' => 'Opérateur', 'value' => $operatorName ?? 'CIBLE CI'],
        ['label' => 'Commune',   'value' => $commune->name ?? 'Toutes communes'],
        ['label' => 'Écritures', 'value' => $taxes->count() . ' taxe' . ($taxes->count() > 1 ? 's' : ''), 'small' => true],
        ['label' => 'Total',     'value' => number_format($taxes->sum('amount'), 0, ',', ' ') . ' FCFA', 'accent' => true],
    ]);
@endphp
@include('pdf.partials.tax-doc-meta', ['metaCols' => $metaCols])

<table class="tdoc-table">
    <thead>
        <tr>
            <th>Commune</th>
            <th style="width:52px;">Type</th>
            <th style="width:46px;">Année</th>
            <th style="width:96px;" class="right">Montant</th>
            <th style="width:70px;">Échéance</th>
            <th style="width:70px;">Payée le</th>
            <th style="width:74px;">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse($taxes as $tax)
        <tr>
            <td><strong>{{ $tax->commune->name }}</strong></td>
            <td>
                @if($tax->type === 'odp')
                    <span class="tdoc-badge tdoc-badge-odp">ODP</span>
                @else
                    <span class="tdoc-badge tdoc-badge-tm">TM</span>
                @endif
            </td>
            <td>{{ $tax->year }}</td>
            <td class="right mono"><strong>{{ number_format($tax->amount, 0, ',', ' ') }}</strong></td>
            <td class="tdoc-muted">{{ $tax->due_date?->format('d/m/Y') ?? '—' }}</td>
            <td class="tdoc-muted">{{ $tax->paid_at?->format('d/m/Y') ?? '—' }}</td>
            <td>
                @if($tax->status === 'payee')
                    <span class="tdoc-badge tdoc-badge-green">Payée</span>
                @elseif($tax->status === 'en_retard')
                    <span class="tdoc-badge tdoc-badge-red">En retard</span>
                @else
                    <span class="tdoc-badge tdoc-badge-orange">En attente</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" class="tdoc-empty">Aucune taxe</td></tr>
        @endforelse

        @if($taxes->count() > 0)
        <tr class="tdoc-total">
            <td colspan="6" class="right">TOTAL GÉNÉRAL</td>
            <td class="tdoc-amount mono">
                {{ number_format($taxes->sum('amount'), 0, ',', ' ') }}<span>FCFA</span>
            </td>
        </tr>
        @endif
    </tbody>
</table>

<div class="tdoc-note">
    ■ État des écritures de taxes communales enregistrées dans Panora
    (montant dû, échéance, date de règlement). Document interne —
    à rapprocher du détail par panneau pour toute justification auprès d'une commune.
</div>

@include('pdf.partials.tax-doc-footer', [
    'footerHint' => 'Taxes communales',
])

</body>
</html>
