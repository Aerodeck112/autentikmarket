<x-layouts.site :title="__('Parolă nouă')" noindex>
    <section class="container-site flex justify-center py-16">
        <form method="POST" action="{{ route('password.update') }}" class="card card-pad w-full max-w-md space-y-4 sm:p-8">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <h1 class="text-3xl">{{ __('Parolă nouă') }}</h1>
            <x-flash />
            <x-input name="email" type="email" :label="__('E-mail')" :value="$email" required />
            <x-input name="password" type="password" :label="__('Parolă nouă')" autocomplete="new-password" required />
            <x-input name="password_confirmation" type="password" :label="__('Confirmă parola')" autocomplete="new-password" required />
            <button class="btn-primary w-full">{{ __('Salvează parola') }}</button>
        </form>
    </section>
</x-layouts.site>
