<x-layouts.site :title="__('Producători')" :description="__('Producătorii și artizanii verificați care participă la Autentik Market.')">
    <section class="container-site pt-10">
        <p class="eyebrow">{{ __('Comunitatea Autentik') }}</p>
        <h1 class="mt-3 text-5xl sm:text-6xl">{{ __('Producători') }}</h1>
        <p class="mt-4 max-w-2xl text-lg text-ink-soft">{{ __('Fiecare producător de pe această pagină a fost verificat de echipa Autentik Market. Oameni reali, cu povești reale.') }}</p>

        <form method="GET" class="mt-8 flex flex-col gap-4">
            <div class="flex max-w-md gap-2">
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Caută după nume sau localitate') }}" class="field-input !rounded-full">
                @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                <button class="btn-primary">{{ __('Caută') }}</button>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ lroute('producers.index', array_filter(['q' => $q])) }}" @class(['chip', 'chip-active' => ! $category])>{{ __('Toate') }}</a>
                @foreach ($categories as $cat)
                    <a href="{{ lroute('producers.index', array_filter(['category' => $cat->slug, 'q' => $q])) }}" @class(['chip', 'chip-active' => $category === $cat->slug])>{{ $cat->icon }} {{ $cat->t('name') }}</a>
                @endforeach
            </div>
        </form>

        @if ($producers->isEmpty())
            <x-empty class="mt-10" :title="__('Niciun producător găsit')" :text="__('Încearcă altă categorie sau revino în curând — comunitatea crește de la o lună la alta.')" />
        @else
            <div class="mt-10 grid grid-cols-2 gap-x-5 gap-y-10 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($producers as $producer)
                    @include('site.partials.producer-card')
                @endforeach
            </div>
            {{ $producers->links() }}
        @endif
    </section>
</x-layouts.site>
