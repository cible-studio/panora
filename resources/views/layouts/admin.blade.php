<!DOCTYPE html>
<html lang="fr" id="html-root">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.cache-fallback')
    <title>Panora — {{ $title ?? 'Dashboard' }}</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">

    <script>
        (function () {
            var theme = localStorage.getItem('theme') || 'light';
            document.getElementById('html-root').setAttribute('data-theme', theme);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .nav-icon { width: 18px; height: 18px; flex-shrink: 0; }
        [x-cloak] { display: none !important; }
    </style>

    {{-- Stack pour les CSS poussés par les vues filles (@push('styles')).
         Sans ça, toutes les règles dans @push('styles') ... @endpush sont
         silencieusement ignorées — c'était le cas pour _form-fne.blade.php
         (Select2 + refonte UI Create/Edit) qui rendait la page sans aucun
         style appliqué. --}}
    @stack('styles')
</head>

<body>
    <div class="app-layout"
         x-data="{ sidebarOpen: false }"
         x-effect="document.documentElement.classList.toggle('sidebar-locked', sidebarOpen)"
         @keydown.escape.window="sidebarOpen = false">

        {{-- Backdrop overlay (mobile uniquement, visible quand sidebar ouverte) --}}
        <div class="sidebar-backdrop" x-show="sidebarOpen" @click="sidebarOpen = false"
             x-transition.opacity.duration.200ms x-cloak></div>

        {{-- ══ SIDEBAR ══ --}}
        <aside class="sidebar" id="main-sidebar" :class="{ 'is-open': sidebarOpen }">
            <div class="sidebar-logo">
                <div class="sidebar-logo-mark">
                    <img id="logo-dark"  class="w-40" src="{{ asset('images/panorab.png') }}" alt="Logo Panora">
                    <img id="logo-light" class="w-40 " src="{{ asset('images/panora.png') }}" alt="Logo Panora" style="display:none;">
                </div>
            </div>

            <div class="role-pill">⚡ <span class="nav-text">{{ Auth::user()?->role?->value ?? 'admin' }}</span></div>

            <nav class="sidebar-nav" @click="sidebarOpen = false">
                <div class="nav-section">
                    <div class="nav-label">Principal</div>
                    <a href="{{ route('dashboard') }}" data-tooltip="Tableau de bord" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
                        <span class="nav-text">Tableau de bord</span>
                    </a>
                    @php $u = auth()->user(); $isAdmin = $u?->role?->value === 'admin'; $isMP = $u?->role?->value === 'mediaplanner'; $isCom = $u?->role?->value === 'commercial'; @endphp
                    {{-- Disponibilités : visible aux 3 rôles staff (admin, MP, commercial)
                         depuis l'ouverture aux commerciaux pour faire des réservations. --}}
                    <a href="{{ route('admin.reservations.disponibilites') }}" data-tooltip="Disponibilités" class="nav-item {{ request()->routeIs('admin.reservations.disponibilites') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg></span>
                        <span class="nav-text">Disponibilités</span>
                    </a>
                    <a href="{{ route('admin.panels.index') }}" data-tooltip="Inventaire" class="nav-item {{ request()->routeIs('admin.panels.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
                        <span class="nav-text">Inventaire</span>
                    </a>
                    <a href="{{ route('admin.campaigns.index') }}" data-tooltip="Campagnes" class="nav-item {{ request()->routeIs('admin.campaigns.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#81358a" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></span>
                        <span class="nav-text">Campagnes</span>
                    </a>
                    <a href="{{ route('admin.clients.index') }}" data-tooltip="Clients" class="nav-item {{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#3aa835" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                        <span class="nav-text">Clients</span>
                    </a>
                    <a href="{{ route('admin.external-agencies.index') }}" data-tooltip="Régies externes" class="nav-item {{ request()->routeIs('admin.external-agencies.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                        <span class="nav-text">Régies externes</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-label">Opérations</div>
                    <a href="{{ route('admin.reservations.index') }}" data-tooltip="Réservations" class="nav-item {{ request()->routeIs('admin.reservations.*') && !request()->routeIs('admin.reservations.disponibilites') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#3aa835" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></span>
                        <span class="nav-text">Réservations</span>
                    </a>
                    @if($isAdmin || $isMP)
                    <a href="{{ route('admin.pose-tasks.index') }}" data-tooltip="Gestion Pose OOH" class="nav-item {{ request()->routeIs('admin.pose-tasks.*') && !request()->routeIs('admin.pose-tasks.sla') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg></span>
                        <span class="nav-text">Gestion Pose OOH</span>
                    </a>
                    <a href="{{ route('admin.piges.index') }}" data-tooltip="Piges Photos" class="nav-item {{ request()->routeIs('admin.piges.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>
                        <span class="nav-text">Piges Photos</span>
                    </a>
                    {{-- M2 Performance Tech : gestion des équipes (mission 2026-06-17) --}}
                    <a href="{{ route('admin.teams.index') }}" data-tooltip="Équipes de pose" class="nav-item {{ request()->routeIs('admin.teams.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                        <span class="nav-text">Équipes de pose</span>
                    </a>
                    @endif
                    @if($isAdmin || $isMP)
                    <a href="{{ route('admin.taxes.index') }}" data-tooltip="Taxes Communes" class="nav-item {{ request()->routeIs('admin.taxes.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#81358a" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                        <span class="nav-text">Taxes Communes</span>
                    </a>
                    @endif
                    @if(Auth::user()?->role === \App\Enums\UserRole::ADMIN)
                    <a href="{{ route('admin.invoices.index') }}" data-tooltip="Facturation" class="nav-item {{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#3aa835" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                        <span class="nav-text">Facturation</span>
                    </a>
                    @endif
                    @php
                        // Badge messages non traités (status=new). Cache 60s pour
                        // ne pas re-query à chaque rendu de page admin.
                        $newMessagesCount = \Illuminate\Support\Facades\Cache::remember(
                            'admin.messages.new_count',
                            60,
                            fn() => \App\Models\ClientMessage::where('status', 'new')->count()
                        );
                    @endphp
                    <a href="{{ route('admin.messages.index') }}" data-tooltip="Messages clients" class="nav-item {{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                        <span class="nav-text">Messages</span>
                        @if($newMessagesCount > 0)
                            <span class="nav-badge red">{{ $newMessagesCount > 99 ? '99+' : $newMessagesCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.alerts.index') }}" data-tooltip="Alertes" class="nav-item {{ request()->routeIs('admin.alerts.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
                        <span class="nav-text">Alertes</span>
                    </a>
                    @if($isAdmin || $isMP)
                    @php
                        // Cache 60s : count des conflits actifs (double-bookings BD).
                        // Coût négligeable mais évite de refaire la query à chaque
                        // page admin. Affiché en badge rouge si > 0.
                        //
                        // Auto-correction : sur la page Conflits elle-même, on
                        // bypass le cache et on l'écrase avec la valeur fraîche.
                        // Ça évite les badges stales quand des conflits ont été
                        // résolus en dehors du endpoint resolve() (suppression
                        // manuelle de panneau côté campagne, nettoyage DB...).
                        if (request()->routeIs('admin.conflicts.*')) {
                            $conflictCount = app(\App\Services\AvailabilityService::class)->countActiveConflicts();
                            \Illuminate\Support\Facades\Cache::put('admin.conflicts.count', $conflictCount, 60);
                        } else {
                            $conflictCount = \Illuminate\Support\Facades\Cache::remember(
                                'admin.conflicts.count',
                                60,
                                fn() => app(\App\Services\AvailabilityService::class)->countActiveConflicts()
                            );
                        }
                    @endphp
                    <a href="{{ route('admin.conflicts.index') }}" data-tooltip="Conflits" class="nav-item {{ request()->routeIs('admin.conflicts.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                        <span class="nav-text">Conflits</span>
                        @if($conflictCount > 0)
                            <span class="nav-badge red">{{ $conflictCount }}</span>
                        @endif
                    </a>

                    @php
                        // Signalements terrain à traiter : même pattern auto-correctif
                        // que Conflits — sur la page Signalements, valeur fraîche +
                        // mise à jour du cache pour les autres pages.
                        if (request()->routeIs('admin.signalements.*')) {
                            $signalementsCount = \App\Models\PoseTaskAction::where('action', 'problem_reported')
                                ->whereNull('resolved_at')->count();
                            \Illuminate\Support\Facades\Cache::put('admin.signalements.pending_count', $signalementsCount, 60);
                        } else {
                            $signalementsCount = \Illuminate\Support\Facades\Cache::remember(
                                'admin.signalements.pending_count',
                                60,
                                fn() => \App\Models\PoseTaskAction::where('action', 'problem_reported')
                                    ->whereNull('resolved_at')->count()
                            );
                        }
                    @endphp
                    <a href="{{ route('admin.signalements.index') }}" data-tooltip="Signalements terrain"
                       id="nav-signalements"
                       class="nav-item {{ request()->routeIs('admin.signalements.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg></span>
                        <span class="nav-text">Signalements</span>
                        {{-- Badge mis à jour live par le heartbeat (toutes les 20s).
                             On le rend toujours dans le DOM (display:none si 0) pour
                             que le JS n'ait pas à le créer dynamiquement. --}}
                        <span class="nav-badge red"
                              id="nav-signalements-badge"
                              data-count="{{ $signalementsCount }}"
                              style="{{ $signalementsCount > 0 ? '' : 'display:none' }}">
                            {{ $signalementsCount }}
                        </span>
                    </a>
                    @endif
                </div>

                <div class="nav-section">
                    <div class="nav-label">Analyse</div>
                    <a href="{{ route('admin.map') }}" data-tooltip="Carte & Heatmap" class="nav-item {{ request()->routeIs('admin.map*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg></span>
                        <span class="nav-text">Carte &amp; Heatmap</span>
                    </a>
                    <a href="{{ route('admin.rapports.index') }}" data-tooltip="Rapports" class="nav-item {{ request()->routeIs('admin.rapports.index') || request()->routeIs('admin.rapports.annulations') || request()->routeIs('admin.rapports.campagnes') || request()->routeIs('admin.rapports.taxes') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#81358a" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                        <span class="nav-text">Rapports</span>
                    </a>
                    @if($isAdmin || $isMP)
                    <a href="{{ route('admin.pose-tasks.sla') }}" data-tooltip="SLA — KPI poses/piges" class="nav-item {{ request()->routeIs('admin.pose-tasks.sla') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                        <span class="nav-text">SLA</span>
                    </a>
                    {{-- M3 SLA enrichi : page analytique motifs de retard (mission 2026-06-17) --}}
                    <a href="{{ route('admin.sla.retards.index') }}" data-tooltip="SLA & Retards — Motifs" class="nav-item {{ request()->routeIs('admin.sla.retards*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                        <span class="nav-text">SLA & Retards</span>
                    </a>
                    @endif

                    {{-- M1 Performance Commerciale (mission 2026-06-17) — admin + MP + commercial.
                         Le commercial voit son propre drill (le service force le scope au self). --}}
                    @if($isAdmin || $isMP || ($isCommercial ?? auth()->user()?->role?->value === 'commercial'))
                    <a href="{{ ($isCommercial ?? auth()->user()?->role?->value === 'commercial') ? route('admin.performance.commercial.me') : route('admin.performance.commercial.index') }}"
                       data-tooltip="Performance commerciale" class="nav-item {{ request()->routeIs('admin.performance.commercial.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
                        <span class="nav-text">{{ ($isCommercial ?? auth()->user()?->role?->value === 'commercial') ? 'Ma performance' : 'Performance commerciale' }}</span>
                    </a>
                    @endif

                    {{-- M2 Performance Technicien (mission 2026-06-17) — admin + MP + technique.
                         Le tech voit son propre drill, idem M1. --}}
                    @php $isTech = auth()->user()?->role?->value === 'technique'; @endphp
                    @if($isAdmin || $isMP || $isTech)
                    <a href="{{ $isTech ? route('admin.performance.tech.me') : route('admin.performance.tech.index') }}"
                       data-tooltip="Performance techniciens" class="nav-item {{ request()->routeIs('admin.performance.tech.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>
                        <span class="nav-text">{{ $isTech ? 'Ma performance' : 'Performance techniciens' }}</span>
                    </a>
                    @endif

                    {{-- Tableau de bord FINANCIER — Admin + Commercial.
                         Cohérent avec la matrice facturation : MP/Technique
                         ne touchent pas aux finances. --}}
                    @if($isAdmin || ($isCommercial ?? auth()->user()?->role?->value === 'commercial'))
                    <a href="{{ route('admin.finance.index') }}" data-tooltip="Tableau de bord financier" class="nav-item {{ request()->routeIs('admin.finance.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                        <span class="nav-text">Finance</span>
                    </a>
                    @endif
                </div>

                {{-- Maintenance : visible pour MP + Admin (hors section
                     Administration qui est admin-only). --}}
                @if($isAdmin || $isMP)
                <div class="nav-section">
                    <div class="nav-label">Terrain</div>
                    <a href="{{ route('admin.maintenances.index') }}" data-tooltip="Maintenance" class="nav-item {{ request()->routeIs('admin.maintenances.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>
                        <span class="nav-text">Maintenance</span>
                    </a>
                </div>
                @endif

                @if($isAdmin)
                <div class="nav-section">
                    <div class="nav-label">Administration</div>
                    <a href="{{ route('admin.users.index') }}" data-tooltip="Utilisateurs" class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                        <span class="nav-text">Utilisateurs</span>
                    </a>
                    <a href="{{ route('admin.audit.logs') }}" data-tooltip="Logs d'audit" class="nav-item {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>
                        <span class="nav-text">Logs d'audit</span>
                    </a>
                    <a href="{{ route('admin.settings.index') }}" data-tooltip="Paramètres" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <span class="icon"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#3aa835" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                        <span class="nav-text">Paramètres</span>
                    </a>
                </div>
                @endif {{-- /admin only section --}}
            </nav>

            <div class="sidebar-footer">
                <div class="user-card" data-tooltip="{{ Auth::user()?->name ?? 'Admin' }}">
                    <div class="user-avatar">{{ strtoupper(substr(Auth::user()?->name ?? 'A', 0, 1)) }}</div>
                    <div class="user-card-info">
                        <div class="user-name">{{ Auth::user()?->name ?? 'Admin' }}</div>
                        <div class="user-role">{{ Auth::user()?->role?->value ?? 'admin' }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="user-logout">
                        @csrf
                        <button type="submit" title="Déconnexion" aria-label="Se déconnecter">⏻</button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Barre couleurs Panora (purement décorative — masquée < tablette) -->
        <div class="brand-bar" aria-hidden="true">
            <div class="brand red"></div>
            <div class="brand yellow"></div>
            <div class="brand green"></div>
            <div class="brand purple"></div>
            <div class="brand blue"></div>
        </div>

        {{-- ══ CONTENU ══ --}}
        <div class="main-area">
            <header class="topbar topbar-fixed">
                {{-- Hamburger : visible uniquement < 768px --}}
                <button type="button" class="sidebar-toggle" @click="sidebarOpen = !sidebarOpen"
                        :aria-expanded="sidebarOpen" aria-label="Ouvrir le menu" aria-controls="main-sidebar">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x-show="!sidebarOpen">
                        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x-show="sidebarOpen" x-cloak>
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>

                @if(!empty($topbarLeft))
                <div class="topbar-left">{{ $topbarLeft }}</div>
                @endif
                <div class="topbar-title">{{ $title ?? 'Dashboard' }}</div>

                <div class="topbar-actions">
                    <label class="theme-switch" title="Changer de thème">
                        <input type="checkbox" id="theme-toggle" onchange="toggleThemeSwitch()">
                        <span class="slider"></span>
                    </label>
                    @php
                        // Source unique de vérité pour le badge cloche : on
                        // demande au service le count exact (non lues, actives).
                        // Évite les écarts entre rendu HTML initial et polling JS.
                        $unreadCount = app(\App\Services\AlertService::class)->unreadCount();
                    @endphp
                    <a href="{{ route('admin.alerts.index') }}" class="btn btn-ghost btn-sm" style="position:relative;" title="Alertes non lues">
                        🔔
                        <span id="alert-badge" class="nav-badge red" style="position:relative;{{ $unreadCount === 0 ? 'display:none;' : '' }}">
                            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                        </span>
                    </a>
                    {{ $topbarActions ?? '' }}
                </div>
            </header>

            <div class="topbar-spacer" aria-hidden="true" style="height:8px;"></div>

            <div class="flash-zone">
                @if (session('success'))
                    <div class="flash flash-success">✓ {{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="flash flash-error">✕ {{ session('error') }}</div>
                @endif
                @if (session('warning'))
                    <div class="flash flash-warning">⚠ {{ session('warning') }}</div>
                @endif
                @if (session('info'))
                    <div class="flash flash-info">ℹ {{ session('info') }}</div>
                @endif
            </div>

            <div class="page-content">{{ $slot }}</div>

            {{-- Footer global admin — conformité affichée discrète mais
                 systématique sur toutes les pages connectées. --}}
            <footer style="padding:18px 24px 22px;text-align:center;color:#9ca3af;font-size:11px;letter-spacing:.3px;border-top:1px solid var(--border);margin-top:24px">
                🔒 Données hébergées et sécurisées — Conformité RGPD
                <span style="opacity:.5;margin:0 6px">·</span>
                Panora © {{ date('Y') }} CIBLE CI
            </footer>
        </div>
    </div>

    {{-- TOAST container --}}
    <div id="toast-container" style="position:fixed;top:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;max-width:380px;"></div>

    {{-- LIGHTBOX global --}}
    <div id="lightbox-overlay"
         onclick="closeLightbox()"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:999999;
                align-items:center;justify-content:center;cursor:zoom-out;">
        <img id="lightbox-img" src="" alt=""
             style="max-width:92vw;max-height:92vh;border-radius:10px;
                    box-shadow:0 20px 80px rgba(0,0,0,.8);object-fit:contain;">
        <button onclick="closeLightbox()" title="Fermer"
                style="position:absolute;top:18px;right:22px;background:rgba(255,255,255,.12);
                       border:none;color:#fff;font-size:26px;line-height:1;width:44px;height:44px;
                       border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
    </div>
    <script>
    function openLightbox(src) {
        document.getElementById('lightbox-img').src = src;
        const ol = document.getElementById('lightbox-overlay');
        ol.style.display = 'flex';
        document.addEventListener('keydown', _lbKey);
    }
    function closeLightbox() {
        document.getElementById('lightbox-overlay').style.display = 'none';
        document.getElementById('lightbox-img').src = '';
        document.removeEventListener('keydown', _lbKey);
    }
    function _lbKey(e) { if (e.key === 'Escape') closeLightbox(); }

    // ════════════════════════════════════════════════════════════════
    // Bouton Retour universel : intercepte les liens dans .topbar-left
    // dont le texte contient "Retour" pour faire history.back() si on a
    // un referrer interne. Sinon on laisse la navigation normale vers
    // l'URL hardcodée (fallback fiable pour les ouvertures en direct).
    // ════════════════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        const link = e.target.closest('.topbar-left a');
        if (!link) return;
        if (!/retour/i.test(link.textContent || '')) return;
        // referrer interne et différent de l'URL actuelle = on peut revenir
        const ref = document.referrer;
        if (ref && ref !== location.href && ref.startsWith(location.origin)) {
            e.preventDefault();
            history.back();
        }
        // Sinon : on laisse l'<a> naviguer vers son href (fallback)
    });
    </script>

    <style>
        .toast { min-width:300px;max-width:380px;padding:14px 16px;border-radius:12px;font-size:13px;font-weight:500;display:flex;align-items:flex-start;gap:12px;pointer-events:all;cursor:pointer;box-shadow:0 8px 32px rgba(0,0,0,.5);animation:toastIn .3s cubic-bezier(0.34,1.56,0.64,1);line-height:1.5;border:1px solid transparent;backdrop-filter:blur(8px); }
        .toast.success { background:rgba(5,46,22,0.95);border-color:#166534;color:#4ade80; }
        .toast.error   { background:rgba(28,10,10,0.95);border-color:#7f1d1d;color:#f87171; }
        .toast.warning { background:rgba(28,18,0,0.95);border-color:#78350f;color:#fbbf24; }
        .toast.info    { background:rgba(12,26,46,0.95);border-color:#1e3a5f;color:#60a5fa; }
        .toast-icon-wrap { width:32px;height:32px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:16px; }
        .toast.success .toast-icon-wrap { background:rgba(74,222,128,0.15); }
        .toast.error   .toast-icon-wrap { background:rgba(248,113,113,0.15); }
        .toast.warning .toast-icon-wrap { background:rgba(251,191,36,0.15); }
        .toast.info    .toast-icon-wrap { background:rgba(96,165,250,0.15); }
        .toast-body    { flex:1;min-width:0; }
        .toast-label   { font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:1px;margin-bottom:2px; }
        .toast-msg-text { font-size:12px;opacity:.8;line-height:1.4; }
        .toast-close   { margin-left:4px;font-size:14px;opacity:.4;cursor:pointer;background:none;border:none;color:inherit;padding:0;flex-shrink:0;align-self:flex-start;margin-top:2px; }
        .toast-close:hover { opacity:1; }
        @keyframes toastIn  { from{opacity:0;transform:translateX(24px) scale(.95)} to{opacity:1;transform:translateX(0) scale(1)} }
        @keyframes toastOut { from{opacity:1;transform:translateX(0);max-height:120px} to{opacity:0;transform:translateX(24px);max-height:0;padding:0} }
    </style>

    {{-- Toast JS — défini AVANT les appels session --}}
    <script>
        const TOAST_ICONS  = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️' };
        const TOAST_LABELS = { success:'Succès', error:'Erreur', warning:'Avertissement', info:'Information' };

        function showToast(type, message, duration, title) {
            duration = duration || (type === 'error' ? 7000 : 5000);
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            const label = title || TOAST_LABELS[type] || 'Notification';
            const icon  = TOAST_ICONS[type] || 'ℹ️';
            toast.innerHTML =
                `<div class="toast-icon-wrap">${icon}</div>` +
                `<div class="toast-body"><div class="toast-label">${label}</div><div class="toast-msg-text">${message}</div></div>` +
                `<button class="toast-close" onclick="dismissToast(this.parentElement);event.stopPropagation()">✕</button>`;
            toast.onclick = () => dismissToast(toast);
            container.appendChild(toast);
            toast._timeout = setTimeout(() => dismissToast(toast), duration);
        }

        function dismissToast(toast) {
            if (!toast || !toast.parentElement) return;
            clearTimeout(toast._timeout);
            toast.style.animation = 'toastOut .25s ease forwards';
            setTimeout(() => toast.remove(), 250);
        }

        window.Toast = {
            success: (m, d) => showToast('success', m, d),
            error:   (m, d) => showToast('error',   m, d),
            warning: (m, d) => showToast('warning', m, d),
            info:    (m, d) => showToast('info',    m, d),
        };
    </script>

    {{-- Toasts session — APRÈS showToast --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if (session('success')) showToast('success', @json(session('success'))); @endif
            @if (session('error'))   showToast('error',   @json(session('error')));   @endif
            @if (session('warning')) showToast('warning', @json(session('warning'))); @endif
            @if (session('info'))    showToast('info',    @json(session('info')));    @endif

            {{-- Synchroniser les logos --}}
            applyLogoForTheme(document.getElementById('html-root').getAttribute('data-theme') || 'light');
        });
    </script>

    {{-- Thème + Alertes --}}
    <script>
        function toggleThemeSwitch() {
            const current = document.getElementById('html-root').getAttribute('data-theme') || 'light';
            const theme = current === 'light' ? 'dark' : 'light';
            document.getElementById('html-root').setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            applyLogoForTheme(theme);
        }

        function applyLogoForTheme(theme) {
            const dl = document.getElementById('logo-dark');
            const ll = document.getElementById('logo-light');
            if (dl) dl.style.display = theme === 'dark'  ? 'block' : 'none';
            if (ll) ll.style.display = theme === 'light' ? 'block' : 'none';
        }

        // ── ALERTES temps réel ──────────────────────────────────────
        let _shownAlertIds = new Set(JSON.parse(localStorage.getItem('shownAlertIds') || '[]'));
        if (_shownAlertIds.size > 200) {
            const arr = [..._shownAlertIds].slice(-100);
            _shownAlertIds = new Set(arr);
            localStorage.setItem('shownAlertIds', JSON.stringify(arr));
        }

        // Polling unifié — UNE source pour le badge (count exact), UNE
        // source pour les toasts (latest). On les sépare pour éviter le
        // bug historique "badge = alerts.length limité à 5" alors que la BD
        // a des dizaines d'alertes non lues.
        async function refreshAlertBadge() {
            const badge = document.getElementById('alert-badge');
            if (!badge) return;

            // Sur /admin/alerts, mark-all-as-read est fait côté serveur à
            // l'ouverture → le badge reste à 0 tant qu'on est sur la page.
            if (window.location.pathname.includes('/admin/alerts')) {
                badge.style.display = 'none';
                return;
            }

            try {
                const res = await fetch('/api/alerts/count', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const { count } = await res.json();
                badge.textContent   = count > 99 ? '99+' : String(count);
                badge.style.display = count > 0 ? 'inline-block' : 'none';
            } catch (e) {}
        }

        async function pollLatestForToasts() {
            // Pas de toasts sur la page alerts (l'utilisateur les voit déjà)
            if (window.location.pathname.includes('/admin/alerts')) return;

            try {
                const res = await fetch('/api/alerts/latest?limit=8', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const alerts = await res.json();

                alerts.forEach(alert => {
                    if (_shownAlertIds.has(String(alert.id))) return;
                    _shownAlertIds.add(String(alert.id));
                    localStorage.setItem('shownAlertIds', JSON.stringify([..._shownAlertIds]));

                    const level = alert.niveau === 'danger'  ? 'error'   :
                                  alert.niveau === 'warning' ? 'warning' : 'info';
                    const groupLabel = alert.meta?.group || 'Alerte';
                    const link = alert.lien || '/admin/alerts';

                    showToast(
                        level,
                        `${alert.title}<br><span style="font-size:11px;opacity:0.7;">${alert.message}</span><br>` +
                        `<a href="${link}" style="font-size:11px;opacity:0.8;text-decoration:underline;">Voir →</a>`,
                        8000,
                        groupLabel
                    );
                });
            } catch (e) {}
        }

        async function checkAlerts() {
            await refreshAlertBadge();
            await pollLatestForToasts();
        }

        document.addEventListener('DOMContentLoaded', () => setTimeout(checkAlerts, 1500));
        setInterval(checkAlerts, 30000);

        // Mise à jour instantanée du badge au focus (retour d'onglet)
        window.addEventListener('focus', refreshAlertBadge);
    </script>

    {{-- ═══════════════════════════════════════════════════════════════
         Signalements live — polling 20s pour :
           - actualiser le badge sidebar
           - toast + son quand un nouveau signal arrive (on est ailleurs)
           - sur /admin/signalements, prepend nouvelles cartes sans reload
         Pas de WebSocket : volume des signalements faible et compatible
         avec n'importe quelle infra (Coolify, shared hosting…).
       ═══════════════════════════════════════════════════════════════ --}}
    @auth
    <script>
    (function () {
        const HEARTBEAT_URL = "{{ route('admin.signalements.heartbeat') }}";
        const POLL_MS       = 20000;
        const badge         = document.getElementById('nav-signalements-badge');
        if (!badge) return;
        const onSignalementsPage = !!document.querySelector('[data-signalements-list]');

        // État local — initialisé depuis le data-* du badge pour ne pas
        // déclencher le toast au tout premier tick après chargement de page.
        let lastKnownLatestId = parseInt(badge.dataset.latestId || '0', 10);
        let firstTick         = true;

        // ── Audio cue : un bip court généré via WebAudio (pas d'asset).
        let audioCtx = null;
        function beep() {
            try {
                audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                const o = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                o.connect(g); g.connect(audioCtx.destination);
                o.type = 'sine';
                o.frequency.setValueAtTime(880, audioCtx.currentTime);
                o.frequency.exponentialRampToValueAtTime(440, audioCtx.currentTime + 0.18);
                g.gain.setValueAtTime(0.001, audioCtx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.18, audioCtx.currentTime + 0.02);
                g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.25);
                o.start();
                o.stop(audioCtx.currentTime + 0.26);
            } catch (e) { /* navigateur bloque autoplay : silencieux */ }
        }

        // ── Toast minimal (réutilise les couleurs du thème).
        function toast(html, href) {
            const wrap = document.createElement('div');
            wrap.style.cssText = `
                position:fixed;right:18px;bottom:18px;z-index:99999;
                background:linear-gradient(180deg,#fff7ed,#fff);
                border:1px solid #fed7aa;border-radius:12px;
                box-shadow:0 12px 32px -8px rgba(0,0,0,.25);
                padding:14px 16px;max-width:340px;font-size:13px;
                color:#9a3412;line-height:1.5;
                animation:slideInRight .25s ease-out;
                display:flex;align-items:flex-start;gap:10px;cursor:pointer`;
            wrap.innerHTML = `
                <div style="font-size:22px;line-height:1">⚠️</div>
                <div style="flex:1;min-width:0">${html}</div>
                <div style="font-size:18px;color:#b45309;line-height:1">×</div>`;
            wrap.onclick = (e) => {
                if (href && !e.target.closest('a')) window.location.href = href;
                else wrap.remove();
            };
            document.body.appendChild(wrap);
            setTimeout(() => { wrap.style.transition = 'opacity .4s'; wrap.style.opacity = '0'; }, 8000);
            setTimeout(() => wrap.remove(), 8500);
        }
        if (!document.getElementById('signalement-toast-anim')) {
            const s = document.createElement('style');
            s.id = 'signalement-toast-anim';
            s.textContent = '@keyframes slideInRight{from{transform:translateX(20px);opacity:0}to{transform:translateX(0);opacity:1}}';
            document.head.appendChild(s);
        }

        function updateBadge(count) {
            badge.dataset.count = count;
            badge.textContent   = count;
            badge.style.display = count > 0 ? '' : 'none';
        }

        async function tick() {
            try {
                const r = await fetch(HEARTBEAT_URL, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!r.ok) return;
                const d = await r.json();

                const prevCount = parseInt(badge.dataset.count || '0', 10);
                updateBadge(d.pending_count);

                const newLatestId = d.latest_id || 0;
                const isNew = !firstTick && newLatestId > lastKnownLatestId;

                if (isNew) {
                    // Nouveaux signaux depuis le dernier tick.
                    const fresh = (d.recent || []).filter(s => s.id > lastKnownLatestId);
                    fresh.slice().reverse().forEach(s => {
                        const ref = s.panel_ref ? `<strong>${s.panel_ref}</strong>` : 'Panneau';
                        const name = s.panel_name ? ` — ${s.panel_name}` : '';
                        const html = `
                            <div style="font-weight:800;margin-bottom:2px">Nouveau signalement</div>
                            <div>${ref}${name}</div>
                            <div style="font-size:11.5px;color:#b45309;margin-top:3px">
                                ${s.type_label} · ${s.actor || 'tech'} · ${s.ago || ''}
                            </div>`;
                        toast(html, "{{ route('admin.signalements.index') }}");
                    });
                    beep();

                    // Sur la page liste, demande au script local de recharger
                    // les cartes (ne force pas le reload — le script de page sait
                    // prepend proprement avec flash highlight).
                    if (onSignalementsPage) {
                        window.dispatchEvent(new CustomEvent('signalements:new', {
                            detail: { sinceId: lastKnownLatestId, count: d.pending_count }
                        }));
                    }
                }

                lastKnownLatestId = Math.max(lastKnownLatestId, newLatestId);
                firstTick = false;
            } catch (e) { /* silencieux */ }
        }

        // Premier tick après 1s pour ne pas charger juste au boot, puis poll régulier.
        setTimeout(tick, 1000);
        const interval = setInterval(tick, POLL_MS);

        // Tick immédiat quand l'onglet redevient actif (économise du poll quand
        // l'admin est sur un autre onglet).
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) tick();
        });
    })();
    </script>
    @endauth

    @stack('scripts')
</body>

</html>
