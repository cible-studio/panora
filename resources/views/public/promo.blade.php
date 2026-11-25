{{--
    Page d'arrivée d'un mail de promotion (2026-11-25) — PromoController.
    Le clic est déjà compté (Brevo et Panora) quand la page s'affiche. Elle
    propose la réponse préremplie vers le service commercial (avec le format
    choisi dans le mail) et l'appel. Elle tente d'ouvrir la messagerie
    toute seule ; le bouton reste là si le navigateur l'empêche.
    Variables : $promo (config/promos.php), $format, $reponse, $parFormat.
--}}
@extends('public.layout')

@section('title', $promo['titre'] . ' — ' . config('charte.nom', 'CIBLE'))

@push('styles')
<meta name="robots" content="noindex, nofollow">
<style>
    .promo-affiche { display: block; width: 100%; height: auto; border-radius: 10px; margin-bottom: 18px; }
    .promo-titre { font-size: 22px; line-height: 1.3; }
    .promo-titre span { color: var(--accent); }
    .promo-choix { background: var(--bg); border-left: 3px solid var(--accent); border-radius: 8px; padding: 12px 14px; margin: 16px 0; font-size: 14px; }
    .promo-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 6px 0 4px; }
    .promo-actions .btn { font-size: 15px; padding: 13px 20px; }
    .promo-formats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; }
    .promo-format { display: block; text-decoration: none; color: var(--text); background: var(--card); border: 2px solid var(--border); border-radius: 10px; overflow: hidden; text-align: center; transition: border-color .15s; }
    .promo-format:hover, .promo-format.actif { border-color: var(--accent); }
    .promo-format img { display: block; width: 100%; height: auto; }
    .promo-format b { display: block; font-size: 17px; color: var(--accent); padding: 8px 6px 0; }
    .promo-format small { display: block; font-size: 11px; color: var(--text2); padding: 0 6px 10px; line-height: 1.35; }
    .promo-slogan { text-align: center; font-weight: 700; font-size: 15px; margin-top: 18px; }
    @media (max-width: 480px) { .promo-actions .btn { width: 100%; text-align: center; } .promo-format b { font-size: 15px; } }
</style>
@endpush

@section('content')
@php
    $tel = 'tel:+225' . preg_replace('/\D/', '', $promo['telephone']);
    $libelle = $format ? $promo['formats'][$format]['libelle'] : null;
@endphp
<div class="card">
    <img class="promo-affiche" src="{{ asset($promo['affiche']) }}" alt="{{ $promo['titre'] }}">

    @if(!($promo['active'] ?? true))
        <h1>Cette offre est terminée</h1>
        <p class="muted">Merci de votre intérêt. Notre service commercial reste à votre disposition pour vous proposer nos panneaux disponibles.</p>
        <div class="promo-actions" style="margin-top:14px;">
            <a class="btn btn-primary" href="mailto:{{ $promo['email'] }}">✉ Écrire au service commercial</a>
            <a class="btn btn-ghost" href="{{ $tel }}">📞 {{ $promo['telephone'] }}</a>
        </div>
    @else
        <h1 class="promo-titre">{{ $promo['titre'] }}</h1>
        <p class="muted">{{ $promo['accroche'] }}</p>

        <div class="promo-choix">
            @if($libelle)
                Vous avez choisi le <strong>{{ $libelle }}</strong>. Votre demande est déjà rédigée : il ne reste qu'à l'envoyer.
            @else
                Votre demande est déjà rédigée : il ne reste qu'à l'envoyer.
            @endif
        </div>

        <div class="promo-actions">
            <a class="btn btn-primary" id="promo-reponse" href="{{ $reponse }}">✉ Écrire au service commercial</a>
            <a class="btn btn-ghost" href="{{ $tel }}">📞 Appeler le {{ $promo['telephone'] }}</a>
        </div>
        <p class="small" style="margin-top:8px;">Si votre messagerie ne s'ouvre pas, écrivez à {{ $promo['email'] }} en précisant le format, la ville et la période. Notre équipe vous confirme les disponibilités sous 1&nbsp;heure ouvrée.</p>

        <h2>Choisissez votre format</h2>
        <div class="promo-formats">
            @foreach($parFormat as $cle => $f)
                <a class="promo-format {{ $cle === $format ? 'actif' : '' }}" href="{{ $f['reponse'] }}">
                    <img src="{{ asset($f['image']) }}" alt="Panneau {{ $f['libelle'] }}">
                    <b>{{ $f['libelle'] }}</b>
                    <small>{{ $f['detail'] }}</small>
                </a>
            @endforeach
        </div>

        @if(!empty($promo['slogan']))<p class="promo-slogan">{{ $promo['slogan'] }}</p>@endif
    @endif
</div>

@if(!empty($jeton))
<script>
    // 25/11 : la page s'est affichée dans un navigateur, donc un client l'a
    // ouverte. Les filtres de sécurité qui testent les liens sans rien
    // afficher ne passent pas ici : leurs clics restent hors du bilan.
    (function () {
        var donnees = new FormData();
        donnees.append('jeton', @json($jeton));
        var url = @json(route('promo.vu'));
        if (!(navigator.sendBeacon && navigator.sendBeacon(url, donnees))) {
            fetch(url, { method: 'POST', body: donnees, keepalive: true, credentials: 'same-origin' }).catch(function () {});
        }
    })();
</script>
@endif
@if($promo['active'] ?? true)
<script>
    // Ouvre la réponse préremplie, comme le faisait l'ancien lien du mail.
    // Si le navigateur l'empêche, le bouton ci-dessus fait la même chose.
    setTimeout(function () { window.location.href = @json($reponse); }, 700);
</script>
@endif
@endsection
