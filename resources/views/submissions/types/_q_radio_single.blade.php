{{-- Question type: radio_single — single-choice radio buttons as labeled cards.
     Options with action_type='stop_*' trigger confirmation dialog before submit. --}}
@php
    $routeArgs   = ['submission_uuid' => $submission->uuid ?? $submission->id, 'question_key' => $question->question_key];
    $currentVal  = $answer ? ($answer->option_value ?? '') : '';
    $options     = $question->options->sortBy('display_order');
    $fieldsetId  = 'fieldset_' . $question->question_key;
@endphp
<form method="POST" action="{{ route('submissions.answers.update', $routeArgs) }}"
      x-data="{ loading: false, selected: '{{ addslashes($currentVal) }}' }"
      @submit="loading = true">
    @csrf
    @method('PUT')

    <fieldset id="{{ $fieldsetId }}" @if($helpId) aria-describedby="{{ $helpId }}" @endif>
        <legend class="sr-only">{{ $question->label }}</legend>
        <div class="space-y-2">
            @foreach($options as $opt)
                @php
                    $isStop      = str_starts_with($opt->action_type ?? '', 'stop');
                    $optRadioId  = $inputId . '_' . $opt->option_value;
                    $confirmMsg  = $isStop
                        ? 'This selection will stop the engagement determination process. Are you sure you want to continue?'
                        : null;
                @endphp
                <label
                    for="{{ $optRadioId }}"
                    class="flex items-start gap-3 rounded-lg border p-3 cursor-pointer transition-colors
                        {{ $currentVal === $opt->option_value
                            ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-600'
                            : 'border-slate-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/50 dark:bg-slate-800 dark:border-slate-600 dark:hover:border-indigo-600' }}"
                    :class="selected === '{{ $opt->option_value }}'
                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-600'
                        : 'border-slate-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/50 dark:bg-slate-800 dark:border-slate-600 dark:hover:border-indigo-600'"
                >
                    <input
                        id="{{ $optRadioId }}"
                        type="radio"
                        name="option_value"
                        value="{{ $opt->option_value }}"
                        {{ $currentVal === $opt->option_value ? 'checked' : '' }}
                        x-model="selected"
                        class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                        aria-label="{{ $opt->option_label }}"
                        @if($isStop)
                            @click="if(!confirm('{{ addslashes($confirmMsg) }}')) { $event.preventDefault(); }"
                        @endif
                    />
                    <span class="flex-1">
                        <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ $opt->option_label }}</span>
                        @if($opt->description)
                            <span class="block text-xs text-slate-600 dark:text-slate-400 mt-0.5">{{ $opt->description }}</span>
                        @endif
                        @if($isStop)
                            <span class="inline-flex items-center gap-1 mt-1 text-xs text-amber-700 dark:text-amber-400">
                                <svg class="w-3 h-3" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                Terminal outcome
                            </span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="mt-3 flex justify-end">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                x-bind:disabled="loading">
            <span x-show="!loading">Save</span>
            <span x-show="loading" x-cloak class="inline-flex items-center gap-1"><span class="spinner spinner-sm" aria-hidden="true"></span>Saving…</span>
        </button>
    </div>
</form>
