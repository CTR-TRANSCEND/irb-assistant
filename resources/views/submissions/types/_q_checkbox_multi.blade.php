{{-- Question type: checkbox_multi — multi-select checkboxes; saves as JSON array --}}
@php
    $routeArgs   = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $jsonVal     = $answer ? ($answer->json_value ?? []) : [];
    $selected    = is_array($jsonVal) ? $jsonVal : [];
    $options     = $question->options->sortBy('display_order');
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ loading: false }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>
        <div class="space-y-2">
            @foreach($options as $opt)
                @php $optId = $inputId . '_' . $opt->option_value; @endphp
                <label for="{{ $optId }}" class="flex items-start gap-3 cursor-pointer">
                    <input
                        id="{{ $optId }}"
                        type="checkbox"
                        name="json_value[]"
                        value="{{ $opt->option_value }}"
                        {{ in_array($opt->option_value, $selected, true) ? 'checked' : '' }}
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
