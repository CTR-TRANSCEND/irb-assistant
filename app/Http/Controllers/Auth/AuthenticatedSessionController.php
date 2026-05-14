<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * REQ-AUTH-003: Login requires both is_approved = true AND is_active = true.
     * REQ-AUTH-012: If !is_approved → reject with "Account pending administrator approval."
     * REQ-AUTH-020: If is_approved && !is_active → reject with "Account deactivated."
     *
     * Gate order: credentials first, then approval, then active. This ensures
     * that wrong-password attempts always return the generic credentials message
     * and never reveal the account's is_approved / is_active status to a probe.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Throws ValidationException on bad credentials (does not leak account state).
        $request->authenticate();

        $user = Auth::user();

        // REQ-AUTH-012: block pending users.
        if (! $user->is_approved) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => __('Account pending administrator approval.'),
            ])->onlyInput('email');
        }

        // REQ-AUTH-020: block deactivated users (distinct message from pending).
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => __('Account deactivated. Contact administrator.'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('studies.index', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
