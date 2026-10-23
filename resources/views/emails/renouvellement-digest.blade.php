{{-- Mail interne du matin : renouvellements (2026-10-22). Cf. RenouvellementDigestMail. --}}
@php
    $title = $role === 'mediaplanner' ? 'Renouvellements à confirmer' : 'Vos renouvellements du jour';
    $ligne = function ($l) {
        $c = $l['campagne'];
        return [
            'client'   => $c->client?->name ?? '—',
            'campagne' => $c->name,
            'fin'      => $c->end_date->format('d/m/Y'),
            'jours'    => $l['jours'] > 0 ? 'J-' . $l['jours'] : 'terminée',
            'tel'      => $l['telephone'],
            'montant'  => $l['montant'] > 0 ? number_format($l['montant'], 0, ',', ' ') . ' FCFA' : null,
        ];
    };
    $th = 'text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#777;padding:6px 8px 6px 0;border-bottom:1px solid #E6E6E6;';
    $td = 'font-size:13px;padding:8px 8px 8px 0;border-bottom:1px solid #F0F0F0;vertical-align:top;';
@endphp

<x-mail.layout :title="$title" :preheader="$title">

    @if($noteTest)
        <p style="font-size:13px;"><strong>Mail de test.</strong> {{ $noteTest }}</p>
    @endif

    <h1>{{ $title }}</h1>
    <p>Bonjour {{ $nom }},</p>

    @if($role === 'mediaplanner')
        <p>Ces clients ont répondu qu'ils souhaitent renouveler ou ajuster leur campagne.
           Une fois la disponibilité vérifiée, confirme le renouvellement dans Panora :
           les panneaux sortiront alors du catalogue des disponibilités.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><th style="{{ $th }}">Client</th><th style="{{ $th }}">Campagne</th><th style="{{ $th }}">Réponse</th><th style="{{ $th }}">Fin</th></tr>
            @foreach($a_confirmer as $l)
                @php $v = $ligne($l); @endphp
                <tr>
                    <td style="{{ $td }}"><strong>{{ $v['client'] }}</strong></td>
                    <td style="{{ $td }}">{{ $v['campagne'] }}</td>
                    <td style="{{ $td }}">{{ $l['dossier']?->libelle() }}</td>
                    <td style="{{ $td }}">{{ $v['fin'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        @if(isset($a_rappeler) && $a_rappeler->isNotEmpty())
            <h2 style="font-size:15px;margin:22px 0 8px;">📅 Rappels prévus aujourd'hui ({{ $a_rappeler->count() }})</h2>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                @foreach($a_rappeler as $l)
                    @php $v = $ligne($l); @endphp
                    <tr>
                        <td style="{{ $td }}"><strong>{{ $v['client'] }}</strong><br><span style="color:#777;">{{ $v['campagne'] }} · fin {{ $v['fin'] }}@if($l['dossier']?->rappel_le?->lt(today())) · <strong style="color:#E20613;">prévu le {{ $l['dossier']->rappel_le->format('d/m') }}</strong>@endif</span></td>
                        <td style="{{ $td }}white-space:nowrap;text-align:right;">{{ $v['tel'] ?? 'pas de téléphone' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if($rappels->isNotEmpty())
            <h2 style="font-size:15px;margin:22px 0 8px;">📞 Demandes de rappel ({{ $rappels->count() }})</h2>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                @foreach($rappels as $l)
                    @php $v = $ligne($l); @endphp
                    <tr>
                        <td style="{{ $td }}"><strong>{{ $v['client'] }}</strong><br><span style="color:#777;">{{ $v['campagne'] }} · fin {{ $v['fin'] }}</span></td>
                        <td style="{{ $td }}white-space:nowrap;text-align:right;">{{ $v['tel'] ?? 'pas de téléphone' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if($appeler->isNotEmpty())
            <h2 style="font-size:15px;margin:22px 0 8px;">⏳ Sans réponse, à appeler ({{ $appeler->count() }})</h2>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                @foreach($appeler as $l)
                    @php $v = $ligne($l); @endphp
                    <tr>
                        <td style="{{ $td }}"><strong>{{ $v['client'] }}</strong><br><span style="color:#777;">{{ $v['campagne'] }} · fin {{ $v['fin'] }} ({{ $v['jours'] }})@if($v['montant']) · {{ $v['montant'] }}@endif</span></td>
                        <td style="{{ $td }}white-space:nowrap;text-align:right;">{{ $v['tel'] ?? 'pas de téléphone' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if($reponses->isNotEmpty())
            <h2 style="font-size:15px;margin:22px 0 8px;">✉️ Réponses reçues ({{ $reponses->count() }})</h2>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                @foreach($reponses as $l)
                    @php $v = $ligne($l); @endphp
                    <tr>
                        <td style="{{ $td }}"><strong>{{ $v['client'] }}</strong><br><span style="color:#777;">{{ $v['campagne'] }}</span>
                            @if($l['dossier']?->reponse_message)<br><em>« {{ $l['dossier']->reponse_message }} »</em>@endif</td>
                        <td style="{{ $td }}text-align:right;">{{ $l['dossier']?->libelle() }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <p style="margin-top:18px;">Après chaque appel, note le résultat dans Panora : si le client a pris sa décision, les relances par mail s'arrêtent.</p>
    @endif

    <div class="cta-wrap">
        <a href="{{ $lien }}" class="cta">Ouvrir les renouvellements</a>
    </div>

    <x-slot:footerNote>
        Mail du matin Panora — renouvellement des campagnes annuelles.
    </x-slot:footerNote>

</x-mail.layout>
