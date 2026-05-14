<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>IRB Assistant — HRP-503, HRP-503c, and HRP-398 Form Drafting</title>
    <meta name="description" content="IRB Assistant — local-first Laravel web app to help researchers draft HRP-503, HRP-503c, and HRP-398 IRB forms from uploaded study documents. Production deployment: https://ignet.org/irb-assistant">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-slate-900 antialiased bg-slate-50 dark:bg-slate-900 dark:text-slate-100">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:text-indigo-700 focus:px-3 focus:py-2 focus:rounded-md focus:ring-2 focus:ring-indigo-600">Skip to content</a>

    <header class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-application-logo class="w-8 h-8" aria-label="IRB Assistant logo" />
                <span class="font-semibold text-slate-900 dark:text-white">IRB Assistant</span>
            </div>
            <nav aria-label="Primary" class="flex items-center gap-2 sm:gap-3">
                @auth
                    <a href="{{ route('studies.index') }}"
                       class="inline-flex items-center px-3 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                        Go to your studies
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-700 hover:text-indigo-700 dark:text-slate-200 dark:hover:text-indigo-400 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-600">
                        Log in
                    </a>
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center px-3 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                        Register
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main">
        <section aria-labelledby="hero-heading" class="px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
            <div class="max-w-3xl mx-auto text-center">
                <p class="text-sm font-semibold tracking-wide uppercase text-indigo-700 dark:text-indigo-400">University of North Dakota — IRB Tooling</p>
                <p class="mt-1 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-slate-400">In collaboration with Sanford Health</p>
                <h1 id="hero-heading" class="mt-3 text-4xl sm:text-5xl font-bold tracking-tight text-slate-900 dark:text-white">
                    IRB Assistant
                </h1>
                <p class="mt-5 text-lg sm:text-xl text-slate-600 dark:text-slate-300">
                    HRP-503, HRP-503c, and HRP-398 IRB Form Drafting Assistant for researchers. Upload your study documents, get an evidence-grounded AI first pass, then export a filled DOCX.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                    @auth
                        <a href="{{ route('studies.index') }}"
                           class="inline-flex items-center justify-center w-full sm:w-auto min-h-[44px] px-6 py-3 text-base font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                            Go to your studies
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center justify-center w-full sm:w-auto min-h-[44px] px-6 py-3 text-base font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                            Log in
                        </a>
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center justify-center w-full sm:w-auto min-h-[44px] px-6 py-3 text-base font-semibold border border-indigo-600 text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-800 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                            Register
                        </a>
                    @endauth
                </div>
                <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">
                    Production deployment:
                    <span class="font-mono text-slate-700 dark:text-slate-300">https://ignet.org/irb-assistant</span>
                </p>
            </div>
        </section>

        <section aria-labelledby="features-heading" class="px-4 sm:px-6 lg:px-8 pb-20">
            <div class="max-w-5xl mx-auto">
                <h2 id="features-heading" class="text-2xl sm:text-3xl font-bold text-center text-slate-900 dark:text-white">
                    How it works
                </h2>
                <ol class="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <li class="bg-white dark:bg-slate-800 ring-1 ring-slate-900/5 dark:ring-white/10 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span aria-hidden="true" class="inline-flex w-9 h-9 items-center justify-center rounded-md bg-indigo-50 text-indigo-700 dark:bg-slate-700 dark:text-indigo-300 font-bold">1</span>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Upload documents</h3>
                        </div>
                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                            Upload your study documents (protocol, consent, recruitment materials). Files stay on your institution's server.
                        </p>
                    </li>
                    <li class="bg-white dark:bg-slate-800 ring-1 ring-slate-900/5 dark:ring-white/10 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span aria-hidden="true" class="inline-flex w-9 h-9 items-center justify-center rounded-md bg-indigo-50 text-indigo-700 dark:bg-slate-700 dark:text-indigo-300 font-bold">2</span>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">AI First-Pass with Evidence</h3>
                        </div>
                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                            An LLM extracts and proposes draft answers for each HRP-503, HRP-503c, or HRP-398 field, each grounded in evidence chunks you can verify.
                        </p>
                    </li>
                    <li class="bg-white dark:bg-slate-800 ring-1 ring-slate-900/5 dark:ring-white/10 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span aria-hidden="true" class="inline-flex w-9 h-9 items-center justify-center rounded-md bg-indigo-50 text-indigo-700 dark:bg-slate-700 dark:text-indigo-300 font-bold">3</span>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">DOCX Export</h3>
                        </div>
                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                            Confirm the answers and export a filled HRP-503, HRP-503c, or HRP-398 <code class="font-mono text-xs">.docx</code> ready for IRB submission.
                        </p>
                    </li>
                </ol>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-slate-500 dark:text-slate-400 space-y-3">
            <p class="text-center sm:text-left">
                Developed by Dr. Junguk Hur, Associate Professor, University of North Dakota School of Medicine and Health Sciences.
                Supported by TRANSCEND RDCDC (NIH/NIGMS P20GM155890).
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-slate-200 dark:border-slate-800 pt-3">
                <p>{{ config('app.name', 'IRB Assistant') }} &middot; {{ config('app.version', 'v0.1') }}</p>
                <p>University of North Dakota &middot; Sanford Health</p>
            </div>
        </div>
    </footer>
</body>
</html>
