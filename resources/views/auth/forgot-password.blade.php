<x-layouts.site :title="__('Am uitat parola')" noindex>
    <section class="container-site flex justify-center py-16">
        <form method="POST" action="{{ lroute('password.email') }}" class="card card-pad w-full max-w-md space-y-4 sm:p-8">
            @csrf
            <h1 class="text-3xl">{{ __('Am uitat parola') }}</h1>
            <p class="text-sm text-ink-soft">{{ __('Îți trimitem un link de resetare pe e-mail, în limba contului tău.') }}</p>
            <x-flash />
            <x-input name="email" type="email" :label="__('E-mail')" required autofocus />
            <button class="btn-primary w-full">{{ __('Trimite linkul') }}</button>
        </form>
    </section>
</x-layouts.site>
