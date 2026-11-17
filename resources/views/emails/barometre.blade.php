{{--
    Baromètre clients — invitation et relance.
    Texte de la direction reçu le 2026-11-17 (Josiane). Objets : BarometreMail::sujet().
    Version texte : emails/plain/barometre (mêmes phrases, à tenir ensemble).

    Envoyé par App\Mail\BarometreMail (Brevo). Variables :
      $client             App\Models\Client (nom de l'entreprise)
      $contact            ?string — interlocuteur retenu par
                          Client::destinataireBarometre() (règle du 17/11)
      $lien               lien personnel sécurisé (expire à la date limite)
      $echeance           date limite de réponse (Carbon)
      $relance            bool — false = premier envoi, true = relance
      $lienDesinscription ?string — « Ne plus recevoir nos messages »
      $noteTest           ?string — bandeau des mails de test
      $duree              « 5 minutes » (config barometre.duree)

    Salutation : règle de la direction (App\Support\Salutation) —
    « Bonjour Awa Koné (ORANGE CI), ».
--}}
@php
    $regie    = config('charte.nom', 'CIBLE');
    $edition  = config('barometre.edition');
    $salut    = \App\Support\Salutation::nom($client, $contact);
    // Toujours en français, quelle que soit la langue du serveur.
    $limite   = $echeance->copy()->locale('fr')->translatedFormat('j F Y');
    $sig      = $signature;
@endphp
<x-mail.layout
    :title="$relance ? 'Rappel | Votre avis compte pour ' . $regie : 'Votre avis compte pour ' . $regie"
    :preheader="$relance
        ? 'Notre Baromètre de Satisfaction Clients ' . $edition . ' : il ne vous faudra que ' . $duree . '.'
        : 'Baromètre de Satisfaction Clients ' . $edition . ' : ' . $duree . ' pour nous aider à progresser.'">

    @if($noteTest)
        <div class="alert">
            <strong>Mail de test.</strong> {{ $noteTest }}
        </div>
    @endif

    <h1>Bonjour {{ $salut }},</h1>

    <p>J'espère que vous allez bien.</p>

    @if($relance)
        <p>
            Nous nous permettons de revenir vers vous concernant notre <strong>Baromètre de Satisfaction Clients {{ $edition }}</strong>,
            pour lequel nous n'avons pas encore reçu votre retour.
        </p>
        <p>
            Votre avis est essentiel pour nous aider à améliorer nos services, renforcer la qualité de notre accompagnement
            et mieux répondre à vos attentes.
        </p>
        <p>Il ne vous faudra que {{ $duree }} pour partager votre expérience avec {{ $regie }}.</p>
    @else
        <p>
            Dans le cadre de notre démarche d'amélioration continue, nous lançons notre
            <strong>Baromètre de Satisfaction Clients {{ $edition }}</strong>, afin de mieux comprendre votre expérience
            avec {{ $regie }}, vos attentes et vos besoins futurs en matière de communication.
        </p>
        <p>
            Votre retour est précieux pour nous permettre d'améliorer la qualité de nos services, de renforcer notre
            accompagnement et de développer des solutions toujours plus adaptées à vos enjeux.
        </p>
        <p>Nous vous invitons à consacrer {{ $duree }} à notre questionnaire :</p>
    @endif

    <div class="cta-wrap">
        <a href="{{ $lien }}" class="cta">👉 {{ $relance ? 'DONNER MON AVIS' : 'PARTICIPER À L\'ENQUÊTE DE SATISFACTION' }}</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $lien }}">{{ $lien }}</a>
        </div>
    </div>

    @if($relance)
        <p>Nous vous remercions par avance pour votre participation et pour la confiance que vous nous accordez.</p>
    @else
        <p>
            Vos réponses contribueront directement à orienter nos priorités d'amélioration et l'évolution de nos offres
            pour {{ (int) $edition + 1 }}.
        </p>
        <p>Nous vous remercions sincèrement pour votre confiance et le temps que vous nous accorderez.</p>
    @endif

    <p>
        Bien cordialement,<br>
        @if(!empty($sig['nom']))
            <strong>{{ $sig['nom'] }}</strong><br>
            @if(!empty($sig['fonction'])){{ $sig['fonction'] }}<br>@endif
            {{ $sig['societe'] ?: $regie }}
        @else
            <strong>L'équipe commerciale {{ $regie }}</strong>
        @endif
    </p>

    <x-slot:footerNote>
        Ce lien vous est personnel et reste valable jusqu'au {{ $limite }} : le questionnaire s'ouvre déjà au nom de {{ $client->name }}.
        @if($lienDesinscription)
            <br><a href="{{ $lienDesinscription }}" style="color:inherit;">Ne plus recevoir nos messages</a>
        @endif
    </x-slot:footerNote>

</x-mail.layout>
