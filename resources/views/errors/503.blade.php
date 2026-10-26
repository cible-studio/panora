@extends('errors.panora')

@section('entete')
    <meta http-equiv="refresh" content="20">
@endsection

@section('code', 'Mise à jour')
@section('titre', 'Panora est en cours de mise à jour')

@section('message')
    <p>Cela ne prend généralement qu'une minute. La page se rechargera toute seule.</p>
@endsection

@section('actions')
    <button type="button" class="btn btn-primaire" onclick="location.reload()">Recharger maintenant</button>
@endsection

@section('note', '')
