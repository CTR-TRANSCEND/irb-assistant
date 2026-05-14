{{-- Section-group navigation for HRP-503.
     Desktop (≥md): sticky sidebar.
     Mobile (<md): collapsible accordion at top.
     LD-P5-2, REQ-P5-001 --}}

{{-- ── Desktop sticky sidebar (hidden on mobile) ────────────────────────────────── --}}
<nav role="navigation"
     aria-label="Section groups"
     class="hidden md:block sticky top-6 space-y-1">
    <p class="px-2 mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
        Sections
    </p>
    @foreach($sectionGroups as $group)
        @php $sectionCodes = $group->section_ids_json ?? []; @endphp
        <div class="mb-3">
            <p class="px-2 py-1 text-xs font-semibold text-slate-700 dark:text-slate-300 leading-snug">
                {{ $group->label }}
            </p>
            <ul class="space-y-0.5">
                @foreach($sectionCodes as $code)
                    @php
                        $isVisible = $sectionVisibility[$code] ?? true;
                    @endphp
                    <li>
                        @if($isVisible)
                            <a href="#section-{{ $code }}"
                               class="group flex items-center justify-between px-2 py-1 rounded-md text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
                               aria-label="Jump to section {{ $code }}">
                                <span>{{ $code }}</span>
                            </a>
                        @else
                            <span class="flex items-center justify-between px-2 py-1 rounded-md text-xs text-slate-400 dark:text-slate-600 cursor-not-allowed"
                                  aria-disabled="true"
                                  role="link"
                                  aria-label="Section {{ $code }} — locked">
                                <span class="opacity-60">{{ $code }}</span>
                                <span class="inline-flex items-center rounded-full bg-slate-200 dark:bg-slate-700 px-1.5 py-0.5 text-xs font-medium text-slate-500 dark:text-slate-400"
                                      aria-hidden="true">Locked</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>

{{-- ── Mobile accordion (visible on mobile, hidden on md+) ─────────────────────── --}}
<div class="md:hidden mb-6"
     x-data="{ open: false }"
     role="navigation"
     aria-label="Section groups">
    <button type="button"
            class="w-full flex items-center justify-between px-4 py-3 bg-slate-100 dark:bg-slate-800 rounded-lg text-sm font-semibold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-controls="section-nav-mobile">
        <span>Navigate Sections</span>
        <svg class="w-4 h-4 transition-transform duration-200"
             :class="open ? 'rotate-180' : ''"
             aria-hidden="true"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div id="section-nav-mobile"
         x-show="open"
         x-cloak
         x-collapse
         class="mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg divide-y divide-slate-100 dark:divide-slate-700">
        @foreach($sectionGroups as $group)
            @php $sectionCodes = $group->section_ids_json ?? []; @endphp
            <div class="px-4 py-3">
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    {{ $group->label }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($sectionCodes as $code)
                        @php $isVisible = $sectionVisibility[$code] ?? true; @endphp
                        @if($isVisible)
                            <a href="#section-{{ $code }}"
                               @click="open = false"
                               class="inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-800 transition-colors"
                               aria-label="Jump to section {{ $code }}">
                                {{ $code }}
                            </a>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-0.5 text-xs font-medium text-slate-400 dark:text-slate-500 cursor-not-allowed"
                                  aria-disabled="true">
                                {{ $code }}
                                <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">🔒</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
