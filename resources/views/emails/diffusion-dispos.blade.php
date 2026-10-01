@php
    /*
     * Confirmation interne d'une diffusion des disponibilités (ou alerte
     * d'échec). Refonte 2026-10-01 : bandeau de statut, chiffres clés,
     * détails, actions — styles en ligne (survivent au transfert).
     */
    $title = $succes
        ? 'Disponibilités envoyées aux clients'
        : "L'envoi des disponibilités a échoué";
    $preheader = $succes
        ? ($test
            ? "Test réussi — les disponibilités {$periode} sont arrivées dans la liste Tests internes."
            : "{$envoi->nb_destinataires} client(s) ont reçu les disponibilités {$periode}.")
        : "Les disponibilités {$periode} ne sont pas parties. Aucun client ne les a reçues.";

    // Couleurs du bandeau : vert = parti, rouge = échec. Un test garde sa
    // couleur de résultat ; la mention « test » est ajoutée à part.
    $ton = $succes
        ? ['fond' => '#f0fdf4', 'bord' => '#16a34a', 'texte' => '#14532d', 'sur' => '#15803d']
        : ['fond' => '#fef2f2', 'bord' => '#dc2626', 'texte' => '#7f1d1d', 'sur' => '#b91c1c'];

    $surtitre = $succes ? ($test ? 'Envoi de test réussi' : 'Envoi réussi') : 'Échec de l\'envoi';
    $titre    = $succes
        ? ($test ? 'Le test est bien parti' : 'Les clients ont reçu les disponibilités')
        : 'Les disponibilités ne sont pas parties';

    $type = ucfirst($envoi->libelleMode()) . ($envoi->auteur ? ' — ' . $envoi->auteur->name : '');

    $chiffres = [
        $succes
            ? ['valeur' => $envoi->nb_destinataires ?? 0, 'libelle' => $test ? 'Destinataires de test' : 'Clients destinataires']
            : ['valeur' => '—', 'libelle' => 'Aucun client contacté'],
        ['valeur' => $envoi->nb_panneaux ?? 0,      'libelle' => 'Panneaux présentés'],
        ['valeur' => $succes ? ($envoi->envoye_at?->format('H\hi') ?? '—') : '—',
         'libelle' => $succes ? 'Envoyé le ' . ($envoi->envoye_at?->format('d/m') ?? '') : 'Non envoyé'],
    ];

    $sans  = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;";
    $lbl   = "{$sans}font-size:12px;color:#6b7280;padding:9px 0;border-bottom:1px solid #f1f5f9;vertical-align:top;";
    $val   = "{$sans}font-size:14px;color:#111827;font-weight:600;padding:9px 0;border-bottom:1px solid #f1f5f9;text-align:right;vertical-align:top;";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    {{-- ═══ Bandeau de statut ═══ --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:{{ $ton['fond'] }};border-left:4px solid {{ $ton['bord'] }};border-radius:8px;">
        <tr><td style="padding:20px 22px;{{ $sans }}">
            <div style="font-size:11px;line-height:14px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{{ $ton['sur'] }};">
                {{ $surtitre }}
            </div>
            <div class="resp-h1" style="font-size:21px;line-height:27px;font-weight:700;color:{{ $ton['texte'] }};margin-top:6px;">
                {{ $titre }}
            </div>
            <div style="font-size:14px;line-height:20px;color:{{ $ton['texte'] }};margin-top:4px;">
                Disponibilités <strong style="color:{{ $ton['texte'] }};">{{ $periode }}</strong>
            </div>
        </td></tr>
    </table>

    @if($test)
        <p style="{{ $sans }}font-size:13px;line-height:20px;color:#92400e;background-color:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 14px;margin:14px 0 0;">
            <strong style="color:#92400e;">Mode test</strong> — seule la liste « Tests internes » de Brevo est concernée. Aucun client n'a été contacté.
        </p>
    @endif

    {{-- ═══ Chiffres clés ═══ --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;">
        <tr>
            @foreach($chiffres as $i => $c)
                <td width="33%" valign="top" style="width:33%;padding:{{ $i === 0 ? '0 6px 0 0' : ($i === 2 ? '0 0 0 6px' : '0 3px') }};">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                           style="background-color:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;">
                        <tr><td align="center" valign="top" height="62" style="height:62px;padding:14px 6px;{{ $sans }}">
                            <div style="font-size:24px;line-height:28px;font-weight:700;color:#0f172a;">{{ $c['valeur'] }}</div>
                            <div style="font-size:11px;line-height:15px;color:#6b7280;margin-top:4px;">{{ $c['libelle'] }}</div>
                        </td></tr>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

    {{-- ═══ Cause et marche à suivre (échec) ═══ --}}
    @unless($succes)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;">
            <tr><td style="{{ $sans }}">
                <div style="font-size:12px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;color:#374151;">Cause</div>
                <div style="font-size:14px;line-height:21px;color:#7f1d1d;background-color:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:12px 14px;margin-top:8px;word-break:break-word;">
                    {{ $envoi->erreur ?: 'Inconnue — voir le journal du serveur.' }}
                </div>
                <div style="font-size:12px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;color:#374151;margin-top:18px;">Que faire</div>
                <div style="font-size:14px;line-height:22px;color:#4b5563;margin-top:6px;">
                    1. Corriger la cause ci-dessus (ou la transmettre à l'administrateur).<br>
                    2. Ouvrir l'écran de diffusion dans Panora.<br>
                    3. Cliquer sur <strong style="color:#111827;">« Envoyer maintenant »</strong>.
                </div>
            </td></tr>
        </table>
    @endunless

    {{-- ═══ Détails ═══ --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;">
        <tr>
            <td style="{{ $lbl }}">Période</td>
            <td style="{{ $val }}">{{ $periode }}</td>
        </tr>
        <tr>
            <td style="{{ $lbl }}">Type d'envoi</td>
            <td style="{{ $val }}">{{ $type }}</td>
        </tr>
        @if($succes)
            <tr>
                <td style="{{ $lbl }}">Envoyé le</td>
                <td style="{{ $val }}">{{ $envoi->envoye_at?->format('d/m/Y à H\hi') ?? '—' }}</td>
            </tr>
        @endif
        @if($envoi->brevo_campaign_id)
            <tr>
                <td style="{{ $lbl }}border-bottom:0;">Campagne Brevo</td>
                <td style="{{ $val }}border-bottom:0;">n° {{ $envoi->brevo_campaign_id }}</td>
            </tr>
        @endif
    </table>

    {{-- ═══ Actions ═══ --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:26px;">
        <tr><td align="center" style="{{ $sans }}">
            @if($succes && $lienApercu)
                <a href="{{ $lienApercu }}" class="resp-cta"
                   style="display:inline-block;background-color:#0f172a;color:#ffffff !important;font-size:14px;font-weight:600;text-decoration:none;padding:13px 26px;border-radius:6px;margin:0 4px 10px;">
                    Voir les statistiques Brevo
                </a>
            @endif
            @if($lienPdf)
                <a href="{{ $lienPdf }}" class="resp-cta"
                   style="display:inline-block;background-color:#ffffff;color:#0f172a !important;font-size:14px;font-weight:600;text-decoration:none;padding:12px 25px;border-radius:6px;border:1px solid #cbd5e1;margin:0 4px 10px;">
                    {{ $succes ? 'Voir le catalogue envoyé' : 'Voir le catalogue préparé' }}
                </a>
            @endif
            @unless($succes)
                <a href="{{ $lienEcran }}" class="resp-cta"
                   style="display:inline-block;background-color:#0f172a;color:#ffffff !important;font-size:14px;font-weight:600;text-decoration:none;padding:13px 26px;border-radius:6px;margin:0 4px 10px;">
                    Ouvrir l'écran de diffusion
                </a>
            @endunless
        </td></tr>
        @if($succes)
            <tr><td align="center" style="{{ $sans }}font-size:12px;line-height:18px;color:#6b7280;padding-top:4px;">
                @if($lienApercu)
                    Ouvertures, clics et désinscriptions — accès au compte Brevo nécessaire.<br>
                @endif
                <a href="{{ $lienEcran }}" style="color:#475569;text-decoration:underline;">Écran de diffusion dans Panora</a>
            </td></tr>
        @endif
    </table>

</x-mail.layout>
