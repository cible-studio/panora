@extends('public.layout')
@section('title', 'Lien indisponible — CIBLE CI')
@section('content')
<div class="card invalid-state">
    <div class="icon">🔒</div>
    <h1>Lien indisponible</h1>
    <p class="muted" style="margin-top:8px;max-width:420px;margin-left:auto;margin-right:auto">
        {{ $reason ?? 'Ce lien n\'est plus accessible. Il a peut-être expiré ou été révoqué.' }}
    </p>
    <p class="small" style="margin-top:16px">
        Si vous pensez qu'il s'agit d'une erreur, contactez votre commercial chez CIBLE CI.
    </p>
</div>
@endsection
