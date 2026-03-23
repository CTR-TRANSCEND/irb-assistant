<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.index', ['tab' => 'templates']) }}" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Template</p>
                    <h2 class="text-xl font-bold text-slate-900">{{ $template->name }}</h2>
                    <p class="text-xs text-slate-400 font-mono mt-0.5">{{ \Illuminate\Support\Str::limit($template->uuid, 16, '...') }}</p>
                </div>
            </div>
            <div>
                @if($template->is_active)
                    <span class="badge badge-green">Active Template</span>
                @else
                    <form method="POST" action="{{ route('admin.templates.activate', ['template' => $template->uuid]) }}">
                        @csrf
                        <x-primary-button>Activate</x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="card">
                <div class="p-6">
                    @if (session('status'))
                        <div class="alert alert-success mb-5">{{ session('status') }}</div>
                    @endif

                    <p class="text-sm text-slate-500 mb-6">Map Word content controls to HRP-503c fields. This enables analysis and export across template revisions.</p>

                    @php
                        $allParts = $parts ?? ['document'];
                        $fmtPart = function (string $p): string {
                            if ($p === 'document') return 'Document';
                            if ($p === 'endnotes') return 'Endnotes';
                            if ($p === 'footnotes') return 'Footnotes';
                            if (preg_match('/^(header|footer)(\d+)$/', $p, $m) === 1) return ucfirst($m[1]).' '.$m[2];
                            return $p;
                        };
                    @endphp

                    <!-- Stats and Drift Summary -->
                    @if(!empty($partStats) || !empty($drift))
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                            @if(!empty($partStats))
                                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-900/5 p-4">
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Mapping Coverage</h4>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($partStats as $s)
                                            @php
                                                $total = (int) ($s['total'] ?? 0);
                                                $mapped = (int) ($s['mapped'] ?? 0);
                                                $pct = $total > 0 ? round(($mapped / $total) * 100) : 0;
                                            @endphp
                                            <div class="bg-white rounded-lg ring-1 ring-slate-900/5 px-3 py-2">
                                                <div class="text-xs font-medium text-slate-700">{{ $fmtPart((string) ($s['part'] ?? '')) }}</div>
                                                <div class="text-sm font-bold text-slate-900 mt-0.5">{{ $mapped }}/{{ $total }}</div>
                                                <div class="mt-1 w-16 h-1 rounded-full bg-slate-200">
                                                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ $pct }}%"></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(!empty($drift))
                                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-900/5 p-4">
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Template Drift</h4>
                                    <p class="text-xs text-slate-500">vs <span class="font-medium text-slate-700">{{ $drift['base_template_name'] ?? 'Active' }}</span></p>
                                    <div class="grid grid-cols-3 gap-3 mt-3">
                                        <div class="stat-card">
                                            <span class="stat-value text-lg text-emerald-600">{{ (int) ($drift['overlap'] ?? 0) }}</span>
                                            <span class="stat-label text-[10px]">Matched</span>
                                        </div>
                                        <div class="stat-card">
                                            <span class="stat-value text-lg text-amber-600">{{ (int) ($drift['this_only'] ?? 0) }}</span>
                                            <span class="stat-label text-[10px]">New</span>
                                        </div>
                                        <div class="stat-card">
                                            <span class="stat-value text-lg text-red-600">{{ (int) ($drift['base_only'] ?? 0) }}</span>
                                            <span class="stat-label text-[10px]">Missing</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if(!empty($driftDetails))
                        @php
                            $matchedCount = count($driftDetails['matched_controls'] ?? []);
                            $thisOnlyCount = count($driftDetails['this_only_controls'] ?? []);
                            $baseOnlyCount = count($driftDetails['base_only_controls'] ?? []);
                        @endphp
                        <details class="mb-6 rounded-xl ring-1 ring-slate-900/5 overflow-hidden">
                            <summary class="px-5 py-3 bg-slate-50 cursor-pointer hover:bg-slate-100 transition-colors flex items-center gap-2 text-sm font-medium text-slate-700">
                                <svg class="w-4 h-4 transform transition-transform group-open:rotate-90 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                Drift Details &mdash; Matched: {{ $matchedCount }} / New: {{ $thisOnlyCount }} / Missing: {{ $baseOnlyCount }}
                            </summary>
                            <div class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
                                @foreach(['matched_controls' => ['Matched', 'badge-green'], 'this_only_controls' => ['New in this', 'badge-amber'], 'base_only_controls' => ['Missing from this', 'badge-red']] as $key => [$label, $badgeClass])
                                    <div>
                                        <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ $label }}</h5>
                                        <div class="space-y-2 max-h-64 overflow-y-auto">
                                            @forelse(($driftDetails[$key] ?? []) as $item)
                                                <div class="bg-slate-50 rounded-lg p-2.5 ring-1 ring-slate-900/5">
                                                    <div class="text-[11px] text-slate-400 font-mono">#{{ (int) ($item['control_index'] ?? 0) }} &middot; {{ \Illuminate\Support\Str::limit((string) ($item['signature_sha256'] ?? ''), 12, '') }}</div>
                                                    @if(!empty($item['placeholder_text']))
                                                        <div class="mt-1 text-xs text-slate-700">{{ \Illuminate\Support\Str::limit($item['placeholder_text'], 100) }}</div>
                                                    @endif
                                                </div>
                                            @empty
                                                <p class="text-xs text-slate-400">(none)</p>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    <!-- Part Switcher -->
                    <div class="flex flex-wrap gap-1 mb-5 border-b border-slate-200">
                        @foreach($allParts as $p)
                            <a
                                href="{{ route('admin.templates.show', ['template' => $template->uuid, 'part' => $p, 'only_fillable' => $onlyFillable ? '1' : null, 'only_unmapped' => $onlyUnmapped ? '1' : null]) }}"
                                @class([
                                    'tab-link',
                                    'tab-link-active' => $part === $p,
                                    'tab-link-inactive' => $part !== $p,
                                ])
                            >
                                {{ $fmtPart((string) $p) }}
                            </a>
                        @endforeach
                    </div>

                    <!-- Filters -->
                    <form method="GET" action="{{ route('admin.templates.show', ['template' => $template->uuid]) }}" class="flex flex-wrap items-center gap-4 mb-6">
                        <input type="hidden" name="part" value="{{ $part }}" />
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" name="only_fillable" value="1" @checked($onlyFillable) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Only fillable
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" name="only_unmapped" value="1" @checked($onlyUnmapped) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Only unmapped
                        </label>
                        <div class="flex items-center gap-3">
                            <x-primary-button>Apply</x-primary-button>
                            <a class="text-sm text-slate-500 hover:text-slate-700" href="{{ route('admin.templates.show', ['template' => $template->uuid, 'part' => $part]) }}">Reset</a>
                        </div>
                    </form>

                    <!-- Mappings Table -->
                    <form method="POST" action="{{ route('admin.templates.mappings', ['template' => $template->uuid, 'part' => $part, 'only_fillable' => $onlyFillable ? '1' : null, 'only_unmapped' => $onlyUnmapped ? '1' : null]) }}">
                        @csrf
                        <input type="hidden" name="part" value="{{ $part }}">

                        <div class="overflow-x-auto rounded-xl ring-1 ring-slate-900/5">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-12">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-20">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Context</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Placeholder</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider min-w-[320px]">Mapped Field</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($controls as $c)
                                        @php
                                            $map = $mappings->get($c->id);
                                            $mappedFieldId = $map?->field_definition_id;
                                            $before = $c->context_before;
                                            $after = $c->context_after;
                                            $status = $driftStatusByControlId[$c->id] ?? null;
                                        @endphp
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ $c->control_index }}</td>
                                            <td class="px-4 py-3">
                                                @if($status === 'matched')
                                                    <span class="badge badge-green text-xs">Matched</span>
                                                @elseif($status === 'new')
                                                    <span class="badge badge-amber text-xs">New</span>
                                                @else
                                                    <span class="text-xs text-slate-300">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($before)
                                                    <div class="text-xs text-slate-600 bg-slate-50 rounded-lg p-2 max-w-xs truncate" title="{{ $before }}">{{ \Illuminate\Support\Str::limit($before, 80) }}</div>
                                                @endif
                                                @if($after)
                                                    <div class="text-xs text-slate-500 bg-slate-50 rounded-lg p-2 mt-1 max-w-xs truncate" title="{{ $after }}">{{ \Illuminate\Support\Str::limit($after, 80) }}</div>
                                                @endif
                                                @if(!$before && !$after)
                                                    <span class="text-xs text-slate-300">(no context)</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-xs text-slate-700 max-w-xs">
                                                    {{ \Illuminate\Support\Str::limit($c->placeholder_text ?? '', 120) }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3" x-data>
                                                <select x-ref="sel" name="mapping[{{ $part }}][{{ $c->control_index }}]" class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">(unmapped)</option>
                                                    @foreach($fields as $f)
                                                        <option value="{{ $f->id }}" @selected($mappedFieldId === $f->id)>
                                                            {{ $f->key }} &mdash; {{ \Illuminate\Support\Str::limit($f->label, 60) }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                @php
                                                    $suggestions = ($mappedFieldId === null) ? ($suggestionsByControlId[$c->id] ?? []) : [];
                                                @endphp
                                                @if(!empty($suggestions))
                                                    <div class="mt-2">
                                                        <span class="text-[11px] text-slate-400">Suggestions</span>
                                                        <div class="mt-1 flex flex-wrap gap-1.5">
                                                            @foreach($suggestions as $s)
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors ring-1 ring-indigo-600/10"
                                                                    x-on:click="$refs.sel.value='{{ (int) ($s['field_definition_id'] ?? 0) }}'; $refs.sel.dispatchEvent(new Event('change'));"
                                                                >
                                                                    {{ $s['key'] ?? '' }}
                                                                    <span class="text-indigo-400">({{ number_format((float) ($s['score'] ?? 0), 2) }})</span>
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6 flex items-center gap-4">
                            <x-primary-button>
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Save Mappings
                            </x-primary-button>
                            <a class="text-sm text-slate-500 hover:text-slate-700" href="{{ route('admin.index', ['tab' => 'templates']) }}">Back to Templates</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
