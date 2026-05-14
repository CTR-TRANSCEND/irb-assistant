{{-- Question type: group_label
     Header-only row that groups subsequent questions within a section.
     No input, no submission_answer row is written. LD-P5-3 --}}
<h4 class="mt-6 font-semibold text-slate-800 dark:text-slate-200">
    {{ $question->number_label ? $question->number_label . ' ' : '' }}{{ $question->label }}
</h4>
