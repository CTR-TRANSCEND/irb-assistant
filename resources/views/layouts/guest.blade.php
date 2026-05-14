<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @hasSection('title')
            <title>@yield('title') — {{ config('app.name', 'IRB Assistant') }}</title>
        @else
            <title>{{ config('app.name', 'IRB Assistant') }}</title>
        @endif

        {{-- Inter is self-hosted via @fontsource/inter (Vite-bundled). The
             previous fonts.bunny.net <link> tags introduced an external
             dependency that conflicted with REQ-UI-017 (no font CDN) and
             also served stale Inter fonts after the M7 self-host landed. --}}

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased dark:bg-slate-900 dark:text-slate-100">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:p-4 focus:bg-white focus:text-indigo-600 focus:font-semibold dark:focus:bg-slate-800">Skip to content</a>
        <script>
            // Apply dark mode before paint to avoid flash
            if (localStorage.getItem('darkMode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        </script>
        <main id="main-content" class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-50 dark:bg-slate-900">
            <div class="flex flex-col items-center gap-2">
                <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="IRB Assistant home">
                    <x-application-logo class="w-12 h-12" aria-hidden="true" />
                </a>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">IRB Assistant</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">IRB Protocol Drafting Tool</p>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white shadow-sm ring-1 ring-slate-900/5 overflow-hidden sm:rounded-xl dark:bg-slate-800 dark:ring-white/10">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
