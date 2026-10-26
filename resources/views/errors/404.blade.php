@extends('errors.panora')

@section('code', 'Erreur 404')
@section('titre', 'Page introuvable')

@section('message')
    <p>Cette adresse n'existe pas, ou plus.</p>
    <p>Si vous veniez de cliquer dans Panora, l'application vient peut-être d'être mise à jour, ou votre session a expiré. Reconnectez-vous pour continuer.</p>
@endsection
