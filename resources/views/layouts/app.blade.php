<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ config('app.name', 'IndexWatch') }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@stack('styles')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
<link rel="stylesheet" href="{{ asset('css/crud.css') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    (function() {
        const theme = localStorage.getItem('indexwatch-theme') || 'dark';
        const root = document.documentElement;
        if (theme === 'light') {
            root.classList.add('light-mode');
            root.classList.remove('dark');
        } else {
            root.classList.add('dark');
            root.classList.remove('light-mode');
        }
    })();
</script>
</head>
<body>

<div class="shell">

  <!-- ===================== SIDEBAR ===================== -->
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none"><path d="M3 12c0-1 .5-2 1.5-2s1.5 2 2.5 2 1.5-4 2.5-4 1.5 6 2.5 6 1.5-5 2.5-5 1.5 3 2.5 3 1.5-1 2.5-1" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div class="brand-name">IndexWatch<span class="dot">.</span><span class="dim">sql</span></div>
    </div>

    <div class="nav-section-label">Monitoreo</div>
    <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
      Panel de control
    </a>
    <a class="nav-item {{ request()->routeIs('indices') ? 'active' : '' }}" href="{{ route('indices') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
      Lista de índices
    </a>
    <a class="nav-item {{ request()->routeIs('servers.*') ? 'active' : '' }}" href="{{ route('servers.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="5" rx="1.5"/><rect x="4" y="10.5" width="16" height="5" rx="1.5"/><rect x="4" y="17" width="16" height="3" rx="1.5"/></svg>
      Servidores
    </a>
    <a class="nav-item {{ request()->routeIs('contacts.*') ? 'active' : '' }}" href="{{ route('contacts.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
      Contactos
    </a>
    <a class="nav-item {{ request()->routeIs('actions') ? 'active' : '' }}" href="{{ route('actions') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z"/></svg>
      Centro de operaciones
    </a>

    <div class="nav-section-label">Sistema</div>
    <a class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 005 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019 9c.21.45.59.79 1.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      Configuración y alertas
    </a>

    <div class="sidebar-foot">
      <!-- Theme toggle button -->
      <button id="theme-toggle" class="theme-toggle">
        <svg id="theme-icon-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg id="theme-icon-light" class="theme-icon-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1" x2="12" y2="3"/>
          <line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1" y1="12" x2="3" y2="12"/>
          <line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
        <span id="theme-label">Modo oscuro</span>
      </button>
      <div class="pulse-row"><span class="dot-live"></span> {{ $activeServer ? 'Conectado · ' . e($activeServer->name) : 'Sin servidor activo' }}</div>
      <div>Último barrido: {{ $activeServer && $activeServer->last_scanned_at ? $activeServer->last_scanned_at->diffForHumans() : 'Nunca' }}</div>
    </div>
    <!-- User Profile Section -->
    <div class="user-profile-section">
        <div class="user-dropdown" id="userDropdown">
            <a href="{{ route('profile.edit') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="7" r="4"/><path d="M5 21h14a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2z"/></svg>
                Perfil
            </a>
            <div class="divider"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M17 16l4-4m0 0l-4-4m4 4H7m0 5a4 4 0 01-4-4V7a4 4 0 014-4h4"/></svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
        <button class="user-profile-btn" id="userProfileBtn">
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div class="user-info">
                <div class="user-name">{{ auth()->user()->name }}</div>
                <div class="user-email">{{ auth()->user()->email }}</div>
            </div>
            <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M6 9l6 6 6-6"/></svg>
        </button>
    </div>
  </aside> 
  <!-- ===================== MAIN ===================== -->
  <main class="main">
    <!-- Page Content -->
    {{ $slot }}
  </main>
</div>

<!-- ===================== TOAST ===================== -->
<div class="toast" id="toast">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
  <span id="toastMsg">Acción completada</span>
</div>

<script>
    // User profile menu toggle
    document.getElementById('userProfileBtn').addEventListener('click', function(e) {
        e.stopPropagation();
        const menu = document.getElementById('userDropdown');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    });

    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        const btn = document.getElementById('userProfileBtn');
        const menu = document.getElementById('userDropdown');
        if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.style.display = 'none';
        }
    });

    // Theme toggle functionality
    (function() {
        const root = document.documentElement;
        const toggleBtn = document.getElementById('theme-toggle');
        const iconDark = document.getElementById('theme-icon-dark');
        const iconLight = document.getElementById('theme-icon-light');
        const label = document.getElementById('theme-label');

        // Get saved theme or default to 'dark'
        let currentTheme = localStorage.getItem('indexwatch-theme') || 'dark';

        function applyTheme(theme) {
            if (theme === 'light') {
                root.classList.add('light-mode');
                root.classList.remove('dark');
                iconDark.style.display = 'none';
                iconLight.style.display = 'block';
                label.textContent = 'Modo claro';
            } else {
                root.classList.remove('light-mode');
                root.classList.add('dark');
                iconDark.style.display = 'block';
                iconLight.style.display = 'none';
                label.textContent = 'Modo oscuro';
            }
        }

        applyTheme(currentTheme);

        toggleBtn.addEventListener('click', function() {
            currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('indexwatch-theme', currentTheme);
            applyTheme(currentTheme);
        });
    })();
</script>

<script src="{{ asset('js/dashboard.js') }}"></script>
@stack('scripts')

</body>
</html>
