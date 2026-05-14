<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * SPEC-UI-001 REQ-UI-001/REQ-UI-002 + SPEC-IRB-FORMSV2-007 (Phase 7).
 *
 * Outstanding #68: converting the previous closure route to an invokable
 * controller makes the root route serializable, so `php artisan route:cache`
 * no longer corrupts the URL matcher and returns 405 on /. Production can
 * re-enable route caching (~2 ms per request saving) after this lands.
 */
final class HomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('studies.index');
        }

        return view('welcome');
    }
}
