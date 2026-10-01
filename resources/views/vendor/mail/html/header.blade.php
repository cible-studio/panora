@props(['url'])
@php
    $logoClair = config('charte.logos.clair') ? asset(config('charte.logos.clair')) : null;
    $nomRegie  = config('charte.nom', trim($slot));
@endphp
<tr>
<td class="header header-cell" style="background-color:{{ config('charte.couleurs.blanc') }};">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logoClair)
<img src="{{ $logoClair }}" class="logo" width="110" alt="{{ $nomRegie }}" style="display:block;width:110px;height:auto;border:0;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
