@props([
    'fieldValues' => collect(),
    'selected' => null,
    'project' => null,
    'stats' => ['total' => 0, 'missing' => 0, 'confirmed' => 0],
])

@php
    $pct = $stats['total'] > 0 ? round((($stats['total'] - $stats['missing']) / $stats['total']) * 100) : 0;
@endphp

<div>
    <div class="flex items-center justify-between">
        <h3 class="text-base font-semibold text-slate-900">Fields</h3>
        <span class="text-xs font-medium text-slate-500">{{ $stats['total'] - $stats['missing'] }}/{{ $stats['total'] }}</span>
    </div>
    <div class="mt-2 progress-bar">
        <div class="progress-bar-fill" style="width: {{ $pct }}%"></div>
    </div>
    <div class="mt-2 flex gap-3 text-xs text-slate-500">
        <span>Missing {{ $stats['missing'] }}</span>
        <span>Confirmed {{ $stats['confirmed'] }}</span>
    </div>
</div>

<div class="mt-3">
    <x-text-input x-model="q" type="text" class="block w-full text-sm" placeholder="Search fields..." />
</div>

<div class="mt-3 rounded-xl ring-1 ring-slate-900/5 divide-y divide-slate-100 max-h-[70vh] overflow-y-auto">
    @foreach($fieldValues as $fv)
        <a
            class="block px-4 py-3 transition-colors {{ $fv['is_selected'] ? 'bg-indigo-50 border-l-2 border-indigo-500' : 'hover:bg-slate-50 border-l-2 border-transparent' }}"
            href="{{ route('projects.show', ['project' => $project->uuid, 'tab' => 'review', 'fv' => $fv['id']]) }}"
            x-show="q === '' || '{{ $fv['search_text'] }}'.includes(q.toLowerCase())"
            x-cloak
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-slate-900 truncate">{{ $fv['label'] }}</div>
                    <div class="text-xs text-slate-500 truncate mt-0.5">{{ $fv['key'] }}</div>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <span @class([
                        'badge text-xs',
                        'badge-green' => $fv['status'] === 'confirmed',
                        'badge-blue' => $fv['status'] === 'suggested',
                        'badge-amber' => $fv['status'] === 'missing',
                        'badge-indigo' => $fv['status'] === 'edited',
                        'badge-gray' => !in_array($fv['status'], ['confirmed', 'suggested', 'missing', 'edited']),
                    ])>{{ $fv['status'] }}</span>
                    @if($fv['evidence_count'] > 0)
                        <span class="text-xs text-slate-400">{{ $fv['evidence_count'] }} ev</span>
                    @endif
                </div>
            </div>
        </a>
    @endforeach
</div>
