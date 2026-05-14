{{-- Question type: text_long — single-line long text (no maxlength) --}}
@php
    $currentValue = $answer ? ($answer->text_value ?? '') : '';
    $routeArgs    = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}" class="flex gap-2" x-data="{ loading: false }" @submit="loading = true">
    @csrf
    @method('PUT')
    <div class="flex-1">
        <label for="{{ $inputId }}" class="sr-only">{{ $question->label }}</label>
        <input
            id="{{ $inputId }}"
            type="text"
            name="text_value"
            value="{{ old('text_value', $currentValue) }}"
            class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
            {{ $question->is_required ? 'required' : '' }}
            @if($helpId) aria-describedby="{{ $helpId }}" @endif
            aria-label="{{ $question->label }}"
        />
    </div>
    <button type="submit"
            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 shrink-0"
            x-bind:disabled="loading">
        <span x-show="!loading">Save</span>
        <span x-show="loading" x-cloak><span class="spinner spinner-sm" aria-hidden="true"></span></span>
    </button>
</form>
