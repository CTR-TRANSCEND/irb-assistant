{{-- Question type: textarea_with_multiple_na — N/A radio set + textarea --}}
@php
    $routeArgs  = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $optionVal  = $answer ? ($answer->option_value ?? '') : '';
    $textVal    = $answer ? ($answer->text_value ?? '') : '';
    $naOptions  = $question->options->where('action_type', 'na')->sortBy('display_order');
    $isNA       = in_array($optionVal, $naOptions->pluck('option_value')->all(), true);
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ isNA: {{ $isNA ? 'true' : 'false' }}, selectedOption: '{{ $optionVal }}', loading: false }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    @if($naOptions->isNotEmpty())
        <fieldset class="mb-3">
            <legend class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">N/A options</legend>
            <div class="flex flex-wrap gap-4">
                @foreach($naOptions as $opt)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="option_value"
                            value="{{ $opt->option_value }}"
                            {{ $optionVal === $opt->option_value ? 'checked' : '' }}
                            x-model="selectedOption"
                            @change="isNA = true"
                            class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            aria-label="{{ $opt->option_label }}"
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $opt->option_label }}</span>
                    </label>
                @endforeach
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        name="option_value"
                        value=""
                        {{ $optionVal === '' ? 'checked' : '' }}
                        x-model="selectedOption"
                        @change="isNA = false"
                        class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        aria-label="Provide explanation"
                    />
                    <span class="text-sm text-slate-700 dark:text-slate-300">Provide explanation</span>
                </label>
            </div>
        </fieldset>
    @endif

    <label for="{{ $inputId }}" class="sr-only">{{ $question->label }}</label>
    <textarea
        id="{{ $inputId }}"
        name="text_value"
        rows="4"
        :disabled="isNA"
        :class="isNA ? 'opacity-50 cursor-not-allowed bg-slate-50' : ''"
        class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 transition-opacity"
        placeholder="Enter your explanation…"
        aria-label="{{ $question->label }}"
        @if($helpId) aria-describedby="{{ $helpId }}" @endif
    >{{ old('text_value', $textVal) }}</textarea>

    <div class="mt-2 flex justify-end">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                x-bind:disabled="loading">
            <span x-show="!loading">Save</span>
            <span x-show="loading" x-cloak class="inline-flex items-center gap-1"><span class="spinner spinner-sm" aria-hidden="true"></span>Saving…</span>
        </button>
    </div>
</form>
