<!DOCTYPE html>
<html lang="fr" id="html-root" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mon espace') — CIBLE CI</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        /* ── DARK MODE (défaut) ── */
        :root,
        [data-theme="dark"] {
            --bg: #080a12;
            --sidebar-bg: #0d0f1a;
            --sidebar-border: rgba(255, 255, 255, 0.06);
            --topbar-bg: rgba(8, 10, 18, 0.95);
            --surface: #0d0f1a;
            --surface2: #13162a;
            --border: rgba(255, 255, 255, 0.06);
            --border2: rgba(255, 255, 255, 0.1);
            --text: #e2e8f0;
            --text2: #94a3b8;
            --text3: #4b5563;
            --footer-bg: #080a12;
            --nav-hover-bg: rgba(226, 6, 19, 0.07);
            --nav-active-bg: rgba(226, 6, 19, 0.1);
            --nav-color: #6b7280;
        }

        /* ── LIGHT MODE ── */
        [data-theme="light"] {
            --bg: #f4f5f7;
            --sidebar-bg: #ffffff;
            --sidebar-border: rgba(0, 0, 0, 0.08);
            --topbar-bg: rgba(255, 255, 255, 0.95);
            --surface: #ffffff;
            --surface2: #f4f5f7;
            --border: rgba(0, 0, 0, 0.07);
            --border2: rgba(0, 0, 0, 0.12);
            --text: #0f172a;
            --text2: #475569;
            --text3: #94a3b8;
            --footer-bg: #eef0f4;
            --nav-hover-bg: rgba(226, 6, 19, 0.05);
            --nav-active-bg: rgba(226, 6, 19, 0.08);
            --nav-color: #64748b;
        }

        body {
            background: var(--bg);
            color: var(--text);
        }

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #e20613;
            border-radius: 4px;
        }

        .sidebar-transition {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeInUp 0.35s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(80px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }

        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .content-wrapper {
            flex: 1;
        }

        .sidebar-wrapper {
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 50;
            flex-shrink: 0;
        }

        /* Nav */
        .nav-category {
            font-size: 9px;
            letter-spacing: .12em;
            color: var(--text3);
            font-weight: 700;
            text-transform: uppercase;
            padding: 0 12px;
            margin-bottom: 4px;
            margin-top: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: var(--nav-color);
            transition: all .15s;
            cursor: pointer;
            text-decoration: none;
            position: relative;
            width: 100%;
            border: none;
            background: none;
        }

        .nav-item:hover {
            background: var(--nav-hover-bg);
            color: var(--text);
        }

        .nav-item.active {
            background: var(--nav-active-bg);
            color: var(--text);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            bottom: 20%;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: #e20613;
        }

        .nav-item .nav-icon {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            opacity: .6;
        }

        .nav-item.active .nav-icon,
        .nav-item:hover .nav-icon {
            opacity: 1;
        }

        .nav-badge {
            margin-left: auto;
            background: #e20613;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 18px;
            text-align: center;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: linear-gradient(135deg, #e20613, #fab80b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 13px;
            color: #fff;
            flex-shrink: 0;
        }

        /* Theme toggle btn */
        .theme-btn {
            padding: 6px 8px;
            border-radius: 8px;
            border: 1px solid var(--border2);
            background: var(--surface2);
            color: var(--text2);
            cursor: pointer;
            font-size: 14px;
            transition: all .15s;
        }

        .theme-btn:hover {
            border-color: #e20613;
            color: var(--text);
        }

        /* Topbar */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            background: var(--topbar-bg);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
        }

        /* Sidebar */
        .sidebar {
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--sidebar-border);
        }

        .sidebar-footer-section {
            padding: 12px;
            border-top: 1px solid var(--sidebar-border);
        }
    </style>
    @stack('styles')
</head>

<body>

@php $client = auth('client')->user(); @endphp

{{-- ═══ Calculer le badge pige 1 fois pour toute la page ════════════════════════ --}}
@once
@php
$_clientPigesNouveaux = 0;
if (auth('client')->check()) {
    $_clientPigesNouveaux = \App\Models\Pige::whereIn('campaign_id',
        auth('client')->user()->campaigns()->pluck('id')
    )
    ->where('status', 'verifie')
    ->where('verified_at', '>=', now()->subDays(7))
    ->count();
}
@endphp
@endonce

