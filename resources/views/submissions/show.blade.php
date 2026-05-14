<x-app-layout>
    @section('title', $formDefinition->form_code.' — '.($study->nickname ?: 'Study'))
    {{-- SPEC-IRB-FORMSV2-004 §C.4 — Submission renderer
         SPEC-IRB-FORMSV2-005 — Phase 5: HRP-503 two-column layout + section group nav
         REQ-041 (24-renderer set), REQ-048 (WCAG 2.1 AA), REQ-049 (UX parity),
         REQ-053 (Accept Draft), REQ-054 (assistance_mode toggle),
         REQ-P5-001 (section-group nav) --}}
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <a href="{{ route('studies.show', ['uuid' => $study->uuid]) }}"
                   class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
                   aria-label="Back to Study">
                    <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <nav class="breadcrumb mb-0.5" aria-label="Breadcrumb">
                        <a href="{{ route('studies.index') }}" class="breadcrumb-item">Studies</a>
                        <span class="breadcrumb-separator" aria-hidden="true">/</span>
                        <a href="{{ route('studies.show', ['uuid' => $study->uuid]) }}" class="breadcrumb-item">{{ $study->nickname ?? 'Untitled study' }}</a>
                        <span class="breadcrumb-separator" aria-hidden="true">/</span>
                        <span class="breadcrumb-item-current">{{ $formDefinition->form_code }}</span>
                    </nav>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $formDefinition->title ?? $formDefinition->form_code }}</h1>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                {{-- Status badge --}}
                @php
                    $statusBadgeMap = [
                        'draft'         => 'badge-blue',
                        'submitted'     => 'badge-indigo',
                        'under_review'  => 'badge-amber',
                        'approved'      => 'badge-green',
                        'rejected'      => 'badge-red',
                        'withdrawn'     => 'badge-gray',
                        'tracking_only' => 'badge-gray',
                    ];
                    $badgeClass = $statusBadgeMap[$submission->status] ?? 'badge-gray';
                @endphp
                <span class="badge {{ $badgeClass }}" role="status" aria-label="Submission status: {{ $submission->status }}">
                    {{ str_replace('_', ' ', $submission->status) }}
                </span>

                {{-- Assistance mode toggle — REQ-054 --}}
                @include('submissions._assistance_mode_toggle')
            </div>
        </div>
    </x-slot>

    @php
        // Outstanding #73: HRP-503 review tab uses a wider container so the
        // nav-left + 3/4-content grid doesn't read as left-confined on wide
        // monitors. All other form/tab combinations keep the default max-w-7xl.
        //
        // Smart default tab (Outstanding #78 — user-reported UX): if the
        // submission has zero answers yet, land on Analyze. Otherwise land
        // on Review where the user was likely editing.
        $hasAnyAnswers = $submission->answers->isNotEmpty();
        $defaultTab = $hasAnyAnswers ? 'review' : 'analyze';
        $currentTab = request()->query('tab', $defaultTab);
        $wrapperWidthClass = ($formDefinition->form_code === 'HRP-503' && $currentTab === 'review')
            ? 'max-w-screen-2xl'
            : 'max-w-7xl';
    @endphp

    <div class="py-6">
        <div class="{{ $wrapperWidthClass }} mx-auto sm:px-6 lg:px-8">

            {{-- Tab navigation --}}
            <div class="card mb-6">
                <nav class="tab-nav overflow-x-auto" aria-label="Submission sections">
                    @php
                        // Outstanding #78 — natural workflow order:
                        // Analyze (start here) → Review (read & edit) → Documents
                        // (link back to Study) → Export (generate DOCX).
                        $tabs = [
                            'analyze'   => ['label' => 'Analyze',   'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                            'review'    => ['label' => 'Review',    'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                            'documents' => ['label' => 'Documents', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                            'export'    => ['label' => 'Export',    'icon' => 'M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ];
                    @endphp
                    {{-- WCAG 4.1.2 (Batch C C8): server-rendered tab links should
                         use aria-current="page", NOT role="tab" + aria-selected,
                         because we don't implement arrow-key tab-widget navigation.
                         Removed role="tablist" / role="tab" / aria-selected. --}}
                    @foreach($tabs as $tabKey => $tabInfo)
                        <a href="{{ request()->fullUrlWithQuery(['tab' => $tabKey]) }}"
                           class="tab-link flex items-center gap-2 whitespace-nowrap {{ $currentTab === $tabKey ? 'tab-link-active' : 'tab-link-inactive' }}"
                           @if($currentTab === $tabKey) aria-current="page" @endif>
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tabInfo['icon'] }}"/>
                            </svg>
                            {{ $tabInfo['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="p-6" aria-label="{{ $tabs[$currentTab]['label'] ?? 'Content' }}">

                    {{-- ============ REVIEW TAB ============ --}}
                    @if($currentTab === 'review')
                        @php
                            $isHrp503 = $formDefinition->form_code === 'HRP-503';

                            // Compute answer stats for right rail
                            $allTopLevelQuestions = $formDefinition->sections->flatMap(
                                fn($s) => $s->questions->filter(fn($q) => is_null($q->parent_question_id) && $q->question_type !== 'group_label')
                            );
                            $totalQuestions = $allTopLevelQuestions->count();
                            $answeredQuestions = $allTopLevelQuestions->filter(
                                fn($q) => isset($answersByQuestionKey[$q->question_key])
                            )->count();
                        @endphp

                        @if($isHrp503)
                            {{-- ── HRP-503: CSS grid — nav (1fr) | content (3fr) ──
                                 The partial renders both mobile (md:hidden) and desktop
                                 (hidden md:block) variants. Including it ONCE prevents
                                 the duplicate id="section-nav-mobile" parse error
                                 (WCAG 4.1.1; Batch B B3). --}}

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                {{-- Section nav: mobile accordion + desktop sticky sidebar --}}
                                <div class="md:col-span-1">
                                    @include('submissions._section_group_nav')
                                </div>

                                {{-- Main content: sections + questions --}}
                                <div class="md:col-span-3 space-y-6">

                                    {{-- Routing outcome banner — REQ-044 stop_* --}}
                                    @if($submission->routing_outcome)
                                        @include('submissions._routing_banner', [
                                            'routingOutcome'   => $submission->routing_outcome,
                                            'routingOutcomeAt' => $submission->routing_outcome_at,
                                        ])
                                    @endif

                                    {{-- Sections --}}
                                    @foreach($formDefinition->sections->sortBy('display_order') as $section)
                                        @include('submissions._section', [
                                            'section'              => $section,
                                            'answersByQuestionKey' => $answersByQuestionKey,
                                            'submission'           => $submission,
                                            'sectionVisibility'    => $sectionVisibility,
                                        ])
                                    @endforeach
                                </div>
                            </div>

                        @else
                            {{-- ── HRP-503c and other forms: single-column on mobile,
                                 main + right rail on md+ (Batch B B1: was lg: which
                                 dropped the rail below content at 768-1023 px tablets) --}}
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                {{-- Main content: sections + questions --}}
                                <div class="md:col-span-3 space-y-6">

                                    {{-- Routing outcome banner — REQ-044 stop_* --}}
                                    @if($submission->routing_outcome)
                                        @include('submissions._routing_banner', [
                                            'routingOutcome'   => $submission->routing_outcome,
                                            'routingOutcomeAt' => $submission->routing_outcome_at,
                                        ])
                                    @endif

                                    {{-- Sections --}}
                                    @foreach($formDefinition->sections->sortBy('display_order') as $section)
                                        @include('submissions._section', [
                                            'section'              => $section,
                                            'answersByQuestionKey' => $answersByQuestionKey,
                                            'submission'           => $submission,
                                            'sectionVisibility'    => $sectionVisibility,
                                        ])
                                    @endforeach
                                </div>

                                {{-- Right rail: progress indicator --}}
                                <div class="md:col-span-1">
                                    <div class="sticky top-6 space-y-4">
                                        <div class="card p-4">
                                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Progress</h3>
                                            @php
                                                $pct = $totalQuestions > 0
                                                    ? round(($answeredQuestions / $totalQuestions) * 100)
                                                    : 0;
                                            @endphp
                                            <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-400 mb-2">
                                                <span>Answered</span>
                                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $answeredQuestions }} / {{ $totalQuestions }}</span>
                                            </div>
                                            <div class="progress-bar" role="progressbar"
                                                 aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"
                                                 aria-label="{{ $pct }}% complete">
                                                <div class="progress-bar-fill" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 text-center">{{ $pct }}% complete</p>
                                        </div>

                                        {{-- Analyze button --}}
                                        <div class="card p-4">
                                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-2">AI Analysis</h3>
                                            <p class="text-xs text-slate-600 dark:text-slate-400 mb-3">Auto-fill fields with evidence from your uploaded documents.</p>
                                            <form method="POST"
                                                  action="{{ route('submissions.analyze', ['submission_uuid' => $submission->uuid ?? $submission->id]) }}"
                                                  x-data="{ loading: false }"
                                                  @submit="loading = true; $dispatch('open-analysis-progress')">
                                                @csrf
                                                <button type="submit"
                                                        class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                        x-bind:disabled="loading">
                                                    <span x-show="!loading" class="inline-flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                        </svg>
                                                        Run Analysis
                                                    </span>
                                                    <span x-show="loading" class="inline-flex items-center gap-1.5" x-cloak>
                                                        <span class="spinner spinner-sm" aria-hidden="true"></span>
                                                        Starting...
                                                    </span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                    {{-- ============ DOCUMENTS TAB ============ --}}
                    @elseif($currentTab === 'documents')
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">Documents are managed at the Study level</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Upload and manage your study documents from the study overview page.</p>
                            <div class="mt-4">
                                <a href="{{ route('studies.show', ['uuid' => $study->uuid]) }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Go to study overview
                                </a>
                            </div>
                        </div>

                    {{-- ============ ANALYZE TAB ============ --}}
                    @elseif($currentTab === 'analyze')
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-indigo-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">Run AI Analysis</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">The AI will review your uploaded documents and suggest field values for unanswered questions.</p>
                            <div class="mt-4">
                                <form method="POST"
                                      action="{{ route('submissions.analyze', ['submission_uuid' => $submission->uuid ?? $submission->id]) }}"
                                      x-data="{ loading: false }"
                                      @submit="loading = true; $dispatch('open-analysis-progress')"
                                      class="inline-block">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                            x-bind:disabled="loading">
                                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        <span x-show="!loading">Start Analysis</span>
                                        <span x-show="loading" x-cloak>Starting...</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                    {{-- ============ EXPORT TAB ============ --}}
                    @elseif($currentTab === 'export')
                        <div class="max-w-lg">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100 mb-2">Export as DOCX</h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Generate a filled {{ $formDefinition->form_code }} DOCX document using the current answers.</p>

                            <form method="POST"
                                  action="{{ route('submissions.exports.store', ['study_uuid' => $study->uuid, 'form_code' => $formDefinition->form_code]) }}"
                                  x-data="{ loading: false }"
                                  @submit="loading = true">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        x-bind:disabled="loading">
                                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span x-show="!loading">Generate DOCX Export</span>
                                    <span x-show="loading" class="inline-flex items-center gap-1.5" x-cloak>
                                        <span class="spinner spinner-sm" aria-hidden="true"></span>
                                        Generating...
                                    </span>
                                </button>
                            </form>

                            <div class="alert alert-info mt-5">
                                <div class="flex gap-2">
                                    <svg class="w-5 h-5 flex-shrink-0 text-sky-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p>The generated document includes all answered fields. Review all answers in the Review tab before exporting for best results.</p>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    {{-- Analysis progress modal — REQ-049 --}}
    @include('submissions._progress_modal', [
        'statusUrl' => route('submissions.analyze.status', ['submission_uuid' => $submission->uuid ?? $submission->id]),
        'cancelUrl' => route('submissions.analyze.cancel', ['submission_uuid' => $submission->uuid ?? $submission->id]),
        'isAssistantMode' => $submission->assistance_mode === 'assistant',
    ])
</x-app-layout>
