@php
    use Illuminate\Support\Str;

    $clientName  = $client?->name ?? 'Client';
    $panelCount  = $panels->count();

    // ── Règle CIBLE CI (cohérente avec le contrôleur) ─────────────────
    //   1-15 jours résiduels → +0.5 mois
    //   16-30 jours          → +1 mois
    //   minimum facturable   → 0.5 mois
    $sd = \Carbon\Carbon::parse($reservation->start_date)->startOfDay();
    $ed = \Carbon\Carbon::parse($reservation->end_date)->startOfDay();
    $totalDays = max(1, (int) $sd->diffInDays($ed));
    $fullMonths = (int) floor($totalDays / 30);
    $remainDays = $totalDays % 30;
    $fraction   = $remainDays === 0 ? 0 : ($remainDays <= 15 ? 0.5 : 1);
    $months     = max(0.5, $fullMonths + $fraction);
    $monthsLabel = rtrim(rtrim(number_format($months, 1, ',', ''), '0'), ',');

    // ── Montant total : total_amount fait foi.
    //    NULL = pas encore fixé → on calcule à partir des panneaux.
    //    0   = saisi explicitement par le commercial (campagne offerte par
    //          ex.) → on respecte 0, pas de fallback calculé.
    $totalAmount = $reservation->total_amount === null
        ? $panels->sum(fn($p) => (float) ($p['monthly_rate'] ?? 0) * $months)
        : (float) $reservation->total_amount;

    $hasAmount = $totalAmount > 0;

    $preheader = "{$panelCount} emplacements · {$totalDays} jour" . ($totalDays > 1 ? 's' : '')
        . ($hasAmount ? ' · ' . number_format($totalAmount, 0, ',', ' ') . ' FCFA' : '');
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
            <div class="val">
                {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
                <div style="font-size:11px;color:#6b7280;margin-top:2px">
                    {{ $totalDays }} jour{{ $totalDays > 1 ? 's' : '' }} · {{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }}
                </div>
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Emplacements</div>
            <div class="val">{{ $panelCount }} panneau{{ $panelCount > 1 ? 'x' : '' }}</div>
        </div>
        @if($totalAmount > 0)
            <div class="info-row">
                <div class="lbl">Montant total à payer</div>
                <div class="val">
                    <strong style="color:#c2570d;font-size:16px">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</strong>
                    <div style="font-size:11px;color:#6b7280;margin-top:2px">
                        Pour la totalité de la campagne ({{ $totalDays }} jour{{ $totalDays > 1 ? 's' : '' }})
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($panels->count() > 0)
        <h2>Détail des emplacements</h2>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
               style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin:8px 0 18px;">
            @foreach($panels->take(5) as $i => $panel)
                @php
                    // Le tarif catalogue n'est pas exposé dans la projection
                    // unifiée — pour les externes c'est la régie qui fixe.
                    // On compare unit (pivot) vs monthly_rate ('catalogue' de
                    // la projection) pour détecter une remise négociée.
                    $unit  = (float) ($panel['monthly_rate'] ?? 0);
                    $total = (float) ($panel['total'] ?? ($unit * $months));
                @endphp
                <tr style="{{ $i > 0 ? 'border-top:1px solid #f1f5f9;' : '' }}">
                    <td style="padding:12px 16px;">
                        <div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;color:#c2570d;font-weight:600;">{{ $panel['reference'] }}</div>
                        <div style="font-size:14px;color:#111827;font-weight:500;margin-top:2px;">{{ Str::limit($panel['name'] ?? '', 50) }}</div>
                        <div style="font-size:12px;color:#6b7280;margin-top:2px;">
                            {{ $panel['commune'] ?? '—' }}
                            @if(!empty($panel['format']) && $panel['format'] !== '—') · {{ $panel['format'] }} @endif
                        </div>
                    </td>
                    <td style="padding:12px 16px;text-align:right;vertical-align:top;white-space:nowrap;">
                        @if($total > 0)
                            <div style="font-size:14px;color:#111827;font-weight:700;">{{ number_format($total, 0, ',', ' ') }} FCFA</div>
                            <div style="font-size:11px;color:#9ca3af;">total période</div>
                            @if($unit > 0)
                                <div style="font-size:11px;color:#6b7280;margin-top:4px;">
                                    {{ number_format($unit, 0, ',', ' ') }} FCFA/mois
                                </div>
                            @endif
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
        <p style="font-size:12px;color:#6b7280;margin:8px 0 18px;">
            ℹ️ Le total prend en compte la durée réelle de votre campagne ({{ $totalDays }} jour{{ $totalDays > 1 ? 's' : '' }} = {{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }}).
        </p>
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
