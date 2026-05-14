{{-- Question type: radio_with_nested_options
     Outer radios; when the selected outer option has nested options the nested
     set is revealed inline. The LEAF option_value is stored as option_value.
     REQ-P5-002, S-P5-4 --}}
@php
    $routeArgs   = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $currentOpt  = $answer ? ($answer->option_value ?? '') : '';
    $outerOptions = $question->options->whereNull('parent_option_id')->sortBy('display_order');

    // Build a map of outer option_value → nested options
    $nestedByParent = [];
    foreach ($question->options->whereNotNull('parent_option_id') as $nested) {
        $nestedByParent[$nested->parent_option_id][] = $nested;
    }

    // Map outer option id → option_value for Alpine lookup
    $outerValueToId = $outerOptions->pluck('id', 'option_value')->toArray();

    // Determine which outer option currently contains the selected value (for pre-population)
    $activeOuterValue = '';
    foreach ($outerOptions as $outer) {
        $nesteds = $nestedByParent[$outer->id] ?? [];
        if ($outer->option_value === $currentOpt) {
            $activeOuterValue = $outer->option_value;
            break;
        }
        foreach ($nesteds as $nested) {
            if ($nested->option_value === $currentOpt) {
                $activeOuterValue = $outer->option_value;
                break 2;
            }
        }
    }
@endphp
<form method="POST"
      action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{
          loading: false,
          outerSelected: @js($activeOuterValue),
          leafSelected: @js($currentOpt)
      }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    {{-- Hidden field carries the actual leaf value --}}
    <input type="hidden" name="option_value" x-bind:value="leafSelected" />

    <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>
        <div class="space-y-2">
            @foreach($outerOptions as $outer)
                @php
                    $outerRadioId = $inputId . '_outer_' . $outer->option_value;
                    $nesteds      = $nestedByParent[$outer->id] ?? [];
                    $hasNested    = ! empty($nesteds);
                @endphp
                <div>
                    <label for="{{ $outerRadioId }}"
                           class="flex items-start gap-3 rounded-lg border p-3 cursor-pointer transition-colors border-slate-200 bg-white hover:border-indigo-300 dark:bg-slate-800 dark:border-slate-600 dark:hover:border-indigo-600"
                           :class="outerSelected === @js($outer->option_value) ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-600' : ''">
                        <input
                            id="{{ $outerRadioId }}"
                            type="radio"
                            value="{{ $outer->option_value }}"
                            {{ $activeOuterValue === $outer->option_value ? 'checked' : '' }}
                            class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                            aria-label="{{ $outer->option_label }}"
                            @if($hasNested)
                                aria-expanded="{{ $activeOuterValue === $outer->option_value ? 'true' : 'false' }}"
                                :aria-expanded="(outerSelected === @js($outer->option_value)).toString()"
                                aria-controls="nested-{{ $outer->option_value }}"
                            @endif
                            @change="
                                outerSelected = @js($outer->option_value);
                                @if($hasNested)
                                    leafSelected = '';
                                @else
                                    leafSelected = @js($outer->option_value);
                                @endif
                            "
                        />
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ $outer->option_label }}
                        </span>
                    </label>

                    @if($hasNested)
                        <div
                            id="nested-{{ $outer->option_value }}"
                            x-show="outerSelected === @js($outer->option_value)"
                            x-cloak
                            class="mt-1 ml-6 pl-3 border-l-2 border-indigo-200 dark:border-indigo-700 space-y-2"
                            role="group"
                            aria-label="Options for {{ $outer->option_label }}"
                        >
                            @foreach($nesteds as $nested)
                                @php $nestedId = $inputId . '_nested_' . $nested->option_value; @endphp
                                <label for="{{ $nestedId }}"
                                       class="flex items-center gap-3 cursor-pointer rounded-md px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                                    <input
                                        id="{{ $nestedId }}"
                                        type="radio"
                                        value="{{ $nested->option_value }}"
                                        {{ $currentOpt === $nested->option_value ? 'checked' : '' }}
                                        class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                                        aria-label="{{ $nested->option_label }}"
                                        @change="leafSelected = @js($nested->option_value)"
                                    />
                                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ $nested->option_label }}</span>
                                </label>
                            @endforeach
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
