<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * REQ-AUTH-010: Create row with role=user, is_approved=false, is_active=true.
     *               Do NOT establish an authenticated session (REQ-AUTH-044).
     * REQ-AUTH-011: Fire Registered event so Breeze listeners (email verification)
     *               keep working. Redirect to login with pending-approval flash.
     * REQ-AUTH-042: Validation rejects duplicate email before creating a row.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'is_active' => true,
            'is_approved' => false,
        ]);

        // REQ-AUTH-011: fire Registered so existing Breeze listeners (email
        // verification) continue to function. Downstream listeners MUST respect
        // is_approved = false and MUST NOT auto-activate the account.
        event(new Registered($user));

        // REQ-AUTH-044: never establish a session. Auth::login() is intentionally absent.
        // Outstanding #55: emit a friendly sentence, not the raw key.
        return redirect(route('login', absolute: false))
            ->with('status', 'Registration received — an administrator will review your account shortly. You will be able to sign in once approved.');
    }
}
