<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Admin</p>
                <h2 class="text-xl font-bold text-slate-900">System Management</h2>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="card">
                <!-- Tab Navigation -->
                <div class="tab-nav overflow-x-auto" role="tablist" aria-label="Admin sections">
                    @php
                        $adminTabs = [
                            'users' => ['label' => 'Users', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                            'providers' => ['label' => 'LLM Providers', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                            'templates' => ['label' => 'Templates', 'icon' => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z'],
                            'settings' => ['label' => 'Settings', 'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
                            'observability' => ['label' => 'Observability', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                            'audit' => ['label' => 'Audit Log', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                        ];
                    @endphp
                    @foreach($adminTabs as $tabKey => $tabInfo)
                        <a
                            id="admin-tab-{{ $tabKey }}"
                            class="tab-link flex items-center gap-2 whitespace-nowrap {{ $tab === $tabKey ? 'tab-link-active' : 'tab-link-inactive' }}"
                            href="{{ route('admin.index', ['tab' => $tabKey]) }}"
                            role="tab"
                            aria-selected="{{ $tab === $tabKey ? 'true' : 'false' }}"
                            aria-controls="admin-tabpanel-{{ $tabKey }}"
                        >
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tabInfo['icon'] }}"/></svg>
                            {{ $tabInfo['label'] }}
                        </a>
                    @endforeach
                </div>

                <div
                    id="admin-tabpanel-{{ $tab }}"
                    class="p-6"
                    role="tabpanel"
                    aria-labelledby="admin-tab-{{ $tab }}"
                >

                    {{-- ============ USERS TAB ============ --}}
                    @if($tab === 'users')
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-slate-900">User Management</h3>
                            <span class="badge badge-gray">{{ ($users ?? collect())->count() }} users</span>
                        </div>
                        <div class="overflow-x-auto rounded-xl ring-1 ring-slate-900/5">
                            <table class="min-w-full text-sm" aria-label="User management table">
                                <caption class="sr-only">List of registered users with role and active status controls</caption>
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Email</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Role</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Active</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Last Login</th>
                                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach(($users ?? collect()) as $u)
                                        @php $formId = 'user-update-'.$u->id; @endphp
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="px-4 py-3 font-medium text-slate-900">{{ $u->email }}</td>
                                            <td class="px-4 py-3 text-slate-700">{{ $u->name }}</td>
                                            <td class="px-4 py-3">
                                                <select name="role" form="{{ $formId }}" class="rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="user" @selected($u->role === 'user')>User</option>
                                                    <option value="admin" @selected($u->role === 'admin')>Admin</option>
                                                </select>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="hidden" name="is_active" value="0" form="{{ $formId }}" />
                                                <label class="relative inline-flex items-center cursor-pointer" aria-label="Active status for {{ $u->name }}">
                                                    <input type="checkbox" name="is_active" value="1" form="{{ $formId }}" @checked($u->is_active) class="sr-only peer" aria-label="User active: {{ $u->name }}" />
                                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600" aria-hidden="true"></div>
                                                </label>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600 text-xs">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <form id="{{ $formId }}" method="POST" action="{{ route('admin.users.update', ['user' => $u->id]) }}">
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                                </form>
                                                <button type="submit" form="{{ $formId }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors">Update</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    {{-- ============ PROVIDERS TAB ============ --}}
                    @elseif($tab === 'providers')
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <div class="lg:col-span-2">
                                <h3 class="text-base font-semibold text-slate-900 mb-4">Configured Providers</h3>
                                <div class="space-y-3">
                                    @forelse(($providers ?? collect()) as $p)
                                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-900/5 p-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-semibold text-slate-900">{{ $p->name }}</span>
                                                        @if($p->is_default) <span class="badge badge-indigo">default</span> @endif
                                                        @if($p->is_enabled)
                                                            <span class="badge badge-green">enabled</span>
                                                        @else
                                                            <span class="badge badge-gray">disabled</span>
                                                        @endif
                                                        @if($p->is_external) <span class="badge badge-amber">external</span> @endif
                                                    </div>
                                                    <div class="flex items-center gap-2 mt-1 text-xs text-slate-600">
                                                        <span class="badge badge-gray">{{ $p->provider_type }}</span>
                                                        @if($p->model) <span>{{ $p->model }}</span> @endif
                                                    </div>
                                                    @if($p->base_url)
                                                        <div class="text-xs text-slate-500 mt-1 truncate">{{ $p->base_url }}</div>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    @if($p->last_tested_at)
                                                        <div class="text-right">
                                                            @if($p->last_test_ok === true)
                                                                <span class="badge badge-green">Passed</span>
                                                            @elseif($p->last_test_ok === false)
                                                                <span class="badge badge-red">Failed</span>
                                                            @endif
                                                            <div class="text-xs text-slate-400 mt-0.5">{{ $p->last_tested_at->diffForHumans() }}</div>
                                                        </div>
                                                    @endif
                                                    <form method="POST" action="{{ route('admin.providers.test', ['provider' => $p->id]) }}">
                                                        @csrf
                                                        <x-secondary-button type="submit" class="text-xs">Test</x-secondary-button>
                                                    </form>
                                                </div>
                                            </div>
                                            @if($p->last_test_ok === false && $p->last_test_error)
                                                <div class="alert alert-error mt-3 text-xs">{{ $p->last_test_error }}</div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="empty-state py-12 rounded-xl bg-slate-50 ring-1 ring-slate-900/5">
                                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            <p class="empty-state-title">No providers configured</p>
                                            <p class="empty-state-text">Add an LLM provider to enable AI analysis.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div>
                                <div class="rounded-xl ring-1 ring-slate-900/5 overflow-hidden">
                                    <div class="px-5 py-4 bg-slate-50 border-b border-slate-100">
                                        <h4 class="text-sm font-semibold text-slate-900">Add / Update Provider</h4>
                                        <p class="text-xs text-slate-600 mt-0.5">OpenAI-compatible, Ollama, LM Studio, or GLM 4.7</p>
                                    </div>
                                    <form class="p-5 space-y-4" method="POST" action="{{ route('admin.providers.store') }}">
                                        @csrf
                                        <div>
                                            <x-input-label for="name" value="Name" />
                                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" placeholder="My Provider" required />
                                        </div>
                                        <div>
                                            <x-input-label for="provider_type" value="Type" />
                                            <select id="provider_type" name="provider_type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="openai">OpenAI</option>
                                                <option value="openai_compat">OpenAI Compatible</option>
                                                <option value="ollama">Ollama</option>
                                                <option value="lmstudio">LM Studio</option>
                                                <option value="glm47">GLM 4.7</option>
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label for="base_url" value="Base URL" />
                                            <x-text-input id="base_url" name="base_url" type="text" class="mt-1 block w-full" placeholder="https://api.openai.com/v1" />
                                        </div>
                                        <div>
                                            <x-input-label for="model" value="Model" />
                                            <x-text-input id="model" name="model" type="text" class="mt-1 block w-full" placeholder="gpt-4.1-mini" />
                                        </div>
                                        <div>
                                            <x-input-label for="api_key" value="API Key" />
                                            <x-text-input id="api_key" name="api_key" type="password" class="mt-1 block w-full" />
                                        </div>
                                        <div>
                                            <x-input-label for="request_params_json" value="Request Params (JSON)" />
                                            <textarea id="request_params_json" name="request_params_json" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 placeholder:text-slate-400" rows="3" placeholder='{"temperature": 0.2}'></textarea>
                                            <x-input-error :messages="$errors->get('request_params_json')" class="mt-2" />
                                        </div>
                                        <div class="flex flex-wrap gap-4 text-sm">
                                            <input type="hidden" name="is_enabled" value="0" />
                                            <input type="hidden" name="is_default" value="0" />
                                            <input type="hidden" name="is_external" value="0" />
                                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_enabled" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Enabled</label>
                                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Default</label>
                                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_external" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> External</label>
                                        </div>
                                        <x-primary-button class="w-full justify-center">Save Provider</x-primary-button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    {{-- ============ TEMPLATES TAB ============ --}}
                    @elseif($tab === 'templates')
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <div class="lg:col-span-2">
                                <h3 class="text-base font-semibold text-slate-900 mb-4">Template Versions</h3>
                                <div class="space-y-3">
                                    @forelse(($templates ?? collect()) as $t)
                                        @php
                                            $controls = (int) (($templateStats['controls'][$t->id] ?? 0));
                                            $mapped = (int) (($templateStats['mapped'][$t->id] ?? 0));
                                            $mapPct = $controls > 0 ? round(($mapped / $controls) * 100) : 0;
                                        @endphp
                                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-900/5 p-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-semibold text-slate-900">{{ $t->name }}</span>
                                                        @if($t->is_active) <span class="badge badge-green">active</span> @endif
                                                    </div>
                                                    <div class="flex items-center gap-3 mt-2 text-xs text-slate-600">
                                                        <span>{{ $controls }} controls</span>
                                                        <span>{{ $mapped }} mapped ({{ $mapPct }}%)</span>
                                                        <span>{{ $t->created_at->diffForHumans() }}</span>
                                                    </div>
                                                    <div class="mt-2 progress-bar">
                                                        <div class="progress-bar-fill" style="width: {{ $mapPct }}%"></div>
                                                    </div>
                                                    <div class="text-xs text-slate-400 mt-2 font-mono">{{ \Illuminate\Support\Str::limit($t->sha256, 20, '...') }}</div>
                                                </div>
                                                <a class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors" href="{{ route('admin.templates.show', ['template' => $t->uuid]) }}">
                                                    Manage &rarr;
                                                </a>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="empty-state py-12 rounded-xl bg-slate-50 ring-1 ring-slate-900/5">
                                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/></svg>
                                            <p class="empty-state-title">No templates uploaded</p>
                                            <p class="empty-state-text">Upload an HRP-503c DOCX template to get started.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div>
                                <div class="rounded-xl ring-1 ring-slate-900/5 overflow-hidden">
                                    <div class="px-5 py-4 bg-slate-50 border-b border-slate-100">
                                        <h4 class="text-sm font-semibold text-slate-900">Upload Template</h4>
                                        <p class="text-xs text-slate-600 mt-0.5">Upload an HRP-503c .docx for content control scanning.</p>
                                    </div>
                                    <form class="p-5 space-y-4" method="POST" action="{{ route('admin.templates.store') }}" enctype="multipart/form-data">
                                        @csrf
                                        <div>
                                            <x-input-label for="name" value="Template name" />
                                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="HRP-503c" />
                                        </div>
                                        <div>
                                            <x-input-label for="template" value=".docx file" />
                                            <input id="template" type="file" name="template" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required />
                                            <x-input-error :messages="$errors->get('template')" class="mt-2" />
                                        </div>
                                        <x-primary-button class="w-full justify-center">Upload</x-primary-button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    {{-- ============ SETTINGS TAB ============ --}}
                    @elseif($tab === 'settings')
                        @php
                            $s = $settings ?? [
                                'allow_external_llm' => false,
                                'retention_days' => 14,
                                'max_upload_bytes' => 104857600,
                                'logging_level' => 'debug',
                            ];
                        @endphp

                        <h3 class="text-base font-semibold text-slate-900">System Settings</h3>
                        <p class="text-sm text-slate-600 mt-1">Instance-wide configuration for all users.</p>

                        <form class="mt-6 space-y-6" method="POST" action="{{ route('admin.settings.store') }}">
                            @csrf

                            <div class="rounded-xl ring-1 ring-slate-900/5 p-5">
                                <div class="flex items-start gap-3">
                                    <input id="allow_external_llm" type="checkbox" name="allow_external_llm" value="1" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($s['allow_external_llm']) />
                                    <div>
                                        <label for="allow_external_llm" class="text-sm font-medium text-slate-900 cursor-pointer">Allow external LLM providers</label>
                                        <p class="text-sm text-slate-600 mt-0.5">When enabled, providers marked as "external" can receive document text for analysis.</p>
                                    </div>
                                </div>
                                <div class="alert alert-warning mt-3 text-xs">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        External providers may receive sensitive research content. Enable only if your organization permits it.
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div class="rounded-xl ring-1 ring-slate-900/5 p-5">
                                    <x-input-label for="retention_days" value="Retention (days)" />
                                    <x-text-input id="retention_days" name="retention_days" type="number" min="1" max="3650" class="mt-2 block w-full" :value="$s['retention_days']" required />
                                    <p class="text-xs text-slate-600 mt-2">Auto-delete uploads and exports after this many days.</p>
                                </div>
                                <div class="rounded-xl ring-1 ring-slate-900/5 p-5">
                                    <x-input-label for="max_upload_bytes" value="Max upload size (bytes)" />
                                    <x-text-input id="max_upload_bytes" name="max_upload_bytes" type="number" min="1048576" max="1073741824" class="mt-2 block w-full" :value="$s['max_upload_bytes']" required />
                                    <p class="text-xs text-slate-600 mt-2">Default: 104,857,600 (100 MB)</p>
                                </div>
                                <div class="rounded-xl ring-1 ring-slate-900/5 p-5">
                                    <x-input-label for="logging_level" value="Logging level" />
                                    <select id="logging_level" name="logging_level" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        @foreach(['debug','info','notice','warning','error','critical','alert','emergency'] as $lvl)
                                            <option value="{{ $lvl }}" @selected($s['logging_level'] === $lvl)>{{ ucfirst($lvl) }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-slate-600 mt-2">Controls verbosity of application logs.</p>
                                </div>
                            </div>

                            <x-primary-button>
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Save Settings
                            </x-primary-button>
                        </form>

                    {{-- ============ AUDIT TAB ============ --}}
                    @elseif($tab === 'audit')
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-slate-900">System Audit Log</h3>
                            <span class="badge badge-gray">{{ method_exists($auditEvents ?? collect(), 'total') ? $auditEvents->total() : ($auditEvents ?? collect())->count() }} events</span>
                        </div>

                        <div class="overflow-x-auto rounded-xl ring-1 ring-slate-900/5">
                            <table class="min-w-full text-sm" aria-label="System audit log">
                                <caption class="sr-only">System audit log showing event type, time, actor, entity, and details</caption>
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Event</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">When</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actor</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Entity</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse(($auditEvents ?? collect()) as $ev)
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="px-4 py-3 font-medium text-slate-900">{{ $ev->event_type }}</td>
                                            <td class="px-4 py-3 text-slate-600 text-xs whitespace-nowrap">{{ $ev->occurred_at?->diffForHumans() ?? $ev->created_at->diffForHumans() }}</td>
                                            <td class="px-4 py-3 text-slate-600 text-xs">{{ $ev->actor_user_id ? '#'.$ev->actor_user_id : '-' }}</td>
                                            <td class="px-4 py-3 text-slate-600 text-xs">
                                                @if($ev->entity_type)
                                                    {{ $ev->entity_type }}@if($ev->entity_id) #{{ $ev->entity_id }}@endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($ev->payload)
                                                    <details class="group">
                                                        <summary class="text-xs text-indigo-600 cursor-pointer hover:text-indigo-800 flex items-center gap-1">
                                                            <svg class="w-3 h-3 transform group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                            View
                                                        </summary>
                                                        <pre class="mt-2 text-xs bg-slate-900 text-slate-100 rounded-lg p-3 overflow-x-auto max-w-md">{{ json_encode($ev->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </details>
                                                @else
                                                    <span class="text-xs text-slate-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5">
                                                <div class="empty-state py-12">
                                                    <p class="empty-state-title">No audit events</p>
                                                    <p class="empty-state-text">System activity will appear here.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if(method_exists($auditEvents ?? collect(), 'links'))
                            <div class="mt-4">
                                {{ $auditEvents->appends(['tab' => 'audit'])->links() }}
                            </div>
                        @endif

                    {{-- ============ OBSERVABILITY TAB ============ --}}
                    @elseif($tab === 'observability')
                        @php
                            $overall = $overallStats ?? ['total' => 0, 'succeeded' => 0, 'failed' => 0];
                        @endphp

                        {{-- Overall summary stat cards --}}
                        <div class="grid grid-cols-3 gap-4 mb-8" role="region" aria-label="Overall run statistics">
                            <div class="rounded-xl ring-1 ring-slate-900/5 p-5 text-center">
                                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Runs</p>
                                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $overall['total'] }}</p>
                            </div>
                            <div class="rounded-xl ring-1 ring-slate-900/5 p-5 text-center">
                                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Succeeded</p>
                                <p class="mt-2 text-3xl font-bold text-green-600">{{ $overall['succeeded'] }}</p>
                            </div>
                            <div class="rounded-xl ring-1 ring-slate-900/5 p-5 text-center">
                                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Failed</p>
                                <p class="mt-2 text-3xl font-bold text-red-600">{{ $overall['failed'] }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            {{-- Analysis Runs list --}}
                            <div class="lg:col-span-2">
                                <h3 class="text-base font-semibold text-slate-900 mb-4">Recent Analysis Runs</h3>
                                <div class="space-y-3" role="list" aria-label="Recent analysis runs">
                                    @forelse(($analysisRuns ?? collect()) as $run)
                                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-900/5 p-4" role="listitem">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="font-medium text-slate-900 text-sm font-mono">{{ substr($run->uuid, 0, 8) }}</span>
                                                        <span @class([
                                                            'badge text-xs',
                                                            'badge-green'  => $run->status === 'succeeded',
                                                            'badge-red'    => $run->status === 'failed',
                                                            'badge-blue'   => $run->status === 'running',
                                                            'badge-amber'  => $run->status === 'queued',
                                                            'badge-gray'   => !in_array($run->status, ['succeeded', 'failed', 'running', 'queued']),
                                                        ]) aria-label="Status: {{ $run->status }}">{{ $run->status }}</span>
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-600 truncate">
                                                        @if($run->project?->name)
                                                            <span class="font-medium">{{ $run->project->name }}</span> &middot;
                                                        @endif
                                                        {{ $run->provider?->name ?? 'No provider' }}
                                                        @if($run->provider?->model) ({{ $run->provider->model }}) @endif
                                                        &middot; prompt v{{ $run->prompt_version }}
                                                    </div>
                                                    @if($run->error)
                                                        <div class="alert alert-error mt-2 text-xs">{{ Str::limit($run->error, 120) }}</div>
                                                    @endif
                                                </div>
                                                <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                                                    <div class="text-xs text-slate-400 whitespace-nowrap">{{ $run->created_at?->diffForHumans() ?? '' }}</div>
                                                    <a
                                                        href="{{ route('admin.runs.show', ['runUuid' => $run->uuid]) }}"
                                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors"
                                                        aria-label="View detail for run {{ substr($run->uuid, 0, 8) }}"
                                                    >Detail &rarr;</a>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="empty-state py-12 rounded-xl bg-slate-50 ring-1 ring-slate-900/5">
                                            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                            <p class="empty-state-title">No analysis runs yet</p>
                                            <p class="empty-state-text">Runs will appear here after users analyze projects.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            {{-- Provider Usage Metrics --}}
                            <div>
                                <h3 class="text-base font-semibold text-slate-900 mb-4">Provider Usage</h3>
                                @php
                                    $providersById   = ($providers ?? collect())->keyBy('id');
                                    $provMetrics     = $providerMetrics ?? collect();
                                @endphp
                                @if($provMetrics->isEmpty())
                                    <div class="rounded-xl ring-1 ring-slate-900/5 p-8 text-center text-sm text-slate-600">No usage data yet.</div>
                                @else
                                    <div class="rounded-xl ring-1 ring-slate-900/5 overflow-hidden" role="table" aria-label="Provider usage metrics">
                                        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 grid grid-cols-5 gap-2 text-xs font-semibold text-slate-600 uppercase tracking-wider" role="row">
                                            <div class="col-span-2" role="columnheader">Provider</div>
                                            <div class="text-right" role="columnheader">Runs</div>
                                            <div class="text-right" role="columnheader">Success</div>
                                            <div class="text-right" role="columnheader">Avg (s)</div>
                                        </div>
                                        @foreach($provMetrics as $metric)
                                            @php
                                                $prov        = $providersById->get((int) $metric->llm_provider_id);
                                                $successRate = $metric->total > 0
                                                    ? round(($metric->succeeded / $metric->total) * 100)
                                                    : 0;
                                                $avgDur      = $metric->avg_duration_s !== null
                                                    ? round((float) $metric->avg_duration_s)
                                                    : null;
                                            @endphp
                                            <div class="px-4 py-3 border-b border-slate-100 last:border-b-0 grid grid-cols-5 gap-2 items-center text-sm" role="row">
                                                <div class="col-span-2" role="cell">
                                                    <div class="font-medium text-slate-900 truncate">{{ $prov?->name ?? 'Unknown' }}</div>
                                                    @if($prov?->model)
                                                        <div class="text-xs text-slate-500 truncate">{{ $prov->model }}</div>
                                                    @endif
                                                    @if($metric->last_used_at)
                                                        <div class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($metric->last_used_at)->diffForHumans() }}</div>
                                                    @endif
                                                </div>
                                                <div class="text-right font-bold text-slate-900" role="cell">{{ (int) $metric->total }}</div>
                                                <div class="text-right" role="cell">
                                                    <span @class([
                                                        'badge text-xs',
                                                        'badge-green' => $successRate >= 80,
                                                        'badge-amber' => $successRate >= 50 && $successRate < 80,
                                                        'badge-red'   => $successRate < 50,
                                                    ]) aria-label="{{ $successRate }}% success rate">{{ $successRate }}%</span>
                                                </div>
                                                <div class="text-right text-slate-700 text-xs" role="cell">
                                                    {{ $avgDur !== null ? $avgDur.'s' : '—' }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
