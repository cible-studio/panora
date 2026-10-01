{{-- Pied de page fixe commun des PDF (charte graphique, 2026-10-01).
     Répété sur chaque page (position:fixed). Gauche : régie · slogan ·
     coordonnées (config('charte.*')) ; droite : numéro de page.

     Paramètres optionnels :
       $footerHint  (?string) — précision ajoutée sous les coordonnées
       $footerPage  (?bool)   — afficher « Page N » (défaut : true)

     ⚠ La vue doit réserver la place en bas de page : marge @page basse
     ≥ 16mm. Le pied est positionné dans cette marge. --}}
@php
    $chF   = $charte ?? \App\Support\PdfCharte::data();
    $coordF = $chF['coordonnees'] ?? [];
    $ligneCoord = implode(' · ', array_filter([
        $coordF['telephones'] ?? null,
        $coordF['email'] ?? null,
        $coordF['site'] ?? null,
    ]));
@endphp
<div class="ch-footer">
    <table>
        <tr>
            <td>
                <span class="ch-footer-brand">{{ $chF['nom'] }}</span>
                @if(!empty($chF['slogan'])) · <span class="ch-footer-slogan">{{ $chF['slogan'] }}</span>@endif
                @if($ligneCoord !== '') · {{ $ligneCoord }}@endif
                @if(!empty($footerHint))<br>{{ $footerHint }}@endif
            </td>
            @if($footerPage ?? true)
                <td class="ch-footer-page">Page <span class="ch-pagenum"></span></td>
            @endif
        </tr>
    </table>
</div>