{{-- Appliquer le thème sauvegardé AVANT le rendu --}}
<script>
    (function() {
        const t = localStorage.getItem('client-theme') || 'dark';
        document.getElementById('html-root').setAttribute('data-theme', t);
    })();
</script>

<div class="main-wrapper">
    {{-- Barre couleurs CIBLE CI --}}
    <div style="width:4px;display:flex;flex-direction:column;flex-shrink:0;">
        <div style="flex:1;background:#e20613;"></div>
        <div style="flex:1;background:#fab80b;"></div>
        <div style="flex:1;background:#22c55e;"></div>
        <div style="flex:1;background:#81358a;"></div>
        <div style="flex:1;background:#3f7fc0;"></div>
    </div>

    {{-- ══ SIDEBAR DESKTOP ══ --}}
    <div class="sidebar-wrapper hidden lg:block w-64">
        <div class="sidebar">
            <div class="sidebar-header">
                <img id="logo-img" src="{{ asset('images/logob.png') }}" alt="CIBLE CI" class="w-40">
                <div style="font-size:9px;letter-spacing:.12em;color:var(--text3);font-weight:700;margin-top:14px;">
                    ESPACE CLIENT
                </div>
            </div>

            <nav class="flex-1 px-3 py-4 overflow-y-auto" style="display:flex;flex-direction:column;gap:2px;">
                <div class="nav-category">Principal</div>

                <a href="{{ route('client.dashboard') }}" class="nav-item {{ request()->routeIs('client.dashboard') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Tableau de bord
                </a>

                <a href="{{ route('client.propositions') }}" class="nav-item {{ request()->routeIs('client.propositions*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    Propositions
                    @php $pendingCount = $client->reservations()->where('status','en_attente')->whereNotNull('proposition_token')->where('end_date','>=',now())->count(); @endphp
                    @if ($pendingCount > 0)
                        <span class="nav-badge">{{ $pendingCount > 9 ? '9+' : $pendingCount }}</span>
                    @endif
                </a>

                <a href="{{ route('client.campagnes') }}" class="nav-item {{ request()->routeIs('client.campagnes*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                    Campagnes
                </a>

                {{-- ── NOUVEAU : Suivi terrain ── --}}
                <div class="nav-category" style="margin-top:12px;">Suivi terrain</div>

                <a href="{{ route('client.poses') }}" class="nav-item {{ request()->routeIs('client.poses*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Poses terrain
                </a>

                <a href="{{ route('client.piges') }}" class="nav-item {{ request()->routeIs('client.piges*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                        <circle cx="12" cy="13" r="4"/>
                    </svg>
                    Preuves d'affichage
                    @if($_clientPigesNouveaux > 0)
                        <span class="nav-badge">{{ $_clientPigesNouveaux > 9 ? '9+' : $_clientPigesNouveaux }}</span>
                    @endif
                </a>

                {{-- ── Mon compte ── --}}
                <div class="nav-category" style="margin-top:12px;">Mon compte</div>

                <a href="{{ route('client.profil') }}" class="nav-item {{ request()->routeIs('client.profil*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Mon profil
                </a>

                <a href="{{ route('client.password.change') }}" class="nav-item {{ request()->routeIs('client.password*') ? 'active' : '' }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Sécurité
                </a>
            </nav>

            <div class="sidebar-footer-section">
                <div style="display:flex;align-items:center;gap:10px;padding:8px;margin-bottom:8px;">
                    <div class="avatar">{{ strtoupper(mb_substr($client->name, 0, 1)) }}</div>
                    <div style="min-width:0;flex:1;">
                        <div style="font-size:12px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $client->name }}
                        </div>
                        <div style="font-size:10px;color:var(--text3);">{{ $client->ncc ?? 'Client' }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('client.logout') }}">
                    @csrf
                    <button type="submit" class="nav-item" style="color:#ef4444;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ══ SIDEBAR MOBILE ══ --}}
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-40 hidden backdrop-blur-sm" onclick="closeSidebar()"></div>
    <aside id="mobile-sidebar" class="fixed top-0 left-0 z-50 w-64 h-full sidebar-transition -translate-x-full sidebar">
        <div class="sidebar-header">
            <img id="mobile-logo" src="{{ asset('images/logob.png') }}" alt="CIBLE CI" class="h-8 w-auto">
            <div style="font-size:9px;letter-spacing:.12em;color:var(--text3);font-weight:700;margin-top:14px;">
                ESPACE CLIENT
            </div>
        </div>

        <nav class="flex-1 px-3 py-4 overflow-y-auto" style="display:flex;flex-direction:column;gap:2px;">
            <div class="nav-category">Principal</div>

            <a href="{{ route('client.dashboard') }}" class="nav-item {{ request()->routeIs('client.dashboard') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Tableau de bord
            </a>

            <a href="{{ route('client.propositions') }}" class="nav-item {{ request()->routeIs('client.propositions*') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                Propositions
                @if($pendingCount > 0)
                    <span class="nav-badge">{{ $pendingCount > 9 ? '9+' : $pendingCount }}</span>
                @endif
            </a>

            <a href="{{ route('client.campagnes') }}" class="nav-item {{ request()->routeIs('client.campagnes*') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                </svg>
                Campagnes
            </a>

            <div class="nav-category" style="margin-top:12px;">Suivi terrain</div>

            <a href="{{ route('client.poses') }}" class="nav-item {{ request()->routeIs('client.poses*') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 11 12 14 22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                Poses terrain
            </a>

            <a href="{{ route('client.piges') }}" class="nav-item {{ request()->routeIs('client.piges*') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                    <circle cx="12" cy="13" r="4"/>
                </svg>
                Preuves d'affichage
                @if($_clientPigesNouveaux > 0)
                    <span class="nav-badge">{{ $_clientPigesNouveaux > 9 ? '9+' : $_clientPigesNouveaux }}</span>
                @endif
            </a>

            <div class="nav-category" style="margin-top:12px;">Mon compte</div>

            <a href="{{ route('client.profil') }}" class="nav-item {{ request()->routeIs('client.profil*') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Mon profil
            </a>

            <a href="{{ route('client.password.change') }}" class="nav-item {{ request()->routeIs('client.password*') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Sécurité
            </a>
        </nav>

        <div class="sidebar-footer-section">
            <div style="display:flex;align-items:center;gap:10px;padding:8px;margin-bottom:8px;">
                <div class="avatar">{{ strtoupper(mb_substr($client->name, 0, 1)) }}</div>
                <div style="min-width:0;flex:1;">
                    <div style="font-size:12px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        {{ $client->name }}
                    </div>
                    <div style="font-size:10px;color:var(--text3);">{{ $client->ncc ?? 'Client' }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('client.logout') }}">
                @csrf
                <button type="submit" class="nav-item" style="color:#ef4444;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Déconnexion
                </button>
            </form>
        </div>
    </aside>

    {{-- ══ CONTENU PRINCIPAL ══ --}}
    <div class="main-content">
        <div class="topbar">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="lg:hidden theme-btn" onclick="toggleSidebar()">
                        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <h1 style="font-size:14px;font-weight:600;color:var(--text);">@yield('page-title', 'Tableau de bord')</h1>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    {{-- Toggle Dark/Light mode --}}
                    <button id="theme-toggle-btn" onclick="toggleClientTheme()" title="Basculer dark/light" style="background:none; border:none; cursor:pointer; padding:0;">
                        <div id="toggle-switch" style="width:52px; height:28px; border-radius:20px; background:#1e2330; border:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; padding:3px; transition:all 0.3s ease; position:relative;">
                            <div id="toggle-thumb" style="width:22px; height:22px; border-radius:50%; background:#e8a020; display:flex; align-items:center; justify-content:center; transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1); transform:translateX(0px); box-shadow:0 2px 8px rgba(0,0,0,0.3);">
                                <svg id="icon-moon" style="width:12px;height:12px;color:#000;" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                                </svg>
                                <svg id="icon-sun" style="width:12px;height:12px;color:#000;display:none;" fill="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="5"/>
                                    <line x1="12" y1="1" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="12" y1="21" x2="12" y2="23" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="1" y1="12" x2="3" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="21" y1="12" x2="23" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </div>
                        </div>
                    </button>
                    <div class="hidden sm:block" style="text-align:right;">
                        <div style="font-size:13px;font-weight:600;color:var(--text);">{{ $client->name }}</div>
                        <div style="font-size:10px;color:var(--text3);">{{ $client->ncc ?? 'Client' }}</div>
                    </div>
                    <div class="avatar">{{ strtoupper(mb_substr($client->name, 0, 1)) }}</div>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="p-4 lg:p-6 animate-fade-in">
                @yield('content')
            </div>
        </div>

        <footer style="border-top:1px solid var(--border);background:var(--footer-bg);">
            <div style="padding:20px 24px;">
                <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;font-size:11px;color:var(--text3);">
                    <div>© {{ date('Y') }} CIBLE CI — Régie OOH Côte d'Ivoire</div>
                    <div style="display:flex;gap:20px;">
                        <a href="#" style="color:var(--text3);text-decoration:none;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'">Mentions légales</a>
                        <a href="#" style="color:var(--text3);text-decoration:none;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'">Confidentialité</a>
                        <a href="#" style="color:var(--text3);text-decoration:none;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'">Contact</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>

