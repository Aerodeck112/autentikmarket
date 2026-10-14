<x-layouts.site :title="__('Autentificare')" noindex>
    <section class="container-site flex justify-center py-12 sm:py-16">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <p class="eyebrow">Autentik Market</p>
                <h1 class="mt-3 text-4xl">{{ __('Bine ai revenit') }}</h1>
            </div>
            <form method="POST" action="{{ lroute('login.store') }}" class="card card-pad space-y-4 sm:p-8">
                @csrf
                <x-input name="email" type="email" :label="__('E-mail')" autocomplete="email" required autofocus />
                <x-input name="password" type="password" :label="__('Parolă')" autocomplete="current-password" required />
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1" class="rounded border-line text-forest"> {{ __('Ține-mă minte') }}</label>
                    <a href="{{ lroute('password.request') }}" class="text-clay hover:underline">{{ __('Am uitat parola') }}</a>
                </div>
                <button class="btn-primary btn-lg w-full">{{ __('Intră în cont') }}</button>
            </form>
            <div class="mt-6 grid gap-3 text-center text-sm sm:grid-cols-2">
                <a href="{{ lroute('register') }}" class="btn-ghost">{{ __('Cont nou de vizitator') }}</a>
                <a href="{{ lroute('register.producer') }}" class="btn-ghost">{{ __('Cont nou de producător') }}</a>
            </div>
        </div>
    </section>
</x-layouts.site>
