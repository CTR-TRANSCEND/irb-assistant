{{-- Question type: scenario_group — named scenario rows, each with sub-fields.
     json_value stores {scenario_key: {field_key: value, ...}, ...}.
     Child questions define the fields per scenario row. --}}
@php
    $routeArgs = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $jsonVal   = $answer ? ($answer->json_value ?? []) : [];
    $saved     = is_array($jsonVal) ? $jsonVal : [];
    $scenarios = $question->options->sortBy('display_order');   // options = scenario rows
    $fields    = $question->children->sortBy('display_order');  // children = field definitions
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ loading: false }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>

        @if($scenarios->isEmpty())
            {{-- Fallback: no options defined; render a single textarea for free text --}}
            <textarea
                id="{{ $inputId }}_text"
                name="json_value[text]"
                rows="4"
                class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
                placeholder="Describe scenarios…"
                aria-label="{{ $question->label }}"
            >{{ old('json_value.text', $saved['text'] ?? '') }}</textarea>
        @else
            <div class="space-y-4">
                @foreach($scenarios as $scenario)
                    @php $scenarioKey = $scenario->option_value; @endphp
                    <div class="rounded-md border border-slate-200 dark:border-slate-700 p-3">
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            {{ $scenario->option_label }}
                        </p>
                        @if($fields->isEmpty())
                            <textarea
                                name="json_value[{{ $scenarioKey }}][description]"
                                rows="3"
                                class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
                                placeholder="Describe this scenario…"
                                aria-label="{{ $scenario->option_label }}: description"
                            >{{ $saved[$scenarioKey]['description'] ?? '' }}</textarea>
                        @else
                            @foreach($fields as $field)
                                @php $fieldKey = $field->question_key; @endphp
                                <div class="mb-2">
                                    <label
                                        for="{{ $inputId }}_{{ $scenarioKey }}_{{ $fieldKey }}"
                                        class="block text-xs text-slate-600 dark:text-slate-400 mb-0.5"
                                    >{{ $field->label }}</label>
                                    <input
                                        id="{{ $inputId }}_{{ $scenarioKey }}_{{ $fieldKey }}"
                                        type="text"
                                        name="json_value[{{ $scenarioKey }}][{{ $fieldKey }}]"
                                        value="{{ $saved[$scenarioKey][$fieldKey] ?? '' }}"
                                        class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
                                        aria-label="{{ $scenario->option_label }}: {{ $field->label }}"
                                    />
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
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
