<x-layouts.site title="Autentik Shop">
    <section class="container-site max-w-3xl pt-16 text-center">
        <p class="eyebrow">Autentik Shop</p>
        <h1 class="mt-3 text-5xl sm:text-6xl">{{ __('Produsele producătorilor noștri, online') }}</h1>
        <p class="mx-auto mt-5 max-w-xl text-lg text-ink-soft">{{ __('Autentik Shop este magazinul online separat al comunității. Comenzile, plata și livrarea se fac pe site-ul magazinului.') }}</p>
        @if ($url)
            <a href="{{ $url }}" target="_blank" rel="noopener" class="btn-accent btn-lg mt-10">{{ __('Descoperă produsele') }} ↗</a>
        @else
            <p class="mt-10 text-ink-soft">{{ __('Linkul către magazin va fi disponibil în curând.') }}</p>
        @endif
    </section>
</x-layouts.site>
