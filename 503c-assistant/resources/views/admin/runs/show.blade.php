<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Admin / Observability</p>
                <h2 class="text-xl font-bold text-slate-900">Analysis Run Detail</h2>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Back link --}}
            <a
                href="{{ route('admin.index', ['tab' => 'observability']) }}"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors"
                aria-label="Back to Observability tab"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Observability
            </a>

            {{-- Run identity card --}}
            <section class="card p-6" aria-label="Run identity">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base font-semibold text-slate-900 font-mono">{{ $run->uuid }}</h3>
                            <span @class([
                                'badge text-xs',
                                'badge-green'  => $run->status === 'succeeded',
                                'badge-red'    => $run->status === 'failed',
                                'badge-blue'   => $run->status === 'running',
                                'badge-amber'  => $run->status === 'queued',
                                'badge-gray'   => !in_array($run->status, ['succeeded', 'failed', 'running', 'queued']),
                            ]) role="status" aria-label="Run status: {{ $run->status }}">{{ $run->status }}</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Created {{ $run->created_at?->diffForHumans() ?? '—' }}</p>
                    </div>
                </div>
            </section>

            {{-- Stat cards --}}
            <section aria-label="Run metrics">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="card p-5 text-center">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Duration</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">
                            @if($durationSeconds !== null)
                                {{ $durationSeconds }}<span class="text-sm font-normal text-slate-500">s</span>
                            @else
                                <span class="text-slate-400 text-base">—</span>
                            @endif
                        </p>
                    </div>

                    <div class="card p-5 text-center">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Fields</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">
                            @if($fieldCount !== null)
                                {{ $fieldCount }}
                            @else
                                <span class="text-slate-400 text-base">—</span>
                            @endif
                        </p>
                    </div>

                    <div class="card p-5 text-center">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Prompt Version</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900 text-sm font-mono">{{ $run->prompt_version ?? '—' }}</p>
                    </div>

                    <div class="card p-5 text-center">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Provider</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 truncate">{{ $run->provider?->name ?? '—' }}</p>
                        @if($run->provider?->model)
                            <p class="text-xs text-slate-500 truncate">{{ $run->provider->model }}</p>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Details table --}}
            <section class="card overflow-hidden" aria-label="Run details">
                <div class="px-5 py-4 bg-slate-50 border-b border-slate-100">
                    <h4 class="text-sm font-semibold text-slate-900">Details</h4>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="grid grid-cols-3 gap-4 px-5 py-3">
                        <dt class="font-medium text-slate-600">Project</dt>
                        <dd class="col-span-2 text-slate-900">{{ $run->project?->name ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-5 py-3">
                        <dt class="font-medium text-slate-600">Initiated by</dt>
                        <dd class="col-span-2 text-slate-900">
                            @if($run->createdBy)
                                {{ $run->createdBy->name }} ({{ $run->createdBy->email }})
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-5 py-3">
                        <dt class="font-medium text-slate-600">Started at</dt>
                        <dd class="col-span-2 text-slate-900">{{ $run->started_at?->toDateTimeString() ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-5 py-3">
                        <dt class="font-medium text-slate-600">Finished at</dt>
                        <dd class="col-span-2 text-slate-900">{{ $run->finished_at?->toDateTimeString() ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-5 py-3">
                        <dt class="font-medium text-slate-600">Provider type</dt>
                        <dd class="col-span-2 text-slate-900">{{ $run->provider?->provider_type ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-5 py-3">
                        <dt class="font-medium text-slate-600">Model</dt>
                        <dd class="col-span-2 text-slate-900">{{ $run->provider?->model ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Error (if any) --}}
            @if($run->error)
                <section aria-label="Run error">
                    <div class="alert alert-error">
                        <div class="flex items-start gap-2">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <p class="font-semibold text-sm">Error</p>
                                <p class="mt-1 text-sm">{{ $run->error }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

        </div>
    </div>
</x-app-layout>
