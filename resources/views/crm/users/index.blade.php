<x-layouts.crm :title="__('Utilizatori & roluri')">
    <form class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Nume, e-mail, telefon, cod AM-…') }}" class="field-input !w-64 !py-1.5"><button class="btn-ghost btn-sm">{{ __('Caută') }}</button>
        <select name="role" onchange="this.form.submit()" class="field-input !w-auto !py-1.5"><option value="">{{ __('Echipa (staff, organizator, admin)') }}</option><option value="all" @selected(request('role') === 'all')>{{ __('Toate conturile') }}</option>@foreach ($roles as $r)<option value="{{ $r->name }}" @selected(request('role') === $r->name)>{{ $r->label_ro }}</option>@endforeach</select>
    </form>
    <p class="mb-4 text-sm text-ink-soft">{{ __('Pentru a adăuga un membru al echipei: persoana își creează cont pe site (sau o adăugați ca cumpărător), apoi îi atribuiți rolul aici (alegeți „Toate conturile” și căutați-o). Cu „Editează” modificați datele oricărui cont, setați o parolă nouă sau trimiteți linkul de alegere a parolei.') }}</p>
    <div class="card overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Utilizator') }}</th><th>{{ __('Limbă') }}</th><th>{{ __('Roluri') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td>{{ $u->name }} @unless ($u->is_active)<span class="rounded-full bg-red-50 px-2 text-[10px] font-semibold text-red-700">{{ __('inactiv') }}</span>@endunless<p class="text-xs text-ink-soft">{{ $u->email ?: '—' }}@if ($u->phone) · {{ $u->phone }}@endif @if ($u->producer) · {{ $u->producer->brand_name }}@endif</p></td>
                    <td><x-lang-badge :lang="$u->preferred_language" /></td>
                    <td>
                        <form method="POST" action="{{ route('crm.users.roles', $u) }}" class="flex flex-wrap items-center gap-3 text-xs">@csrf @method('PUT')
                            @foreach ($roles as $r)
                                @if ($r->name !== 'super_admin' || auth()->user()->isSuperAdmin())
                                    <label class="flex items-center gap-1"><input type="checkbox" name="roles[]" value="{{ $r->name }}" @checked($u->roles->contains('id', $r->id)) class="rounded border-line text-forest">{{ $r->label_ro }}</label>
                                @endif
                            @endforeach
                            <button class="btn-ghost btn-sm">{{ __('Salvează') }}</button>
                        </form>
                    </td>
                    <td class="text-right">@if (! $u->isSuperAdmin() || auth()->user()->isSuperAdmin())<a href="{{ route('crm.users.edit', $u) }}" class="btn-ghost btn-sm">{{ __('Editează') }}</a>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}

    <h2 class="mt-10 text-2xl">{{ __('Permisiuni pe roluri') }}</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-3">
        @foreach ($roles as $r)
            <div class="card card-pad"><p class="font-display text-lg">{{ $r->label_ro }}</p><p class="mt-2 text-xs text-ink-soft">{{ $r->permissions->pluck('name')->implode(', ') ?: '—' }}</p></div>
        @endforeach
    </div>
</x-layouts.crm>
