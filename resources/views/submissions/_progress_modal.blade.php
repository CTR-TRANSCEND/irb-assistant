{{-- SPEC-IRB-FORMSV2-004 §C.5 — Analysis progress modal
     Mirrors the existing project analysis modal pattern from projects/show.blade.php.
     REQ-049: preserve toast/breadcrumb/loading UX --}}
<div
    x-data="submissionAnalysisProgress(
        {{ Js::from($statusUrl) }},
        {{ Js::from($cancelUrl) }},
        {{ Js::from($isAssistantMode) }}
    )"
    x-init="bootstrap()"
    x-show="open"
    x-cloak
    @open-analysis-progress.window="openModal()"
    @keydown.escape.window="open && closeModalKeepPolling()"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 py-6 backdrop-blur-sm"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="submission-analysis-progress-title"
    tabindex="-1"
>
    <div class="w-full max-w-md rounded-lg bg-white shadow-2xl dark:bg-slate-800">
        <div class="border-b border-slate-200 px-5 py-3 dark:border-slate-700 flex items-center justify-between">
            <h2 id="submission-analysis-progress-title" class="text-base font-semibold text-slate-900 dark:text-white">Analysis</h2>
            <span x-show="run.status === 'queued'"     class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200">Queued</span>
            <span x-show="run.status === 'running'"    class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-100">Running</span>
            <span x-show="run.status === 'cancelling'" class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-100">Cancelling…</span>
            <span x-show="run.status === 'cancelled'"  class="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-600 dark:text-slate-100">Cancelled</span>
            <span x-show="run.status === 'failed'"     class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-100">Failed</span>
            <span x-show="run.status === 'succeeded'"  class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-100">Done</span>
        </div>

        <div class="px-5 py-4">
            <ul class="space-y-2.5">
                <template x-for="step in visibleSteps" :key="step.key">
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center" aria-hidden="true">
                            <svg x-show="stepState(step.key) === 'pending'" class="h-4 w-4 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                            <svg x-show="stepState(step.key) === 'active'"  class="h-5 w-5 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <svg x-show="stepState(step.key) === 'done'"    class="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <svg x-show="stepState(step.key) === 'failed'"  class="h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p :class="stepState(step.key) === 'active' ? 'text-sm font-semibold text-slate-900 dark:text-white' : (stepState(step.key) === 'done' ? 'text-sm text-slate-700 dark:text-slate-300' : 'text-sm text-slate-400 dark:text-slate-500')" x-text="step.label"></p>
                            <p x-show="stepState(step.key) === 'active' && stepSubText(step.key)" class="mt-0.5 text-xs text-indigo-700 dark:text-indigo-300 leading-relaxed" x-text="stepSubText(step.key)"></p>
                            <p x-show="stepState(step.key) === 'pending'" class="mt-0.5 text-xs text-slate-400 dark:text-slate-500" x-text="step.hint"></p>
                        </div>
                    </li>
                </template>
            </ul>

            <div x-show="run.is_stale" class="mt-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                <p class="font-semibold">No update in 90+ seconds.</p>
                <p class="mt-0.5">The job may be waiting on the LLM. Check the server queue if this persists.</p>
            </div>

            <div x-show="run.status === 'failed'" class="mt-4 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-900">
                <p class="font-semibold">Analysis failed.</p>
                <p x-show="run.error" x-text="run.error" class="mt-0.5"></p>
            </div>

            <div x-show="run.status === 'cancelling'" class="mt-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                <p class="font-semibold">Stopping at next checkpoint…</p>
                <p class="mt-0.5">Partial results that already saved are kept.</p>
            </div>

            <div x-show="run.status === 'cancelled'" class="mt-4 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                <p class="font-semibold">Analysis cancelled.</p>
                <p class="mt-0.5">Any batches that completed before cancellation kept their results.</p>
            </div>
        </div>

        <div class="border-t border-slate-200 px-5 py-2.5 dark:border-slate-700 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span>Elapsed <span class="font-mono" x-text="elapsed"></span></span>
            <span x-show="run.heartbeat_age_seconds !== null" x-text="`Last update ${run.heartbeat_age_seconds}s ago`"></span>
        </div>

        <div class="border-t border-slate-200 px-5 py-3 dark:border-slate-700 flex items-center justify-end gap-2">
            <button x-show="run.status === 'queued' || run.status === 'running'" @click="cancelRun()" type="button"
                    class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:bg-slate-700 dark:border-red-700 dark:text-red-300 dark:hover:bg-slate-600">
                Cancel
            </button>
            <button x-show="run.status === 'queued' || run.status === 'running' || run.status === 'cancelling'" @click="closeModalKeepPolling()" type="button"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-600">
                Hide
            </button>
            <button x-show="run.status === 'succeeded' || run.status === 'failed' || run.status === 'cancelled'" @click="closeModal()" type="button"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                <span x-text="run.status === 'succeeded' ? 'See results' : 'Close'"></span>
            </button>
        </div>
    </div>
</div>

