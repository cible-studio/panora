@extends('errors.panora')

@section('code', 'Erreur 404')
@section('titre', 'Page introuvable')

@section('message')
    <p>Cette adresse n'existe pas, ou plus.</p>
    <p>Si vous veniez de cliquer dans Panora, l'application vient peut-être d'être mise à jour, ou votre session a expiré. Reconnectez-vous pour continuer.</p>
@endsection

{{-- Lien reçu par un client (catalogue, proposition…) : voir errors/panora.blade.php. --}}
@section('message-client')
    <p>Ce lien n'est plus valable, ou le document est en cours de mise à jour.</p>
    <p>Réessayez dans quelques minutes. Si le problème continue, écrivez-nous : nous vous renvoyons la version à jour.</p>
@endsection
