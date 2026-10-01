@php
    $ch = config('charte.couleurs');
    $preheader = "Réponse de " . $operator . " à votre message « " . \Illuminate\Support\Str::limit($cm->subject, 80) . " »";
@endphp

<x-mail.layout title="Réponse à votre message" :preheader="$preheader">

    <span class="pill pill-success">↩ Réponse de {{ $operator }}</span>

    <h1>Bonjour {{ $cm->from_name }},</h1>

    <p>
        Suite à votre message du
        <strong>{{ $cm->created_at->format('d/m/Y à H:i') }}</strong>
        (« {{ \Illuminate\Support\Str::limit($cm->subject, 80) }} »),
        voici notre retour.
    </p>

    <div style="background:{{ $ch['blanc'] }};border:1px solid {{ $ch['gris'] }};border-left:4px solid {{ $ch['vert'] }};padding:16px 20px;font-size:14px;color:{{ $ch['noir'] }};line-height:1.6;white-space:pre-wrap;margin:18px 0 22px;">{{ $replyBody }}</div>

    <h2>Pour rappel, votre message initial</h2>
    <div style="background:{{ $ch['gris'] }};border:0;padding:14px 18px;font-size:13px;color:{{ $ch['noir'] }};line-height:1.55;white-space:pre-wrap;font-style:italic;">{{ $cm->body }}</div>

    <p style="margin-top:24px;">
        Si cette réponse ne couvre pas votre demande, n'hésitez pas à
        revenir vers nous via votre espace client (« Contacter la régie »)
        — nous restons à votre disposition.
    </p>

    <x-slot:footerNote>
        Email envoyé par l'équipe {{ $operator }} — référence de votre message : #{{ $cm->id }}.
    </x-slot:footerNote>

</x-mail.layout>
