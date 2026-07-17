<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    <body class="antialiased bg-gradient-to-br from-blue-50 to-indigo-50">
        <div class="min-h-screen flex flex-col sm:justify-center items-center p-4">
            <!-- Logo Section -->
            <div class="mb-8 text-center">
                <div class="w-20 h-20 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-3xl flex items-center justify-center mb-4 mx-auto md3-elevation-3">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">IndexWatch</h1>
                <p class="text-gray-500 mt-1">Monitoreo de índices de bases de datos</p>
            </div>

            <!-- Card -->
            <div class="w-full sm:max-w-md bg-white rounded-3xl p-8 md3-elevation-3">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
