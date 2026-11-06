<?php

namespace App\Http\Controllers\Crm;

use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\InvitationService;
use App\Services\AuditLogger;
use App\Services\Search\GlobalSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q'));

        return view('crm.users.index', [
            'users' => User::with(['roles', 'producer:id,user_id,brand_name'])
                ->when($request->input('role') && $request->input('role') !== 'all', fn ($x) => $x->whereHas('roles', fn ($w) => $w->where('name', $request->input('role'))))
                ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('email', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('member_code', 'like', "%{$q}%")->orWhereRaw(GlobalSearch::fullNameSql().' like ?', ["%{$q}%"])->tap(fn ($w) => GlobalSearch::orWherePhoneDigits($w, $q))))
                ->when(! $request->input('role'), fn ($x) => $x->whereHas('roles', fn ($w) => $w->whereIn('name', ['staff', 'organizer', 'admin', 'super_admin'])))
                ->whereNull('anonymized_at')
                ->orderBy('first_name')->paginate(30)->withQueryString(),
            'roles' => Role::with('permissions')->get(),
        ]);
    }

    /** Editarea oricărui cont (echipă, producător, cumpărător) — un cont de Super Admin doar de un Super Admin. */
    public function edit(Request $request, User $user): View
    {
        $this->guardSuperAdmin($request, $user);

        return view('crm.users.edit', ['account' => $user->load(['roles', 'producer']), 'roles' => Role::orderBy('id')->get()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardSuperAdmin($request, $user);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40', 'regex:/^[0-9 +().-]{6,40}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['required', Rule::enum(Language::class)],
            'is_active' => ['nullable', 'boolean'],
            'new_password' => ['nullable', 'confirmed', Password::defaults()],
        ], ['email.required_without' => __('Completează cel puțin e-mailul sau telefonul.')]);
        $active = $request->boolean('is_active');
        if ($user->is($request->user()) && ! $active) {
            return back()->with('error', __('Nu îți poți dezactiva propriul cont.'));
        }
        $old = $user->only(['email', 'phone', 'is_active', 'preferred_language']);
        $user->fill([
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'email' => isset($data['email']) ? strtolower($data['email']) : null,
            'phone' => $data['phone'] ?? null, 'city' => $data['city'] ?? null,
            'preferred_language' => $data['preferred_language'], 'is_active' => $active,
        ]);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        if (! empty($data['new_password'])) {
            $user->password = $data['new_password'];
        }
        $user->save();
        AuditLogger::log('user.updated_by_admin', $user, $old, $user->only(['email', 'phone', 'is_active', 'preferred_language']) + ['password_changed' => ! empty($data['new_password'])]);

        return back()->with('success', __('Contul a fost actualizat.').(! empty($data['new_password']) ? ' '.__('Parola nouă a fost setată; comunic-o persoanei.') : ''));
    }

    /** Link de alegere a parolei (invitație), valabil 7 zile, trimis pe e-mail în limba persoanei. */
    public function sendPasswordLink(Request $request, User $user, InvitationService $invitations): RedirectResponse
    {
        $this->guardSuperAdmin($request, $user);
        if (! $user->email) {
            return back()->with('error', __('Contul nu are e-mail. Setează o parolă nouă și comunic-o persoanei.'));
        }
        $invitations->send($user, $user->producer);

        return back()->with('success', __('Linkul de alegere a parolei a fost trimis la :e.', ['e' => $user->email]));
    }

    protected function guardSuperAdmin(Request $request, User $user): void
    {
        abort_if($user->isSuperAdmin() && ! $request->user()->isSuperAdmin(), 403);
        abort_if($user->anonymized_at !== null, 404);
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
