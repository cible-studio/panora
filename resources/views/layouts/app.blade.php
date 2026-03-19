<!-- Layout/app.blade.php -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CIBLE CI — {{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-900 text-white flex h-screen overflow-hidden">

    {{-- SIDEBAR --}}
    <aside class="w-56 bg-gray-900 border-r border-gray-700 flex flex-col flex-shrink-0 h-screen overflow-y-auto">

        {{-- Logo --}}
        <div class="p-5 border-b border-gray-700">
            <div class="text-yellow-400 font-black text-xl">CIBLE CI</div>
            <div class="text-gray-400 text-xs">RÉGIE OOH V4</div>
        </div>

        {{-- User role --}}
        <div class="px-4 py-3 border-b border-gray-700">
            <span class="bg-yellow-400 text-gray-900 text-xs font-bold px-3 py-1 rounded-full">
                🛡️ {{ ucfirst(auth()->user()->role->value) }}
            </span>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-4 space-y-1">

            <div class="text-gray-500 text-xs font-semibold uppercase tracking-wider px-2 mb-2">
                Principal
            </div>

            <a href="/dashboard"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm
               {{ request()->is('dashboard') ? 'bg-yellow-400 text-gray-900 font-semibold' : 'text-gray-300 hover:bg-gray-800' }}">
                <span>📊</span> Tableau de bord
            </a>

            <a href="/admin/panels"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm
               {{ request()->is('admin/panels*') ? 'bg-yellow-400 text-gray-900 font-semibold' : 'text-gray-300 hover:bg-gray-800' }}">
                <span>🪧</span>
                <span>Disponibilités</span>
                <span class="ml-auto bg-yellow-400 text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full">64</span>
            </a>

            <a href="/admin/panels"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>📋</span> Inventaire
            </a>

            <a href="#"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>📢</span>
                <span>Campagnes</span>
                <span class="ml-auto bg-yellow-400 text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full">12</span>
            </a>

            <a href="#"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>🏢</span> Clients
            </a>

            <a href="#"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>📄</span> Propositions
            </a>

            <div class="text-gray-500 text-xs font-semibold uppercase tracking-wider px-2 mt-4 mb-2">
                Opérations
            </div>

            <a href="#"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>✅</span>
                <span>Confirmations</span>
                <span class="ml-auto bg-yellow-400 text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full">3</span>
            </a>

            <a href="/admin/pose-tasks"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>🏗️</span> Gestion Pose OOH
            </a>

            <a href="#"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>📸</span> Piges Photos
            </a>

            <a href="#"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>📤</span> Export Piges
            </a>

            <a href="#"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>🏛️</span> Taxes Communes
            </a>

            <a href="#"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>💰</span> Facturation
            </a>

            <a href="/admin/alerts"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>🔔</span>
                <span>Alertes</span>
                <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">5</span>
            </a>

            <div class="text-gray-500 text-xs font-semibold uppercase tracking-wider px-2 mt-4 mb-2">
                Analyse
            </div>

            <a href="/admin/map"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>🗺️</span> Carte & Heatmap
            </a>

            <a href="#"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>📈</span> Rapports
            </a>

            @if(auth()->user()->isAdmin())
            <div class="text-gray-500 text-xs font-semibold uppercase tracking-wider px-2 mt-4 mb-2">
                Administration
            </div>

            <a href="/admin/users"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>👥</span> Utilisateurs
            </a>

            <a href="/admin/settings/communes"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>⚙️</span> Paramètres
            </a>

            <a href="/admin/maintenances"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-800">
                <span>🔧</span> Maintenance
            </a>
            @endif

        </nav>

        {{-- Logout --}}
        <div class="p-4 border-t border-gray-700">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full text-left text-gray-400 hover:text-white text-sm px-3 py-2 rounded-lg hover:bg-gray-800">
                    🚪 Déconnexion
                </button>
            </form>
        </div>

    </aside>

    {{-- MAIN --}}
    <main class="flex-1 overflow-y-auto bg-gray-900">

        {{-- TOPBAR --}}
        <div class="sticky top-0 z-10 bg-gray-900 border-b border-gray-700 px-6 py-3 flex items-center justify-between">
            <div class="text-white font-semibold text-lg">
                {{ $header ?? 'Tableau de bord' }}
            </div>
            <div class="flex items-center gap-4">
                <input type="text" placeholder="Rechercher..."
                    class="bg-gray-800 text-gray-300 text-sm px-4 py-2 rounded-lg border border-gray-600 w-48 focus:outline-none focus:border-yellow-400">
                <div class="text-gray-300 text-sm">
                    👤 {{ auth()->user()->name }}
                </div>
            </div>
        </div>

        {{-- CONTENT --}}
        <div class="p-6">
            {{ $slot }}
        </div>

    </main>

</body>
</html>

