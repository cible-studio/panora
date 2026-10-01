@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    // Couleur du bouton lue dans la charte : vert pour « success »,
    // rouge (accent principal) pour tout le reste.
    $fond = in_array($color, ['success', 'green'], true)
        ? config('charte.couleurs.vert')
        : config('charte.couleurs.rouge');
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="background-color:{{ $fond }};border-top:14px solid {{ $fond }};border-bottom:14px solid {{ $fond }};border-left:28px solid {{ $fond }};border-right:28px solid {{ $fond }};color:{{ config('charte.couleurs.blanc') }};">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
