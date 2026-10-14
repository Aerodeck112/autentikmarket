<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q'));

        return view('crm.users.index', [
            'users' => User::with('roles')
                ->when($request->input('role'), fn ($x, $r) => $x->whereHas('roles', fn ($w) => $w->where('name', $r)))
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('email', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")))
                ->whereHas('roles', fn ($w) => $w->whereIn('name', $request->input('role') ? [$request->input('role')] : ['staff', 'organizer', 'admin', 'super_admin']))
                ->orderBy('first_name')->paginate(30)->withQueryString(),
            'roles' => Role::with('permissions')->get(),
        ]);
    }

    public function roles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['roles' => ['array'], 'roles.*' => ['exists:roles,name']]);
        $names = $data['roles'] ?? [];
        // Doar un super admin poate acorda / retrage rolul super_admin.
        if ((in_array('super_admin', $names, true) || $user->isSuperAdmin()) && ! $request->user()->isSuperAdmin()) {
            abort(403);
        }
        if ($user->id === $request->user()->id && $user->isSuperAdmin() && ! in_array('super_admin', $names, true)) {
            return back()->with('error', __('Nu îți poți retrage singur rolul de Super Admin.'));
        }
        $old = $user->roles->pluck('name')->all();
        $user->roles()->sync(Role::whereIn('name', $names)->pluck('id'));
        AuditLogger::log('user.roles_changed', $user, ['roles' => $old], ['roles' => $names]);

        return back()->with('success', __('Rolurile au fost actualizate.'));
    }
}
