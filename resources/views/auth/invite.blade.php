<x-layouts.site :title="__('Alege parola')" noindex>
    <section class="container-site flex justify-center py-16">
        <form method="POST" action="{{ $action }}" class="card card-pad w-full max-w-md space-y-4 sm:p-8">
            @csrf
            <h1 class="text-3xl">{{ __('Bun venit, :name!', ['name' => $user->first_name]) }}</h1>
            <p class="text-sm text-ink-soft">
                @if ($user->producer)
                    {{ __('Alege o parolă pentru contul de producător :brand. Te vei autentifica cu adresa :email.', ['brand' => $user->producer->brand_name, 'email' => $user->email]) }}
                @else
                    {{ __('Alege o parolă pentru contul tău Autentik. Te vei autentifica cu adresa :email.', ['email' => $user->email]) }}
                @endif
            </p>
            <x-flash />
            <x-input name="password" type="password" :label="__('Parolă nouă')" autocomplete="new-password" required />
            <x-input name="password_confirmation" type="password" :label="__('Confirmă parola')" autocomplete="new-password" required />
            <p class="text-xs text-ink-soft">{{ __('Minim 8 caractere, cu litere și cifre.') }}</p>
            <button class="btn-primary w-full">{{ __('Salvează parola') }}</button>
        </form>
    </section>
</x-layouts.site>
