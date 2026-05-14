{{-- Question type: checkbox_single — standalone boolean checkbox --}}
@php
    $routeArgs   = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $currentBool = $answer ? (bool) $answer->bool_value : false;
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ loading: false }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <div class="flex items-start gap-3">
        <input
            id="{{ $inputId }}"
            type="checkbox"
            name="bool_value"
            value="1"
            {{ $currentBool ? 'checked' : '' }}
            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
            aria-label="{{ $question->label }}"
            @if($helpId) aria-describedby="{{ $helpId }}" @endif
        />
        <label for="{{ $inputId }}" class="text-sm text-slate-700 dark:text-slate-300 cursor-pointer leading-snug">
            {{ $question->label }}
        </label>
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
