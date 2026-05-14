{{-- Question type: textarea — multi-paragraph free text --}}
@php
    $currentValue = $answer ? ($answer->text_value ?? '') : '';
    $routeArgs    = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}" x-data="{ loading: false }" @submit="loading = true">
    @csrf
    @method('PUT')
    <label for="{{ $inputId }}" class="sr-only">{{ $question->label }}</label>
    <textarea
        id="{{ $inputId }}"
        name="text_value"
        rows="4"
        class="block w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 placeholder:text-slate-400"
        placeholder="Enter your response…"
        {{ $question->is_required ? 'required' : '' }}
        @if($helpId) aria-describedby="{{ $helpId }}" @endif
        aria-label="{{ $question->label }}"
    >{{ old('text_value', $currentValue) }}</textarea>
    <div class="mt-2 flex justify-end">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                x-bind:disabled="loading">
            <span x-show="!loading" class="inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Save
            </span>
            <span x-show="loading" x-cloak class="inline-flex items-center gap-1">
                <span class="spinner spinner-sm" aria-hidden="true"></span>Saving…
            </span>
        </button>
    </div>
</form>
