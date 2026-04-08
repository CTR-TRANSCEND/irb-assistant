<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <nav class="breadcrumb mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('projects.index') }}" class="breadcrumb-item" aria-current="page">Projects</a>
                </nav>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Projects</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Manage your IRB protocol drafts</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Create Project -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('projects.store') }}" class="flex gap-3 items-end" x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="name" value="New project name" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" placeholder="e.g. Phase II Clinical Trial Protocol" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <x-primary-button :disabled="false" x-bind:disabled="loading">
                            <span x-show="!loading" class="inline-flex items-center">
                                <svg class="w-4 h-4 mr-1.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Create
                            </span>
                            <span x-show="loading" class="inline-flex items-center" x-cloak>
                                <span class="spinner mr-1.5" aria-hidden="true"></span>
                                Creating...
                            </span>
                        </x-primary-button>
                    </form>
                </div>
            </div>

            <!-- Projects List -->
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wider mb-3 dark:text-slate-400">Your projects</h3>

                @if($projects->isEmpty())
                    <div class="card">
                        <div class="empty-state py-16">
                            <svg class="empty-state-icon" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <p class="empty-state-title">No projects yet</p>
                            <p class="empty-state-text">Create your first project to get started with IRB protocol drafting.</p>
                            <div class="mt-4">
                                <label for="name" class="sr-only">New project name</label>
                                <a href="#name" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    <svg class="w-4 h-4 mr-1.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Create your first project
                                </a>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($projects as $project)
                            <a href="{{ route('projects.show', ['project' => $project->uuid]) }}" class="card group hover:shadow-md hover:ring-indigo-200 dark:hover:ring-indigo-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                <div class="p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-semibold text-slate-900 group-hover:text-indigo-600 truncate transition-colors dark:text-slate-100 dark:group-hover:text-indigo-400">{{ $project->name }}</h4>
                                            <p class="text-sm text-slate-600 mt-1 dark:text-slate-400">Updated {{ $project->updated_at->diffForHumans() }}</p>
                                        </div>
                                        <span @class([
                                            'badge',
                                            'badge-blue' => $project->status === 'draft',
                                            'badge-amber' => $project->status === 'analysis',
                                            'badge-indigo' => $project->status === 'review',
                                            'badge-green' => $project->status === 'export',
                                            'badge-gray' => !in_array($project->status, ['draft', 'analysis', 'review', 'export']),
                                        ]) aria-label="Status: {{ $project->status }}">{{ $project->status }}</span>
                                    </div>
                                    @php
                                        $total = $project->required_total_count ?? 0;
                                        $done = $project->required_completed_count ?? 0;
                                        $pct = $total > 0 ? round(($done / $total) * 100) : 0;
                                    @endphp
                                    @if($total > 0)
                                        <div class="mt-4">
                                            <div class="flex items-center justify-between text-xs text-slate-600 mb-1 dark:text-slate-400">
                                                <span>Progress</span>
                                                <span>{{ $done }}/{{ $total }} fields</span>
                                            </div>
                                            <div class="progress-bar" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $pct }}% complete">
                                                <div class="progress-bar-fill" style="width: {{ $pct }}%"></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
