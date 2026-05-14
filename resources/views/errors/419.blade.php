<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session expired — IRB Assistant</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased">
    <main class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-16">
        <div class="max-w-md w-full text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-100 text-amber-700 mb-6">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold tracking-wide uppercase text-amber-700">419 — Session expired</p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                Your session timed out
            </h1>
            <p class="mt-4 text-base text-slate-600 dark:text-slate-300">
                For security, the page form expired before submission. This usually happens after the page was idle for a while or a long-running action took longer than expected. Please log in again to continue.
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                @auth
                    <a href="{{ route('studies.index') }}" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                        Back to my studies
                    </a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                        Log in again
                    </a>
                @endauth
                <a href="{{ route('welcome') }}" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold border border-slate-300 text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-800 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                    About IRB Assistant
                </a>
            </div>

            <p class="mt-10 text-xs text-slate-500 dark:text-slate-400">
                If this keeps happening during a long analysis, the operation may have taken longer than the server-side timeout. Try again, and if the problem persists contact your administrator.
            </p>
        </div>
    </main>
</body>
</html>
