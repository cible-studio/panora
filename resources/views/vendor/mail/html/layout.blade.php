@php
    /*
     * Gabarit des mails « Markdown » Laravel (notifications) — charte de la
     * régie (config/charte.php), aligné sur components/mail/layout.blade.php.
     * Les styles de themes/default.css sont convertis en ligne au rendu.
     */
    $ch     = config('charte.couleurs');
    $lisere = collect(config('charte.lisere', ['rouge', 'jaune', 'vert', 'bleu', 'violet']))
        ->map(fn ($k) => $ch[$k] ?? null)->filter()->values();
    $fontsUrl = config('charte.polices.google_fonts');
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<!--[if mso]><style>td,p,a,div,span,h1,h2,h3{font-family:Arial,Helvetica,sans-serif !important;}</style><![endif]-->
@if($fontsUrl)
<!--[if !mso]><!--><link href="{{ $fontsUrl }}" rel="stylesheet"><!--<![endif]-->
@endif
<style>
@media only screen and (max-width: 599px) {
.content-cell {
padding-left: 20px !important;
padding-right: 20px !important;
}
.header-cell {
padding-left: 20px !important;
padding-right: 20px !important;
}
.foot-col {
display: block !important;
width: 100% !important;
text-align: left !important;
}
.foot-col-r {
padding-top: 12px !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
text-align: center !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body style="margin:0;padding:0;background-color:{{ $ch['gris'] }};">

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:{{ $ch['gris'] }};">
<tr>
<td align="center" style="padding:24px 12px;">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;max-width:600px;word-break:break-word;">

<!-- Liseré -->
<tr>
<td style="font-size:0;line-height:0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
@foreach($lisere as $couleur)
<td width="{{ round(100 / max($lisere->count(), 1)) }}%" height="6" style="height:6px;background-color:{{ $couleur }};font-size:0;line-height:0;">&nbsp;</td>
@endforeach
</tr></table>
</td>
</tr>

{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;background-color:{{ $ch['blanc'] }};">
<table class="inner-body" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td>
</tr>
</table>
</body>
</html>
