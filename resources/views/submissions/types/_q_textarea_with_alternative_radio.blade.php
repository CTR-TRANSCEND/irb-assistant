{{-- Question type: textarea_with_alternative_radio
     Two-mode input: textarea OR radio group.
     User picks mode via toggle radios at top ("Enter text" / "Choose from options").
     Saves as JSON: {mode: "text"|"radio", text: string|null, radio: string|null}
     REQ-P5-002, S-P5-7 --}}
@php
    $routeArgs  = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $jsonVal    = $answer ? ($answer->json_value ?? []) : [];
    $initMode   = $jsonVal['mode'] ?? 'text';
    $initText   = $jsonVal['text'] ?? '';
    $initRadio  = $jsonVal['radio'] ?? '';
    $options    = $question->options->sortBy('display_order');

    $modeTextId  = $inputId . '_mode_text';
    $modeRadioId = $inputId . '_mode_radio';
    $textAreaId  = $inputId . '_text';
@endphp
<form method="POST"
      action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{
          loading: false,
          mode: @json($initMode),
          textVal: @json($initText),
          radioVal: @json($initRadio),
          switchMode(m) {
              this.mode = m;
              if (m === 'text') { this.radioVal = ''; }
              else              { this.textVal  = ''; }
          }
      }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    {{-- Mode-selector toggle --}}
    <fieldset class="mb-3">
        <legend class="sr-only">Input mode for {{ $question->label }}</legend>
        <div class="flex gap-4 flex-wrap">
            <label for="{{ $modeTextId }}"
                   class="flex items-center gap-2 text-sm font-medium cursor-pointer"
                   :class="mode === 'text' ? 'text-indigo-700 dark:text-indigo-300' : 'text-slate-600 dark:text-slate-400'">
                <input
                    id="{{ $modeTextId }}"
                    type="radio"
                    value="text"
                    {{ $initMode === 'text' ? 'checked' : '' }}
                    class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    aria-label="Enter text"
                    @change="switchMode('text')"
                />
                Enter text
            </label>
            <label for="{{ $modeRadioId }}"
                   class="flex items-center gap-2 text-sm font-medium cursor-pointer"
                   :class="mode === 'radio' ? 'text-indigo-700 dark:text-indigo-300' : 'text-slate-600 dark:text-slate-400'">
                <input
                    id="{{ $modeRadioId }}"
                    type="radio"
                    value="radio"
                    {{ $initMode === 'radio' ? 'checked' : '' }}
                    class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    aria-label="Choose from options"
                    @change="switchMode('radio')"
                />
                Choose from options
            </label>
        </div>
    </fieldset>

    {{-- Hidden mode field --}}
    <input type="hidden" name="json_value[mode]" x-bind:value="mode" />

    {{-- Text mode --}}
    <div x-show="mode === 'text'" x-cloak>
        <label for="{{ $textAreaId }}" class="sr-only">{{ $question->label }} — text entry</label>
        <textarea
            id="{{ $textAreaId }}"
            name="json_value[text]"
            rows="4"
            class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 placeholder:text-slate-400"
            placeholder="Enter your response…"
            @if($helpId) aria-describedby="{{ $helpId }}" @endif
            x-model="textVal"
            :disabled="mode !== 'text'"
        ></textarea>
        {{-- Carry radio as empty when in text mode --}}
        <input type="hidden" name="json_value[radio]" value="" />
    </div>

    {{-- Radio mode --}}
    <div x-show="mode === 'radio'" x-cloak>
        {{-- Carry text as empty when in radio mode --}}
        <input type="hidden" name="json_value[text]" value="" />
        <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
            <legend class="sr-only">{{ $question->label }} — select an option</legend>
            <div class="space-y-2">
                @foreach($options as $opt)
                    @php $optId = $inputId . '_radio_' . $opt->option_value; @endphp
                    <label for="{{ $optId }}"
                           class="flex items-start gap-3 rounded-lg border p-3 cursor-pointer transition-colors border-slate-200 bg-white hover:border-indigo-300 dark:bg-slate-800 dark:border-slate-600 dark:hover:border-indigo-600"
                           :class="radioVal === @js($opt->option_value) ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-600' : ''">
                        <input
                            id="{{ $optId }}"
                            type="radio"
                            name="json_value[radio]"
                            value="{{ $opt->option_value }}"
                            {{ $initRadio === $opt->option_value && $initMode === 'radio' ? 'checked' : '' }}
                            class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                            aria-label="{{ $opt->option_label }}"
                            :disabled="mode !== 'radio'"
                            x-model="radioVal"
                        />
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ $opt->option_label }}
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
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
