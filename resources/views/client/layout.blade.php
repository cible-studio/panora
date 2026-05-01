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
        * { margin:0; padding:0; box-sizing:border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { font-family:'Inter',sans-serif; }

        /* ── DARK MODE (défaut) ── */
        :root, [data-theme="dark"] {
            --bg:           #080a12;
            --sidebar-bg:   #0d0f1a;
            --sidebar-border: rgba(255,255,255,0.06);
            --topbar-bg:    rgba(8,10,18,0.95);
            --surface:      #0d0f1a;
            --surface2:     #13162a;
            --border:       rgba(255,255,255,0.06);
            --border2:      rgba(255,255,255,0.1);
            --text:         #e2e8f0;
            --text2:        #94a3b8;
            --text3:        #4b5563;
            --footer-bg:    #080a12;
            --nav-hover-bg: rgba(226,6,19,0.07);
            --nav-active-bg:rgba(226,6,19,0.1);
            --nav-color:    #6b7280;
        }

        /* ── LIGHT MODE ── */
        [data-theme="light"] {
            --bg:           #f4f5f7;
            --sidebar-bg:   #ffffff;
            --sidebar-border: rgba(0,0,0,0.08);
            --topbar-bg:    rgba(255,255,255,0.95);
            --surface:      #ffffff;
            --surface2:     #f4f5f7;
            --border:       rgba(0,0,0,0.07);
            --border2:      rgba(0,0,0,0.12);
            --text:         #0f172a;
            --text2:        #475569;
            --text3:        #94a3b8;
            --footer-bg:    #eef0f4;
            --nav-hover-bg: rgba(226,6,19,0.05);
            --nav-active-bg:rgba(226,6,19,0.08);
            --nav-color:    #64748b;
        }

        body { background:var(--bg); color:var(--text); min-height:100vh; }

        ::-webkit-scrollbar { width:6px; height:6px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:#e20613; border-radius:4px; }

        /* ─────────── LAYOUT PRINCIPAL ─────────── */
        :root {
            --client-sidebar-width: 256px;  /* w-64 */
            --client-stripes-width: 4px;
        }

        .app {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* ─────────── SIDEBAR DESKTOP ─────────── */
        .sidebar-desktop {
            width: calc(var(--client-sidebar-width) + var(--client-stripes-width));
            flex-shrink: 0;
            display: none;          /* caché par défaut, révélé par MQ desktop */
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 30;
        }
        @media (min-width: 1024px) {
            .sidebar-desktop { display: flex; }
        }

        .sidebar {
            width: var(--client-sidebar-width);
            flex: 1;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Stripes brand */
        .brand-stripes {
            width: var(--client-stripes-width);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        .brand-stripes > div { flex: 1; }

        /* ─────────── SIDEBAR MOBILE (drawer off-canvas) ─────────── */
        .sidebar-mobile {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: 280px;
            max-width: 85vw;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            z-index: 60;
            transform: translate3d(-100%, 0, 0);
            transition: transform 0.28s cubic-bezier(.4,0,.2,1);
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 24px rgba(0,0,0,.35);
        }
        .sidebar-mobile.is-open { transform: translate3d(0, 0, 0); }
        @media (min-width: 1024px) {
            .sidebar-mobile { display: none; }
        }

        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 55;
            display: none;
        }
        .sidebar-backdrop.is-visible { display: block; }
        @media (min-width: 1024px) {
            .sidebar-backdrop { display: none !important; }
        }

        /* ─────────── MAIN CONTENT ─────────── */
        .main-content {
            flex: 1;
            min-width: 0;            /* CRITIQUE : empêche le flex item de déborder */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Topbar */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            background: var(--topbar-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
        }
        @media (min-width: 768px) { .topbar { padding: 12px 24px; } }

        .content-wrapper { flex: 1; }

        .content-inner { padding: 16px; }
        @media (min-width: 1024px) { .content-inner { padding: 24px; } }

        /* Footer */
        .client-footer {
            border-top: 1px solid var(--border);
            background: var(--footer-bg);
            padding: 16px 16px;
        }
        @media (min-width: 768px) { .client-footer { padding: 20px 24px; } }

        /* ─────────── NAV ITEMS ─────────── */
        .sidebar-header {
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--sidebar-border);
            flex-shrink: 0;
        }
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .sidebar-footer-section {
            padding: 12px;
            border-top: 1px solid var(--sidebar-border);
            flex-shrink: 0;
        }

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
        .nav-category:first-of-type { margin-top: 0; }

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
            text-align: left;
        }
        .nav-item:hover { background: var(--nav-hover-bg); color: var(--text); }
        .nav-item.active { background: var(--nav-active-bg); color: var(--text); }
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 20%; bottom: 20%;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: #e20613;
        }
        .nav-item .nav-icon { width: 16px; height: 16px; flex-shrink: 0; opacity: .6; }
        .nav-item.active .nav-icon, .nav-item:hover .nav-icon { opacity: 1; }
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
            width: 34px; height: 34px;
            border-radius: 9px;
            background: linear-gradient(135deg, #e20613, #fab80b);
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 13px; color: #fff;
            flex-shrink: 0;
        }

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
        .theme-btn:hover { border-color: #e20613; color: var(--text); }

        /* Hamburger mobile */
        .hamburger { display: inline-flex; }
        @media (min-width: 1024px) { .hamburger { display: none; } }

        /* Animations */
        @keyframes fadeInUp { from { opacity:0; transform: translateY(16px); } to { opacity:1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeInUp 0.35s ease-out; }
        @keyframes slideIn { from { opacity:0; transform: translateX(80px); } to { opacity:1; transform: translateX(0); } }
        .animate-slide-in { animation: slideIn 0.3s ease-out; }

        /* Bloque le scroll du body quand le drawer mobile est ouvert */
        html.sidebar-locked, body.sidebar-locked { overflow: hidden; }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            .sidebar-mobile { transition: none; }
            *, *::before, *::after { animation-duration: .01ms !important; }
        }
    </style>
    @stack('styles')
</head>

<body>
@php
    $client = auth('client')->user();
    $pendingPropositions = $client?->reservations()
        ->where('status','en_attente')
        ->whereNotNull('proposition_token')
        ->where('end_date','>=',now())
        ->count() ?? 0;
@endphp

{{-- Applique le thème AVANT le rendu (anti-flash) --}}
<script>
    (function () {
        var t = localStorage.getItem('client-theme') || 'light';
        document.getElementById('html-root').setAttribute('data-theme', t);
    })();
</script>

{{-- ═══════════════════════════════════════════════════════════════════
     NAVIGATION RÉUTILISABLE — partagée entre sidebar desktop et mobile
═══════════════════════════════════════════════════════════════════ --}}
@php
    $logoB = asset('images/logob.png');
    $logoL = asset('images/logol.png');
    $navItems = [
        ['type' => 'category', 'label' => 'Principal'],
        [
            'type'  => 'item',
            'label' => 'Tableau de bord',
            'route' => 'client.dashboard',
            'active'=> request()->routeIs('client.dashboard'),
            'icon'  => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        ],
        [
            'type'  => 'item',
            'label' => 'Propositions',
            'route' => 'client.propositions',
            'active'=> request()->routeIs('client.propositions*'),
            'badge' => $pendingPropositions,
            'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        ],
        [
            'type'  => 'item',
            'label' => 'Campagnes',
            'route' => 'client.campagnes',
            'active'=> request()->routeIs('client.campagnes*'),
            'icon'  => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        ],
        ['type' => 'category', 'label' => 'Mon compte'],
        [
            'type'  => 'item',
            'label' => 'Mon profil',
            'route' => 'client.profil',
            'active'=> request()->routeIs('client.profil*'),
            'icon'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        ],
        [
            'type'  => 'item',
            'label' => 'Sécurité',
            'route' => 'client.password.change',
            'active'=> request()->routeIs('client.password*'),
            'icon'  => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        ],
    ];
@endphp

<div class="app" x-data="{ open: false }"
     x-effect="document.documentElement.classList.toggle('sidebar-locked', open)"
     @keydown.escape.window="open = false">

    {{-- ═══ SIDEBAR DESKTOP ═══ --}}
    <div class="sidebar-desktop">
        <div class="sidebar">
            <div class="sidebar-header">
                <img class="logo-img h-8 w-auto" src="{{ $logoB }}" data-logo-light="{{ $logoL }}" data-logo-dark="{{ $logoB }}" alt="CIBLE CI">
                <div style="font-size:9px;letter-spacing:.12em;color:var(--text3);font-weight:700;margin-top:14px;">ESPACE CLIENT</div>
            </div>
            <nav class="sidebar-nav">
                @foreach($navItems as $item)
                    @if($item['type'] === 'category')
                        <div class="nav-category">{{ $item['label'] }}</div>
                    @else
                        <a href="{{ route($item['route']) }}" class="nav-item {{ ($item['active'] ?? false) ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
                            {{ $item['label'] }}
                            @if(!empty($item['badge']))
                                <span class="nav-badge">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </nav>
            <div class="sidebar-footer-section">
                <div style="display:flex;align-items:center;gap:10px;padding:8px;margin-bottom:8px;">
                    <div class="avatar">{{ strtoupper(mb_substr($client->name, 0, 1)) }}</div>
                    <div style="min-width:0;flex:1;">
                        <div style="font-size:12px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $client->name }}</div>
                        <div style="font-size:10px;color:var(--text3);">{{ $client->ncc ?? 'Client' }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('client.logout') }}">
                    @csrf
                    <button type="submit" class="nav-item" style="color:#ef4444;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
        {{-- Stripes décoratives CIBLE CI --}}
        <div class="brand-stripes" aria-hidden="true">
            <div style="background:#e20613;"></div>
            <div style="background:#fab80b;"></div>
            <div style="background:#22c55e;"></div>
            <div style="background:#81358a;"></div>
            <div style="background:#3f7fc0;"></div>
        </div>
    </div>

    {{-- ═══ DRAWER MOBILE ═══ --}}
    <div class="sidebar-backdrop" :class="{ 'is-visible': open }" @click="open = false"></div>

    <aside class="sidebar-mobile" :class="{ 'is-open': open }" @click="open = false">
        <div class="sidebar-header">
            <img class="logo-img h-8 w-auto" src="{{ $logoB }}" data-logo-light="{{ $logoL }}" data-logo-dark="{{ $logoB }}" alt="CIBLE CI">
            <div style="font-size:9px;letter-spacing:.12em;color:var(--text3);font-weight:700;margin-top:14px;">ESPACE CLIENT</div>
        </div>
        <nav class="sidebar-nav">
            @foreach($navItems as $item)
                @if($item['type'] === 'category')
                    <div class="nav-category">{{ $item['label'] }}</div>
                @else
                    <a href="{{ route($item['route']) }}" class="nav-item {{ ($item['active'] ?? false) ? 'active' : '' }}">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
                        {{ $item['label'] }}
                        @if(!empty($item['badge']))
                            <span class="nav-badge">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-footer-section">
            <div style="display:flex;align-items:center;gap:10px;padding:8px;margin-bottom:8px;">
                <div class="avatar">{{ strtoupper(mb_substr($client->name, 0, 1)) }}</div>
                <div style="min-width:0;flex:1;">
                    <div style="font-size:12px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $client->name }}</div>
                    <div style="font-size:10px;color:var(--text3);">{{ $client->ncc ?? 'Client' }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('client.logout') }}">
                @csrf
                <button type="submit" class="nav-item" style="color:#ef4444;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Déconnexion
                </button>
            </form>
        </div>
    </aside>

    {{-- ═══ CONTENU PRINCIPAL ═══ --}}
    <div class="main-content">

        <header class="topbar">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div style="display:flex;align-items:center;gap:12px;min-width:0;">
                    <button class="hamburger theme-btn" type="button" @click="open = !open" aria-label="Menu" style="padding:6px 10px;">
                        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <h1 style="font-size:14px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">@yield('page-title', 'Tableau de bord')</h1>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                    <button id="theme-toggle-btn" class="theme-btn" onclick="toggleClientTheme()" title="Basculer dark/light" type="button">🌙</button>
                    <div class="hidden sm:block" style="text-align:right;">
                        <div style="font-size:13px;font-weight:600;color:var(--text);">{{ $client->name }}</div>
                        <div style="font-size:10px;color:var(--text3);">{{ $client->ncc ?? 'Client' }}</div>
                    </div>
                    <div class="avatar">{{ strtoupper(mb_substr($client->name, 0, 1)) }}</div>
                </div>
            </div>
        </header>

        <div class="content-wrapper">
            <div class="content-inner animate-fade-in">
                @yield('content')
            </div>
        </div>

        <footer class="client-footer">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;font-size:11px;color:var(--text3);">
                <div>© {{ date('Y') }} CIBLE CI — Régie OOH Côte d'Ivoire</div>
                <div style="display:flex;gap:20px;flex-wrap:wrap;">
                    <a href="#" style="color:var(--text3);text-decoration:none;">Mentions légales</a>
                    <a href="#" style="color:var(--text3);text-decoration:none;">Confidentialité</a>
                    <a href="#" style="color:var(--text3);text-decoration:none;">Contact</a>
                </div>
            </div>
        </footer>
    </div>
</div>

{{-- Toast container --}}
<div id="toast-container" style="position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:360px;"></div>

{{-- Alpine.js (pour le drawer mobile + scroll lock) --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<script>
    // ── THEME ──────────────────────────────────────────
    (function () {
        const t = localStorage.getItem('client-theme') || 'light';
        applyClientTheme(t);
    })();

    function toggleClientTheme() {
        const current = localStorage.getItem('client-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem('client-theme', next);
        applyClientTheme(next);
    }

    function applyClientTheme(theme) {
        document.getElementById('html-root').setAttribute('data-theme', theme);
        const btn = document.getElementById('theme-toggle-btn');
        if (btn) btn.textContent = theme === 'dark' ? '🌙' : '☀️';

        // Tous les logos avec data-logo-light/dark
        document.querySelectorAll('.logo-img').forEach(el => {
            const src = theme === 'dark' ? el.dataset.logoDark : el.dataset.logoLight;
            if (src) el.src = src;
        });
    }

    // ── TOAST ──────────────────────────────────────────
    function showToast(message, type) {
        type = type || 'success';
        const container = document.getElementById('toast-container');
        if (!container) return;
        const color = type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#fab80b';
        const toast = document.createElement('div');
        toast.className = 'animate-slide-in';
        toast.style.cssText = 'background:var(--surface);border:1px solid var(--border2);border-left:3px solid '+color+';border-radius:10px;padding:12px 16px;font-size:13px;color:var(--text);box-shadow:0 8px 24px rgba(0,0,0,.25);';
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity .3s';
            setTimeout(function () { toast.remove(); }, 300);
        }, 4000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        @if(session('success')) showToast(@json(session('success')), 'success'); @endif
        @if(session('error'))   showToast(@json(session('error')),   'error');   @endif
        @if(session('warning')) showToast(@json(session('warning')), 'warning'); @endif
    });
</script>
@stack('scripts')
</body>
</html>
