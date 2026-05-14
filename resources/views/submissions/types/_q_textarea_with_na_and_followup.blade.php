{{-- Question type: textarea_with_na_and_followup
     N/A checkbox + main textarea + followup textarea.
     When N/A is checked the text/followup inputs are disabled and their values cleared.
     Saves as JSON: {na: bool, text: string|null, followup: string|null}
     REQ-P5-002, S-P5-6 --}}
@php
    $routeArgs   = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $jsonVal     = $answer ? ($answer->json_value ?? []) : [];
    $initNa      = isset($jsonVal['na']) ? (bool) $jsonVal['na'] : false;
    $initText    = $jsonVal['text'] ?? '';
    $initFollowup = $jsonVal['followup'] ?? '';

    $naId       = $inputId . '_na';
    $textId     = $inputId . '_text';
    $followupId = $inputId . '_followup';
    $textHelpId = $inputId . '_text_help';
    $fupHelpId  = $inputId . '_followup_help';

    // Look for optional labels in question options/metadata
    $followupLabel = $question->followup_label ?? 'Follow-up / additional context';
    $textLabel     = $question->text_label ?? 'Explanation';
@endphp
<form method="POST"
      action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{
          loading: false,
          isNa: {{ $initNa ? 'true' : 'false' }},
          textVal: @json($initText),
          followupVal: @json($initFollowup),
          toggleNa() {
              this.isNa = !this.isNa;
              if (this.isNa) {
                  this.textVal = '';
                  this.followupVal = '';
              }
          }
      }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    {{-- N/A toggle --}}
    <div class="mb-3 flex items-center gap-2">
        <input
            id="{{ $naId }}"
            type="checkbox"
            name="json_value[na]"
            value="1"
            {{ $initNa ? 'checked' : '' }}
            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            aria-label="Not applicable"
            @change="toggleNa()"
        />
        <label for="{{ $naId }}" class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
            Not Applicable (N/A)
        </label>
    </div>

    {{-- Main textarea --}}
    <div class="mb-3" x-bind:class="isNa ? 'opacity-50' : ''">
        <label for="{{ $textId }}"
               class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
            {{ $textLabel }}
        </label>
        <textarea
            id="{{ $textId }}"
            name="json_value[text]"
            rows="4"
            class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 placeholder:text-slate-400"
            placeholder="Enter your response…"
            :disabled="isNa"
            :aria-disabled="isNa.toString()"
            @if($helpId) aria-describedby="{{ $helpId }}" @endif
            x-model="textVal"
        ></textarea>
    </div>

    {{-- Followup textarea — hidden when N/A --}}
    <div x-show="!isNa" x-cloak class="mb-3">
        <label for="{{ $followupId }}"
               class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
            {{ $followupLabel }}
        </label>
        <textarea
            id="{{ $followupId }}"
            name="json_value[followup]"
            rows="3"
            class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 placeholder:text-slate-400"
            placeholder="Additional details…"
            :disabled="isNa"
            x-model="followupVal"
        ></textarea>
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
