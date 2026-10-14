<x-layouts.site :title="__('Contact')">
    <section class="container-site grid gap-12 pt-10 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <p class="eyebrow">{{ __('Contact') }}</p>
            <h1 class="mt-3 text-5xl">{{ __('Hai să vorbim') }}</h1>
            <p class="mt-4 text-lg text-ink-soft">{{ __('Ai o întrebare despre târg, vrei să participi ca producător sau ai o idee pentru comunitate? Scrie-ne în română sau maghiară.') }}</p>
            <dl class="mt-8 space-y-4 text-sm">
                @if (setting('org.email'))<div><dt class="text-ink-soft">E-mail</dt><dd><a class="text-lg text-clay" href="mailto:{{ setting('org.email') }}">{{ setting('org.email') }}</a></dd></div>@endif
                @if (setting('org.phone'))<div><dt class="text-ink-soft">{{ __('Telefon') }}</dt><dd class="text-lg">{{ setting('org.phone') }}</dd></div>@endif
                @if (setting('social.facebook'))<div><dt class="text-ink-soft">Facebook</dt><dd><a class="text-clay" href="{{ setting('social.facebook') }}" target="_blank" rel="noopener">Autentik Market ↗</a></dd></div>@endif
            </dl>
        </div>
        <form method="POST" action="{{ lroute('contact.store') }}" class="card card-pad space-y-4 lg:col-span-7">
            @csrf
            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('Nume')" required />
                <x-input name="email" type="email" :label="__('E-mail')" required />
                <x-input name="phone" :label="__('Telefon')" />
                <x-input name="subject" :label="__('Subiect')" />
            </div>
            <x-textarea name="message" :label="__('Mesaj')" rows="6" required />
            <x-checkbox name="privacy" :label="__('Am citit politica de confidențialitate și sunt de acord cu prelucrarea datelor pentru a primi un răspuns.')" />
            <button class="btn-primary btn-lg">{{ __('Trimite mesajul') }}</button>
        </form>
    </section>
</x-layouts.site>
