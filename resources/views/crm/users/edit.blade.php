<x-layouts.crm :title="__('Editează contul').' — '.$account->name">
    <x-slot:breadcrumb><a href="{{ route('crm.users.index', ['role' => 'all']) }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Utilizatori & roluri') }}</a></x-slot:breadcrumb>
    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('crm.users.update', $account) }}" class="card card-pad space-y-4 lg:col-span-2">@csrf @method('PUT')
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl">{{ __('Date cont') }}</h2>
                <p class="font-mono text-sm text-ink-soft">{{ $account->member_code }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="first_name" :label="__('Prenume')" :value="$account->first_name" required />
                <x-input name="last_name" :label="__('Nume')" :value="$account->last_name" required />
                <x-input name="email" type="email" label="E-mail" :value="$account->email" />
                <x-input name="phone" :label="__('Telefon')" :value="$account->phone" :help="__('Cu telefonul se poate și autentifica.')" />
                <x-input name="city" :label="__('Localitate')" :value="$account->city" />
                <x-select name="preferred_language" :label="__('Limba de comunicare')" :options="['ro' => 'Română (RO)', 'hu' => 'Magyar (HU)']" :value="$account->preferredLanguage()->value" required />
            </div>
            <x-checkbox name="is_active" :label="__('Cont activ (se poate autentifica)')" :checked="$account->is_active" />
            <div class="rounded-xl border border-line p-4">
                <p class="text-sm font-semibold">{{ __('Parolă nouă (opțional)') }}</p>
                <p class="text-xs text-ink-soft">{{ __('Pentru cine nu are e-mail sau nu se descurcă: setați parola aici și comunicați-o persoanei. Gol = parola rămâne neschimbată.') }}</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <x-input name="new_password" type="password" :label="__('Parolă nouă')" autocomplete="new-password" />
                    <x-input name="new_password_confirmation" type="password" :label="__('Confirmă parola')" autocomplete="new-password" />
                </div>
            </div>
            <button class="btn-primary">{{ __('Salvează') }}</button>
        </form>
        <div class="space-y-6">
            <div class="card card-pad space-y-2 text-sm">
                <h2 class="text-lg">{{ __('Legături') }}</h2>
                <p>{{ __('Roluri') }}: {{ $account->roles->pluck('label_ro')->implode(', ') ?: '—' }}</p>
                @if ($account->producer)<p><a href="{{ route('crm.producers.show', $account->producer) }}" class="text-clay hover:underline">{{ __('Fișa producătorului') }}: {{ $account->producer->brand_name }}</a></p>@endif
                @if ($account->hasRole('visitor'))<p><a href="{{ route('crm.visitors.show', $account) }}" class="text-clay hover:underline">{{ __('Fișa cumpărătorului') }}</a></p>@endif
                <p class="text-xs text-ink-soft">{{ __('Ultima autentificare') }}: {{ $account->last_login_at?->format('d.m.Y H:i') ?? '—' }} · {{ __('Înscris') }}: {{ $account->created_at->format('d.m.Y') }}</p>
            </div>
            <form method="POST" action="{{ route('crm.users.password-link', $account) }}" class="card card-pad space-y-2 text-sm">@csrf
                <h2 class="text-lg">{{ __('Link de alegere a parolei') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('Persoana primește pe e-mail, în limba ei, un link valabil 7 zile să își aleagă singură parola.') }}</p>
                <button class="btn-ghost btn-sm" @disabled(! $account->email)>✉️ {{ __('Trimite linkul') }}</button>
            </form>
        </div>
    </div>
</x-layouts.crm>
