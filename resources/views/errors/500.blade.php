@extends('errors.panora')

@section('code', 'Erreur 500')
@section('titre', 'Un problème est survenu')

@section('message')
    <p>Panora n'a pas pu afficher cette page. L'erreur a été enregistrée.</p>
    <p>Réessayez dans un instant. Si elle revient, prévenez l'administrateur en indiquant l'heure et la page concernée.</p>
@endsection

@section('actions')
    <button type="button" class="btn btn-primaire" onclick="location.reload()">Réessayer</button>
    {{-- La page enfant s'exécute avant la mise en page : $connexion n'y existe pas encore. --}}
    <a class="btn btn-secondaire" href="{{ route(request()->is('client', 'client/*') ? 'client.login' : 'login') }}">Retour au tableau de bord</a>
@endsection

@section('note', '')
