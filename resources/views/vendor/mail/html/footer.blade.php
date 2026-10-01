@php
    $ch         = config('charte.couleurs');
    $nomRegie   = config('charte.nom', 'CIBLE');
    $slogan     = config('charte.slogan');
    $coord      = config('charte.coordonnees', []);
    $logoSombre = config('charte.logos.sombre') ? asset(config('charte.logos.sombre')) : null;
    $siteUrl    = ! empty($coord['site'])
        ? (str_starts_with($coord['site'], 'http') ? $coord['site'] : 'https://' . $coord['site'])
        : null;
@endphp
<tr>
<td style="background-color:{{ $ch['noir'] }};">
<table class="footer" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="left" style="padding:24px 32px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="foot-col" valign="middle" style="vertical-align:middle;">
@if($logoSombre)
<img src="{{ $logoSombre }}" width="90" alt="{{ $nomRegie }}" style="display:block;width:90px;height:auto;border:0;">
@else
<span style="font-size:18px;font-weight:800;color:{{ $ch['blanc'] }};">{{ $nomRegie }}</span>
@endif
</td>
@if($slogan)
<td class="foot-col foot-col-r" valign="middle" align="right" style="vertical-align:middle;text-align:right;font-family:'Poppins', Arial, Helvetica, sans-serif;font-size:14px;font-weight:700;color:{{ $ch['blanc'] }};">{{ $slogan }}</td>
@endif
</tr>
</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px;">
<tr>
<td style="padding-top:14px;font-size:12px;line-height:1.7;color:{{ $ch['gris'] }};">
@if(!empty($coord['activite']))<strong style="color:{{ $ch['blanc'] }};">{{ $nomRegie }}</strong> · {{ $coord['activite'] }}<br>@endif
@if(!empty($coord['adresse'])){{ $coord['adresse'] }}<br>@endif
@if(!empty($coord['telephones'])){{ $coord['telephones'] }}<br>@endif
@if(!empty($coord['email']))<a href="mailto:{{ $coord['email'] }}" style="color:{{ $ch['gris'] }};">{{ $coord['email'] }}</a>@endif
@if(!empty($coord['email']) && $siteUrl) · @endif
@if($siteUrl)<a href="{{ $siteUrl }}" style="color:{{ $ch['gris'] }};">{{ $coord['site'] }}</a>@endif
</td>
</tr>
<tr>
<td style="padding-top:12px;">
{{ Illuminate\Mail\Markdown::parse($slot) }}
<p style="color:{{ $ch['gris'] }};font-size:11px;margin:4px 0 0;">Mail envoyé par Panora</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
