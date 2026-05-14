{{-- Question type: checkbox_multi_with_section_triggers
     Multi-checkbox list where selected options may unlock downstream sections.
     On change: submits the whole form via PUT so server-side SectionTriggerEvaluator
     recomputes section visibility on the next page load. No client-side trigger logic.
     REQ-P5-002, REQ-P5-003, LD-P5-1 --}}
@php
    $routeArgs  = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $jsonVal    = $answer ? ($answer->json_value ?? []) : [];
    $selected   = is_array($jsonVal) ? array_values($jsonVal) : [];
    $options    = $question->options->sortBy('display_order');
@endphp
<form method="POST"
      action="{{ route('submissions.answers.update', $routeArgs) }}"
      id="form-{{ $inputId }}"
      x-data="{ loading: false }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>
        <div class="space-y-3">
            @foreach($options as $opt)
                @php
                    $optId        = $inputId . '_' . $opt->option_value;
                    $isChecked    = in_array($opt->option_value, $selected, true);
                    $triggersCode = $opt->action_type === 'triggers_section' ? $opt->action_target : null;
                @endphp
                <div>
                    <label for="{{ $optId }}"
                           class="flex items-start gap-3 cursor-pointer group">
                        <input
                            id="{{ $optId }}"
                            type="checkbox"
                            name="json_value[]"
                            value="{{ $opt->option_value }}"
                            {{ $isChecked ? 'checked' : '' }}
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                            aria-label="{{ $opt->option_label }}"
                            @change="$el.form.requestSubmit()"
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-300 leading-snug">
                            {{ $opt->option_label }}
                            @if($triggersCode)
                                <span class="ml-1.5 inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300"
                                      aria-label="Unlocks Section {{ $triggersCode }}">
                                    Unlocks {{ $triggersCode }}
                                </span>
                            @endif
                        </span>
                    </label>
                    @if($opt->note ?? null)
                        <p class="mt-0.5 ml-7 text-xs italic text-slate-500 dark:text-slate-400">
                            {{ $opt->note }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </fieldset>

    {{-- Hidden submit — triggered programmatically on checkbox change --}}
    <div class="mt-3 flex justify-end">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                x-bind:disabled="loading">
            <span x-show="!loading">Save</span>
            <span x-show="loading" x-cloak><span class="spinner spinner-sm" aria-hidden="true"></span></span>
        </button>
    </div>
</form>
