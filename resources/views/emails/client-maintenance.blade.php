@php
    $operator   = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $clientName = $client?->name ?? 'Client';
    $count      = $maintenances->count();

    if ($context === 'down') {
        $preheader = "Votre campagne {$campaign->name} : {$count} panneau(x) actuellement en maintenance.";
        $title     = "Maintenance sur votre campagne";
    } else {
        $remaining = $count;
        $preheader = $remaining > 0
            ? "Un panneau est de retour en ligne — {$remaining} encore en maintenance sur votre campagne."
            : "Tous vos panneaux sont de nouveau diffusés.";
        $title     = "Retour en service";
    }
@endphp

<x-mail.layout title="{{ $title }}" :preheader="$preheader">

    @if($context === 'down')
        <span class="pill" style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;">🔧 Maintenance en cours</span>

        <h1>Information importante</h1>

        <p>Bonjour {{ $clientName }},</p>

        <p>
            Sur votre campagne <strong>{{ $campaign->name }}</strong>,
            <strong>{{ $count }} panneau{{ $count > 1 ? 'x sont' : ' est' }}</strong>
            actuellement en maintenance. Nos équipes interviennent pour rétablir
            l'affichage au plus vite. Vous recevrez un mail dès que la situation
            est résolue.
        </p>

        <div class="info">
            <div class="info-row">
                <div class="lbl">Campagne</div>
                <div class="val"><strong>{{ $campaign->name }}</strong></div>
            </div>
            <div class="info-row">
                <div class="lbl">Période</div>
                <div class="val">{{ $campaign->start_date?->format('d/m/Y') }} → {{ $campaign->end_date?->format('d/m/Y') }}</div>
            </div>
            <div class="info-row">
                <div class="lbl">Panneaux en maintenance</div>
                <div class="val"><strong style="color:#ea580c;">{{ $count }}</strong></div>
            </div>
        </div>

        <h2 style="font-size:15px;margin-top:24px;">Détail des panneaux concernés</h2>

        <div class="info">
            @foreach($maintenances as $m)
                <div class="info-row" style="align-items:flex-start;">
                    <div class="lbl">
                        <code>{{ $m->panel?->reference ?? '—' }}</code>
                    </div>
                    <div class="val">
                        {{ $m->panel?->name ?? '—' }}
                        <br>
                        <span style="font-size:12px;color:#6b7280;">
                            Signalé le {{ $m->date_signalement?->format('d/m/Y') }}
                            @if($m->date_fin_prevue)
                                · Retour prévu le
                                <strong style="color:#16a34a;">{{ $m->date_fin_prevue->format('d/m/Y') }}</strong>
                                @php $days = $m->daysRemaining(); @endphp
                                @if($days !== null && $days >= 0)
                                    ({{ $days === 0 ? 'aujourd\'hui' : 'dans ' . $days . ' jour' . ($days > 1 ? 's' : '') }})
                                @endif
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <p style="margin-top:18px;color:#4b5563;font-size:13px;">
            Vos autres panneaux de la campagne continuent leur diffusion normalement.
            La durée totale de votre campagne sera ajustée si nécessaire pour vous
            garantir le service payé.
        </p>

    @else
        @php
            $remaining = $maintenances->count();
            $backRef   = $maintenanceResolved?->panel?->reference;
        @endphp

        <span class="pill pill-success">✓ Panneau de retour en ligne</span>

        <h1>Bonne nouvelle</h1>

        <p>Bonjour {{ $clientName }},</p>

        @if($backRef)
            <p>
                Le panneau <strong>{{ $backRef }}</strong> de votre campagne
                <strong>{{ $campaign->name }}</strong> est de nouveau en service.
                L'affichage a repris.
            </p>
        @else
            <p>
                Un panneau de votre campagne <strong>{{ $campaign->name }}</strong>
                est de nouveau en service. L'affichage a repris.
            </p>
        @endif

        @if($remaining > 0)
            <div class="info">
                <div class="info-row">
                    <div class="lbl">Encore en maintenance</div>
                    <div class="val">
                        <strong style="color:#ea580c;">
                            {{ $remaining }} panneau{{ $remaining > 1 ? 'x' : '' }}
                        </strong>
                    </div>
                </div>
            </div>

            <p style="margin-top:14px;color:#4b5563;font-size:13px;">
                Nos équipes poursuivent les interventions sur les panneaux restants.
                Vous serez tenu informé à chaque rétablissement.
            </p>
        @else
            <div class="info">
                <div class="info-row">
                    <div class="lbl">Statut campagne</div>
                    <div class="val">
                        <strong style="color:#16a34a;">Diffusion intégrale rétablie ✓</strong>
                    </div>
                </div>
            </div>

            <p style="margin-top:14px;color:#4b5563;font-size:13px;">
                Tous vos panneaux sont de nouveau diffusés. Merci de votre patience.
            </p>
        @endif
    @endif

    <x-slot:footerNote>
        Cet email est envoyé automatiquement à chaque évolution de l'état d'un
        panneau de votre campagne. Pour toute question, contactez votre
        interlocuteur {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>
