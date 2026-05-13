@php
    $clientName = $client?->name ?? 'Client';
    $ref        = $reservation->reference;

    $sd = \Carbon\Carbon::parse($reservation->start_date)->startOfDay();
    $ed = \Carbon\Carbon::parse($reservation->end_date)->startOfDay();
    $totalDays  = max(1, (int) $sd->diffInDays($ed));
    $totalAmount = (float) ($reservation->total_amount ?? 0);
    $panelCount  = $reservation->panels->count() + $reservation->externalPanels->count();

    $title    = $isUrgent ? 'Votre proposition expire bientôt' : 'Rappel — proposition à valider';
    $preheader = $expiresAt
        ? "Votre proposition {$ref} expire le " . $expiresAt->format('d/m/Y à H:i')
        : "Rappel proposition {$ref}";

    $expiresAtLabel = $expiresAt?->format('d/m/Y à H:i') ?? '—';
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill">Rappel · J+{{ $reminderStep }}</span>

    <h1>Bonjour {{ $clientName }},</h1>

    @if($reminderStep <= 2)
        <p>
            Vous n'avez pas encore validé la proposition commerciale que nous vous
            avons transmise il y a quelques jours. Ce message est un simple rappel —
            vous avez encore jusqu'au <strong>{{ $expiresAtLabel }}</strong> pour répondre.
        </p>
    @else
        <p>
            Votre proposition commerciale arrive à expiration : il ne vous reste que
            <strong>{{ $daysLeft ?? '—' }} jour(s)</strong> pour la valider ou la refuser.
            Au-delà du <strong>{{ $expiresAtLabel }}</strong>, le lien ne sera plus accessible
            et les emplacements pourront être proposés à d'autres annonceurs.
        </p>
    @endif

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $ref }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">
                {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
                <div style="font-size:11px;color:#6b7280;margin-top:2px">
                    {{ $totalDays }} jour{{ $totalDays > 1 ? 's' : '' }}
                </div>
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Emplacements</div>
            <div class="val">{{ $panelCount }} panneau{{ $panelCount > 1 ? 'x' : '' }}</div>
        </div>
        @if($totalAmount > 0)
            <div class="info-row">
                <div class="lbl">Montant total</div>
                <div class="val">
                    <strong style="color:#c2570d;font-size:16px">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</strong>
                </div>
            </div>
        @endif
        <div class="info-row">
            <div class="lbl">Expire le</div>
            <div class="val">
                <strong style="color:{{ $isUrgent ? '#ef4444' : '#c2570d' }}">{{ $expiresAtLabel }}</strong>
            </div>
        </div>
    </div>

    <div class="cta-wrap">
        <a href="{{ $lien }}" class="cta">Consulter et répondre</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $lien }}">{{ $lien }}</a>
        </div>
    </div>

    @if($commercial)
        <p style="font-size:13px;color:#4b5563;margin-top:24px;line-height:1.6">
            Pour toute question, contactez votre interlocuteur
            <strong>{{ $commercial->name }}</strong>@if($commercial->email)
                à <a href="mailto:{{ $commercial->email }}">{{ $commercial->email }}</a>@endif.
        </p>
    @endif

    <x-slot:footerNote>
        Rappel automatique — proposition {{ $ref }}. Vous ne recevrez plus de relance après le {{ $expiresAtLabel }}.
    </x-slot:footerNote>

</x-mail.layout>
