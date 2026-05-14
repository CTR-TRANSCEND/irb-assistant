{{-- Question type: na_or_multi_checkbox — N/A radio + multi-checkbox list.
     bool_value=1 means N/A; json_value stores array of checked option_values. --}}
@php
    $routeArgs = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $isNA      = $answer ? (bool) $answer->bool_value : false;
    $jsonVal   = $answer ? ($answer->json_value ?? []) : [];
    $checked   = is_array($jsonVal) ? $jsonVal : [];
    $options   = $question->options->where('action_type', '!=', 'na')->sortBy('display_order');
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ loading: false, isNA: {{ $isNA ? 'true' : 'false' }} }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>

        {{-- N/A toggle --}}
        <div class="mb-3" role="radiogroup" aria-label="N/A or select all that apply">
            <label class="flex items-center gap-3 cursor-pointer mb-2">
                <input
                    type="radio"
                    id="{{ $inputId }}_na"
                    name="bool_value"
                    value="1"
                    {{ $isNA ? 'checked' : '' }}
                    x-model.boolean="isNA"
                    :value="true"
                    class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    aria-label="Not applicable"
                />
                <span class="text-sm text-slate-700 dark:text-slate-300">Not applicable (N/A)</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input
                    type="radio"
                    id="{{ $inputId }}_select"
                    name="bool_value"
                    value="0"
                    {{ !$isNA ? 'checked' : '' }}
                    x-model.boolean="isNA"
                    :value="false"
                    class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    aria-label="Select all that apply"
                />
                <span class="text-sm text-slate-700 dark:text-slate-300">Select all that apply</span>
            </label>
        </div>

        {{-- Checkbox list (revealed when not N/A) --}}
        <div x-show="!isNA" x-cloak class="space-y-2 ml-2 pl-3 border-l-2 border-slate-200 dark:border-slate-700">
            @foreach($options as $opt)
                @php $optId = $inputId . '_' . $opt->option_value; @endphp
                <label for="{{ $optId }}" class="flex items-start gap-3 cursor-pointer">
                    <input
                        id="{{ $optId }}"
                        type="checkbox"
                        name="json_value[]"
                        value="{{ $opt->option_value }}"
                        {{ in_array($opt->option_value, $checked, true) ? 'checked' : '' }}
                        :disabled="isNA"
                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                        aria-label="{{ $opt->option_label }}"
                    />
                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ $opt->option_label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="mt-3 flex justify-end">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                x-bind:disabled="loading">
            <span x-show="!loading">Save</span>
            <span x-show="loading" x-cloak><span class="spinner spinner-sm" aria-hidden="true"></span></span>
        </button>
    </div>
</form>
