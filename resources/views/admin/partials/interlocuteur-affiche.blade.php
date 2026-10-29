{{--
    Interlocuteur d'un dossier, sous le nom du client (fiches campagne,
    réservation, devis — 2026-10-29). Sans interlocuteur alors que le client
    en a : badge « à préciser » (ses mails partent aux contacts cochés).

    Paramètres : $dossier (Campaign, Reservation ou Quote), $lienModifier (optionnel)
--}}
@php
    $interlocuteur = $dossier->interlocuteur;
    $aDesContacts = !$interlocuteur && $dossier->client_id
        && \App\Models\ClientContact::where('client_id', $dossier->client_id)->exists();
@endphp
@if($interlocuteur)
    <div style="margin-top:6px;font-size:12px;color:var(--text2)" title="Interlocuteur du dossier : il reçoit les mails de suivi, et les documents en plus de l'adresse de la fiche.">
        🗣 <strong style="color:var(--text)">{{ $interlocuteur->name }}</strong>{{ $interlocuteur->position ? ' — ' . $interlocuteur->position : '' }}
        @if($interlocuteur->email)<div style="font-size:11.5px;color:var(--text3)">{{ $interlocuteur->email }}</div>@endif
    </div>
@elseif($aDesContacts)
    <div style="margin-top:6px">
        <span title="Aucun interlocuteur choisi : les mails de suivi partent aux contacts cochés « reçoit les mails » du client."
              style="cursor:help;font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:9px;background:rgba(245,158,11,.12);color:#d97706">Interlocuteur à préciser</span>
        @if(!empty($lienModifier))
            <a href="{{ $lienModifier }}" style="font-size:11px;color:var(--accent);margin-left:6px">Choisir</a>
        @endif
    </div>
@endif
