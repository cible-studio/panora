{{-- Footer fixe des PDF taxes communales : mention plateforme à gauche,
     pagination à droite. Variable optionnelle :
       $footerHint (?string) — précision de bas de page (ex. la formule).
     NB : on utilise une ternaire et non @if — une directive Blade collée
     à un mot (« automatiquement@if ») n'est pas compilée. --}}
<div class="tdoc-footer">
    <table>
        <tr>
            <td>
                Plateforme <strong>Panora</strong> · opérée par <strong>{{ $operatorName ?? 'CIBLE CI' }}</strong>
                — Document généré automatiquement{{ !empty($footerHint) ? ' · ' . $footerHint : '' }}
            </td>
            <td class="right">Page <span class="tdoc-pagenum"></span></td>
        </tr>
    </table>
</div>
