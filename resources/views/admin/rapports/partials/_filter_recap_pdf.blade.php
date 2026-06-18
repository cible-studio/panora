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
        $typeNote  = $isGlobale
            ? 'Aucun filtre dimensionnel — données sur l\'ensemble du parc.'
            : 'Données restreintes aux critères de sélection ci-dessous.';
    @endphp
    <div style="margin-top:6px;margin-bottom:14px;padding:8px 12px;background:#fafafa;border-left:3px solid {{ $typeColor }};border-radius:3px;font-size:9.5px;color:#374151;line-height:1.5">
        {{-- Ligne 1 : TYPE D'EXPORT (la VRAIE info que le destinataire veut voir d'emblée) --}}
        <div style="margin-bottom:4px;padding-bottom:4px;border-bottom:1px dotted #d1d5db">
            <strong style="color:{{ $typeColor }};text-transform:uppercase;letter-spacing:.5px;font-size:9px">Type d'export :</strong>
            <strong style="color:#111827;font-size:10.5px">{{ $typeLabel }}</strong>
            <span style="color:#6b7280;font-style:italic">— {{ $typeNote }}</span>
        </div>
        {{-- Ligne 2 : période --}}
        <div>
            <strong style="color:#111827">📅 Période :</strong> {{ $filterRecap['periode_label'] }}
        </div>
        {{-- Ligne 3 : filtres actifs (uniquement si "Synthèse filtrée") --}}
        @if(!empty($filterRecap['filters']))
            <div style="margin-top:4px">
                <strong style="color:#111827">🎯 Critères de sélection :</strong>
                @foreach($filterRecap['filters'] as $f)
                    <span style="display:inline-block;margin-right:10px">
                        {{ $f['icon'] }} <strong>{{ $f['label'] }} :</strong> {{ $f['value'] }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
@endif
