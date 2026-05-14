<x-app-layout>
    <x-slot name="header">
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
                    <span class="breadcrumb-item-current">HRP-398</span>
                </nav>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">HRP-398: AI Considerations Worksheet</h1>
            </div>
        </div>
    </x-slot>

    {{-- CSRF token for fetch() calls --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ── Status header ─────────────────────────────────────────────────── --}}
            <div class="flex items-center gap-3">
                <span class="badge badge-gray" role="status" aria-label="Status: tracking only">tracking only</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">This form is guidance-only and is not submitted to the IRB.</span>
            </div>

            {{-- ── Aggregate-counts card ─────────────────────────────────────────── --}}
            <div class="card" role="region" aria-label="HRP-398 progress summary">
                <div class="card-body">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
                        Progress — {{ $counts['addressed'] }}/{{ $counts['total'] }} items addressed
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <span class="badge badge-green" aria-label="{{ $counts['addressed'] }} addressed">
                            {{ $counts['addressed'] }} addressed
                        </span>
                        <span class="badge badge-amber" aria-label="{{ $counts['needs_work'] }} needs work">
                            {{ $counts['needs_work'] }} needs work
                        </span>
                        <span class="badge badge-gray" aria-label="{{ $counts['not_applicable'] }} not applicable">
                            {{ $counts['not_applicable'] }} N/A
                        </span>
                        <span class="badge badge-blue" aria-label="{{ $counts['not_started'] }} not started">
                            {{ $counts['not_started'] }} not started
                        </span>
                    </div>
                </div>
            </div>

            {{-- ── Section accordions ────────────────────────────────────────────── --}}
            @foreach($panelData as $sectionIndex => $section)
                <div class="card" x-data="{ open: true }" id="section-{{ $sectionIndex }}">
                    {{-- Section header / toggle --}}
                    <button
                        type="button"
                        class="w-full flex items-center justify-between p-4 sm:p-6 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 rounded-t-xl"
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        aria-controls="section-body-{{ $sectionIndex }}"
                    >
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                            {{ $section['section_title'] }}
                        </h3>
                        <svg
                            class="w-5 h-5 text-slate-400 transition-transform duration-200 flex-shrink-0"
                            :class="open ? 'rotate-180' : ''"
                            aria-hidden="true"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    {{-- Section body --}}
                    <div
                        id="section-body-{{ $sectionIndex }}"
                        x-show="open"
                        x-collapse
                        class="border-t border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700"
                    >
                        @foreach($section['items'] as $item)
                            @php
                                $itemId        = $item['id'];
                                $currentStatus = $item['status'];
                                $radioName     = 'status_' . $itemId;
                                $updateUrl     = route('submissions.worksheet.update', [
                                    'submission_uuid' => $submission->id,
                                    'item_id'         => $itemId,
                                ]);
                            @endphp
                            <div
                                class="p-4 sm:p-6 space-y-4"
                                id="item-{{ $itemId }}"
                                x-data="{
                                    currentStatus: @js($currentStatus),
                                    notes: @js($item['notes'] ?? ''),
                                    saving: false,
                                    saveError: false,
                                    async save() {
                                        if (this.saving) return;
                                        this.saving = true;
                                        this.saveError = false;
                                        try {
                                            const res = await fetch(@js($updateUrl), {
                                                method: 'PUT',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                                },
                                                body: JSON.stringify({
                                                    status: this.currentStatus,
                                                    notes: this.notes,
                                                }),
                                            });
                                            if (!res.ok) this.saveError = true;
                                        } catch {
                                            this.saveError = true;
                                        } finally {
                                            this.saving = false;
                                        }
                                    }
                                }"
                            >
                                {{-- Item label --}}
                                <p class="text-sm italic text-slate-700 dark:text-slate-300">
                                    {{ $item['label'] }}
                                </p>

                                {{-- Examples collapsible --}}
                                @if(!empty($item['examples']))
                                    <div x-data="{ showExamples: false }">
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                            @click="showExamples = !showExamples"
                                            :aria-expanded="showExamples.toString()"
                                        >
                                            <span x-text="showExamples ? 'Hide examples' : 'Show examples'"></span>
                                        </button>
                                        <div
                                            x-show="showExamples"
                                            x-collapse
                                            class="mt-2 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/50 rounded p-3"
                                        >
                                            @foreach($item['examples'] as $example)
                                                <p>{{ $example }}</p>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Status radio group — fieldset+legend is sufficient
                                     ARIA semantics; the redundant role="radiogroup"
                                     causes screen readers to announce the group twice
                                     (Batch B B6). --}}
                                <fieldset>
                                    <legend class="sr-only">Status for: {{ $item['label'] }}</legend>
                                    <div class="flex flex-wrap gap-2">

                                        @foreach([
                                            ['value' => 'addressed',      'label' => 'Addressed',      'badge' => 'badge-green'],
                                            ['value' => 'needs_work',     'label' => 'Needs Work',     'badge' => 'badge-amber'],
                                            ['value' => 'not_applicable', 'label' => 'N/A',            'badge' => 'badge-gray'],
                                            ['value' => 'not_started',    'label' => 'Not Started',    'badge' => 'badge-blue'],
                                        ] as $opt)
                                            <label
                                                class="cursor-pointer"
                                                :class="{{ json_encode($opt['badge']) }} === {{ json_encode($opt['badge']) }} && currentStatus === {{ json_encode($opt['value']) }}
                                                    ? 'ring-2 ring-offset-1 ring-indigo-500 rounded-full'
                                                    : ''"
                                            >
                                                <input
                                                    type="radio"
                                                    name="{{ $radioName }}"
                                                    value="{{ $opt['value'] }}"
                                                    class="sr-only"
                                                    :checked="currentStatus === {{ json_encode($opt['value']) }}"
                                                    @change="currentStatus = {{ json_encode($opt['value']) }}; save()"
                                                    aria-label="{{ $opt['label'] }}"
                                                >
                                                <span
                                                    class="badge {{ $opt['badge'] }} cursor-pointer select-none"
                                                    :class="currentStatus === {{ json_encode($opt['value']) }} ? 'ring-2 ring-offset-1 ring-indigo-500' : ''"
                                                    aria-hidden="true"
                                                >
                                                    {{ $opt['label'] }}
                                                </span>
                                            </label>
                                        @endforeach

                                    </div>
                                </fieldset>

                                {{-- Notes textarea --}}
                                <div>
                                    <label
                                        for="notes-{{ $itemId }}"
                                        class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1"
                                    >
                                        Reviewer notes
                                    </label>
                                    <textarea
                                        id="notes-{{ $itemId }}"
                                        rows="4"
                                        maxlength="65535"
                                        class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-y p-2"
                                        placeholder="Optional reviewer notes…"
                                        aria-label="Reviewer notes for: {{ $item['label'] }}"
                                        x-model="notes"
                                        @blur="save()"
                                    ></textarea>
                                </div>

                                {{-- Save indicator (WCAG 1.4.1 — don't rely on color alone for state). --}}
                                <div class="flex items-center gap-2 text-xs" aria-live="polite" aria-atomic="true">
                                    <span x-show="saving" class="inline-flex items-center gap-1 text-slate-400">
                                        <span class="spinner spinner-sm" aria-hidden="true"></span>
                                        Saving…
                                    </span>
                                    <span x-show="saveError && !saving" class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                                        </svg>
                                        Save failed — please try again.
                                    </span>
                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- ── Back link ─────────────────────────────────────────────────────── --}}
            <div class="pb-4">
                <a href="{{ route('studies.show', ['uuid' => $study->uuid]) }}"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 focus:outline-none focus:underline">
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to study overview
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
