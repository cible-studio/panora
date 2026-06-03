@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $clientName = $client?->name ?? 'Client';
    $ref = $reservation->reference;

    if ($context === \App\Mail\PropositionDateChangeMail::CONTEXT_REQUESTED) {
        $title     = "Demande de décalage — {$ref}";
        $preheader = "{$clientName} souhaite décaler la proposition {$ref}.";
        $pillClass = 'pill';
        $pillStyle = 'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;';
        $pillText  = '🗓 Décalage demandé';
    } elseif ($context === \App\Mail\PropositionDateChangeMail::CONTEXT_ACCEPTED) {
        $title     = "Décalage accepté — {$ref}";
        $preheader = "Bonne nouvelle : la période de la proposition {$ref} est décalée.";
        $pillClass = 'pill pill-success';
        $pillStyle = '';
        $pillText  = '✅ Décalage accepté';
    } else { // refused
        $title     = "Demande de décalage — {$ref}";
        $preheader = "La proposition {$ref} reste valide sur la période initiale.";
        $pillClass = 'pill';
        $pillStyle = 'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;';
        $pillText  = '🗓 Demande non retenue';
    }
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="{{ $pillClass }}" style="{{ $pillStyle }}">{{ $pillText }}</span>

    @if($context === \App\Mail\PropositionDateChangeMail::CONTEXT_REQUESTED)
        {{-- ── Mail destiné au commercial / admin ────────────────── --}}
        <h1>Demande de décalage de dates</h1>

        <p>
            <strong>{{ $clientName }}</strong> souhaite décaler la proposition
            <strong>{{ $ref }}</strong>. Tu peux accepter (les nouvelles dates
            remplaceront les actuelles) ou refuser (les dates initiales sont
            conservées) directement depuis la fiche réservation.
        </p>

        <div class="info">
            <div class="info-row">
                <div class="lbl">Référence</div>
                <div class="val"><code>{{ $ref }}</code></div>
            </div>
            <div class="info-row">
                <div class="lbl">Client</div>
                <div class="val"><strong>{{ $clientName }}</strong></div>
            </div>
            <div class="info-row">
                <div class="lbl">Dates actuelles</div>
                <div class="val">
                    {{ $reservation->start_date->format('d/m/Y') }}
                    → {{ $reservation->end_date->format('d/m/Y') }}
                </div>
            </div>
            <div class="info-row">
                <div class="lbl">Dates demandées</div>
                <div class="val">
                    <strong style="color:#ea580c">
                        {{ $reservation->requested_start_date?->format('d/m/Y') }}
                        → {{ $reservation->requested_end_date?->format('d/m/Y') }}
                    </strong>
                </div>
            </div>
        </div>

        @if(!empty($reservation->date_change_note))
            <h2 style="font-size:14px;margin-top:22px">Note du client</h2>
            <div style="background:#fff7ed;border:1px solid #fed7aa;border-left:4px solid #f97316;border-radius:8px;padding:12px 14px;font-size:13.5px;color:#7c2d12;line-height:1.55;white-space:pre-wrap">{{ $reservation->date_change_note }}</div>
        @endif

        <div class="cta-wrap">
            <a href="{{ $reservationUrl }}" class="cta">Ouvrir la fiche réservation</a>
            <div class="cta-fallback">
                Lien direct : <a href="{{ $reservationUrl }}">{{ $reservationUrl }}</a>
            </div>
        </div>

        <x-slot:footerNote>
            Tu reçois ce mail parce que tu es le commercial responsable de cette réservation.
        </x-slot:footerNote>

    @elseif($context === \App\Mail\PropositionDateChangeMail::CONTEXT_ACCEPTED)
        {{-- ── Mail destiné au client ─────────────────────────────── --}}
        <h1>Bonne nouvelle, {{ $clientName }} !</h1>

        <p>
            Ta demande de décalage de la proposition <strong>{{ $ref }}</strong> est acceptée.
            La nouvelle période a été appliquée et la proposition est de nouveau disponible
            à confirmer ou refuser.
        </p>

        <div class="info">
            <div class="info-row">
                <div class="lbl">Référence</div>
                <div class="val"><code>{{ $ref }}</code></div>
            </div>
            <div class="info-row">
                <div class="lbl">Nouvelle période</div>
                <div class="val">
                    <strong style="color:#16a34a">
                        {{ $newPeriod ?: ($reservation->start_date->format('d/m/Y') . ' → ' . $reservation->end_date->format('d/m/Y')) }}
                    </strong>
                </div>
            </div>
            @if($oldPeriod)
                <div class="info-row">
                    <div class="lbl">Ancienne période</div>
                    <div class="val" style="color:#6b7280;text-decoration:line-through">{{ $oldPeriod }}</div>
                </div>
            @endif
        </div>

        @if($propositionUrl)
            <div class="cta-wrap">
                <a href="{{ $propositionUrl }}" class="cta">Revoir la proposition</a>
                <div class="cta-fallback">
                    Si le bouton ne fonctionne pas, copie ce lien :<br>
                    <a href="{{ $propositionUrl }}">{{ $propositionUrl }}</a>
                </div>
            </div>
        @endif

        <x-slot:footerNote>
            Tu peux maintenant confirmer ou refuser la proposition sur cette nouvelle période.
            Pour toute question, contacte ton interlocuteur {{ $operator }}.
        </x-slot:footerNote>

    @else
        {{-- ── refused — mail destiné au client ─────────────────────── --}}
        <h1>Bonjour {{ $clientName }},</h1>

        <p>
            Nous n'avons pas pu répondre favorablement à ta demande de décalage de la
            proposition <strong>{{ $ref }}</strong>. La proposition reste valide sur la
            période initiale et tu peux toujours la confirmer, la refuser ou faire
            une nouvelle proposition de dates.
        </p>

        @if(!empty($reason))
            <h2 style="font-size:14px;margin-top:22px">Précisions de notre équipe</h2>
            <div style="background:#fff7ed;border:1px solid #fed7aa;border-left:4px solid #f97316;border-radius:8px;padding:12px 14px;font-size:13.5px;color:#7c2d12;line-height:1.55;white-space:pre-wrap">{{ $reason }}</div>
        @endif

        <div class="info">
            <div class="info-row">
                <div class="lbl">Référence</div>
                <div class="val"><code>{{ $ref }}</code></div>
            </div>
            <div class="info-row">
                <div class="lbl">Période initiale</div>
                <div class="val">
                    <strong>
                        {{ $reservation->start_date->format('d/m/Y') }}
                        → {{ $reservation->end_date->format('d/m/Y') }}
                    </strong>
                </div>
            </div>
        </div>

        @if($propositionUrl)
            <div class="cta-wrap">
                <a href="{{ $propositionUrl }}" class="cta">Revoir la proposition</a>
                <div class="cta-fallback">
                    Si le bouton ne fonctionne pas, copie ce lien :<br>
                    <a href="{{ $propositionUrl }}">{{ $propositionUrl }}</a>
                </div>
            </div>
        @endif

        <x-slot:footerNote>
            Pour discuter d'autres dates ou alternatives, contacte directement ton
            interlocuteur {{ $operator }}.
        </x-slot:footerNote>
    @endif

</x-mail.layout>