<script>
function submissionAnalysisProgress(statusUrl, cancelUrl, isAssistantMode) {
    const ALL_STEPS = [
        { key: 'prepare', label: 'Prepare',          hint: 'Read submission, identify questions' },
        { key: 'extract', label: 'Extract evidence',  hint: 'Ask the LLM for verbatim quotes from your docs' },
        { key: 'draft',   label: 'AI drafts',          hint: 'Generate suggestions for unanswered questions' },
        { key: 'save',    label: 'Save results',       hint: 'Persist analysis run' },
    ];
    return {
        statusUrl, cancelUrl, isAssistantMode,
        cancelling: false, open: false,
        run: { status: null, progress_step: null, progress_current: 0, progress_total: 0, progress_message: 'Starting…', is_stale: false, heartbeat_age_seconds: null, started_at: null, finished_at: null, error: null },
        startedAtMs: null, elapsed: '0s', timer: null, tickerTimer: null,

        get visibleSteps() { return this.isAssistantMode ? ALL_STEPS : ALL_STEPS.filter(s => s.key !== 'draft'); },

        stepState(key) {
            const status = this.run.status, backendStep = this.run.progress_step;
            if (status === 'failed') {
                const ak = this.backendStepToPipelineKey(backendStep);
                const order = this.visibleSteps.map(s => s.key);
                const ai = order.indexOf(ak), mi = order.indexOf(key);
                if (ai === -1) return 'failed';
                if (mi < ai) return 'done'; if (mi === ai) return 'failed'; return 'pending';
            }
            if (status === 'succeeded') return 'done';
            if (status === 'queued' || backendStep === 'queued' || backendStep === null) return 'pending';
            const ak = this.backendStepToPipelineKey(backendStep);
            const order = this.visibleSteps.map(s => s.key);
            const ai = order.indexOf(ak), mi = order.indexOf(key);
            if (ai === -1) return 'pending';
            if (mi < ai) return 'done'; if (mi === ai) return 'active'; return 'pending';
        },

        backendStepToPipelineKey(s) {
            if (s === 'initializing') return 'prepare';
            if (s === 'first_pass_batch') return 'extract';
            if (s === 'drafting_field') return 'draft';
            if (s === 'completed') return 'save';
            return null;
        },

        stepSubText(key) {
            if (this.stepState(key) !== 'active') return '';
            if (this.run.progress_message) return this.run.progress_message;
            const cur = this.run.progress_current || 0, tot = this.run.progress_total || 0;
            if (key === 'extract' && tot > 0) return `Question group ${cur} of ${tot}`;
            if (key === 'draft'   && tot > 0) return `Question ${cur} of ${tot}`;
            if (key === 'prepare') return 'Loading documents…';
            if (key === 'save')    return 'Writing results…';
            return '';
        },

        bootstrap() { this.fetchStatus(true); },
        openModal() { this.open = true; this.startPolling(); this.startTicker(); },
        closeModalKeepPolling() { this.open = false; },
        closeModal() { this.open = false; this.stopPolling(); this.stopTicker(); },
        startPolling() { if (!this.timer) { this.timer = setInterval(() => this.fetchStatus(false), 2000); } },

        async cancelRun() {
            if (this.cancelling) return;
            if (!window.confirm('Cancel this analysis? Partial results that already saved are kept.')) return;
            this.cancelling = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const resp = await fetch(this.cancelUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
                    credentials: 'same-origin', body: '{}',
                });
                if (!resp.ok) { const d = await resp.json().catch(() => ({})); alert(d.error || `Cancel failed (HTTP ${resp.status}).`); }
            } catch (e) { alert('Cancel failed: ' + e.message); } finally { this.cancelling = false; }
        },

        stopPolling()  { if (this.timer)       { clearInterval(this.timer);       this.timer       = null; } },
        startTicker()  { if (!this.tickerTimer) { this.tickerTimer = setInterval(() => this.recalcElapsed(), 1000); } },
        stopTicker()   { if (this.tickerTimer)  { clearInterval(this.tickerTimer); this.tickerTimer = null; } },

        async fetchStatus(openIfActive) {
            try {
                const resp = await fetch(this.statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!resp.ok) return;
                const data = await resp.json();
                if (!data.has_run) return;
                const prevStatus = this.run.status;
                this.run = data.run;
                if (data.run.started_at) { this.startedAtMs = Date.parse(data.run.started_at); this.recalcElapsed(); }
                const isActive = ['queued', 'running', 'cancelling'].includes(data.run.status);
                if (openIfActive && isActive) { this.openModal(); }
                if (prevStatus && ['queued', 'running'].includes(prevStatus) && data.run.status === 'succeeded') {
                    setTimeout(() => window.location.reload(), 1500);
                }
                if (['failed', 'succeeded', 'cancelled'].includes(data.run.status)) { this.stopPolling(); }
            } catch (e) { /* swallow; next tick will retry */ }
        },

        recalcElapsed() {
            if (!this.startedAtMs) { this.elapsed = '0s'; return; }
            const sec = Math.max(0, Math.floor((Date.now() - this.startedAtMs) / 1000));
            if (sec < 60) { this.elapsed = `${sec}s`; }
            else if (sec < 3600) { this.elapsed = `${Math.floor(sec/60)}m ${sec%60}s`; }
            else { this.elapsed = `${Math.floor(sec/3600)}h ${Math.floor((sec%3600)/60)}m`; }
        },
    };
}
</script>
