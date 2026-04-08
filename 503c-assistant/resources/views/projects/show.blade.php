<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('projects.index') }}" class="text-slate-500 hover:text-slate-700 transition-colors" aria-label="Back to Projects">
                    <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <nav class="breadcrumb mb-0.5" aria-label="Breadcrumb">
                        <a href="{{ route('projects.index') }}" class="breadcrumb-item">Projects</a>
                        <span class="breadcrumb-separator" aria-hidden="true">/</span>
                        <span class="breadcrumb-item-current">{{ $project->name }}</span>
                    </nav>
                    <h2 class="text-xl font-bold text-slate-900">{{ $project->name }}</h2>
                </div>
            </div>
            <span @class([
                'badge',
                'badge-blue' => $project->status === 'draft',
                'badge-amber' => $project->status === 'analysis',
                'badge-indigo' => $project->status === 'review',
                'badge-green' => $project->status === 'export',
                'badge-gray' => !in_array($project->status, ['draft', 'analysis', 'review', 'export']),
            ]) aria-label="Project status: {{ $project->status }}" role="status">{{ $project->status }}</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="card">
                <!-- Tab Navigation -->
                <div class="tab-nav overflow-x-auto" role="tablist" aria-label="Project sections">
                    @php
                        $tabs = [
                            'documents' => ['label' => 'Documents', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                            'review' => ['label' => 'Review', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                            'questions' => ['label' => 'Questions', 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                            'export' => ['label' => 'Export', 'icon' => 'M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                            'activity' => ['label' => 'Activity', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ];
                    @endphp
                    @foreach($tabs as $tabKey => $tabInfo)
                        <a
                            id="tab-{{ $tabKey }}"
                            class="tab-link flex items-center gap-2 whitespace-nowrap {{ $tab === $tabKey ? 'tab-link-active' : 'tab-link-inactive' }}"
                            href="{{ route('projects.show', ['project' => $project->uuid, 'tab' => $tabKey]) }}"
                            role="tab"
                            aria-selected="{{ $tab === $tabKey ? 'true' : 'false' }}"
                            aria-controls="tabpanel-{{ $tabKey }}"
                        >
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tabInfo['icon'] }}"/></svg>
                            {{ $tabInfo['label'] }}
                        </a>
                    @endforeach
                </div>

                <div
                    id="tabpanel-{{ $tab }}"
                    class="p-6"
                    role="tabpanel"
                    aria-labelledby="tab-{{ $tab }}"
                >

                    {{-- ============ DOCUMENTS TAB ============ --}}
                    @if($tab === 'documents')
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <div class="space-y-6">
                                <!-- Upload Section -->
                                <div>
                                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                        Upload documents
                                    </h3>
                                    <p class="text-sm text-slate-600 mt-1">DOCX, PDF, or TXT files. Study outlines work too.</p>

                                    <form class="mt-4" method="POST" action="{{ route('projects.documents.store', ['project' => $project->uuid]) }}" enctype="multipart/form-data" x-data="{ loading: false }" @submit="loading = true">
                                        @csrf
                                        <div class="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center hover:border-indigo-400 transition-colors">
                                            <svg class="mx-auto h-10 w-10 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <label for="documents" class="sr-only">Select documents to upload</label>
                                            <input id="documents" type="file" name="documents[]" multiple class="mt-3 block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                                        </div>
                                        <x-input-error :messages="$errors->get('documents')" class="mt-2" />
                                        <x-input-error :messages="$errors->get('documents.*')" class="mt-2" />
                                        <div class="mt-4">
                                            <x-primary-button class="w-full justify-center" x-bind:disabled="loading">
                                                <span x-show="!loading" class="inline-flex items-center">
                                                    <svg class="w-4 h-4 mr-1.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                                    Upload & Extract
                                                </span>
                                                <span x-show="loading" class="inline-flex items-center" x-cloak>
                                                    <span class="spinner mr-1.5" aria-hidden="true"></span>
                                                    Uploading...
                                                </span>
                                            </x-primary-button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Analysis Section -->
                                <div class="border-t border-slate-200 pt-6">
                                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                        AI Analysis
                                    </h3>
                                    <p class="text-sm text-slate-600 mt-1">Auto-fill fields with evidence from your documents.</p>

                                    <form class="mt-3" method="POST" action="{{ route('projects.provider.update', ['project' => $project->uuid]) }}" x-data="{ loading: false }" @submit="loading = true">
                                        @csrf
                                        <input type="hidden" name="tab" value="documents" />
                                        <x-input-label value="LLM Provider" />
                                        <select name="llm_provider_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="">System default</option>
                                            @foreach(($providerOptions ?? collect()) as $p)
                                                <option value="{{ $p->id }}" @selected((int) ($project->llm_provider_id ?? 0) === (int) $p->id)>
                                                    {{ $p->name }} @if($p->model) ({{ $p->model }}) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('llm_provider_id')" class="mt-2" />
                                        <div class="mt-2">
                                            <x-secondary-button type="submit" class="text-xs" x-bind:disabled="loading">
                                                <span x-show="!loading">Save preference</span>
                                                <span x-show="loading" class="inline-flex items-center">
                                                    <span class="spinner spinner-sm mr-2" aria-hidden="true"></span> Saving...
                                                </span>
                                            </x-secondary-button>
                                        </div>
                                    </form>

                                    @if(!($hasEnabledProvider ?? false))
                                        <div class="alert alert-warning mt-3">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                                No LLM provider enabled. Configure one in Admin &gt; LLM Providers.
                                            </div>
                                        </div>
                                    @endif

                                    <form class="mt-3" method="POST" action="{{ route('projects.analyze', ['project' => $project->uuid]) }}" x-data="{ loading: false }" @submit="loading = true">
                                        @csrf
                                        <x-primary-button class="w-full justify-center" :disabled="!($hasEnabledProvider ?? false)" x-bind:disabled="loading || !{{ ($hasEnabledProvider ?? false) ? 'true' : 'false' }}">
                                            <span x-show="!loading" class="inline-flex items-center">
                                                <svg class="w-4 h-4 mr-1.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                                Run Analysis
                                            </span>
                                            <span x-show="loading" class="inline-flex items-center" x-cloak>
                                                <span class="spinner mr-1.5" aria-hidden="true"></span>
                                                Analyzing...
                                            </span>
                                        </x-primary-button>
                                    </form>
                                </div>
                            </div>

                            <!-- Document List -->
                            <div class="lg:col-span-2">
                                <h3 class="text-base font-semibold text-slate-900">Uploaded Documents</h3>
                                <div class="mt-3 space-y-3">
                                    @forelse($documents as $doc)
                                        <div class="flex items-start gap-4 p-4 rounded-xl bg-slate-50 ring-1 ring-slate-900/5">
                                            <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center {{ $doc->kind === 'pdf' ? 'bg-red-100 text-red-600' : ($doc->kind === 'docx' ? 'bg-blue-100 text-blue-600' : 'bg-slate-100 text-slate-600') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-slate-900 truncate">{{ $doc->original_filename }}</span>
                                                    <span class="badge badge-gray">{{ strtoupper($doc->kind) }}</span>
                                                </div>
                                                <div class="flex items-center gap-3 mt-1 text-xs text-slate-600">
                                                    <span>{{ number_format($doc->size_bytes / 1024, 1) }} KB</span>
                                                    <span @class([
                                                        'badge text-xs',
                                                        'badge-green' => $doc->extraction_status === 'completed',
                                                        'badge-blue' => $doc->extraction_status === 'pending',
                                                        'badge-red' => in_array($doc->extraction_status, ['failed', 'blocked']),
                                                        'badge-gray' => !in_array($doc->extraction_status, ['completed', 'pending', 'failed', 'blocked']),
                                                    ])>{{ $doc->extraction_status }}</span>
                                                    @if($doc->scan_status)
                                                        <span @class([
                                                            'badge text-xs',
                                                            'badge-green' => $doc->scan_status === 'clean',
                                                            'badge-amber' => $doc->scan_status === 'unscanned',
                                                            'badge-red' => $doc->scan_status === 'infected',
                                                        ])>scan: {{ $doc->scan_status }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-slate-400 mt-1">{{ $doc->created_at->diffForHumans() }}</div>
                                            </div>
                                        </div>
                                        @if($doc->extraction_status === 'failed')
                                            <div class="alert alert-error -mt-1 ml-14">{{ $doc->extraction_error }}</div>
                                        @endif
                                        @if($doc->extraction_status === 'blocked')
                                            <div class="alert alert-error -mt-1 ml-14">
                                                {{ $doc->extraction_error }}
                                                @if($doc->quarantine_storage_path) File quarantined.@endif
                                            </div>
                                        @endif
                                    @empty
                                        <div class="empty-state py-16 rounded-xl bg-slate-50 ring-1 ring-slate-900/5">
                                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <p class="empty-state-title">No documents yet</p>
                                            <p class="empty-state-text">Upload study documents to begin.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                    {{-- ============ REVIEW TAB ============ --}}
                    @elseif($tab === 'review')
                        @php
                            $viewModel = new \App\ViewModels\Projects\ReviewTabViewModel(
                                $project,
                                $selectedFieldValue
                            );
                            $fieldListData = $viewModel->getFieldListData($fieldValues);
                        @endphp

                        <p class="text-sm text-slate-600 mb-5">Review AI suggestions, verify evidence, and confirm each field.</p>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" x-data="{ q: '' }">
                            <div class="lg:col-span-1">
                                <x-review-field-list
                                    :fieldValues="$fieldListData['fieldValues']"
                                    :selected="$selectedFieldValue"
                                    :project="$project"
                                    :stats="$fieldListData['stats']"
                                />
                            </div>

                            <div class="lg:col-span-2">
                                @if($selectedFieldValue === null)
                                    <div class="empty-state py-16">
                                        <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                                        <p class="empty-state-title">Select a field to review</p>
                                        <p class="empty-state-text">Choose a field from the list to see details and evidence.</p>
                                    </div>
                                @else
                                    @php
                                        $evParam = request()->query('ev');
                                        $evParam = is_string($evParam) && ctype_digit($evParam) ? (int) $evParam : null;
                                        $fieldEditorData = $viewModel->getFieldEditorData();
                                        $evidenceViewerData = $viewModel->getEvidenceViewerData($evParam);
                                    @endphp

                                    <x-review-field-editor
                                        :fieldValue="$fieldEditorData"
                                        :project="$project"
                                    />

                                    <div class="mt-6">
                                        <x-review-evidence-viewer
                                            :fieldValue="$evidenceViewerData"
                                            :project="$project"
                                            :activeEvidenceId="$evParam"
                                        />
                                    </div>
                                @endif
                            </div>
                        </div>

                    {{-- ============ QUESTIONS TAB ============ --}}
                    @elseif($tab === 'questions')
                        @php
                            $missing = $missingFieldValues ?? collect();
                            $stats = $fieldValueStats ?? ['total' => 0, 'missing' => 0, 'suggested' => 0, 'edited' => 0, 'confirmed' => 0];
                            $pct = $stats['total'] > 0 ? round((($stats['total'] - $stats['missing']) / $stats['total']) * 100) : 0;
                        @endphp

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">Quick Fill</h3>
                                <p class="text-sm text-slate-600">Complete missing fields to reach draft-ready state.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-slate-900">{{ $stats['total'] - $stats['missing'] }} / {{ $stats['total'] }}</div>
                                    <div class="text-xs text-slate-600">fields completed</div>
                                </div>
                                <div class="w-16 h-16 relative">
                                    <svg class="w-16 h-16 -rotate-90" viewBox="0 0 36 36">
                                        <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" stroke-width="3" class="text-slate-100"/>
                                        <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="{{ $pct }}, 100" stroke-linecap="round" class="text-indigo-500"/>
                                    </svg>
                                    <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-slate-700">{{ $pct }}%</span>
                                </div>
                            </div>
                        </div>

                        @if($missing->isEmpty())
                            <div class="empty-state py-16 rounded-xl bg-emerald-50 ring-1 ring-emerald-600/10">
                                <svg class="mx-auto h-12 w-12 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="mt-3 text-sm font-semibold text-emerald-900">All fields completed</p>
                                <p class="mt-1 text-sm text-emerald-700">You can proceed to Export.</p>
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach($missing as $fv)
                                    <div class="rounded-xl bg-white ring-1 ring-slate-900/5 p-5">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-slate-900">{{ $fv->field->label ?? $fv->field->key }}</h4>
                                                <p class="text-xs text-slate-600 mt-0.5">{{ $fv->field->key }}</p>
                                                @if($fv->field->question_text)
                                                    <p class="text-sm text-slate-700 mt-2">{{ $fv->field->question_text }}</p>
                                                @endif
                                            </div>
                                            <span class="badge badge-amber">Missing</span>
                                        </div>

                                        <form class="mt-4" method="POST" action="{{ route('projects.fields.update', ['project' => $project->uuid, 'value' => $fv->id]) }}" x-data="{ loading: false }" @submit="loading = true">
                                            @csrf
                                            <input type="hidden" name="tab" value="questions" />
                                            <textarea name="final_value" class="w-full rounded-lg border-slate-300 text-sm focus:ring-brand-500 focus:border-brand-500 placeholder:text-slate-400" rows="3" placeholder="Enter your response..."></textarea>
                                            <div class="flex justify-end mt-3">
                                                <input type="hidden" name="confirm" value="1" />
                                                <x-primary-button x-bind:disabled="loading">
                                                    <span x-show="!loading" class="inline-flex items-center">
                                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        Save & Confirm
                                                    </span>
                                                    <span x-show="loading" class="inline-flex items-center">
                                                        <span class="spinner spinner-sm mr-2" aria-hidden="true"></span> Saving...
                                                    </span>
                                                </x-primary-button>
                                            </div>
                                        </form>

                                        @if($fv->evidence->count() > 0)
                                            <div class="mt-4 border-t border-slate-100 pt-4">
                                                <p class="text-xs font-medium text-slate-600 uppercase tracking-wider mb-2">Evidence ({{ $fv->evidence->count() }})</p>
                                                <div class="space-y-2">
                                                    @foreach($fv->evidence->take(2) as $ev)
                                                        <div class="flex items-start justify-between gap-3 p-3 rounded-lg bg-slate-50 text-sm">
                                                            <div class="flex-1 min-w-0">
                                                                <span class="font-medium text-slate-700">{{ $ev->chunk->document->original_filename ?? 'Document' }}</span>
                                                                <p class="mt-1 text-slate-600 text-xs line-clamp-2">{{ $ev->excerpt_text }}</p>
                                                            </div>
                                                            <a class="text-indigo-600 hover:text-indigo-800 text-xs font-medium whitespace-nowrap" href="{{ route('projects.show', ['project' => $project->uuid, 'tab' => 'review', 'fv' => $fv->id, 'ev' => $ev->id]) }}">View &rarr;</a>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                    {{-- ============ EXPORT TAB ============ --}}
                    @elseif($tab === 'export')
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Export Protocol
                                </h3>
                                <p class="text-sm text-slate-600 mt-1">Generate a filled HRP-503c DOCX using the active template and current field values.</p>

                                <form class="mt-5" method="POST" action="{{ route('projects.exports.store', ['project' => $project->uuid]) }}" x-data="{ loading: false }" @submit="loading = true">
                                    @csrf
                                    <x-primary-button class="w-full justify-center" x-bind:disabled="loading">
                                        <span x-show="!loading" class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Generate DOCX Export
                                        </span>
                                        <span x-show="loading" class="inline-flex items-center" x-cloak>
                                            <span class="spinner mr-1.5" aria-hidden="true"></span>
                                            Generating...
                                        </span>
                                    </x-primary-button>
                                </form>

                                <div class="alert alert-info mt-5">
                                    <div class="flex gap-2">
                                        <svg class="w-5 h-5 flex-shrink-0 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <p>The generated document includes all confirmed and suggested field values. Review all fields before exporting for best results.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-2">
                                <h3 class="text-base font-semibold text-slate-900">Export History</h3>
                                <p class="text-sm text-slate-600 mt-1">Previous exports and download links</p>

                                <div class="mt-4 space-y-3">
                                    @forelse(($exports ?? collect()) as $ex)
                                        <div class="flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 ring-1 ring-slate-900/5">
                                            <div class="flex items-center gap-3">
                                                <div @class([
                                                    'flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center',
                                                    'bg-emerald-100 text-emerald-600' => $ex->status === 'ready',
                                                    'bg-red-100 text-red-600' => $ex->status === 'failed',
                                                    'bg-sky-100 text-sky-600' => $ex->status === 'processing',
                                                    'bg-slate-100 text-slate-600' => !in_array($ex->status, ['ready', 'failed', 'processing']),
                                                ])>
                                                    @if($ex->status === 'ready')
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    @elseif($ex->status === 'failed')
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    @else
                                                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                    @endif
                                                </div>
                                                <div>
                                                    <span class="font-medium text-slate-900 text-sm">Export #{{ substr($ex->uuid, 0, 8) }}</span>
                                                    <div class="flex items-center gap-2 mt-0.5">
                                                        <span @class([
                                                            'badge text-xs',
                                                            'badge-green' => $ex->status === 'ready',
                                                            'badge-red' => $ex->status === 'failed',
                                                            'badge-blue' => $ex->status === 'processing',
                                                            'badge-gray' => !in_array($ex->status, ['ready', 'failed', 'processing']),
                                                        ])>{{ $ex->status }}</span>
                                                        <span class="text-xs text-slate-600">{{ $ex->created_at->diffForHumans() }}</span>
                                                    </div>
                                                    @if($ex->status === 'failed')
                                                        <p class="mt-1 text-xs text-red-600">{{ $ex->error }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($ex->status === 'ready' && $ex->storage_path)
                                                <a class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 shadow-sm transition-colors" href="{{ route('exports.download', ['export' => $ex->uuid]) }}">
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                    Download
                                                </a>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="empty-state py-16 rounded-xl bg-slate-50 ring-1 ring-slate-900/5">
                                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <p class="empty-state-title">No exports yet</p>
                                            <p class="empty-state-text">Generate your first export to download a filled HRP-503c document.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                    {{-- ============ ACTIVITY TAB ============ --}}
                    @elseif($tab === 'activity')
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">Activity Log</h3>
                                <p class="text-sm text-slate-600">Timeline of uploads, analysis runs, edits, and exports.</p>
                            </div>
                            <span class="badge badge-gray">{{ ($auditEvents ?? collect())->count() }} events</span>
                        </div>

                        <div class="space-y-1">
                            @forelse(($auditEvents ?? collect()) as $ev)
                                <div class="flex gap-4 p-4 rounded-xl hover:bg-slate-50 transition-colors">
                                    @php
                                        $letter = strtoupper(substr($ev->event_type ?? 'E', 0, 1));
                                        $colors = match($letter) {
                                            'D' => 'bg-blue-100 text-blue-700',
                                            'A' => 'bg-purple-100 text-purple-700',
                                            'F' => 'bg-amber-100 text-amber-700',
                                            'E' => 'bg-emerald-100 text-emerald-700',
                                            'P' => 'bg-red-100 text-red-700',
                                            default => 'bg-slate-100 text-slate-700',
                                        };
                                    @endphp
                                    <span class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold {{ $colors }}">
                                        {{ $letter }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-slate-900 text-sm">{{ $ev->event_type }}</span>
                                            <span class="text-xs text-slate-600">{{ $ev->occurred_at?->diffForHumans() ?? $ev->created_at->diffForHumans() }}</span>
                                        </div>
                                        @if($ev->causer_id)
                                            <p class="text-xs text-slate-600 mt-0.5">User #{{ $ev->causer_id }}</p>
                                        @endif
                                        @if($ev->payload)
                                            <details class="mt-2 group">
                                                <summary class="text-xs text-slate-600 cursor-pointer hover:text-slate-800 flex items-center gap-1">
                                                    <svg class="w-3 h-3 transform group-open:rotate-90 transition-transform" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                    Details
                                                </summary>
                                                <pre class="mt-2 text-xs bg-slate-900 text-slate-100 rounded-lg p-4 overflow-x-auto">{{ json_encode($ev->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </details>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state py-16">
                                    <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <p class="empty-state-title">No activity yet</p>
                                    <p class="empty-state-text">Activity appears here as you work on this project.</p>
                                </div>
                            @endforelse
                        </div>

                        <!-- Danger Zone -->
                        <div class="mt-10 border-t border-slate-200 pt-6">
                            <h3 class="text-base font-semibold text-red-900">Danger Zone</h3>
                            <p class="text-sm text-slate-600 mt-1">Permanently delete this project, all documents, analysis data, and exports. Audit events are retained with redacted payloads.</p>

                            <x-danger-button
                                class="mt-4"
                                x-data=""
                                x-on:click.prevent="$dispatch('open-modal', 'confirm-project-deletion')"
                            >
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Delete Project
                            </x-danger-button>

                            <x-modal name="confirm-project-deletion" :show="$errors->projectDeletion->isNotEmpty()" focusable>
                                <form method="post" action="{{ route('projects.destroy', ['project' => $project->uuid]) }}" class="p-6">
                                    @csrf
                                    @method('delete')

                                    <h2 class="text-lg font-bold text-slate-900">Delete this project?</h2>
                                    <p class="mt-2 text-sm text-slate-600">This action cannot be undone. Type the project name and enter your password to confirm.</p>

                                    <div class="mt-6 space-y-4">
                                        <div>
                                            <x-input-label for="confirm_name" value="Project name" />
                                            <x-text-input id="confirm_name" name="confirm_name" type="text" class="mt-1 block w-full" placeholder="{{ $project->name }}" />
                                            <x-input-error :messages="$errors->projectDeletion->get('confirm_name')" class="mt-2" />
                                        </div>

                                        <div>
                                            <x-input-label for="password" value="Your password" />
                                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" placeholder="Password" />
                                            <x-input-error :messages="$errors->projectDeletion->get('password')" class="mt-2" />
                                        </div>
                                    </div>

                                    <div class="mt-6 flex justify-end gap-3">
                                        <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                                        <x-danger-button>Delete project</x-danger-button>
                                    </div>
                                </form>
                            </x-modal>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
