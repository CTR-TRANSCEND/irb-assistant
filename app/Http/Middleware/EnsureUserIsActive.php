<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    // @MX:NOTE: [AUTO] Dual-gate middleware — extended in SPEC-AUTH-001 to reject sessions
    //           where EITHER is_approved=false OR is_active=false. REQ-AUTH-021: a session
    //           held open across an admin un-approval or deactivation action is invalidated
    //           at the next request through this middleware. Class name is intentionally
    //           kept as EnsureUserIsActive (not renamed) per plan.md §M3 decision.

    /**
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Refresh from DB so that an admin action that changed is_approved/is_active
        // since this PHP process loaded the model is reflected immediately (REQ-AUTH-021).
        // actingAs() in tests injects a stale in-memory model; fresh() fixes that too.
        $user = $user->fresh();

        // REQ-AUTH-021: gate on is_approved (new, SPEC-AUTH-001).
        if (! $user->is_approved) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Account pending administrator approval.',
            ]);
        }

        // REQ-AUTH-021: gate on is_active (existing, extended).
        if (! $user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account is disabled. Please contact an administrator.',
            ]);
        }

        return $next($request);
    }
}
