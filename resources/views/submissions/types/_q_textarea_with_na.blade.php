{{-- Question type: textarea_with_na — checkbox N/A + textarea (Alpine disables textarea when N/A checked) --}}
@php
    $routeArgs    = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $jsonVal      = $answer ? ($answer->json_value ?? null) : null;
    $naChecked    = is_array($jsonVal) && in_array('na', $jsonVal, true);
    $textVal      = $answer ? ($answer->text_value ?? '') : '';
    $naInputId    = $inputId . '_na';
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ isNA: {{ $naChecked ? 'true' : 'false' }} }"
      @submit.prevent="loading = true; $el.submit()"
      x-data="{ loading: false, isNA: {{ $naChecked ? 'true' : 'false' }} }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <div class="flex items-center gap-2 mb-3">
        <input
            id="{{ $naInputId }}"
            type="checkbox"
            name="na_checked"
            value="1"
            {{ $naChecked ? 'checked' : '' }}
            x-model="isNA"
            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            aria-label="Not applicable"
        />
        <label for="{{ $naInputId }}" class="text-sm text-slate-700 dark:text-slate-300 cursor-pointer">N/A — not applicable</label>
    </div>

    <label for="{{ $inputId }}" class="sr-only">{{ $question->label }}</label>
    <textarea
        id="{{ $inputId }}"
        name="text_value"
        rows="4"
        :disabled="isNA"
        :class="isNA ? 'opacity-50 cursor-not-allowed bg-slate-50' : ''"
        class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 transition-opacity"
        placeholder="{{ $naChecked ? 'N/A' : 'Enter your response…' }}"
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
