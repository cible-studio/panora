@php
    $preheader = "Nouveau message de " . $cm->from_name . " — sujet : " . \Illuminate\Support\Str::limit($cm->subject, 100);
@endphp

<x-mail.layout title="Nouveau message client" :preheader="$preheader">

    <span class="pill pill-warning">✉️ Nouveau message client</span>

    <h1>Message de {{ $cm->from_name }}</h1>

    <p>
        Un client vient d'envoyer un message via le formulaire
        <strong>« Contacter la régie »</strong> de son espace.
        Une réponse est attendue sous 24 heures ouvrées.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Expéditeur</div>
            <div class="val">{{ $cm->from_name }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Email</div>
            <div class="val"><code>{{ $cm->from_email }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>#{{ $cm->id }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Reçu le</div>
            <div class="val">{{ $cm->created_at->format('d/m/Y à H:i') }}</div>
        </div>
    </div>

    <h2>Objet</h2>
    <p style="margin-top:0;"><strong>{{ $cm->subject }}</strong></p>

    <h2>Contenu du message</h2>
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;font-size:14px;color:#374151;line-height:1.6;white-space:pre-wrap;">{{ $cm->body }}</div>

    <div class="cta-wrap">
        <a href="{{ $showUrl }}" class="cta">Répondre depuis Panora</a>
        <div class="cta-fallback">
            La réponse depuis l'interface est tracée en historique et envoyée
            au client automatiquement par email.<br>
            <a href="{{ $showUrl }}">{{ $showUrl }}</a>
        </div>
    </div>

    <p style="margin-top:24px;color:#6b7280;font-size:13px;">
        Vous pouvez aussi répondre directement à cet email — l'adresse
        de réponse pointe sur <strong>{{ $cm->from_email }}</strong>.
        Mais la trace ne sera pas conservée dans /admin/messages.
    </p>

    <x-slot:footerNote>
        Notification automatique — message archivé sous référence #{{ $cm->id }}.
    </x-slot:footerNote>

</x-mail.layout>
