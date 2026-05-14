{{-- SPEC-IRB-FORMSV2-004 §C.5 — Routing outcome banner
     REQ-044: stop_* terminal routing outcomes --}}
@php
    $isStop = str_starts_with($routingOutcome ?? '', 'stop');
    $bannerClass = $isStop
        ? 'border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700'
        : 'border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700';
    $iconClass = $isStop ? 'text-red-500' : 'text-amber-500';
    $textClass  = $isStop ? 'text-red-800 dark:text-red-200' : 'text-amber-800 dark:text-amber-200';
    $headingClass = $isStop ? 'text-red-900 dark:text-red-100' : 'text-amber-900 dark:text-amber-100';

    // Batch C copy: map raw outcome keys to human-readable phrases so the
    // user does not see snake_case identifiers in production.
    $outcomeLabels = [
        'stop_not_engaged'  => 'Your institution is not engaged in this research',
        'stop_exempt'       => 'This research qualifies for IRB exemption',
        'stop_not_research' => 'This activity does not meet the definition of research',
        'stop_not_human'    => 'This activity does not involve human subjects',
        'continue'          => 'Continue to the full review',
    ];
    $outcomeLabel = $outcomeLabels[$routingOutcome ?? ''] ?? ucwords(str_replace('_', ' ', $routingOutcome ?? ''));
@endphp

<div class="routing-banner-amber rounded-lg border {{ $bannerClass }} px-4 py-4 mb-6" role="alert">
    <div class="flex gap-3">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5 {{ $iconClass }}" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
        <div>
            <p class="text-sm font-semibold {{ $headingClass }}">Routing Outcome: Terminal</p>
            <p class="mt-1 text-sm {{ $textClass }}">
                This submission has reached a terminal outcome:
                <strong>{{ $outcomeLabel }}</strong>
                @if($routingOutcomeAt ?? null)
                    at question <code class="font-mono text-xs">{{ $routingOutcomeAt }}</code>.
                @endif
                Subsequent sections may not be applicable.
            </p>
            <p class="mt-1 text-xs {{ $textClass }} opacity-80">
                To resume editing or override this outcome, please contact the IRB office.
            </p>
        </div>
    </div>
</div>
