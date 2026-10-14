<x-layouts.site :title="__('Confirmă adresa de e-mail')" noindex>
    <section class="container-site flex justify-center py-16">
        <div class="card card-pad w-full max-w-md space-y-4 text-center sm:p-8">
            <h1 class="text-3xl">{{ __('Confirmă adresa de e-mail') }}</h1>
            <p class="text-ink-soft">{{ __('Ți-am trimis un link de confirmare. Verifică și folderul Spam.') }}</p>
            <x-flash />
            <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn-primary">{{ __('Retrimite e-mailul') }}</button></form>
            <a href="{{ route('dashboard.redirect') }}" class="block text-sm text-clay underline">{{ __('Continuă') }}</a>
        </div>
    </section>
</x-layouts.site>
