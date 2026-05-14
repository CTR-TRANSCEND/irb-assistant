<x-app-layout>
    @section("title", "Studies")
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Your Studies</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Manage your IRB protocol submissions</p>
            </div>
            <a href="{{ route('studies.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Study
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if($studies->isEmpty())
                <div class="card">
                    <div class="empty-state py-16">
                        <svg class="empty-state-icon" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="empty-state-title">No studies yet</p>
                        <p class="empty-state-text">Create your first study to begin drafting your IRB submissions.</p>
                        <div class="mt-6">
                            <a href="{{ route('studies.create') }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create your first study
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($studies as $study)
                        @php
                            $submissionsByCode = $study->submissions->keyBy(
                                fn ($s) => optional($s->formDefinition)->form_code
                            );
                            $statusBadgeMap = [
                                'draft'         => 'badge-blue',
                                'submitted'     => 'badge-indigo',
                                'under_review'  => 'badge-amber',
                                'approved'      => 'badge-green',
                                'rejected'      => 'badge-red',
                                'withdrawn'     => 'badge-gray',
                                'tracking_only' => 'badge-gray',
                            ];
                            $formCodes = ['HRP-503', 'HRP-503c', 'HRP-398'];
                        @endphp
                        <a href="{{ route('studies.show', ['uuid' => $study->uuid]) }}"
                           class="card group hover:shadow-md hover:ring-indigo-200 dark:hover:ring-indigo-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                           aria-label="Open study: {{ $study->nickname ?? $study->application_title ?? 'Untitled study' }}">
                            <div class="p-5">
                                <div class="flex items-start gap-3">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-slate-900 group-hover:text-indigo-600 transition-colors dark:text-slate-100 dark:group-hover:text-indigo-400 truncate">
                                            {{ $study->nickname ?? 'Untitled study' }}
                                        </h3>
                                        @if($study->application_title)
                                            <p class="mt-0.5 text-sm text-slate-700 dark:text-slate-300 truncate" title="{{ $study->application_title }}">
                                                {{ Str::limit($study->application_title, 60) }}
                                            </p>
                                        @endif
                                        @if($study->pi_name)
                                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">PI: {{ $study->pi_name }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Created {{ $study->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>

                                {{-- 3 progress badges — REQ-IRB-FORMSV2-049a.
                                     HRP-398 is permanently `tracking_only` (REQ-014a) so a status-only
                                     pill is uninformative. Per REQ-049a it MUST render the
                                     worksheet_assist_state aggregate as "N/M items addressed". --}}
                                @php
                                    // HRP-398 has ~40 guidance items per the JSON schema. Phase 6
                                    // ships the full panel; Phase 4 PR-1 shows the count even
                                    // though the worksheet UI isn't wired yet.
                                    $hrp398ItemTotal = 40;
                                @endphp
                                <div class="mt-4 flex flex-wrap gap-2" role="list" aria-label="Submission statuses">
                                    @foreach($formCodes as $code)
                                        @php
                                            $sub = $submissionsByCode[$code] ?? null;
                                        @endphp
                                        @if($code === 'HRP-398')
                                            @php
                                                $addressed = $sub
                                                    ? $sub->worksheetAssistStates()
                                                        ->where('status', 'addressed')
                                                        ->count()
                                                    : 0;
                                                $badgeText = "HRP-398: {$addressed}/{$hrp398ItemTotal} items addressed";
                                            @endphp
                                            <span class="badge badge-gray" role="listitem" aria-label="{{ $badgeText }}">
                                                {{ $badgeText }}
                                            </span>
                                        @else
                                            @php
                                                $status = $sub ? ($sub->status ?? 'draft') : 'draft';
                                                $badgeClass = $statusBadgeMap[$status] ?? 'badge-gray';
                                            @endphp
                                            <span class="badge {{ $badgeClass }}" role="listitem" aria-label="{{ $code }}: {{ $status }}">
                                                {{ $code }}: {{ str_replace('_', ' ', $status) }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