{{-- FAB Mobile --}}
<button class="fixed bottom-5 left-5 z-50 lg:hidden w-11 h-11 rounded-full flex items-center justify-center shadow-lg" style="background:#e20613;" onclick="toggleSidebar()">
    <svg style="width:18px;height:18px;color:#fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

{{-- Toast --}}
<div id="toast-container" style="position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:360px;"></div>

<script>
    // ── THEME ──────────────────────────────────────────
    (function() {
        const t = localStorage.getItem('client-theme') || 'dark';
        applyClientTheme(t);
    })();

    function toggleClientTheme() {
        const current = localStorage.getItem('client-theme') || 'dark';
        const next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem('client-theme', next);
        applyClientTheme(next);
    }

    function applyClientTheme(theme) {
        document.getElementById('html-root').setAttribute('data-theme', theme);

        const thumb = document.getElementById('toggle-thumb');
        const toggle = document.getElementById('toggle-switch');
        const moon = document.getElementById('icon-moon');
        const sun = document.getElementById('icon-sun');

        if (!thumb) return;

        if (theme === 'light') {
            thumb.style.transform = 'translateX(24px)';
            toggle.style.background = '#e2e8f0';
            toggle.style.border = '1px solid rgba(0,0,0,0.1)';
            thumb.style.background = '#f97316';
            moon.style.display = 'none';
            sun.style.display = 'block';
        } else {
            thumb.style.transform = 'translateX(0px)';
            toggle.style.background = '#1e2330';
            toggle.style.border = '1px solid rgba(255,255,255,0.1)';
            thumb.style.background = '#e8a020';
            moon.style.display = 'block';
            sun.style.display = 'none';
        }

        const logos = ['logo-img', 'mobile-logo'];
        logos.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.src = theme === 'dark' ? '{{ asset('images/logob.png') }}' : '{{ asset('images/logol.png') }}';
        });
    }

    // ── SIDEBAR ────────────────────────────────────────
    function toggleSidebar() {
        const sidebar = document.getElementById('mobile-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
        document.body.style.overflow = sidebar.classList.contains('-translate-x-full') ? '' : 'hidden';
    }

    function closeSidebar() {
        document.getElementById('mobile-sidebar').classList.add('-translate-x-full');
        document.getElementById('sidebar-overlay').classList.add('hidden');
        document.body.style.overflow = '';
    }

    // ── TOAST ──────────────────────────────────────────
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const color = type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#fab80b';
        const toast = document.createElement('div');
        toast.className = 'animate-slide-in';
        toast.style.cssText = `background:var(--surface);border:1px solid var(--border2);border-left:3px solid ${color};border-radius:10px;padding:12px 16px;font-size:13px;color:var(--text);box-shadow:0 8px 24px rgba(0,0,0,.25);`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity .3s';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (session('success'))
            showToast('{{ session('success') }}');
        @endif
        @if (session('error'))
            showToast('{{ session('error') }}', 'error');
        @endif
        @if (session('warning'))
            showToast('{{ session('warning') }}', 'warning');
        @endif

        document.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) setTimeout(() => closeSidebar(), 100);
            });
        });
    });
</script>
@stack('scripts')
</body>
</html>