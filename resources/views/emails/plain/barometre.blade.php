{{-- Baromètre clients — version texte de emails/barometre (mêmes phrases, mêmes variables). --}}
@php
    $regie   = config('charte.nom', 'CIBLE');
    $edition = config('barometre.edition');
    $limite  = $echeance->copy()->locale('fr')->translatedFormat('j F Y');
    $sig     = $signature;
@endphp
@if($noteTest)
[TEST] {{ $noteTest }}

@endif
Bonjour {{ \App\Support\Salutation::nom($client, $contact) }},

J'espère que vous allez bien.

@if($relance)
Nous nous permettons de revenir vers vous concernant notre Baromètre de Satisfaction Clients {{ $edition }}, pour lequel nous n'avons pas encore reçu votre retour.

Votre avis est essentiel pour nous aider à améliorer nos services, renforcer la qualité de notre accompagnement et mieux répondre à vos attentes.

Il ne vous faudra que {{ $duree }} pour partager votre expérience avec {{ $regie }}.

Donner mon avis : {{ $lien }}

Nous vous remercions par avance pour votre participation et pour la confiance que vous nous accordez.
@else
Dans le cadre de notre démarche d'amélioration continue, nous lançons notre Baromètre de Satisfaction Clients {{ $edition }}, afin de mieux comprendre votre expérience avec {{ $regie }}, vos attentes et vos besoins futurs en matière de communication.

Votre retour est précieux pour nous permettre d'améliorer la qualité de nos services, de renforcer notre accompagnement et de développer des solutions toujours plus adaptées à vos enjeux.

Nous vous invitons à consacrer {{ $duree }} à notre questionnaire :
Participer à l'enquête de satisfaction : {{ $lien }}

Vos réponses contribueront directement à orienter nos priorités d'amélioration et l'évolution de nos offres pour {{ (int) $edition + 1 }}.

Nous vous remercions sincèrement pour votre confiance et le temps que vous nous accorderez.
@endif

Bien cordialement,
@if(!empty($sig['nom']))
{{ $sig['nom'] }}
@if(!empty($sig['fonction'])){{ $sig['fonction'] }}
@endif
{{ $sig['societe'] ?: $regie }}
@else
L'équipe commerciale {{ $regie }}
@endif

--
Ce lien vous est personnel et reste valable jusqu'au {{ $limite }} : le questionnaire s'ouvre déjà au nom de {{ $client->name }}.
@if($lienDesinscription)
Ne plus recevoir nos messages : {{ $lienDesinscription }}
@endif
