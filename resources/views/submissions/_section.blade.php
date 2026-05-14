{{-- SPEC-IRB-FORMSV2-004 §C.5 — Section wrapper
     SPEC-IRB-FORMSV2-005 — Phase 5: visibility gating via $sectionVisibility
     REQ-048: role="region" + aria-labelledby for WCAG 2.1 AA
     REQ-P5-003: locked sections render as dimmed card; visible sections render normally --}}
@php
    $isSectionVisible = $sectionVisibility[$section->section_code] ?? true;
    $anchorId = 'section-' . $section->section_code;
@endphp

@if($isSectionVisible)
    <section
        id="{{ $anchorId }}"
        role="region"
        aria-labelledby="heading-{{ $section->section_code }}"
        class="mb-8"
    >
        <div class="mb-4 pb-3 border-b border-slate-200 dark:border-slate-700">
            <h2
                id="heading-{{ $section->section_code }}"
                class="text-lg font-bold text-slate-900 dark:text-slate-100"
            >
                {{ $section->section_number ? $section->section_number . '. ' : '' }}{{ $section->title }}
            </h2>
            @if($section->description ?? null)
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $section->description }}</p>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Only render top-level questions (parent_question_id IS NULL) --}}
            @foreach($section->questions->where('parent_question_id', null)->sortBy('display_order') as $question)
                @include('submissions._question', [
                    'question'             => $question,
                    'answersByQuestionKey' => $answersByQuestionKey,
                    'submission'           => $submission,
                ])
            @endforeach
        </div>
    </section>
@else
    {{-- Locked section — dimmed card; answers preserved in DB (REQ-P5-007) --}}
    <div id="{{ $anchorId }}"
         class="card opacity-50 cursor-not-allowed mb-8"
         aria-disabled="true"
         role="region"
         aria-labelledby="heading-locked-{{ $section->section_code }}">
        <div class="p-4 flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 mt-0.5 text-slate-400 dark:text-slate-500" aria-hidden="true"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <div>
                <h3 id="heading-locked-{{ $section->section_code }}"
                    class="font-semibold text-slate-500 dark:text-slate-500">
                    {{ $section->section_number ? $section->section_number . '. ' : '' }}{{ $section->title }}
                </h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 mt-0.5">
                    Locked — complete the trigger question to unlock this section.
                </p>
            </div>
        </div>
    </div>
@endif
