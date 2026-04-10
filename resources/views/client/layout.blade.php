<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mon espace') — CIBLE CI</title>
    
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
            background: #0a0c15;
            min-height: 100vh;
        }
        
        /* Custom colors */
        :root {
            --primary: #e8a020;
            --primary-dark: #c47a00;
            --primary-light: #fbbf24;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #1a1d2e;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        /* Sidebar transition */
        .sidebar-transition {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Animation fade in */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in {
            animation: fadeInUp 0.4s ease-out;
        }
        
        /* Toast animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }

        /* Main wrapper for sticky footer */
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Main content wrapper */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Content that grows */
        .content-wrapper {
            flex: 1;
        }

        /* Sidebar wrapper */
        .sidebar-wrapper {
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 50;
        }

        /* Fix menu categories */
        .nav-category {
            font-size: 0.65rem;
            letter-spacing: 0.05em;
            color: #6b7280;
            font-weight: 600;
        }

        /* Active nav item */
        .nav-item-active {
            background-color: rgba(232, 160, 32, 0.1);
            color: #e8a020;
            border-right: 2px solid #e8a020;
        }

        /* Hover effects */
        .nav-item-hover:hover {
            background-color: rgba(232, 160, 32, 0.1);
            color: #e8a020;
        }
    </style>
    @stack('styles')
</head>

<body>

@php $client = auth('client')->user(); @endphp

<div class="main-wrapper">
    <!-- Sidebar -->
    <div class="sidebar-wrapper hidden lg:block w-72">
        <aside class="h-full bg-gradient-to-b from-[#11131f] to-[#0a0c15] border-r border-white/5 overflow-y-auto flex flex-col">
            <!-- Logo Section -->
            <div class="p-6 border-b border-white/5">
                <div class="text-2xl font-extrabold bg-gradient-to-r from-[#e8a020] to-[#fbbf24] bg-clip-text text-transparent">CIBLE CI</div>
                <div class="text-[10px] text-gray-500 mt-1 tracking-wider">ESPACE CLIENT PREMIUM</div>
            </div>

            <!-- Navigation Menu - Clean Structure -->
            <nav class="flex-1 p-4 space-y-1">
                <!-- Main Navigation -->
                <div>
                    <div class="nav-category px-4 py-2">MENU PRINCIPAL</div>
                    <div class="space-y-1">
                        <a href="{{ route('client.dashboard') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.dashboard') ? 'nav-item-active' : 'nav-item-hover' }}">
                            <span class="text-xl">📊</span>
                            <span class="text-sm font-medium">Tableau de bord</span>
                        </a>
                        
                        <a href="{{ route('client.propositions') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.propositions*') ? 'nav-item-active' : 'nav-item-hover' }}">
                            <span class="text-xl">📋</span>
                            <span class="text-sm font-medium">Propositions</span>
                            @php $pendingCount = $client->reservations()->where('status','en_attente')->whereNotNull('proposition_token')->where('end_date','>=',now())->count(); @endphp
                            @if($pendingCount > 0)
                                <span class="ml-auto bg-[#e8a020] text-[#0a0c15] text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingCount > 9 ? '9+' : $pendingCount }}</span>
                            @endif
                        </a>
                        
                        <a href="{{ route('client.campagnes') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.campagnes*') ? 'nav-item-active' : 'nav-item-hover' }}">
                            <span class="text-xl">📢</span>
                            <span class="text-sm font-medium">Campagnes</span>
                        </a>
                    </div>
                </div>

                <!-- Separator -->
                <div class="h-px bg-white/5 my-4"></div>

                <!-- Account Section -->
                <div>
                    <div class="nav-category px-4 py-2">MON COMPTE</div>
                    <div class="space-y-1">
                        <a href="{{ route('client.profil') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.profil*') ? 'nav-item-active' : 'nav-item-hover' }}">
                            <span class="text-xl">👤</span>
                            <span class="text-sm font-medium">Mon profil</span>
                        </a>
                        
                        <a href="{{ route('client.password.change') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.password*') ? 'nav-item-active' : 'nav-item-hover' }}">
                            <span class="text-xl">🔒</span>
                            <span class="text-sm font-medium">Sécurité</span>
                        </a>
                    </div>
                </div>

                <!-- Additional Features -->
                <div class="h-px bg-white/5 my-4"></div>
                
                <div>
                    <div class="nav-category px-4 py-2">RESSOURCES</div>
                    <div class="space-y-1">
                        <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all nav-item-hover">
                            <span class="text-xl">🔍</span>
                            <span class="text-sm font-medium">Recherche</span>
                        </a>
                        
                        <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all nav-item-hover">
                            <span class="text-xl">📚</span>
                            <span class="text-sm font-medium">Références</span>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Sidebar Footer - Logout (Fixed at bottom) -->
            <div class="mt-auto p-4 border-t border-white/5">
                <form method="POST" action="{{ route('client.logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-3 w-full px-4 py-3 rounded-xl text-red-400 hover:bg-red-500/10 border border-red-500/20 transition-all">
                        <span class="text-xl">🚪</span>
                        <span class="text-sm font-medium">Déconnexion</span>
                    </button>
                </form>
            </div>
        </aside>
    </div>

    <!-- Mobile Sidebar -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden" onclick="closeSidebar()"></div>
    <aside id="mobile-sidebar" class="fixed top-0 left-0 z-50 w-72 h-full bg-gradient-to-b from-[#11131f] to-[#0a0c15] border-r border-white/5 overflow-y-auto sidebar-transition -translate-x-full flex flex-col">
        <div class="p-6 border-b border-white/5">
            <div class="text-2xl font-extrabold bg-gradient-to-r from-[#e8a020] to-[#fbbf24] bg-clip-text text-transparent">CIBLE CI</div>
            <div class="text-[10px] text-gray-500 mt-1 tracking-wider">ESPACE CLIENT PREMIUM</div>
        </div>

        <nav class="flex-1 p-4 space-y-1">
            <div>
                <div class="nav-category px-4 py-2">MENU PRINCIPAL</div>
                <div class="space-y-1">
                    <a href="{{ route('client.dashboard') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.dashboard') ? 'nav-item-active' : 'nav-item-hover' }}">
                        <span class="text-xl">📊</span>
                        <span class="text-sm font-medium">Tableau de bord</span>
                    </a>
                    <a href="{{ route('client.propositions') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.propositions*') ? 'nav-item-active' : 'nav-item-hover' }}">
                        <span class="text-xl">📋</span>
                        <span class="text-sm font-medium">Propositions</span>
                    </a>
                    <a href="{{ route('client.campagnes') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.campagnes*') ? 'nav-item-active' : 'nav-item-hover' }}">
                        <span class="text-xl">📢</span>
                        <span class="text-sm font-medium">Campagnes</span>
                    </a>
                </div>
            </div>

            <div class="h-px bg-white/5 my-4"></div>

            <div>
                <div class="nav-category px-4 py-2">MON COMPTE</div>
                <div class="space-y-1">
                    <a href="{{ route('client.profil') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.profil*') ? 'nav-item-active' : 'nav-item-hover' }}">
                        <span class="text-xl">👤</span>
                        <span class="text-sm font-medium">Mon profil</span>
                    </a>
                    <a href="{{ route('client.password.change') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all {{ request()->routeIs('client.password*') ? 'nav-item-active' : 'nav-item-hover' }}">
                        <span class="text-xl">🔒</span>
                        <span class="text-sm font-medium">Sécurité</span>
                    </a>
                </div>
            </div>

            <div class="h-px bg-white/5 my-4"></div>

            <div>
                <div class="nav-category px-4 py-2">RESSOURCES</div>
                <div class="space-y-1">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all nav-item-hover">
                        <span class="text-xl">🔍</span>
                        <span class="text-sm font-medium">Recherche</span>
                    </a>
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 transition-all nav-item-hover">
                        <span class="text-xl">📚</span>
                        <span class="text-sm font-medium">Références</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="mt-auto p-4 border-t border-white/5">
            <form method="POST" action="{{ route('client.logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-3 w-full px-4 py-3 rounded-xl text-red-400 hover:bg-red-500/10 border border-red-500/20 transition-all">
                    <span class="text-xl">🚪</span>
                    <span class="text-sm font-medium">Déconnexion</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="sticky top-0 z-40 bg-[#0a0c15]/95 backdrop-blur-xl border-b border-white/5 px-4 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden text-gray-400 hover:text-[#e8a020] transition-colors" onclick="toggleSidebar()">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <h1 class="text-lg font-semibold text-white">@yield('page-title', 'Tableau de bord')</h1>
                </div>
                <div class="flex items-center gap-4">
                    <div class="hidden sm:block text-right">
                        <div class="text-sm font-semibold text-white">{{ $client->name }}</div>
                        <div class="text-[10px] text-gray-500">{{ $client->ncc ?? 'Client' }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-[#e8a020] to-[#fbbf24] flex items-center justify-center text-[#0a0c15] font-bold text-base">
                        {{ strtoupper(mb_substr($client->name, 0, 1)) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Wrapper (grows to push footer down) -->
        <div class="content-wrapper">
            <div class="p-4 lg:p-8">
                @yield('content')
            </div>
        </div>

        <!-- Footer (fixed at bottom) -->
        <footer class="border-t border-white/5 bg-[#0a0c15]">
            <div class="py-6 px-4 lg:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-gray-500">
                    <div>© {{ date('Y') }} CIBLE CI — Tous droits réservés</div>
                    <div class="flex gap-6">
                        <a href="#" class="hover:text-[#e8a020] transition-colors">Mentions légales</a>
                        <a href="#" class="hover:text-[#e8a020] transition-colors">Confidentialité</a>
                        <a href="#" class="hover:text-[#e8a020] transition-colors">Contact</a>
                    </div>
                    <!-- <div class="flex gap-4">
                        <a href="#" class="hover:text-[#e8a020] transition-colors">📘 Facebook</a>
                        <a href="#" class="hover:text-[#e8a020] transition-colors">📸 Instagram</a>
                        <a href="#" class="hover:text-[#e8a020] transition-colors">💼 LinkedIn</a>
                    </div> -->
                </div>
            </div>
        </footer>
    </div>
</div>

<!-- Floating mobile menu button -->
<button class="fixed bottom-5 left-5 z-50 lg:hidden w-12 h-12 rounded-full bg-[#e8a020] shadow-lg flex items-center justify-center hover:bg-[#c47a00] transition-all" onclick="toggleSidebar()">
    <svg class="w-6 h-6 text-[#0a0c15]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>

<!-- Toast container -->
<div id="toast-container" class="fixed bottom-5 right-5 z-50 space-y-2"></div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('mobile-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('-translate-x-full');
        if (overlay) overlay.classList.toggle('hidden');
        document.body.style.overflow = sidebar.classList.contains('-translate-x-full') ? '' : 'hidden';
    }

    function closeSidebar() {
        const sidebar = document.getElementById('mobile-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'border-l-[#10b981]' : type === 'error' ? 'border-l-[#ef4444]' : 'border-l-[#e8a020]';
        toast.className = `bg-[#11131f] border border-white/5 rounded-xl p-3 text-sm text-white shadow-xl animate-slide-in ${bgColor} border-l-4`;
        toast.innerHTML = message;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if(session('success'))
            showToast('{{ session('success') }}');
        @endif
        @if(session('error'))
            showToast('{{ session('error') }}', 'error');
        @endif
        @if(session('warning'))
            showToast('{{ session('warning') }}', 'warning');
        @endif
    });

    // Fermer la sidebar au clic sur un lien sur mobile
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                setTimeout(() => closeSidebar(), 100);
            }
        });
    });
</script>

@stack('scripts')
</body>
</html>