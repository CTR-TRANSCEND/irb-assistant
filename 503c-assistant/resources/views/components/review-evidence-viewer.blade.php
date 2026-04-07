@props([
    'fieldValue' => 'array',
    'project' => App\Models\Project::class,
    'activeEvidenceId' => '?int',
])

<div class="rounded-xl ring-1 ring-slate-900/5 overflow-hidden" aria-label="Evidence viewer">
    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 flex items-center gap-2">
        <svg class="w-4 h-4 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <h4 class="text-sm font-semibold text-slate-700">Evidence</h4>
    </div>

    @if(! $fieldValue['has_evidence'])
        <div class="empty-state py-10">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="empty-state-title">No evidence yet</p>
            <p class="empty-state-text">Run analysis to generate evidence citations.</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
            <!-- Evidence List -->
            <div>
                <div class="px-4 py-2 text-xs font-medium text-slate-600 uppercase tracking-wider bg-slate-50/50">Citations</div>
                <div class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto" role="list" aria-label="Evidence citations">
                    @foreach($fieldValue['evidence'] as $ev)
                        <a
                            class="block px-4 py-3 transition-colors {{ $ev['is_active'] ? 'bg-indigo-50 border-l-2 border-indigo-500' : 'hover:bg-slate-50 border-l-2 border-transparent' }}"
                            href="{{ route('projects.show', ['project' => $project->uuid, 'tab' => 'review', 'fv' => $fieldValue['id'] ?? 0, 'ev' => $ev['id']]) }}"
                            role="listitem"
                            aria-current="{{ $ev['is_active'] ? 'true' : 'false' }}"
                        >
                            <div class="flex items-center gap-2 text-xs text-slate-600">
                                <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span class="font-medium">{{ $ev['doc_name'] }}</span>
                                @if($ev['page'] !== null) <span>p.{{ $ev['page'] }}</span> @endif
                                @if($ev['chunk_index'] !== null) <span>chunk {{ $ev['chunk_index'] + 1 }}</span> @endif
                            </div>
                            <p class="mt-1 text-sm text-slate-700 line-clamp-3">{{ $ev['excerpt'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Chunk Context -->
            <div>
                <div class="px-4 py-2 text-xs font-medium text-slate-600 uppercase tracking-wider bg-slate-50/50">Source Context</div>
                <div class="p-4">
                    @if($fieldValue['active_document'] !== null)
                        <div class="flex items-center gap-2 text-xs text-slate-600 mb-3">
                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="font-medium">{{ $fieldValue['active_document']->original_filename }}</span>
                            @if($fieldValue['active_chunk']?->page_number !== null) <span>page {{ $fieldValue['active_chunk']->page_number }}</span> @endif
                            @if($fieldValue['active_chunk']?->chunk_index !== null) <span>chunk {{ $fieldValue['active_chunk']->chunk_index + 1 }}</span> @endif
                        </div>
                    @endif

                    @if($fieldValue['highlighted_chunk'] !== '')
                        <div class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed bg-slate-50 rounded-lg p-4 ring-1 ring-slate-900/5 max-h-[360px] overflow-y-auto" aria-label="Source text context">{!! $fieldValue['highlighted_chunk'] !!}</div>
                    @else
                        <p class="text-sm text-slate-600">Select an evidence citation to view its source context.</p>
                    @endif

                    @if($fieldValue['quote_mismatch'])
                        <div class="alert alert-warning mt-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                The evidence quote does not appear verbatim in the stored chunk text.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
