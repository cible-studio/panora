@props(['client', 'route' => 'admin.clients.show'])
{{-- Lien vers une fiche client (2026-11-02) : un lien pour les rôles du
     module « clients » (config/acces.php), du texte simple pour les autres —
     le MP n'a plus les pages Clients, un lien le mènerait à une page interdite. --}}
@if($client && \App\Support\Acces::peut('clients'))<a href="{{ route($route, $client) }}" {{ $attributes }}>{{ $slot }}</a>@else<span {{ $attributes->except(['href', 'title']) }}>{{ $slot }}</span>@endif
