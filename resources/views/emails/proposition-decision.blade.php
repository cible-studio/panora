@php
    $title = $isAccepted ? 'Proposition acceptée' : 'Proposition refusée';
    $badgeClass = $isAccepted ? 'badge badge-success' : 'badge badge-danger';
    $badgeLabel = $isAccepted ? '✅ Acceptée' : '❌ Refusée';
    $heroIcon   = $isAccepted ? '🎉' : '⚠️';
    $heroText   = $isAccepted
        ? 'Le client a <strong>accepté</strong> votre proposition.'
        : 'Le client a <strong>refusé</strong> votre proposition.';
    $totalAmount = (float) ($reservation->total_amount ?? 0);
@endphp

<x-mail.layout :title="$title">

    <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>

    <h1>{{ $heroIcon }} {{ $title }}</h1>
    <p>{!! $heroText !!}</p>

    <div class="info-box">
        <div class="info-box-row">
            <div class="lbl">Référence</div>
            <div class="val"><span class="code">{{ $reservation->reference }}</span></div>
        </div>
        <div class="info-box-row">
            <div class="lbl">Client</div>
            <div class="val"><strong>{{ $client?->name ?? '—' }}</strong></div>
        </div>
        <div class="info-box-row">
            <div class="lbl">Période</div>
            <div class="val">{{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}</div>
        </div>
        <div class="info-box-row">
            <div class="lbl">Panneaux</div>
            <div class="val">{{ $reservation->panels->count() }} emplacement{{ $reservation->panels->count() > 1 ? 's' : '' }}</div>
        </div>
        @if($totalAmount > 0)
            <div class="info-box-row">
                <div class="lbl">Montant</div>
                <div class="val"><strong style="color:#e8a020;">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</strong></div>
            </div>
        @endif
        <div class="info-box-row">
            <div class="lbl">Décision prise le</div>
            <div class="val">{{ now()->format('d/m/Y à H:i') }}</div>
        </div>
    </div>

    @if(!$isAccepted && $reason)
        <h2>Motif du refus</h2>
        <div style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:14px 18px;color:#cbd5e1;font-size:13px;line-height:1.5;">
            {{ $reason }}
        </div>
    @endif

    @if($isAccepted)
        <h2>Prochaines étapes</h2>
        <ul style="color:#cbd5e1;font-size:13px;line-height:1.7;padding-left:18px;">
            <li>La réservation a été automatiquement marquée comme <strong>confirmée</strong>.</li>
            <li>Vous pouvez créer la <strong>campagne</strong> associée depuis la fiche réservation.</li>
            <li>Pensez à préparer la <strong>facturation</strong> et le planning de pose.</li>
        </ul>
    @else
        <h2>Que faire ensuite ?</h2>
        <ul style="color:#cbd5e1;font-size:13px;line-height:1.7;padding-left:18px;">
            <li>Contactez le client pour <strong>comprendre le motif</strong> et proposer des alternatives.</li>
            <li>La réservation est marquée comme <strong>refusée</strong> et les panneaux ont été <strong>libérés</strong>.</li>
            <li>Vous pouvez créer une <strong>nouvelle proposition</strong> ajustée si besoin.</li>
        </ul>
    @endif

    <div class="cta-wrap">
        <a href="{{ $showLink }}" class="cta-btn">Voir la fiche réservation →</a>
        <div class="cta-sub">Plateforme CIBLE CI · Espace administrateur</div>
    </div>

    <x-slot:footerNote>
        Notification automatique suite à la décision du client sur la proposition.
    </x-slot:footerNote>

</x-mail.layout>
