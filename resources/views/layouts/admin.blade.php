<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>PROGICIA — {{ $title ?? 'Dashboard' }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
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
        <a href="#" class="nav-item">
          <span class="icon">📋</span> Disponibilités
          <span class="nav-badge">64</span>
        </a>
        <a href="#" class="nav-item">
          <span class="icon">🗂️</span> Inventaire
        </a>
        <a href="#" class="nav-item">
          <span class="icon">📁</span> Campagnes
          <span class="nav-badge blue">12</span>
        </a>
        <a href="{{ route('admin.clients.index') }}"
          class="nav-item {{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">
          <span class="icon">👥</span> Clients
        </a>
        <a href="{{ route('admin.external-agencies.index') }}"
          class="nav-item {{ request()->routeIs('admin.external-agencies.*') ? 'active' : '' }}">
          <span class="icon">🏢</span> Régies externes
        </a>
        <a href="#" class="nav-item">
          <span class="icon">📄</span> Propositions
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-label">Opérations</div>
        <a href="#" class="nav-item">
          <span class="icon">✅</span> Confirmations
          <span class="nav-badge red">3</span>
        </a>
        <a href="#" class="nav-item">
          <span class="icon">🏗️</span> Gestion Pose OOH
        </a>
        <a href="#" class="nav-item">
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
        <a href="#" class="nav-item">
          <span class="icon">🔔</span> Alertes
          <span class="nav-badge red">5</span>
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-label">Analyse</div>
        <a href="#" class="nav-item">
          <span class="icon">📈</span> Statistiques
        </a>
        <a href="#" class="nav-item">
          <span class="icon">📑</span> Rapports
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
          <button type="submit" style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:16px;"
                  title="Déconnexion" onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--text3)'">
            ⏻
          </button>
        </form>
      </div>
    </div>

  </aside>

  {{-- ══ CONTENU ══ --}}
  <div class="main-area" style="margin-left:235px;">

    {{-- Topbar --}}
    <div class="topbar">
      <div class="topbar-title">{{ $title ?? 'Dashboard' }}</div>
      <div style="display:flex;align-items:center;gap:10px;">
        <input type="text" class="topbar-search" placeholder="🔍 Rechercher...">
        <button class="btn btn-ghost btn-sm">🔔 <span class="nav-badge red" style="position:relative">5</span></button>
        {{ $topbarActions ?? '' }}
      </div>
    </div>

    {{-- Flash messages --}}
    <div style="padding: 0 20px;">
      @if(session('success'))
        <div class="flash flash-success" style="margin-top:16px;">
          ✓ {{ session('success') }}
        </div>
      @endif
      @if(session('error'))
        <div class="flash flash-error" style="margin-top:16px;">
          ✕ {{ session('error') }}
        </div>
      @endif
    </div>

    {{-- Page --}}
    <div class="page-content">
      {{ $slot }}
    </div>

  </div>

</div>
</body>
</html>