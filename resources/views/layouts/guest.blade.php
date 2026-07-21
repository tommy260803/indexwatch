<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'IndexWatch') }}</title>

        <!-- Fonts - Material Design uses Roboto -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            (function() {
                const theme = localStorage.getItem('indexwatch-theme') || 'dark';
                const root = document.documentElement;
                if (theme === 'light') {
                    root.classList.remove('dark');
                } else {
                    root.classList.add('dark');
                }
            })();
        </script>
        <style>
            * {
                font-family: 'Roboto', sans-serif;
            }
            .md3-elevation-3 {
                box-shadow: 0 10px 20px rgba(0,0,0,0.1), 0 6px 6px rgba(0,0,0,0.1);
            }
            .md3-ripple {
                position: relative;
                overflow: hidden;
            }
            .md3-ripple::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.3);
                transform: translate(-50%, -50%);
                transition: width 0.3s, height 0.3s;
            }
            .md3-ripple:active::after {
                width: 200px;
                height: 200px;
            }
        </style>
    </head>
    <body class="antialiased bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-900 dark:to-gray-800 dark:text-gray-100">
        <div class="min-h-screen flex flex-col sm:justify-center items-center p-4 relative">
            <!-- Theme toggle -->
            <button id="theme-toggle" class="absolute top-4 right-4 p-2 rounded-full bg-white/80 dark:bg-gray-800/80 text-gray-700 dark:text-gray-200 shadow-sm hover:bg-white dark:hover:bg-gray-700 transition-colors" aria-label="Cambiar tema">
                <svg id="theme-icon-dark" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
                <svg id="theme-icon-light" class="w-5 h-5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
            </button>

            <!-- Logo Section -->
            <div class="mb-8 text-center">
                <div class="w-20 h-20 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-3xl flex items-center justify-center mb-4 mx-auto md3-elevation-3">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">IndexWatch</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Monitoreo de índices de bases de datos</p>
            </div>

            <!-- Card -->
            <div class="w-full sm:max-w-md bg-white dark:bg-gray-900 rounded-3xl p-8 md3-elevation-3">
                {{ $slot }}
            </div>
        </div>

        <script>
            (function() {
                const root = document.documentElement;
                const toggleBtn = document.getElementById('theme-toggle');
                const iconDark = document.getElementById('theme-icon-dark');
                const iconLight = document.getElementById('theme-icon-light');
                let currentTheme = localStorage.getItem('indexwatch-theme') || 'dark';

                function applyTheme(theme) {
                    if (theme === 'light') {
                        root.classList.remove('dark');
                        iconDark.classList.add('hidden');
                        iconLight.classList.remove('hidden');
                    } else {
                        root.classList.add('dark');
                        iconDark.classList.remove('hidden');
                        iconLight.classList.add('hidden');
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
    </body>
</html>
