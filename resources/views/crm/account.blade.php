<x-layouts.crm :title="__('Contul meu')">
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('crm.me.update') }}" class="card card-pad space-y-4">@csrf @method('PUT')
            <h2 class="text-xl">{{ __('Datele mele') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="first_name" :label="__('Prenume')" :value="$user->first_name" required />
                <x-input name="last_name" :label="__('Nume')" :value="$user->last_name" required />
                <x-input name="email" type="email" label="E-mail" :value="$user->email" required />
                <x-input name="phone" :label="__('Telefon')" :value="$user->phone" :help="__('Te poți autentifica și cu numărul de telefon.')" />
            </div>
            <x-select name="preferred_language" :label="__('Limba')" :options="['ro' => 'Română (RO)', 'hu' => 'Magyar (HU)']" :value="$user->preferredLanguage()->value" required />
            <p class="text-xs text-ink-soft">{{ __('Roluri') }}: {{ $user->roles->pluck('label_ro')->implode(', ') ?: '—' }}</p>
            <button class="btn-primary">{{ __('Salvează') }}</button>
        </form>
        <form method="POST" action="{{ route('crm.me.password') }}" class="card card-pad space-y-4">@csrf @method('PUT')
            <h2 class="text-xl">{{ __('Schimbă parola') }}</h2>
            <x-input name="current_password" type="password" :label="__('Parola actuală')" autocomplete="current-password" required />
            <x-input name="password" type="password" :label="__('Parolă nouă')" autocomplete="new-password" required />
            <x-input name="password_confirmation" type="password" :label="__('Confirmă parola')" autocomplete="new-password" required />
            <button class="btn-ghost">{{ __('Schimbă parola') }}</button>
        </form>
    </div>
</x-layouts.crm>
