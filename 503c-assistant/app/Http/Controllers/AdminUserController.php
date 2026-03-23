<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function update(Request $request, User $user, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:admin,user'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->id === $request->user()->id && (bool) $data['is_active'] === false) {
            return back()->withErrors(['is_active' => 'You cannot disable your own account.']);
        }

        if ($user->role === 'admin' && (string) $data['role'] !== 'admin') {
            $adminCount = User::query()->where('role', 'admin')->where('is_active', true)->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['role' => 'Cannot remove the last active admin.']);
            }
        }

        $before = [
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
        ];

        $user->role = (string) $data['role'];
        $user->is_active = (bool) $data['is_active'];
        $user->save();

        $audit->log(
            request: $request,
            eventType: 'admin.user.updated',
            project: null,
            entityType: 'user',
            entityId: $user->id,
            entityUuid: null,
            payload: [
                'before' => $before,
                'after' => [
                    'role' => $user->role,
                    'is_active' => (bool) $user->is_active,
                ],
            ],
        );

        return redirect()->route('admin.index', ['tab' => 'users'])->with('status', 'User updated.');
    }
}
