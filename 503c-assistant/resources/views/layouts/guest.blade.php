<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', '503c Assistant') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased dark:bg-slate-900 dark:text-slate-100">
        <script>
            // Apply dark mode before paint to avoid flash
            if (localStorage.getItem('darkMode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        </script>
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-50 dark:bg-slate-900">
            <div class="flex flex-col items-center gap-2">
                <a href="/" class="flex items-center gap-3">
                    <x-application-logo class="w-12 h-12" />
                </a>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">503c Assistant</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">IRB Protocol Drafting Tool</p>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden sm:rounded-xl dark:bg-slate-800 dark:ring-white/10">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
