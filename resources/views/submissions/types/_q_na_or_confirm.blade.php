{{-- Question type: na_or_confirm — N/A toggle or affirmative confirmation checkbox.
     bool_value=1 means N/A; json_value={confirmed:true} when confirmed. --}}
@php
    $routeArgs = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $isNA      = $answer ? (bool) $answer->bool_value : false;
    $jsonVal   = $answer ? ($answer->json_value ?? []) : [];
    $confirmed = is_array($jsonVal) ? (bool) ($jsonVal['confirmed'] ?? false) : false;
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ loading: false, isNA: {{ $isNA ? 'true' : 'false' }} }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>
        <div class="space-y-2 mb-3" role="radiogroup" aria-label="N/A or confirm">
            <label class="flex items-center gap-3 cursor-pointer">
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
                    id="{{ $inputId }}_confirm"
                    name="bool_value"
                    value="0"
                    {{ !$isNA ? 'checked' : '' }}
                    x-model.boolean="isNA"
                    :value="false"
                    class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    aria-label="I confirm"
                />
                <span class="text-sm text-slate-700 dark:text-slate-300">I confirm the above</span>
            </label>
        </div>

        <div x-show="!isNA" x-cloak class="ml-7">
            <label for="{{ $inputId }}_confirmed" class="flex items-start gap-3 cursor-pointer">
                <input
                    id="{{ $inputId }}_confirmed"
                    type="checkbox"
                    name="json_value[confirmed]"
                    value="1"
                    {{ $confirmed ? 'checked' : '' }}
                    :disabled="isNA"
                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                    aria-label="Confirmed"
                />
                <span class="text-sm text-slate-700 dark:text-slate-300">Confirmed</span>
            </label>
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
