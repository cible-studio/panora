@php
    use Illuminate\Support\Str;
    $ch = config('charte.couleurs');

    $clientName  = $client?->name ?? 'Client';
    $panelCount  = $panels->count();

    // ── Règle CIBLE CI (cohérente avec le contrôleur) ─────────────────
    //   1-15 jours résiduels → +0.5 mois
    //   16-30 jours          → +1 mois
    //   minimum facturable   → 0.5 mois
    // RÈGLE PATRONNE v2 2026-06-25 — délègue à Campaign::computeBillableMonths
    // (SOURCE UNIQUE : mois civils + tolérance +1j).
    $months      = \App\Models\Campaign::computeBillableMonths(
        \Carbon\Carbon::parse($reservation->start_date),
        \Carbon\Carbon::parse($reservation->end_date)
    );
    $monthsLabel = rtrim(rtrim(number_format($months, 1, ',', ''), '0'), ',');

    // ── Montant total : total_amount fait foi.
    //    NULL = pas encore fixé → on calcule à partir des panneaux.
    //    0   = saisi explicitement par le commercial (campagne offerte par
    //          ex.) → on respecte 0, pas de fallback calculé.
    $totalAmount = $reservation->total_amount === null
        ? $panels->sum(fn($p) => (float) ($p['monthly_rate'] ?? 0) * $months)
        : (float) $reservation->total_amount;

    // hasAmount = montant non null (0 inclus, c'est une décision business
    // valable). On affiche systématiquement le total — y compris 0 FCFA.
    $hasAmount = $reservation->total_amount !== null;
    $isOffert  = $hasAmount && $totalAmount === 0.0;

    $preheader = "{$panelCount} emplacements · {$totalDays} jour" . ($totalDays > 1 ? 's' : '');
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
                <div style="font-size:11px;color:{{ $ch['noir'] }};margin-top:2px">
                    {{ $totalDays }} jour{{ $totalDays > 1 ? 's' : '' }} · {{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }}
                </div>
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Emplacements</div>
            <div class="val">{{ $panelCount }} panneau{{ $panelCount > 1 ? 'x' : '' }}</div>
        </div>
    </div>

    @if($panels->count() > 0)
        @php
            // Résumé statistique : groupage par commune × format pour les
            // grosses propositions (>10). Évite de spammer le client avec
            // 100 lignes — il verra le détail complet sur la page web.
            $isLarge = $panels->count() > 10;
            $grouped = $panels->groupBy(function ($p) {
                $commune = $p['commune'] ?? '—';
                $format  = $p['format'] ?? '—';
                return $commune . '|||' . $format;
            })->map(function ($items, $key) {
                [$commune, $format] = explode('|||', $key);
                return [
                    'commune' => $commune,
                    'format'  => $format,
                    'count'   => $items->count(),
                    'total'   => $items->sum(fn($p) => (float) ($p['total'] ?? 0)),
                ];
            })->sortByDesc('count')->values();
        @endphp

        @if($isLarge)
            {{-- Vue résumée : groupes par commune × format (Top 8) --}}
            <h2>Répartition des {{ $panels->count() }} emplacements</h2>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                   style="border-top:2px solid {{ $ch['noir'] }};border-bottom:1px solid {{ $ch['gris'] }};margin:8px 0 18px;">
                @foreach($grouped->take(8) as $i => $row)
                    <tr style="{{ $i > 0 ? 'border-top:1px solid ' . $ch['gris'] . ';' : '' }}">
                        <td style="padding:11px 0;">
                            <div style="font-size:14px;color:{{ $ch['noir'] }};font-weight:600;">
                                {{ $row['count'] }} × {{ $row['format'] !== '—' ? $row['format'] : 'panneau' }}
                            </div>
                            <div style="font-size:12px;color:{{ $ch['noir'] }};margin-top:2px;">
                                📍 {{ $row['commune'] }}
                            </div>
                        </td>
                    </tr>
                @endforeach
                @if($grouped->count() > 8)
                    <tr style="border-top:1px solid {{ $ch['gris'] }};background:{{ $ch['gris'] }};">
                        <td style="padding:10px 16px;font-size:12px;color:{{ $ch['noir'] }};text-align:center;">
                            + {{ $grouped->count() - 8 }} autres regroupements géographiques
                        </td>
                    </tr>
                @endif
            </table>
            <p style="font-size:12px;color:{{ $ch['noir'] }};margin:8px 0 18px;">
                ℹ️ Vue résumée — la liste complète des {{ $panels->count() }} emplacements (références, dimensions, photos)
                est disponible sur la page de la proposition.
            </p>
        @else
            {{-- Vue détaillée : ≤ 10 panneaux, on affiche jusqu'à 5 lignes complètes --}}
            <h2>Détail des emplacements</h2>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                   style="border-top:2px solid {{ $ch['noir'] }};border-bottom:1px solid {{ $ch['gris'] }};margin:8px 0 18px;">
                @foreach($panels->take(5) as $i => $panel)
                    <tr style="{{ $i > 0 ? 'border-top:1px solid ' . $ch['gris'] . ';' : '' }}">
                        <td style="padding:12px 0;">
                            <div style="font-family:Consolas,Menlo,'Courier New',monospace;font-size:12px;color:{{ $ch['rouge'] }};font-weight:700;">{{ $panel['reference'] }}</div>
                            <div style="font-size:14px;color:{{ $ch['noir'] }};font-weight:500;margin-top:2px;">{{ Str::limit($panel['name'] ?? '', 50) }}</div>
                            <div style="font-size:12px;color:{{ $ch['noir'] }};margin-top:2px;">
                                {{ $panel['commune'] ?? '—' }}
                                @if(!empty($panel['format']) && $panel['format'] !== '—') · {{ $panel['format'] }} @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                @if($panels->count() > 5)
                    <tr style="border-top:1px solid {{ $ch['gris'] }};background:{{ $ch['gris'] }};">
                        <td style="padding:10px 16px;font-size:12px;color:{{ $ch['noir'] }};text-align:center;">
                            + {{ $panels->count() - 5 }} autre{{ $panels->count() - 5 > 1 ? 's' : '' }} emplacement{{ $panels->count() - 5 > 1 ? 's' : '' }} —
                            liste complète sur la page de la proposition.
                        </td>
                    </tr>
                @endif
            </table>
            <p style="font-size:12px;color:{{ $ch['noir'] }};margin:8px 0 18px;">
                ℹ️ Le tarif et les conditions complètes sont disponibles sur la page de la proposition.
            </p>
        @endif
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

    {{-- Bloc interlocuteur commercial — c'est le COMMERCIAL qui envoie
         qui doit apparaître ici (pas le MP qui a construit la propo).
         resolveCommercialContact() retourne commercial_user_id si défini,
         sinon retombe sur user_id (créateur). On filtre aussi les rôles
         non-commerciaux pour éviter d'afficher "Media Planner" au client. --}}
    @php
        $com = $reservation->resolveCommercialContact();
        $comRole = $com?->role?->value ?? null;
        // Si le contact résolu n'est pas un commercial/admin (ex: fallback
        // sur le MP créateur faute de mieux), on masque son rôle interne
        // pour ne pas afficher "Media Planner" au client.
        $hideInternalRole = !in_array($comRole, ['admin', 'commercial'], true);
    @endphp
    @if($com)
        <h2 style="font-size:14px;font-weight:700;color:{{ $ch['rouge'] }};margin:20px 0 10px;">Votre interlocuteur commercial</h2>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
               style="background:{{ $ch['blanc'] }};border:1px solid {{ $ch['gris'] }};border-left:4px solid {{ $ch['rouge'] }};margin-bottom:14px;">
            <tr>
                <td style="padding:14px 16px;">
                    <div style="font-size:14px;font-weight:700;color:{{ $ch['noir'] }};">{{ $com->name }}</div>
                    @if(!$hideInternalRole && $com->role?->label())
                        <div style="font-size:11px;color:{{ $ch['noir'] }};margin-top:2px;">{{ $com->role->label() }}</div>
                    @endif
                    <div style="margin-top:10px;font-size:12px;color:{{ $ch['noir'] }};line-height:1.7;">
                        @if($com->email)
                            📧 <a href="mailto:{{ $com->email }}" style="color:{{ $ch['rouge'] }};text-decoration:none;">{{ $com->email }}</a><br>
                        @endif
                        @if($com->whatsapp_number)
                            📱 <a href="https://wa.me/{{ preg_replace('/\D/', '', $com->whatsapp_number) }}" style="color:{{ $ch['rouge'] }};text-decoration:none;">{{ $com->whatsapp_number }}</a> (WhatsApp)
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    @endif

    <p style="color:{{ $ch['noir'] }};font-size:13px;margin-top:24px;">
        @if($com)
            Pour toute question, contactez {{ $com->name }} directement aux coordonnées ci-dessus.
        @else
            Pour toute question, contactez l'équipe commerciale aux coordonnées de votre interlocuteur.
        @endif
    </p>

    <x-slot:footerNote>
        Cet email vous est adressé suite à l'établissement d'une proposition pour la réservation {{ $reservation->reference }}.
    </x-slot:footerNote>

</x-mail.layout>
