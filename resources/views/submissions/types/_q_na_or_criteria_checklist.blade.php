{{-- Question type: na_or_criteria_checklist — N/A radio + criterion_checkbox child rows.
     bool_value=1 means N/A; json_value stores {criterion_key: bool, ...} map.
     Child rows are criterion_checkbox type (not standalone). --}}
@php
    $routeArgs = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $isNA      = $answer ? (bool) $answer->bool_value : false;
    $jsonVal   = $answer ? ($answer->json_value ?? []) : [];
    $criteria  = is_array($jsonVal) ? $jsonVal : [];
    $children  = $question->children->sortBy('display_order');
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ loading: false, isNA: {{ $isNA ? 'true' : 'false' }} }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>

        {{-- N/A toggle --}}
        <div class="mb-3" role="radiogroup" aria-label="N/A or complete criteria checklist">
            <label class="flex items-center gap-3 cursor-pointer mb-2">
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
                    id="{{ $inputId }}_criteria"
                    name="bool_value"
                    value="0"
                    {{ !$isNA ? 'checked' : '' }}
                    x-model.boolean="isNA"
                    :value="false"
                    class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    aria-label="Complete criteria checklist"
                />
                <span class="text-sm text-slate-700 dark:text-slate-300">Complete criteria checklist</span>
            </label>
        </div>

        {{-- Criteria checklist (revealed when not N/A) --}}
        <div x-show="!isNA" x-cloak class="space-y-2 ml-2 pl-3 border-l-2 border-slate-200 dark:border-slate-700">
            @if($children->isEmpty())
                <p class="text-xs text-slate-500 dark:text-slate-400 italic">No criteria defined.</p>
            @else
                @foreach($children as $child)
                    @php
                        $childKey     = $child->question_key;
                        $childChecked = (bool) ($criteria[$childKey] ?? false);
                        $childOptId   = $inputId . '_criterion_' . $loop->index;
                    @endphp
                    <label for="{{ $childOptId }}" class="flex items-start gap-3 cursor-pointer">
                        <input
                            id="{{ $childOptId }}"
                            type="checkbox"
                            name="json_value[{{ $childKey }}]"
                            value="1"
                            {{ $childChecked ? 'checked' : '' }}
                            :disabled="isNA"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                            aria-label="{{ $child->label }}"
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-300">
                            {{ $child->number_label ? $child->number_label . ' ' : '' }}{{ $child->label }}
                        </span>
                    </label>
                @endforeach
            @endif
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
