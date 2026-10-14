<x-layouts.site :title="__('Recenzii')" :description="__('Recenzii lăsate de vizitatori pentru producătorii Autentik Market.')">
    <section class="container-site pt-10">
        <p class="eyebrow">{{ __('Vocea comunității') }}</p>
        <h1 class="mt-3 text-5xl sm:text-6xl">{{ __('Recenzii') }}</h1>
        <p class="mt-4 max-w-2xl text-lg text-ink-soft">{{ __('Doar vizitatorii care au cumpărat efectiv de la un producător la târg pot lăsa o recenzie. Fiecare recenzie este moderată.') }}</p>
        @if ($total)
            <p class="mt-6 font-display text-3xl"><span class="text-honey">★ {{ $average }}</span> <span class="text-lg text-ink-soft">/ 5 · {{ $total }} {{ __('recenzii') }}</span></p>
        @endif
        @if ($reviews->isEmpty())
            <x-empty class="mt-10" :title="__('Încă nu există recenzii')" :text="__('După fiecare târg, vizitatorii primesc invitația de a evalua producătorii de la care au cumpărat.')" />
        @else
            <div class="mt-10 columns-1 gap-6 sm:columns-2 lg:columns-3">
                @foreach ($reviews as $review)
                    <figure class="card card-pad mb-6 break-inside-avoid">
                        @include('site.partials.stars', ['rating' => $review->rating])
                        @if ($review->body)<blockquote class="mt-3 text-ink">„{{ $review->body }}”</blockquote>@endif
                        @if ($review->photoUrl())<img src="{{ $review->photoUrl() }}" alt="" loading="lazy" class="mt-3 w-full rounded-xl object-cover">@endif
                        <figcaption class="mt-4 text-sm text-ink-soft">
                            {{ $review->authorDisplayName() }} → <a href="{{ lroute('producers.show', $review->producer->slug) }}" class="text-clay hover:underline">{{ $review->producer->brand_name }}</a>
                            @if ($review->event)<span class="block text-xs">{{ $review->event->t('title') }}, {{ format_date_local($review->event->event_date) }}</span>@endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
            {{ $reviews->links() }}
        @endif
    </section>
</x-layouts.site>
