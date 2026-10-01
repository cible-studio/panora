{{-- Liseré aux 5 couleurs de la charte (rouge, jaune, vert, bleu, violet —
     ordre défini par config('charte.lisere')). Table plutôt que flex :
     DomPDF ne gère pas flexbox. Hauteur réglable via $lisereHeight (px). --}}
@php($chL = $charte ?? \App\Support\PdfCharte::data())
<table class="ch-lisere">
    <tr>
        @foreach($chL['lisere'] as $couleurLisere)
            <td style="background:{{ $couleurLisere }};{{ isset($lisereHeight) ? 'height:' . (int) $lisereHeight . 'px;' : '' }}"></td>
        @endforeach
    </tr>
</table>
