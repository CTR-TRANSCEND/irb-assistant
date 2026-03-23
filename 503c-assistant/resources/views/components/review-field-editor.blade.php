@props([
    'fieldValue' => 'array',
    'project' => App\Models\Project::class,
])

<div class="rounded-xl ring-1 ring-slate-900/5 overflow-hidden">
    <div class="px-5 py-4 bg-slate-50 border-b border-slate-100">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-slate-500">{{ $fieldValue['key'] }}</p>
                <h3 class="text-lg font-bold text-slate-900 mt-0.5">{{ $fieldValue['label'] }}</h3>
                <div class="flex items-center gap-3 mt-1">
                    <span @class([
                        'badge text-xs',
                        'badge-green' => $fieldValue['status'] === 'confirmed',
                        'badge-blue' => $fieldValue['status'] === 'suggested',
                        'badge-amber' => $fieldValue['status'] === 'missing',
                        'badge-indigo' => $fieldValue['status'] === 'edited',
                    ])>{{ $fieldValue['status'] }}</span>
                    @if($fieldValue['confidence'] !== null)
                        <span class="text-xs text-slate-500">confidence: {{ $fieldValue['confidence'] }}</span>
                    @endif
                </div>
            </div>
            @if($fieldValue['evidence_count'] > 0)
                <span class="badge badge-gray">{{ $fieldValue['evidence_count'] }} evidence</span>
            @endif
        </div>
    </div>

    <div class="p-5">
        <label class="text-sm font-medium text-slate-700">Your answer</label>
        <form class="mt-2" method="POST" action="{{ route('projects.fields.update', ['project' => $project->uuid, 'value' => $fieldValue['id']]) }}">
            @csrf
            <input type="hidden" name="tab" value="review" />
            <textarea name="final_value" class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 placeholder:text-slate-400" rows="5">{{ old('final_value', $fieldValue['final_value'] ?? $fieldValue['suggested_value']) }}</textarea>

            <div class="mt-3 flex items-center justify-between">
                <label class="text-sm text-slate-600 flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="confirm" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    Mark as confirmed
                </label>
                <x-primary-button>Save</x-primary-button>
            </div>
        </form>

        @if($fieldValue['show_suggested'])
            <div class="mt-5 border-t border-slate-100 pt-4">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">AI Suggestion</p>
                <div class="text-sm text-slate-700 bg-slate-50 rounded-lg p-4 whitespace-pre-wrap ring-1 ring-slate-900/5">{{ $fieldValue['suggested_value'] }}</div>
            </div>
        @endif

        @if($fieldValue['is_edited'])
            <div class="alert alert-warning mt-4">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    This field was manually edited from the AI suggestion.
                </div>
            </div>
        @endif
    </div>
</div>
