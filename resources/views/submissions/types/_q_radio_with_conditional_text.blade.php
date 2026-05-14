{{-- Question type: radio_with_conditional_text — radio + textarea revealed per option's requires_textarea --}}
@php
    $routeArgs  = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $currentOpt = $answer ? ($answer->option_value ?? '') : '';
    $currentTxt = $answer ? ($answer->text_value ?? '') : '';
    $options    = $question->options->sortBy('display_order');
    $requiresTA = $options->where('requires_textarea', true)->pluck('option_value')->values()->toJson();
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ loading: false, selected: '{{ addslashes($currentOpt) }}', requiresTA: {{ $requiresTA }} }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset>
        <legend class="sr-only">{{ $question->label }}</legend>
        <div class="space-y-2 mb-3">
            @foreach($options as $opt)
                @php $optRadioId = $inputId . '_' . $opt->option_value; @endphp
                <label for="{{ $optRadioId }}"
                       class="flex items-start gap-3 rounded-lg border p-3 cursor-pointer transition-colors border-slate-200 bg-white hover:border-indigo-300 dark:bg-slate-800 dark:border-slate-600 dark:hover:border-indigo-600"
                       :class="selected === '{{ $opt->option_value }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-600' : ''">
                    <input
                        id="{{ $optRadioId }}"
                        type="radio"
                        name="option_value"
                        value="{{ $opt->option_value }}"
                        {{ $currentOpt === $opt->option_value ? 'checked' : '' }}
                        x-model="selected"
                        class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                        aria-label="{{ $opt->option_label }}"
                    />
                    <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $opt->option_label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    {{-- Conditional textarea --}}
    <div x-show="requiresTA.includes(selected)" x-cloak class="mt-2">
        @php $taLabelOpt = $options->firstWhere('requires_textarea', true); @endphp
        <label for="{{ $inputId }}_text" class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
            {{ $taLabelOpt?->conditional_textarea_label ?? 'Please explain' }}
        </label>
        <textarea
            id="{{ $inputId }}_text"
            name="text_value"
            rows="3"
            class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
            placeholder="Enter your explanation…"
            aria-label="{{ $taLabelOpt?->conditional_textarea_label ?? 'Explanation' }}"
        >{{ old('text_value', $currentTxt) }}</textarea>
    </div>

    <div class="mt-3 flex justify-end">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                x-bind:disabled="loading">
            <span x-show="!loading">Save</span>
            <span x-show="loading" x-cloak><span class="spinner spinner-sm" aria-hidden="true"></span></span>
        </button>
    </div>
</form>
