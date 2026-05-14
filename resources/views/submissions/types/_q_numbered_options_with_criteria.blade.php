{{-- Question type: numbered_options_with_criteria
     Numbered checkbox list (1., 2., 3., …) where each option has a `criteria` text
     that renders as helper text below the label.
     Stores selected option_values as a JSON array (json_value).
     REQ-P5-002, S-P5-5 --}}
@php
    $routeArgs = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $jsonVal   = $answer ? ($answer->json_value ?? []) : [];
    $selected  = is_array($jsonVal) ? array_values(array_map('strval', $jsonVal)) : [];
    $options   = $question->options->sortBy('display_order');
@endphp
<form method="POST"
      action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ loading: false }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>
        <ol class="space-y-3 list-none">
            @foreach($options as $index => $opt)
                @php
                    $optId      = $inputId . '_' . $opt->option_value;
                    $isChecked  = in_array((string) $opt->option_value, $selected, true);
                    $numLabel   = ($index + 1) . '.';
                    $criteriaId = 'criteria_' . $optId;
                @endphp
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 w-6 shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400 text-right select-none"
                          aria-hidden="true">{{ $numLabel }}</span>
                    <div class="flex-1">
                        <label for="{{ $optId }}" class="flex items-start gap-2 cursor-pointer">
                            <input
                                id="{{ $optId }}"
                                type="checkbox"
                                name="json_value[]"
                                value="{{ $opt->option_value }}"
                                {{ $isChecked ? 'checked' : '' }}
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                                aria-label="{{ $opt->option_label }}"
                                @if($opt->criteria ?? null)
                                    aria-describedby="{{ $criteriaId }}"
                                @endif
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300 leading-snug">
                                {{ $opt->option_label }}
                            </span>
                        </label>
                        @if($opt->criteria ?? null)
                            <p id="{{ $criteriaId }}"
                               class="mt-1 ml-6 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                {{ $opt->criteria }}
                            </p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
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
