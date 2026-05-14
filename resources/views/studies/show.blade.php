<x-app-layout>
    @section('title', $study->nickname ?: 'Study')
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <a href="{{ route('studies.index') }}"
                   class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
                   aria-label="Back to Studies">
                    <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <nav class="breadcrumb mb-0.5" aria-label="Breadcrumb">
                        <a href="{{ route('studies.index') }}" class="breadcrumb-item">Studies</a>
                        <span class="breadcrumb-separator" aria-hidden="true">/</span>
                        <span class="breadcrumb-item-current">{{ $study->nickname ?? 'Untitled study' }}</span>
                    </nav>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                        {{ $study->nickname ?? 'Untitled study' }}
                    </h1>
                    @if($study->application_title)
                        <p class="mt-0.5 text-sm text-slate-700 dark:text-slate-300 font-medium">{{ $study->application_title }}</p>
                    @endif
                    @if($study->pi_name)
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">PI: {{ $study->pi_name }}</p>
                    @endif
                    <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Created {{ $study->created_at->diffForHumans() }}</p>
                </div>
            </div>

            {{-- Kebab menu --}}
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button type="button"
                            aria-label="Study actions"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition ease-in-out duration-150 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">
                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/>
                        </svg>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <button type="button"
                            x-data=""
                            x-on:click.prevent="$dispatch('open-modal', 'confirm-study-deletion')"
                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-700 hover:bg-red-50 focus:outline-none focus:bg-red-50 transition duration-150 ease-in-out">
                        <span class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete study
                        </span>
                    </button>
                </x-slot>
            </x-dropdown>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @php
                $submissionsByCode = $study->submissions->keyBy(
                    fn ($s) => optional($s->formDefinition)->form_code
                );
                $formCodes = [
                    'HRP-503'  => ['name' => 'HRP-503: Full Protocol Application', 'desc' => 'Full IRB application for human-subjects research.'],
                    'HRP-503c' => ['name' => 'HRP-503c: Engagement Determination', 'desc' => 'Short engagement determination form.'],
                    'HRP-398'  => ['name' => 'HRP-398: AI Considerations Worksheet', 'desc' => 'Guidance worksheet for AI/ML in research (not submitted to IRB).'],
                ];
                $statusBadgeMap = [
                    'draft'         => 'badge-blue',
                    'submitted'     => 'badge-indigo',
                    'under_review'  => 'badge-amber',
                    'approved'      => 'badge-green',
                    'rejected'      => 'badge-red',
                    'withdrawn'     => 'badge-gray',
                    'tracking_only' => 'badge-gray',
                ];
                $currentUrl = request()->url();
            @endphp

            {{-- ── Source Documents (SPEC-IRB-FORMSV2-008 REQ-P8-006) ──────────────── --}}
            <div class="card mb-6"
                 x-data="{
                     dragging: false,
                     handleDrop(e) {
                         this.dragging = false;
                         const files = e.dataTransfer?.files;
                         if (!files || files.length === 0) return;
                         const input = this.$refs.fileInput;
                         input.files = files;
                         this.$refs.uploadForm.submit();
                     }
                 }"
                 @dragover.prevent="dragging = true"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="handleDrop($event)">

                <div class="px-6 pt-5 pb-3 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white" id="documents-heading">
                        Source Documents
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Documents are shared across all submissions in this study and used by the AI Analyze feature.
                    </p>
                </div>

                {{-- Flash messages --}}
                @if(session('error'))
                    <div class="mx-6 mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 dark:bg-red-900/20 dark:border-red-800" role="alert">
                        <p class="text-sm text-red-800 dark:text-red-300">{{ session('error') }}</p>
                    </div>
                @endif

                {{-- Upload card --}}
                <div class="p-6">
                    <form method="POST"
                          x-ref="uploadForm"
                          action="{{ route('studies.documents.store', ['study_uuid' => $study->uuid]) }}"
                          enctype="multipart/form-data"
                          aria-labelledby="documents-heading">
                        @csrf

                        <div class="relative rounded-xl border-2 border-dashed transition-colors"
                             :class="dragging
                                 ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                 : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500'">
                            <div class="flex flex-col items-center justify-center gap-3 px-6 py-8 text-center">
                                <svg class="w-10 h-10 text-slate-400 dark:text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>

                                <div>
                                    <label for="doc-file-input"
                                           class="cursor-pointer font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300 focus-within:underline">
                                        Choose a file
                                    </label>
                                    <span class="text-sm text-slate-500 dark:text-slate-400"> or drag and drop here</span>
                                </div>

                                <p id="doc-file-hint"
                                   class="text-xs text-slate-500 dark:text-slate-400">
                                    PDF or DOCX, max 100&nbsp;MB. Documents are scanned and encrypted at rest.
                                </p>

                                <input id="doc-file-input"
                                       x-ref="fileInput"
                                       type="file"
                                       name="file"
                                       required
                                       accept="application/pdf,.pdf,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                       aria-describedby="doc-file-hint"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                       @change="$refs.uploadForm.submit()" />
                            </div>
                        </div>

                        @error('file')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                        @enderror

                        {{-- Fallback submit button (works without JS / @change) --}}
                        <noscript>
                            <div class="mt-3 flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Upload
                                </button>
                            </div>
                        </noscript>
                    </form>
                </div>

                {{-- Document list --}}
                @if($study->documents->isNotEmpty())
                    <div class="border-t border-slate-200 dark:border-slate-700">
                        <ul class="divide-y divide-slate-100 dark:divide-slate-700/60" role="list" aria-label="Uploaded documents">
                            @foreach($study->documents as $doc)
                                @php
                                    $extractionBadge = match($doc->extraction_status) {
                                        'pending'    => ['label' => 'Pending',    'class' => 'badge-gray'],
                                        'processing' => ['label' => 'Processing', 'class' => 'badge-amber'],
                                        'completed'  => ['label' => 'Extracted',  'class' => 'badge-green'],
                                        'failed'     => ['label' => 'Failed',     'class' => 'badge-red'],
                                        default      => ['label' => ucfirst((string) $doc->extraction_status), 'class' => 'badge-gray'],
                                    };

                                    // Human-readable size without Number::fileSize() (Laravel 10 compat)
                                    $bytes = (int) $doc->size_bytes;
                                    if ($bytes >= 1_048_576) {
                                        $sizeLabel = number_format($bytes / 1_048_576, 1) . ' MB';
                                    } elseif ($bytes >= 1024) {
                                        $sizeLabel = number_format($bytes / 1024, 0) . ' KB';
                                    } else {
                                        $sizeLabel = $bytes . ' B';
                                    }
                                @endphp
                                <li class="flex items-center justify-between gap-4 px-6 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <svg class="w-5 h-5 shrink-0 text-slate-400 dark:text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200" title="{{ $doc->original_filename }}">
                                                {{ $doc->original_filename }}
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ $sizeLabel }} &middot; {{ $doc->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 shrink-0">
                                        <span class="badge {{ $extractionBadge['class'] }}" aria-label="Extraction status: {{ $extractionBadge['label'] }}">
                                            {{ $extractionBadge['label'] }}
                                        </span>

                                        <form method="POST"
                                              action="{{ route('studies.documents.destroy', ['study_uuid' => $study->uuid, 'doc_uuid' => $doc->uuid]) }}"
                                              onsubmit="return confirm('Delete {{ addslashes($doc->original_filename) }}? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 dark:hover:text-red-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 transition-colors"
                                                    aria-label="Delete {{ $doc->original_filename }}">
                                                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="px-6 pb-6">
                        <p class="text-sm text-slate-500 dark:text-slate-400 italic">No documents uploaded yet. Upload a PDF or DOCX to enable the AI Analyze feature on your submissions.</p>
                    </div>
                @endif
            </div>
            {{-- ── End Source Documents ─────────────────────────────────────────── --}}

            <div class="card">
                {{-- Tab strip: server-rendered links — use aria-current="page" instead
                     of role="tab" since arrow-key navigation is not implemented
                     (WCAG 4.1.2; Batch C C8). --}}
                <nav class="tab-nav overflow-x-auto" aria-label="Submission forms">
                    @foreach($formCodes as $code => $info)
                        @php
                            $sub = $submissionsByCode[$code] ?? null;
                            $tabUrl = $sub
                                ? route('submissions.show', ['uuid' => $study->uuid, 'form_code' => $code])
                                : '#';
                            $isActive = str_contains($currentUrl, urlencode($code)) || str_contains($currentUrl, $code);
                        @endphp
                        <a href="{{ $tabUrl }}"
                           class="tab-link whitespace-nowrap {{ $isActive ? 'tab-link-active' : 'tab-link-inactive' }}"
                           @if($isActive) aria-current="page" @endif>
                            {{ $code }}
                        </a>
                    @endforeach
                </nav>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        @foreach($formCodes as $code => $info)
                            @php
                                $sub = $submissionsByCode[$code] ?? null;
                                $status = $sub ? ($sub->status ?? 'draft') : 'draft';
                                $badgeClass = $statusBadgeMap[$status] ?? 'badge-gray';
                                $isHrp398 = $code === 'HRP-398';
                            @endphp
                            <div class="submission-card rounded-xl bg-slate-50 dark:bg-slate-700/50 ring-1 ring-slate-900/5 dark:ring-white/10 p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">{{ $info['name'] }}</h3>
                                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $info['desc'] }}</p>
                                    </div>
                                    <span class="badge {{ $badgeClass }}" aria-label="Status: {{ str_replace('_', ' ', $status) }}">
                                        {{ str_replace('_', ' ', $status) }}
                                    </span>
                                </div>
                                <div class="mt-4">
                                    @if($sub)
                                        <a href="{{ route('submissions.show', ['uuid' => $study->uuid, 'form_code' => $code]) }}"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                            {{ $isHrp398 ? 'View HRP-398 guidance' : 'Continue this submission' }}
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-500 dark:text-slate-400 italic">Submission not yet available</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Study deletion confirmation modal --}}
    <x-modal name="confirm-study-deletion" :show="false" focusable>
        <form method="post" action="{{ route('studies.destroy', ['uuid' => $study->uuid]) }}" class="p-6" autocomplete="off">
            @csrf
            @method('delete')

            <h2 id="modal-title-confirm-study-deletion" class="text-lg font-bold text-slate-900 dark:text-white">Delete this study?</h2>

            <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 dark:bg-red-900/20 dark:border-red-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-800 dark:text-red-300">You are about to delete</p>
                <p class="mt-1 text-base font-semibold text-red-900 dark:text-red-200 break-words">{{ $study->nickname ?? $study->application_title ?? 'Untitled study' }}</p>
            </div>

            <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">This action cannot be undone. All submissions, answers, uploads, and exports associated with this study will be permanently deleted.</p>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                <x-danger-button>Delete study</x-danger-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
