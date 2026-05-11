@php
    $priorityLabel = ['urgente'=>'🔴 Urgente','haute'=>'🟠 Haute','normale'=>'🔵 Normale','faible'=>'⚪ Faible'][$maintenance->priorite] ?? $maintenance->priorite;

    [$title, $intro, $pillText, $pillClass, $preheader] = match($context) {
        'assigned' => [
            'Nouvelle maintenance assignée',
            "Une maintenance vient de vous être attribuée. Merci d'intervenir dès que possible.",
            'Nouvelle assignation',
            'pill pill-warning',
            "Panneau {$panel?->reference} — {$maintenance->type_panne}",
        ],
        'updated' => [
            'Maintenance mise à jour',
            "Des informations ont été modifiées sur une maintenance qui vous est assignée.",
            'Mise à jour',
            'pill',
            "Modification de la fiche {$panel?->reference}",
        ],
        'resolved' => [
            'Maintenance résolue',
            "La maintenance signalée sur ce panneau vient d'être marquée comme résolue.",
            'Résolue',
            'pill pill-success',
            "Panneau {$panel?->reference} remis en service",
        ],
        default => ['Notification maintenance', '', 'Info', 'pill', ''],
    };
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="{{ $pillClass }}">{{ $pillText }}</span>

    <h1>Bonjour {{ $tech?->name ?? $signaler?->name ?? '' }},</h1>
    <p>{{ $intro }}</p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Panneau</div>
            <div class="val">
                <code>{{ $panel?->reference }}</code> — {{ $panel?->name }}
                @if($panel?->commune)<br><span style="color:#6b7280;font-size:12px;">📍 {{ $panel->commune->name }}</span>@endif
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Type de panne</div>
            <div class="val">{{ $maintenance->type_panne }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Priorité</div>
            <div class="val">{{ $priorityLabel }}</div>
        </div>
        @if($maintenance->description)
        <div class="info-row">
            <div class="lbl">Description</div>
            <div class="val">{{ $maintenance->description }}</div>
        </div>
        @endif
        @if($context === 'resolved' && $maintenance->solution)
        <div class="info-row">
            <div class="lbl">Solution</div>
            <div class="val">{{ $maintenance->solution }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Résolu le</div>
            <div class="val">{{ $maintenance->date_resolution?->format('d/m/Y') ?? '—' }}</div>
        </div>
        @endif
        @if($context !== 'resolved' && $signaler)
        <div class="info-row">
            <div class="lbl">Signalé par</div>
            <div class="val">
                {{ $signaler->name }}
                @if($signaler->whatsapp_number)<br><span style="color:#6b7280;font-size:12px;">WhatsApp : {{ $signaler->whatsapp_number }}</span>@endif
            </div>
        </div>
        @endif
    </div>

    <div class="cta-wrap">
        <a href="{{ $showUrl }}" class="cta">Voir la fiche maintenance</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $showUrl }}">{{ $showUrl }}</a>
        </div>
    </div>

    <x-slot:footerNote>
        Notification automatique — merci de ne pas répondre à cette adresse.
    </x-slot:footerNote>

</x-mail.layout>
