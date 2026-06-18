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
    <div style="margin-top:6px;margin-bottom:14px;padding:8px 12px;background:#fafafa;border-left:3px solid #e8a020;border-radius:3px;font-size:9.5px;color:#374151;line-height:1.5">
        <div>
            <strong style="color:#111827">📅 Période :</strong> {{ $filterRecap['periode_label'] }}
        </div>
        @if(!empty($filterRecap['filters']))
            <div style="margin-top:4px">
                <strong style="color:#111827">🎯 Filtres actifs :</strong>
                @foreach($filterRecap['filters'] as $f)
                    <span style="display:inline-block;margin-right:10px">
                        {{ $f['icon'] }} <strong>{{ $f['label'] }} :</strong> {{ $f['value'] }}
                    </span>
                @endforeach
            </div>
        @else
            <div style="margin-top:4px;font-style:italic;color:#6b7280">
                Aucun filtre — données sur l'ensemble du parc
            </div>
        @endif
    </div>
@endif
