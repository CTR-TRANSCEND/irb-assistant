<x-app-layout>
    @section("title", "New study")
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('studies.index') }}"
               class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
               aria-label="Back to Studies">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <nav class="breadcrumb mb-0.5" aria-label="Breadcrumb">
                    <a href="{{ route('studies.index') }}" class="breadcrumb-item">Studies</a>
                    <span class="breadcrumb-separator" aria-hidden="true">/</span>
                    <span class="breadcrumb-item-current">New Study</span>
                </nav>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Create a new Study</h1>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info mb-6">
                        <div class="flex gap-2">
                            <svg class="w-5 h-5 flex-shrink-0 text-sky-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm">A Study automatically creates <strong>3 submission drafts</strong>: HRP-503 (full application), HRP-503c (engagement determination), and HRP-398 (AI considerations worksheet). You can fill any or all of them; HRP-398 is guidance-only and not submitted to the IRB.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('studies.store') }}" x-data="{ loading: false }" @submit="loading = true">
                        @csrf

                        <div class="space-y-5">
                            <div>
                                <x-input-label for="nickname" value="Study nickname (optional)" />
                                <x-text-input id="nickname" name="nickname" type="text" class="mt-1 block w-full"
                                    value="{{ old('nickname') }}"
                                    placeholder="e.g., Phase II XYZ-123 Trial" />
                                <p class="form-help">A short label used in lists and breadcrumbs.</p>
                                <x-input-error :messages="$errors->get('nickname')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="application_title" value="Application title (optional)" />
                                <x-text-input id="application_title" name="application_title" type="text" class="mt-1 block w-full"
                                    value="{{ old('application_title') }}"
                                    maxlength="500"
                                    placeholder="e.g., A Phase II Open-Label Trial of XYZ-123 in Patients with Refractory Glioblastoma" />
                                <p class="form-help">The formal IRB application title. The AI uses this for context when proposing field values.</p>
                                <x-input-error :messages="$errors->get('application_title')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="pi_name" value="Principal Investigator (optional)" />
                                <x-text-input id="pi_name" name="pi_name" type="text" class="mt-1 block w-full"
                                    value="{{ old('pi_name', auth()->user()->name ?? '') }}"
                                    maxlength="255"
                                    placeholder="Dr. Jane Doe" />
                                <p class="form-help">Auto-filled from your account; edit if a different PI leads this study.</p>
                                <x-input-error :messages="$errors->get('pi_name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="project_summary" value="Project summary (optional)" />
                                <textarea
                                    id="project_summary"
                                    name="project_summary"
                                    rows="4"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 text-sm"
                                    placeholder="What is the study about, who are the participants, what is the intervention or observation?">{{ old('project_summary') }}</textarea>
                                <p class="form-help">Background fed to the AI so drafts reference your actual study instead of generic placeholders.</p>
                                <x-input-error :messages="$errors->get('project_summary')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="oversight" value="Oversight / sponsor (optional)" />
                                <x-text-input id="oversight" name="oversight" type="text" class="mt-1 block w-full"
                                    value="{{ old('oversight') }}"
                                    placeholder="e.g., NIH R01, internal funding" />
                                <x-input-error :messages="$errors->get('oversight')" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-between gap-4">
                            <a href="{{ route('studies.index') }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors dark:bg-slate-800 dark:text-slate-200 dark:border-slate-600 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                                Cancel
                            </a>
                            <x-primary-button x-bind:disabled="loading">
                                <span x-show="!loading" class="inline-flex items-center">
                                    <svg class="w-4 h-4 mr-1.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create Study
                                </span>
                                <span x-show="loading" class="inline-flex items-center" x-cloak>
                                    <span class="spinner mr-1.5" aria-hidden="true"></span>
                                    Creating...
                                </span>
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
