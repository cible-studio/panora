@php
    // Pied de page clair et centré (2026-10-29 : plus de fond noir dans les
    // mails, même règle que components/mail/layout).
    $ch         = config('charte.couleurs');
    $nomRegie   = config('charte.nom', 'CIBLE');
    $slogan     = config('charte.slogan');
    $coord      = config('charte.coordonnees', []);
    $logoClair  = config('charte.logos.clair') ? asset(config('charte.logos.clair')) : null;
    $siteUrl    = ! empty($coord['site'])
        ? (str_starts_with($coord['site'], 'http') ? $coord['site'] : 'https://' . $coord['site'])
        : null;
@endphp
<tr>
<td style="background-color:#F4F4F4;">
<table class="footer" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center" style="padding:24px 32px;text-align:center;">
@if($logoClair)
<img src="{{ $logoClair }}" width="72" alt="{{ $nomRegie }}" style="display:inline-block;width:72px;height:auto;border:0;">
@else
<span style="font-size:16px;font-weight:800;color:{{ $ch['noir'] }};">{{ $nomRegie }}</span>
@endif
@if($slogan)
<div style="padding-top:8px;font-family:'Poppins', Arial, Helvetica, sans-serif;font-size:13px;font-weight:700;color:{{ $ch['noir'] }};">{{ $slogan }}</div>
@endif
<div style="padding-top:10px;font-size:12px;line-height:1.7;color:#888888;">
@if(!empty($coord['activite'])){{ $nomRegie }} · {{ $coord['activite'] }}<br>@endif
@if(!empty($coord['adresse'])){{ $coord['adresse'] }}<br>@endif
@if(!empty($coord['telephones'])){{ $coord['telephones'] }}<br>@endif
@if(!empty($coord['email']))<a href="mailto:{{ $coord['email'] }}" style="color:#535353;">{{ $coord['email'] }}</a>@endif
@if(!empty($coord['email']) && $siteUrl) · @endif
@if($siteUrl)<a href="{{ $siteUrl }}" style="color:#535353;">{{ $coord['site'] }}</a>@endif
</div>
<div style="padding-top:10px;">
{{ Illuminate\Mail\Markdown::parse($slot) }}
<p style="color:#888888;font-size:11px;margin:4px 0 0;text-align:center;">Mail envoyé par Panora</p>
</div>
</td>
</tr>
</table>
</td>
</tr>
