{{-- Barre de marque des PDF taxes communales (fond clair).
     Variables attendues :
       $docTitle  (string)  — ex. « Détail des taxes communales »
       $docPeriod (?string) — ex. « Janvier → Septembre 2026 » (optionnel)
     $logoCibleLight / $operatorName sont injectés par le View composer
     d'AppServiceProvider sur toutes les vues `pdf.*`.

     ⚠️ On utilise volontairement logoCibleLight (logol.png, texte noir) :
     le bandeau est clair, logoCibleDark (texte blanc) y serait invisible. --}}
<table class="tdoc-top">
    <tr>
        <td>
            @if(!empty($logoCibleLight))
                <img src="{{ $logoCibleLight }}" alt="{{ $operatorName ?? 'CIBLE CI' }}" class="tdoc-logo">
            @endif
            <div class="tdoc-title">{{ $operatorName ?? 'CIBLE CI' }} — {{ $docTitle }}</div>
        </td>
        <td class="tdoc-top-right">
            @if(!empty($docPeriod)){{ $docPeriod }} &nbsp;·&nbsp; @endif
            Édité le {{ now()->format('d/m/Y') }}
        </td>
    </tr>
</table>
<div class="tdoc-rule"></div>
