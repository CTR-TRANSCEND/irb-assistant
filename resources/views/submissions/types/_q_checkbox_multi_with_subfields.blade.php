{{-- Question type: checkbox_multi_with_subfields — parent checkboxes; options with
     action_type='reveal_subfields' show child questions (x-show) when selected.
     REQ-044: reveal_subfields Alpine pattern. Saves as JSON nested object. --}}
@php
    $routeArgs     = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $jsonVal       = $answer ? ($answer->json_value ?? []) : [];
    $selected      = is_array($jsonVal) ? array_keys(array_filter($jsonVal, fn($v) => !empty($v) || $v === true)) : [];
    $revealOptions = $question->options
        ->where('action_type', 'reveal_subfields')
        ->pluck('option_value')
        ->values()
        ->toJson();
    $options       = $question->options->sortBy('display_order');
    $children      = $question->children->sortBy('display_order');
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{
          loading: false,
          selected: {{ json_encode($selected) }},
          revealOpts: {{ $revealOptions }},
          isChecked(val) { return this.selected.includes(val); },
          toggle(val) {
              if (this.isChecked(val)) {
                  this.selected = this.selected.filter(v => v !== val);
              } else {
                  this.selected.push(val);
              }
          },
          showSubfields(val) { return this.isChecked(val) && this.revealOpts.includes(val); }
      }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>
        <div class="space-y-3">
            @foreach($options as $opt)
                @php
                    $optId       = $inputId . '_' . $opt->option_value;
                    $hasSubfield = $opt->action_type === 'reveal_subfields';
                    $childQ      = $hasSubfield
                        ? $children->firstWhere('question_key', $opt->action_target)
                        : null;
                @endphp
                <div>
                    <label for="{{ $optId }}" class="flex items-start gap-3 cursor-pointer">
                        <input
                            id="{{ $optId }}"
                            type="checkbox"
                            name="json_value[{{ $opt->option_value }}]"
                            value="1"
                            {{ in_array($opt->option_value, $selected, true) ? 'checked' : '' }}
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                            aria-label="{{ $opt->option_label }}"
                            @click="toggle('{{ $opt->option_value }}')"
                            @if($hasSubfield)
                                aria-expanded="{{ in_array($opt->option_value, $selected, true) ? 'true' : 'false' }}"
                                :aria-expanded="isChecked('{{ $opt->option_value }}').toString()"
                                @if($childQ) aria-controls="subfield-{{ $childQ->question_key }}" @endif
                            @endif
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $opt->option_label }}</span>
                    </label>

                    @if($hasSubfield && $childQ)
                        {{-- Revealed subfield (child question) — REQ-044 --}}
                        <div
                            id="subfield-{{ $childQ->question_key }}"
                            x-show="showSubfields('{{ $opt->option_value }}')"
                            x-cloak
                            class="mt-2 ml-7 pl-3 border-l-2 border-indigo-200 dark:border-indigo-700"
                            role="region"
                            aria-label="Additional information for: {{ $opt->option_label }}"
                        >
                            @php
                                $childAnswer  = $answersByQuestionKey[$childQ->question_key] ?? null;
                                $childInputId = 'q_' . $childQ->question_key;
                                $childHelpId  = 'help_' . $childQ->question_key;
                            @endphp
                            <p class="text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                {{ $childQ->label }}
                            </p>
                            @if($childQ->instruction)
                                <p id="{{ $childHelpId }}" class="text-xs text-slate-500 dark:text-slate-400 mb-1">
                                    {{ $childQ->instruction }}
                                </p>
                            @endif
                            @include('submissions.types._q_' . $childQ->question_type, [
                                'question'   => $childQ,
                                'answer'     => $childAnswer,
                                'submission' => $submission,
                                'inputId'    => $childInputId,
                                'helpId'     => ($childQ->instruction ? $childHelpId : null),
                            ])
                        </div>
                    @endif
                </div>
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
