{{-- Question type: checkbox_with_optional_textarea
     Single checkbox + textarea that is enabled only when checked.
     Saves as JSON: {checked: bool, text: string|null}
     REQ-P5-002, S-P5-8 --}}
@php
    $routeArgs  = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $jsonVal    = $answer ? ($answer->json_value ?? []) : [];
    $initChecked = isset($jsonVal['checked']) ? (bool) $jsonVal['checked'] : false;
    $initText   = $jsonVal['text'] ?? '';

    $cbId    = $inputId . '_checkbox';
    $textId  = $inputId . '_text';
    $helpTaId = $inputId . '_ta_help';

    $textareaLabel = $question->textarea_label ?? 'Please explain';
@endphp
<form method="POST"
      action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{
          loading: false,
          checked: {{ $initChecked ? 'true' : 'false' }},
          textVal: @json($initText)
      }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    {{-- Hidden fields ensure values are always submitted --}}
    <input type="hidden" name="json_value[checked]" x-bind:value="checked ? '1' : '0'" />

    <div class="flex items-start gap-3 mb-3">
        <input
            id="{{ $cbId }}"
            type="checkbox"
            value="1"
            {{ $initChecked ? 'checked' : '' }}
            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
            aria-label="{{ $question->label }}"
            @if($helpId) aria-describedby="{{ $helpId }}" @endif
            aria-expanded="{{ $initChecked ? 'true' : 'false' }}"
            :aria-expanded="checked.toString()"
            aria-controls="{{ $textId }}"
            @change="checked = $el.checked; if (!checked) textVal = ''"
        />
        <label for="{{ $cbId }}"
               class="text-sm text-slate-700 dark:text-slate-300 cursor-pointer leading-snug">
            {{ $question->label }}
        </label>
    </div>

    {{-- Optional textarea — shown and enabled when checkbox is checked --}}
    <div id="{{ $textId }}"
         x-show="checked"
         x-cloak
         class="ml-7 transition-all"
         role="region"
         aria-label="{{ $textareaLabel }}">
        <label for="{{ $textId }}_input"
               class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
            {{ $textareaLabel }}
        </label>
        <textarea
            id="{{ $textId }}_input"
            name="json_value[text]"
            rows="3"
            class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 placeholder:text-slate-400"
            placeholder="Enter details…"
            :disabled="!checked"
            x-model="textVal"
        ></textarea>
    </div>

    {{-- When unchecked, send empty text --}}
    <input type="hidden" name="json_value[text]" x-show="!checked" x-cloak value="" />

    <div class="mt-3 flex justify-end">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                x-bind:disabled="loading">
            <span x-show="!loading">Save</span>
            <span x-show="loading" x-cloak><span class="spinner spinner-sm" aria-hidden="true"></span></span>
        </button>
    </div>
</form>
