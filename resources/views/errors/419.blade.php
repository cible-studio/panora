{{-- Rarement affichée : bootstrap/app.php renvoie d'abord vers la connexion ou la page d'avant. --}}
@extends('errors.panora')

@section('code', 'Session expirée')
@section('titre', 'Votre session a expiré')

@section('message')
    <p>Pour votre sécurité, Panora vous déconnecte après une période d'inactivité, et lors de certaines mises à jour.</p>
    <p>Reconnectez-vous, puis refaites votre dernière action.</p>
@endsection
