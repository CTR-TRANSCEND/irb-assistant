{{--
    Admin Users Tab Partial — SPEC-AUTH-001 M5
    Variables expected:
        $pendingUsers   — Collection of User models with is_approved = false
        $allUsers       — Collection of all User models
        $pending_count  — int count of pending users
--}}

{{-- ===== Section 1: Pending Registrations ===== --}}
<div class="mb-10">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
            Pending registrations
            @if(($pending_count ?? 0) > 0)
                <span class="ml-2 badge badge-amber">{{ $pending_count }}</span>
            @endif
        </h3>
    </div>

    @if(($pendingUsers ?? collect())->isEmpty())
        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-900/5 p-8 text-center dark:bg-slate-800 dark:ring-white/10">
            <p class="text-sm text-slate-500 dark:text-slate-400">No pending registrations</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl ring-1 ring-slate-900/5 dark:ring-white/10">
            <table class="min-w-full text-sm">
                <caption class="sr-only">Pending user registrations awaiting admin approval</caption>
                <thead class="bg-slate-50 dark:bg-slate-700">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider dark:text-slate-300">Name</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider dark:text-slate-300">Email</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider dark:text-slate-300">Registered</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($pendingUsers as $u)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/50">
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $u->name }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $u->email }}</td>
                            <td class="px-4 py-3 text-slate-600 text-xs dark:text-slate-400 whitespace-nowrap">
                                {{ $u->created_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Approve --}}
                                    <form method="POST" action="{{ route('admin.users.approve', ['user' => $u->id]) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                                            Approve
                                        </button>
                                    </form>

                                    {{-- Reject — destructive: confirm before submit --}}
                                    <form method="POST"
                                        action="{{ route('admin.users.reject', ['user' => $u->id]) }}"
                                        onsubmit="return confirm('Reject and permanently delete the pending registration for {{ addslashes($u->email) }}? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ===== Section 2: All Users ===== --}}
<div>
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">All users</h3>
        <span class="badge badge-gray">{{ ($allUsers ?? collect())->count() }} total</span>
    </div>

    <div class="overflow-x-auto rounded-xl ring-1 ring-slate-900/5 dark:ring-white/10">
        <table class="min-w-full text-sm">
            <caption class="sr-only">All registered users with role, approval status, and management controls</caption>
            <thead class="bg-slate-50 dark:bg-slate-700">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider dark:text-slate-300">Name</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider dark:text-slate-300">Email</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider dark:text-slate-300">Role</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider dark:text-slate-300">Status</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider dark:text-slate-300">Last Login</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse(($allUsers ?? collect()) as $u)
                    @php
                        $isSelf        = $u->id === auth()->id();
                        $isTargetAdmin = $u->role === 'admin';
                    @endphp
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/50">

                        {{-- Name --}}
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            {{ $u->name }}
                            @if($isSelf)
                                <span class="ml-1 text-xs text-slate-400 dark:text-slate-500">(you)</span>
                            @endif
                        </td>

                        {{-- Email --}}
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $u->email }}</td>

                        {{-- Role badge --}}
                        <td class="px-4 py-3">
                            @if($u->role === 'admin')
                                <span class="badge badge-indigo">admin</span>
                            @else
                                <span class="badge badge-gray">user</span>
                            @endif
                        </td>

                        {{-- Status badge: green=Approved, amber=Pending, gray=Inactive --}}
                        <td class="px-4 py-3">
                            @if(!$u->is_approved)
                                <span class="badge badge-amber">Pending</span>
                            @elseif(!$u->is_active)
                                <span class="badge badge-gray">Inactive</span>
                            @else
                                <span class="badge badge-green">Approved</span>
                            @endif
                        </td>

                        {{-- Last Login --}}
                        <td class="px-4 py-3 text-slate-600 text-xs dark:text-slate-400 whitespace-nowrap">
                            {{ $u->last_login_at?->diffForHumans() ?? 'Never' }}
                        </td>

                        {{-- Actions — all guarded; no destructive actions on self or admins per REQ-AUTH-018/040 --}}
                        <td class="px-4 py-3">
                            @if($isSelf)
                                <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                            @else
                                <div class="flex items-center justify-end gap-2 flex-wrap">

                                    {{-- Promote: user → admin, visible for non-admin targets --}}
                                    @if(!$isTargetAdmin)
                                        <form method="POST" action="{{ route('admin.users.update', ['user' => $u->id]) }}">
                                            @csrf
                                            <input type="hidden" name="role" value="admin" />
                                            <input type="hidden" name="is_active" value="{{ $u->is_active ? '1' : '0' }}" />
                                            <button type="submit"
                                                class="inline-flex items-center px-3 py-1 text-xs font-medium text-slate-700 border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700">
                                                Promote
                                            </button>
                                        </form>
                                    @else
                                        {{-- Demote: admin → user, visible for non-self admin targets --}}
                                        <form method="POST" action="{{ route('admin.users.update', ['user' => $u->id]) }}">
                                            @csrf
                                            <input type="hidden" name="role" value="user" />
                                            <input type="hidden" name="is_active" value="{{ $u->is_active ? '1' : '0' }}" />
                                            <button type="submit"
                                                class="inline-flex items-center px-3 py-1 text-xs font-medium text-slate-700 border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700">
                                                Demote
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Activate: only if currently inactive AND not self --}}
                                    @if(!$u->is_active)
                                        <form method="POST" action="{{ route('admin.users.activate', ['user' => $u->id]) }}">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                                                Activate
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Deactivate: only if approved + active + not admin + not self — REQ-AUTH-040 --}}
                                    @if($u->is_approved && $u->is_active && !$isTargetAdmin)
                                        <form method="POST"
                                            action="{{ route('admin.users.deactivate', ['user' => $u->id]) }}"
                                            onsubmit="return confirm('Deactivate {{ addslashes($u->name) }}? They will not be able to log in until reactivated.')">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition-colors">
                                                Deactivate
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Delete: only if approved + not admin + not self — REQ-AUTH-017, REQ-AUTH-040 --}}
                                    @if($u->is_approved && !$isTargetAdmin)
                                        <form method="POST"
                                            action="{{ route('admin.users.destroy', ['user' => $u->id]) }}"
                                            onsubmit="return confirm('Permanently delete {{ addslashes($u->name) }} ({{ addslashes($u->email) }})?\n\nThis will also delete their projects. This action cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                                                Delete
                                            </button>
                                        </form>
                                    @endif

                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state py-12">
                                <p class="empty-state-title">No users</p>
                                <p class="empty-state-text">No users are registered yet.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
