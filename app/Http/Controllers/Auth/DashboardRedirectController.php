<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    /** Fiecare rol ajunge în zona lui. */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->canAccessCrm()) {
            return redirect()->route('crm.dashboard');
        }
        if ($user->producer || $user->hasRole('producer')) {
            return redirect()->route($user->producer ? 'producer.dashboard' : 'producer.onboarding');
        }
        if ($user->isStaff()) {
            return redirect()->route('staff.index');
        }

        return redirect()->route('account.index');
    }
}
