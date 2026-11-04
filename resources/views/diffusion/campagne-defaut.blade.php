{{--
    Gabarit PAR DÉFAUT du mail de disponibilités envoyé aux clients via Brevo.

    Utilisé tant qu'aucun modèle n'est configuré dans BREVO_TEMPLATE_DISPOS.
    Dès que la boss aura conçu son modèle dans l'éditeur visuel de Brevo,
    ce gabarit n'est plus utilisé — il sert de point de départ et de filet
    de sécurité.

    ⚠ Les balises entre doubles accolades précédées d'un @ sont destinées à
    BREVO, pas à Blade : Brevo les remplace au moment de l'envoi, pour
    chaque destinataire.
        @{{ contact.CONTACT }}   nom du contact (attribut Brevo)
        @{{ contact.SOCIETE }}   entreprise cliente (attribut Brevo)
        @{{ mirror }}            lien « Consulter la version en ligne »
        @{{ unsubscribe }}       lien de désinscription — OBLIGATOIRE

    Mise en page en tableaux avec styles en ligne : c'est la seule façon
    d'obtenir le même rendu dans Gmail, Outlook et sur mobile.
--}}
@php
    $accent = '#e8a020';
    $sombre = '#0d1117';
    $logo   = url('images/logob.png');

    // Deux catalogues depuis le 2026-10-29 : Abidjan et l'intérieur du
    // pays. Une zone sans emplacement disponible n'a pas de lien dans
    // $valeurs — son bloc ne s'affiche donc pas du tout.
    $zones = array_values(array_filter([
        ['libelle' => 'à Abidjan',             'pastille' => '🟢', 'lien' => $valeurs['LIEN_ABIDJAN']   ?? null, 'nb' => $valeurs['NB_ABIDJAN']   ?? 0],
        ['libelle' => "à l'intérieur du pays", 'pastille' => '🔵', 'lien' => $valeurs['LIEN_INTERIEUR'] ?? null, 'nb' => $valeurs['NB_INTERIEUR'] ?? 0],
    ], fn ($z) => $z['lien'] !== null));

    // Texte de la direction (2026-11-04), le même que le modèle Brevo n° 14
    // (docs/diffusion/modele-brevo-disponibilites-leger.html).
    $abidjan   = isset($valeurs['LIEN_ABIDJAN']);
    $interieur = isset($valeurs['LIEN_INTERIEUR']);
    $ou = match (true) {
        $abidjan && $interieur => "à Abidjan et dans les principales villes de Côte d'Ivoire",
        $abidjan               => 'à Abidjan',
        default                => "dans les principales villes de Côte d'Ivoire",
    };
    $couverture = match (true) {
        $abidjan && $interieur => 'Abidjan et intérieur du pays',
        $abidjan               => 'Abidjan',
        default                => 'Intérieur du pays',
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nos disponibilités {{ $valeurs['PERIODE'] }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">

    {{-- Aperçu affiché par les messageries sous l'objet --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $valeurs['NB_PANNEAUX'] }} emplacements disponibles {{ $valeurs['PERIODE'] }} : photo, format, localisation et trafic estimé de chacun.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;">
        <tr>
            <td align="center" style="padding:24px 12px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;">

                    {{-- Version en ligne --}}
                    <tr>
                        <td align="right" style="padding:12px 24px;background:#ffffff;">
                            <a href="@{{ mirror }}" style="font-size:12px;color:#6b7280;text-decoration:underline;">Consulter la version en ligne</a>
                        </td>
                    </tr>

                    {{-- En-tête --}}
                    <tr>
                        <td align="center" style="background:{{ $sombre }};padding:26px 24px;">
                            <img src="{{ $logo }}" alt="CIBLE CI" width="150" style="display:block;border:0;max-width:150px;height:auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="height:4px;background:{{ $accent }};font-size:0;line-height:0;">&nbsp;</td>
                    </tr>

                    {{-- Titre --}}
                    <tr>
                        <td align="center" style="padding:34px 32px 8px;">
                            <h1 style="margin:0;font-size:22px;line-height:1.3;font-weight:800;color:#111827;">
                                De nouveaux emplacements publicitaires sont disponibles pour vos prochaines campagnes&nbsp;!
                            </h1>
                        </td>
                    </tr>

                    {{-- Encadré personnalisé --}}
                    <tr>
                        <td style="padding:22px 32px 6px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf3e1;border-radius:22px;">
                                <tr>
                                    <td align="center" style="padding:26px 28px;font-size:15px;line-height:1.6;color:#1f2937;">
                                        <p style="margin:0 0 12px;font-weight:700;font-size:16px;">
                                            Bonjour @{{ contact.CONTACT | default : "Madame, Monsieur" }},
                                        </p>
                                        <p style="margin:0;">
                                            {{-- L'entreprise cliente (2026-11-03) : l'interlocuteur sait pourquoi il reçoit le mail. --}}
                                            Anticipez vos prochaines prises de parole en découvrant notre sélection{% if contact.SOCIETE %} pour <strong>@{{ contact.SOCIETE }}</strong>&nbsp;:{% else %} de{% endif %}
                                            <strong>{{ $valeurs['NB_PANNEAUX'] }} emplacements disponibles {{ $valeurs['PERIODE'] }}</strong>, {{ $ou }}.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Chiffres clés --}}
                    <tr>
                        <td style="padding:22px 32px 4px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" width="50%" style="padding:14px 8px;border:1px solid #e5e7eb;border-radius:12px;">
                                        <div style="font-size:30px;font-weight:800;color:{{ $accent }};line-height:1;">{{ $valeurs['NB_PANNEAUX'] }}</div>
                                        <div style="margin-top:6px;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Emplacements</div>
                                    </td>
                                    <td width="12" style="font-size:0;">&nbsp;</td>
                                    <td align="center" width="50%" style="padding:14px 8px;border:1px solid #e5e7eb;border-radius:12px;">
                                        <div style="font-size:15px;font-weight:700;color:#111827;line-height:1.3;">{{ ucfirst($valeurs['PERIODE']) }}</div>
                                        <div style="margin-top:6px;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Période</div>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0 0;text-align:center;font-size:14px;color:#374151;">
                                🌍 <strong>Couverture&nbsp;:</strong> {{ $couverture }}
                            </p>
                        </td>
                    </tr>

                    {{-- Boutons : un catalogue par zone (2026-10-29).
                         Aucune zone disponible → pas de bloc du tout,
                         plutôt qu'une légende sans bouton. --}}
                    @if($zones)
                    <tr>
                        <td align="center" style="padding:28px 32px 10px;">
                            <p style="margin:0 0 14px;font-size:15px;font-weight:700;color:#111827;">Découvrez les disponibilités selon vos zones d'intérêt</p>
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                @foreach($zones as $z)
                                    <tr>
                                        <td align="center" style="background:{{ $loop->first ? $accent : $sombre }};border-radius:8px;">
                                            <a href="{{ $z['lien'] }}"
                                               style="display:inline-block;padding:16px 34px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">
                                                {{ $z['pastille'] }} Consulter les disponibilités {{ $z['libelle'] }}
                                            </a>
                                        </td>
                                    </tr>
                                    @unless($loop->last)
                                        <tr><td height="10" style="height:10px;font-size:0;line-height:0;">&nbsp;</td></tr>
                                    @endunless
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Le contenu du catalogue, puis l'appel à l'échange --}}
                    <tr>
                        <td align="center" style="padding:24px 40px 34px;font-size:14px;line-height:1.6;color:#374151;">
                            <p style="margin:0 0 14px;">
                                <strong>Bien plus qu'un catalogue d'emplacements&nbsp;:</strong> retrouvez pour chaque support sa photographie,
                                son format, sa localisation, son trafic estimé et sa description.
                            </p>
                            <p style="margin:0 0 6px;"><strong>Un ou plusieurs emplacements vous intéressent&nbsp;?</strong></p>
                            <p style="margin:0 0 14px;">
                                Répondez simplement à cet email en indiquant les références souhaitées. Notre équipe vous confirmera
                                leur disponibilité et vous transmettra une proposition commerciale <strong>sous 1&nbsp;heure ouvrée</strong>.
                            </p>
                            <p style="margin:0 0 14px;"><strong>Anticipez vos réservations pour sécuriser les meilleurs emplacements pour vos prochaines campagnes.</strong></p>
                            <p style="margin:0 0 14px;">Bien cordialement,</p>
                            <p style="margin:0;">
                                <strong style="color:#111827;">L'équipe commerciale CIBLE</strong><br>
                                <em>Votre visibilité, notre métier.</em>
                            </p>
                        </td>
                    </tr>

                    {{-- Pied de page --}}
                    <tr>
                        <td align="center" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:22px 32px;font-size:12px;line-height:1.6;color:#6b7280;">
                            <strong style="color:#374151;">CIBLE CI</strong> · {{ config('charte.coordonnees.activite') }}<br>
                            @if(config('charte.coordonnees.telephones')){{ config('charte.coordonnees.telephones') }}<br>@endif
                            <a href="mailto:{{ config('brevo.sender.email') }}" style="color:#6b7280;">{{ config('brevo.sender.email') }}</a>
                            <br><br>
                            Vous recevez ce mail deux fois par mois en tant que client de CIBLE CI.<br>
                            <a href="@{{ unsubscribe }}" style="color:#6b7280;text-decoration:underline;">Ne plus recevoir nos disponibilités</a>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
