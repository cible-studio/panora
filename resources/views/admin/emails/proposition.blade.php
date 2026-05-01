@php
    use Illuminate\Support\Str;

    $clientName  = $client?->name ?? 'Client';
    $panelCount  = $panels->count();
    $months      = max(1.0, (function ($s, $e) {
        $s = \Carbon\Carbon::parse($s)->startOfDay();
        $e = \Carbon\Carbon::parse($e)->endOfDay();
        $m = (int) abs($s->diffInMonths($e));
        $r = abs($s->copy()->addMonths($m)->diffInDays($e));
        return (float) ($r > 0 ? $m + 1 : $m);
    })($reservation->start_date, $reservation->end_date));
    $totalAmount = $panels->sum(fn($p) => (float) ($p->monthly_rate ?? 0) * $months);
    $preheader   = "{$panelCount} emplacements proposés du "
        . $reservation->start_date->format('d/m/Y')
        . ' au ' . $reservation->end_date->format('d/m/Y') . '.';
@endphp

<x-mail.layout title="Proposition commerciale" :preheader="$preheader">

    <span class="pill">Proposition commerciale</span>

    <h1>Bonjour {{ $clientName }},</h1>
    <p>
        Nous avons sélectionné <strong>{{ $panelCount }} emplacement{{ $panelCount > 1 ? 's' : '' }}</strong>
        pour votre prochaine campagne d'affichage. Vous pouvez consulter le détail
        et confirmer ou refuser depuis le bouton ci-dessous.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $reservation->reference }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">{{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Emplacements</div>
            <div class="val">{{ $panelCount }} panneau{{ $panelCount > 1 ? 'x' : '' }}</div>
        </div>
        @if($totalAmount > 0)
            <div class="info-row">
                <div class="lbl">Montant indicatif</div>
                <div class="val"><strong style="color:#c2570d;">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</strong></div>
            </div>
        @endif
    </div>

    @if($panels->count() > 0)
        <h2>Aperçu des emplacements</h2>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
               style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin:8px 0 18px;">
            @foreach($panels->take(5) as $i => $panel)
                @php
                    $unit  = (float) ($panel->monthly_rate ?? 0);
                    $total = $unit * $months;
                @endphp
                <tr style="{{ $i > 0 ? 'border-top:1px solid #f1f5f9;' : '' }}">
                    <td style="padding:12px 16px;">
                        <div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;color:#c2570d;font-weight:600;">{{ $panel->reference }}</div>
                        <div style="font-size:14px;color:#111827;font-weight:500;margin-top:2px;">{{ Str::limit($panel->name, 50) }}</div>
                        <div style="font-size:12px;color:#6b7280;margin-top:2px;">
                            {{ $panel->commune?->name ?? '—' }}
                            @if($panel->format?->name) · {{ $panel->format->name }} @endif
                        </div>
                    </td>
                    <td style="padding:12px 16px;text-align:right;vertical-align:top;white-space:nowrap;">
                        @if($total > 0)
                            <div style="font-size:14px;color:#111827;font-weight:600;">{{ number_format($total, 0, ',', ' ') }} FCFA</div>
                            <div style="font-size:11px;color:#9ca3af;">sur la période</div>
                        @else
                            <div style="font-size:12px;color:#6b7280;">Sur devis</div>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if($panels->count() > 5)
                <tr style="border-top:1px solid #f1f5f9;background:#f9fafb;">
                    <td colspan="2" style="padding:10px 16px;font-size:12px;color:#6b7280;text-align:center;">
                        + {{ $panels->count() - 5 }} autre{{ $panels->count() - 5 > 1 ? 's' : '' }} emplacement{{ $panels->count() - 5 > 1 ? 's' : '' }} —
                        détails complets sur la page de la proposition.
                    </td>
                </tr>
            @endif
        </table>
    @endif

    <div class="cta-wrap">
        <a href="{{ $lien }}" class="cta">Consulter et répondre</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $lien }}">{{ $lien }}</a>
        </div>
    </div>

    @if($expiresAt)
        <div class="alert alert-warning">
            Cette proposition expire le <strong>{{ $expiresAt->format('d/m/Y à H:i') }}</strong>.
            Au-delà, le lien ne sera plus accessible.
        </div>
    @endif

    <p style="color:#6b7280;font-size:13px;margin-top:24px;">
        Vous pouvez également nous appeler ou répondre directement à cet email pour toute question.
        Notre équipe commerciale est à votre disposition.
    </p>

    <x-slot:footerNote>
        Cet email vous est adressé suite à l'établissement d'une proposition pour la réservation {{ $reservation->reference }}.
    </x-slot:footerNote>

</x-mail.layout>
