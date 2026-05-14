{{-- SPEC-IRB-FORMSV2-004 §C.5 — Assistance mode toggle
     REQ-054: Strict/Assistant toggle
     REQ-048: role="radiogroup" + aria-labelledby for WCAG 2.1 AA --}}
@php
    $isStrict   = $submission->assistance_mode === 'strict';
    $isAssistant = $submission->assistance_mode === 'assistant';
    $toggleId   = 'assistance-mode-label-' . $submission->id;
@endphp

<div role="radiogroup" aria-labelledby="{{ $toggleId }}" class="flex items-center gap-3">
    <span id="{{ $toggleId }}" class="sr-only">Assistance mode</span>

    {{-- Switch to Strict --}}
    <form method="POST"
          action="{{ route('submissions.assistance_mode', ['uuid' => $study->uuid, 'form_code' => $formDefinition->form_code]) }}">
        @csrf
        <input type="hidden" name="assistance_mode" value="strict" />
        <button type="submit"
                role="radio"
                aria-checked="{{ $isStrict ? 'true' : 'false' }}"
                aria-label="Strict mode: audit-grade evidence-only suggestions"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-l-lg text-xs font-semibold border transition ease-in-out duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500
                    {{ $isStrict
                        ? 'bg-amber-500 text-white border-amber-500 shadow-sm'
                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700'
                    }}">
            <svg class="h-3.5 w-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            Strict
        </button>
    </form>

    {{-- Switch to Assistant --}}
    <form method="POST"
          action="{{ route('submissions.assistance_mode', ['uuid' => $study->uuid, 'form_code' => $formDefinition->form_code]) }}">
        @csrf
        <input type="hidden" name="assistance_mode" value="assistant" />
        <button type="submit"
                role="radio"
                aria-checked="{{ $isAssistant ? 'true' : 'false' }}"
                aria-label="Assistant mode: AI drafts for missing fields"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-r-lg text-xs font-semibold border transition ease-in-out duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 -ml-px
                    {{ $isAssistant
                        ? 'bg-slate-700 text-white border-slate-700 shadow-sm dark:bg-slate-600 dark:border-slate-600'
                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700'
                    }}">
            <svg class="h-3.5 w-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
            Assistant
        </button>
    </form>

    {{-- Live region for screen readers (announces mode change) --}}
    <span role="status" class="sr-only" aria-live="polite">
        Current mode: {{ $isStrict ? 'Strict — audit-grade evidence-only mode.' : 'Assistant — AI drafts for missing fields.' }}
    </span>
</div>
