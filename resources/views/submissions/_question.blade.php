{{-- SPEC-IRB-FORMSV2-004 §C.5 — Question wrapper / dispatcher
     Dispatches to type-specific partials under submissions/types/.
     REQ-048: label + aria-describedby for helper text --}}
@php
    $answer = $answersByQuestionKey[$question->question_key] ?? null;
    $isGroupLabel = $question->question_type === 'group_label';
    $inputId = 'q_' . $question->question_key;
    $helpId  = 'help_' . $question->question_key;
@endphp

<div
    id="question-{{ $question->question_key }}"
    class="rounded-lg {{ $answer && ($answer->suggestion_source === 'ai_draft') ? 'border border-amber-300 bg-amber-50/50 dark:bg-amber-900/10 dark:border-amber-600 p-4' : ($answer && ($answer->suggestion_source === 'evidence') ? 'border border-indigo-200 bg-indigo-50/30 dark:bg-indigo-900/10 dark:border-indigo-700 p-4' : ($answer ? 'border border-emerald-200 bg-emerald-50/30 dark:bg-emerald-900/10 dark:border-emerald-700 p-4' : 'p-4')) }}"
>
    @if($isGroupLabel)
        {{-- group_label renders as styled subheading only; no input --}}
        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-2">
            {{ $question->number_label ? $question->number_label . ' ' : '' }}{{ $question->label }}
        </h3>
    @else
        {{-- Question prompt --}}
        <div class="mb-3">
            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                {{ $question->number_label ? $question->number_label . ' ' : '' }}{{ $question->label }}
                @if($question->is_required)
                    <span class="text-red-500 ml-0.5" aria-label="required">*</span>
                @endif
            </p>
            @if($question->instruction)
                <p id="{{ $helpId }}" class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $question->instruction }}</p>
            @endif
            @if($question->note)
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded px-2 py-1">
                    <span class="font-semibold">Note:</span> {{ $question->note }}
                </p>
            @endif
        </div>

        {{-- AI draft banner — REQ-053 --}}
        @if($answer && $answer->suggestion_source === 'ai_draft')
            <div class="mb-3 flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-600 px-3 py-2 text-xs text-amber-800 dark:text-amber-300">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                <span>AI-drafted suggestion — review carefully before accepting.</span>
            </div>
        @endif

        {{-- Dispatch to type-specific partial --}}
        @include('submissions.types._q_' . $question->question_type, [
            'question'   => $question,
            'answer'     => $answer,
            'submission' => $submission,
            'inputId'    => $inputId,
            'helpId'     => ($question->instruction ? $helpId : null),
        ])

        {{-- Accept Draft button — REQ-053 --}}
        @if($answer && $answer->suggestion_source === 'ai_draft')
            <div class="mt-3">
                <form method="POST"
                      action="{{ route('submissions.answers.accept_draft', [
                          'submission_uuid' => $submission->uuid ?? $submission->id,
                          'question_key'   => $question->question_key,
                      ]) }}"
                      x-data="{ loading: false }"
                      @submit="loading = true">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2"
                            x-bind:disabled="loading"
                            aria-label="Accept AI draft for {{ $question->label }}">
                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-show="!loading">Accept Draft</span>
                        <span x-show="loading" x-cloak>Accepting...</span>
                    </button>
                </form>
            </div>
        @endif

        {{-- Validation errors for this question --}}
        <x-input-error :messages="$errors->get($question->question_key)" class="mt-2" />
    @endif
</div>
