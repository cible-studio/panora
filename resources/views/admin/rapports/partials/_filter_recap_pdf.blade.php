{{-- _filter_recap_pdf.blade.php
     Bandeau de récap des filtres actifs pour les exports PDF des rapports.
     Source unique : RapportFilterContextService.

     Variable attendue :
       $filterRecap = [
         'periode_label' => '01/01/2026 → 30/06/2026 (Cette année)',
         'filters'       => [['icon' => '🌍', 'label' => 'Zone', 'value' => 'Abidjan'], …],
         'hasAnyFilter'  => bool,
       ]

     Si aucun filtre actif, on rend juste la ligne période (le contexte
     reste utile : un PDF sans date d'application n'a aucune valeur). --}}
@if(isset($filterRecap))
    @php
        $isGlobale = empty($filterRecap['hasAnyFilter']);
        $typeLabel = $isGlobale ? 'Synthèse globale' : 'Synthèse filtrée';
        $typeColor = $isGlobale ? '#0f766e' : '#b45309'; // teal vs ambre
    @endphp
    {{-- Bandeau compact 1 ligne (2 si filtres actifs) — pensé pour ne pas
         détonner avec le header PDF et ne pas dupliquer la période. --}}
    <div style="margin-top:4px;margin-bottom:10px;padding:5px 10px;background:#f8fafc;border-left:3px solid {{ $typeColor }};font-size:9px;color:#374151;line-height:1.5">
        <span style="color:{{ $typeColor }};text-transform:uppercase;letter-spacing:.4px;font-weight:700;font-size:8.5px">Type :</span>
        <strong style="color:#111827">{{ $typeLabel }}</strong>
        <span style="color:#9ca3af">·</span>
        <strong style="color:#374151">Période :</strong> {{ $filterRecap['periode_label'] }}
        @if(!empty($filterRecap['filters']))
            <br>
            <span style="color:{{ $typeColor }};text-transform:uppercase;letter-spacing:.4px;font-weight:700;font-size:8.5px">Critères :</span>
            @foreach($filterRecap['filters'] as $f)
                <span style="margin-right:8px"><strong style="color:#374151">{{ $f['label'] }} :</strong> {{ $f['value'] }}</span>
            @endforeach
        @endif
    </div>
@endif
