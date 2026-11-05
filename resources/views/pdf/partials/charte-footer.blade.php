{{-- Pied de page fixe commun des PDF — HARMONISÉ le 2026-11-05.
     Répété sur chaque page (position:fixed). Même pied pour tous les
     documents (rapports internes, proposition, disponibilités, catalogue) ;
     seuls le devis et la facture gardent le leur (décision « 1-a »,
     cf. charte-footer-devis).

       gauche  : CIBLE · activité (Régie & Studio Créatif…)
                 téléphones · e-mail · site            ← config('charte.*')
       droite  : mention · édité le jj/mm/aaaa à hh:mm
                 Page N

     Avant : chaque PDF ajoutait sa propre ligne (« CIBLE SARL — Régie OOH… »,
     « Régie Publicitaire », « Édité par Panora le… ») sous la marque et le
     slogan — redites et formulations différentes d'un document à l'autre.

     Paramètres optionnels :
       $footerHint    (?string) — mention courte (« Document confidentiel »,
                                  « Réf. RES-0042 »…), avant la date
       $footerPage    (?bool)   — « Page N » (défaut : true)
       $footerEdition (?bool)   — « édité le … » (défaut : true)

     ⚠ La vue doit réserver la place en bas de page : marge @page basse
     ≥ 16mm. Le pied (deux lignes) est positionné dans cette marge. --}}
@php
    $chF    = $charte ?? \App\Support\PdfCharte::data();
    $coordF = $chF['coordonnees'] ?? [];
    $contactsF = implode(' · ', array_filter([
        $coordF['telephones'] ?? null,
        $coordF['email'] ?? null,
        $coordF['site'] ?? null,
    ]));
    $mentionF = implode(' · ', array_filter([
        $footerHint ?? null,
        ($footerEdition ?? true) ? 'édité le ' . now()->format('d/m/Y à H:i') : null,
    ]));
@endphp
<div class="ch-footer">
    <table>
        <tr>
            <td>
                <span class="ch-footer-brand">{{ $chF['nom'] }}</span>@if(!empty($coordF['activite'])) · {{ $coordF['activite'] }}@endif
                @if($contactsF !== '')<br>{{ $contactsF }}@endif
            </td>
            <td class="ch-footer-droite">
                {{ \Illuminate\Support\Str::ucfirst($mentionF) }}
                @if($footerPage ?? true)<br>Page <span class="ch-pagenum"></span>@endif
            </td>
        </tr>
    </table>
</div>
