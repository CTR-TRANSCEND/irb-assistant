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

        <!-- Apply dark mode before paint to prevent flash -->
        <script>
            if (localStorage.getItem('darkMode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        </script>
    </head>
    <body class="font-sans antialiased dark:bg-slate-900">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:p-4 focus:bg-white focus:text-indigo-600 focus:font-semibold">Skip to content</a>
        <div class="min-h-screen bg-slate-50 dark:bg-slate-900">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white border-b border-slate-200 dark:bg-slate-800 dark:border-slate-700">
                    <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main id="main-content">
                {{ $slot }}
            </main>
        </div>

        <!-- Dark mode + Toast notification stores -->
        <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('darkMode', {
                on: localStorage.getItem('darkMode') === 'true',
                toggle() {
                    this.on = !this.on;
                    localStorage.setItem('darkMode', this.on);
                    document.documentElement.classList.toggle('dark', this.on);
                },
                init() {
                    document.documentElement.classList.toggle('dark', this.on);
                }
            });

            Alpine.store('toast', {
                items: [],
                add(message, type = 'success', duration = 4000) {
                    const id = Date.now();
                    this.items.push({ id, message, type });
                    setTimeout(() => this.remove(id), duration);
                },
                remove(id) {
                    this.items = this.items.filter(item => item.id !== id);
                }
            });
        });
        </script>

        @if(session('status'))
        <script>
        document.addEventListener('alpine:initialized', () => Alpine.store('toast').add(@json(session('status')), 'success'));
        </script>
        @endif

        <!-- Toast rendering -->
        <div x-data class="toast-container" aria-live="polite" aria-atomic="true">
            <template x-for="toast in $store.toast.items" :key="toast.id">
                <div
                    class="toast"
                    :class="'toast-' + toast.type"
                    x-text="toast.message"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-2"
                ></div>
            </template>
        </div>
    </body>
</html>
