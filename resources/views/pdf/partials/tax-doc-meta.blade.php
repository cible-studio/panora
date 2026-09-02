{{-- Bloc méta horizontal des PDF taxes communales.
     Variable attendue :
       $metaCols : array de colonnes, chacune
         ['label' => 'Période', 'value' => '…', 'accent' => bool, 'small' => bool]
     - accent : valeur affichée en or (réservé à la période)
     - small  : valeur en corps réduit (compteurs, libellés longs)
     Le nombre de colonnes est variable (filtres actifs) — la largeur se
     répartit automatiquement. --}}
@if(!empty($metaCols))
<table class="tdoc-meta">
    <tr>
        @foreach($metaCols as $col)
            <th>{{ $col['label'] }}</th>
        @endforeach
    </tr>
    <tr>
        @foreach($metaCols as $col)
            <td class="{{ !empty($col['accent']) ? 'accent' : '' }} {{ !empty($col['small']) ? 'small' : '' }}">
                {{ $col['value'] }}
            </td>
        @endforeach
    </tr>
</table>
@endif
