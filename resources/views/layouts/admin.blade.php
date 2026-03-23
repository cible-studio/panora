<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PROGICIA — {{ $title ?? 'Dashboard' }}</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <div class="app-layout" x-data>

        {{-- ══ SIDEBAR ══ --}}
        <aside class="sidebar">

            <div class="sidebar-logo">
                <div class="sidebar-logo-mark">CIBLE CI</div>
                <div class="sidebar-logo-sub">Régie OOH</div>
            </div>

            <div class="role-pill">
                ⚡ {{ Auth::user()->role->value }}
            </div>

            <nav style="flex:1; padding: 8px 0;">

                <div class="nav-section">
                    <div class="nav-label">Principal</div>

                    <a href="{{ route('dashboard') }}"
                        class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <span class="icon">📊</span> Tableau de bord
                    </a>

                    <a href="{{ route('admin.reservations.disponibilites') }}"
                        class="nav-item {{ request()->routeIs('admin.reservations.disponibilites') ? 'active' : '' }}">
                        <span class="icon">📋</span> Disponibilités
                        <span class="nav-badge">{{ App\Models\Panel::where('status', 'libre')->count() }}</span>
                    </a>

                    <a href="{{ route('admin.panels.index') }}"
                        class="nav-item {{ request()->routeIs('admin.panels.*') ? 'active' : '' }}">
                        <span class="icon">🪧</span> Inventaire
                    </a>

                    <a href="{{ route('admin.campaigns.index') }}"
                        class="nav-item {{ request()->routeIs('admin.campaigns.*') ? 'active' : '' }}">
                        <span class="icon">📁</span> Campagnes
                        <span class="nav-badge blue">
                            {{ App\Models\Campaign::where('status', 'actif')->count() }}
                        </span>
                    </a>

                    <a href="{{ route('admin.clients.index') }}"
                        class="nav-item {{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">
                        <span class="icon">👥</span> Clients
                    </a>

                    <a href="{{ route('admin.external-agencies.index') }}"
                        class="nav-item {{ request()->routeIs('admin.external-agencies.*') ? 'active' : '' }}">
                        <span class="icon">🏢</span> Régies externes
                    </a>

                    <a href="{{ route('admin.propositions.index') }}"
                        class="nav-item {{ request()->routeIs('admin.propositions.*') ? 'active' : '' }}">
                        <span class="icon">📄</span> Propositions
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-label">Opérations</div>

                    <a href="{{ route('admin.reservations.index') }}"
                        class="nav-item {{ request()->routeIs('admin.reservations.*') && !request()->routeIs('admin.reservations.disponibilites') ? 'active' : '' }}">
                        <span class="icon">✅</span> Confirmations
                        <span class="nav-badge red">
                            {{ App\Models\Reservation::where('status', 'en_attente')->count() }}
                        </span>
                    </a>

                    <a href="{{ route('admin.pose-tasks.index') }}"
                        class="nav-item {{ request()->routeIs('admin.pose-tasks.*') ? 'active' : '' }}">
                        <span class="icon">🏗️</span> Gestion Pose OOH
                    </a>

                    <a href="{{ route('admin.piges.index') }}"
                        class="nav-item {{ request()->routeIs('admin.piges.*') ? 'active' : '' }}">
                        <span class="icon">📷</span> Piges Photos
                    </a>

                    <a href="#" class="nav-item">
                        <span class="icon">📤</span> Export Piges
                    </a>

                    <a href="#" class="nav-item">
                        <span class="icon">🏛️</span> Taxes Communes
                    </a>

                    <a href="#" class="nav-item">
                        <span class="icon">💰</span> Facturation
                    </a>

                    <a href="{{ route('admin.alerts.index') }}"
                        class="nav-item {{ request()->routeIs('admin.alerts.*') ? 'active' : '' }}">
                        <span class="icon">🔔</span> Alertes
                        <span class="nav-badge red">
                            {{ App\Models\Alert::where('is_read', false)->count() }}
                        </span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-label">Analyse</div>

                    <a href="{{ route('admin.map') }}"
                        class="nav-item {{ request()->routeIs('admin.map*') ? 'active' : '' }}">
                        <span class="icon">🗺️</span> Carte & Heatmap
                    </a>

                    <a href="#" class="nav-item">
                        <span class="icon">📈</span> Statistiques
                    </a>

                    <a href="#" class="nav-item">
                        <span class="icon">📑</span> Rapports
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-label">Administration</div>

                    <a href="{{ route('admin.users.index') }}"
                        class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <span class="icon">👥</span> Utilisateurs
                    </a>

                    <a href="{{ route('admin.maintenances.index') }}"
                        class="nav-item {{ request()->routeIs('admin.maintenances.*') ? 'active' : '' }}">
                        <span class="icon">🔧</span> Maintenance
                    </a>

                    <a href="{{ route('admin.audit.logs') }}"
                        class="nav-item {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                        <span class="icon">📋</span> Logs d'audit
                    </a>

                    <a href="{{ route('admin.settings.communes.index') }}"
                        class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <span class="icon">⚙️</span> Paramètres
                    </a>
                </div>

            </nav>

            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                    <div>
                        <div class="user-name">{{ Auth::user()->name }}</div>
                        <div class="user-role">{{ Auth::user()->role->value }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" style="margin-left:auto">
                        @csrf
                        <button type="submit"
                            style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:16px;"
                            title="Déconnexion" onmouseover="this.style.color='var(--red)'"
                            onmouseout="this.style.color='var(--text3)'">⏻</button>
                    </form>
                </div>
            </div>

        </aside>

        {{-- ══ CONTENU ══ --}}
        <div class="main-area" style="margin-left:235px;">

            {{-- TOPBAR FIXE --}}
            <div class="topbar" style="position:fixed; top:0; left:235px; right:0; z-index:30;">
                <div class="topbar-title">{{ $title ?? 'Dashboard' }}</div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="text" class="topbar-search" placeholder="🔍 Rechercher...">
                    <button class="btn btn-ghost btn-sm">🔔
                        <span class="nav-badge red" style="position:relative">
                            {{ App\Models\Alert::where('is_read', false)->count() }}
                        </span>
                    </button>
                    {{ $topbarActions ?? '' }}
                </div>
            </div>

            {{-- ESPACE POUR LA TOPBAR FIXE --}}
            <div style="height:52px;"></div>

            <div style="padding: 0 20px;">
                @if (session('success'))
                    <div class="flash flash-success" style="margin-top:16px;">
                        ✓ {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="flash flash-error" style="margin-top:16px;">
                        ✕ {{ session('error') }}
                    </div>
                @endif
            </div>

            <div class="page-content">
                {{ $slot }}
            </div>

        </div>

    </div>

    {{-- ══ TOAST CONTAINER ══ --}}
    <div id="toast-container"
        style="position:fixed;bottom:24px;right:24px;z-index:99999;
            display:flex;flex-direction:column-reverse;gap:8px;
            pointer-events:none;max-width:420px;">
    </div>

    {{-- Flash → Toast automatique --}}
    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', () => showToast('success', @json(session('success'))));
        </script>
    @endif
    @if (session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', () => showToast('error', @json(session('error'))));
        </script>
    @endif
    @if (session('warning'))
        <script>
            document.addEventListener('DOMContentLoaded', () => showToast('warning', @json(session('warning'))));
        </script>
    @endif
    @if (session('info'))
        <script>
            document.addEventListener('DOMContentLoaded', () => showToast('info', @json(session('info'))));
        </script>
    @endif

    <style>
        .toast {
            min-width: 280px;
            max-width: 420px;
            padding: 12px 14px 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            pointer-events: all;
            cursor: pointer;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.45), 0 2px 8px rgba(0, 0, 0, 0.2);
            animation: toastIn .25s cubic-bezier(0.34, 1.56, 0.64, 1);
            line-height: 1.4;
            border: 1px solid transparent;
        }

        .toast.success {
            background: #052e16;
            border-color: #166534;
            color: #4ade80;
        }

        .toast.error {
            background: #1c0a0a;
            border-color: #7f1d1d;
            color: #f87171;
        }

        .toast.warning {
            background: #1c1200;
            border-color: #78350f;
            color: #fbbf24;
        }

        .toast.info {
            background: #0c1a2e;
            border-color: #1e3a5f;
            color: #60a5fa;
        }

        .toast-icon {
            font-size: 15px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .toast-msg {
            flex: 1;
        }

        .toast-close {
            margin-left: 4px;
            font-size: 13px;
            opacity: .5;
            cursor: pointer;
            background: none;
            border: none;
            color: inherit;
            padding: 0;
            flex-shrink: 0;
            line-height: 1;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateX(24px) scale(.95);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        @keyframes toastOut {
            from {
                opacity: 1;
                transform: translateX(0);
                max-height: 80px;
                margin-bottom: 0;
            }

            to {
                opacity: 0;
                transform: translateX(24px);
                max-height: 0;
                margin-bottom: -8px;
                padding: 0;
            }
        }
    </style>

    <script>
        function showToast(type, message, duration) {
            duration = duration || (type === 'error' ? 7000 : 5000);
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.innerHTML =
                '<span class="toast-icon">' + (icons[type] || 'ℹ️') + '</span>' +
                '<span class="toast-msg">' + message + '</span>' +
                '<button class="toast-close" onclick="dismissToast(this.parentElement);event.stopPropagation()">✕</button>';

            toast.onclick = () => dismissToast(toast);
            container.appendChild(toast);

            const t = setTimeout(() => dismissToast(toast), duration);
            toast._timeout = t;
        }

        function dismissToast(toast) {
            if (!toast || !toast.parentElement) return;
            clearTimeout(toast._timeout);
            toast.style.animation = 'toastOut .2s ease forwards';
            setTimeout(() => toast.remove(), 200);
        }

        window.Toast = {
            success: (m, d) => showToast('success', m, d),
            error: (m, d) => showToast('error', m, d),
            warning: (m, d) => showToast('warning', m, d),
            info: (m, d) => showToast('info', m, d),
        };
    </script>

    @stack('scripts')
</body>

</html>
