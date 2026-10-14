<x-layouts.site :title="$producer->brand_name" :description="Str::limit((string) $producer->t('description'), 160)" :image="$producer->coverUrl()">
    <section class="relative">
        <div class="h-64 w-full overflow-hidden bg-forest sm:h-96 grain">
            @if ($producer->coverUrl())
                <img src="{{ $producer->coverUrl() }}" alt="" class="size-full object-cover">
            @endif
        </div>
        <div class="container-site">
            <div class="-mt-16 flex flex-col gap-6 sm:-mt-20 sm:flex-row sm:items-end">
                <div class="grid size-32 shrink-0 place-items-center overflow-hidden rounded-full border-4 border-paper bg-cream shadow-[var(--shadow-lift)] sm:size-40">
                    @if ($producer->logoUrl())
                        <img src="{{ $producer->logoUrl() }}" alt="{{ $producer->brand_name }}" class="size-full object-cover">
                    @else
                        <span class="font-display text-5xl text-forest">{{ $producer->initials() }}</span>
                    @endif
                </div>
                <div class="flex-1 pb-2">
                    @if ($producer->category)<p class="eyebrow">{{ $producer->category->icon }} {{ $producer->category->t('name') }}</p>@endif
                    <h1 class="mt-2 text-4xl sm:text-5xl">{{ $producer->brand_name }}</h1>
                    <p class="mt-2 text-ink-soft">{{ $producer->city }}@if ($producer->county), {{ $producer->county }}@endif
                        @if ($reviewsCount) · <span class="text-honey">★ {{ $rating }}</span> ({{ $reviewsCount }} {{ __('recenzii') }})@endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 pb-2">
                    @if ($producer->shop_url)
                        <a href="{{ $producer->shop_url }}" target="_blank" rel="noopener" class="btn-accent">{{ __('Vezi produsele în Autentik Shop') }} ↗</a>
                    @endif
                    @foreach (['website' => 'Web', 'facebook' => 'Facebook', 'instagram' => 'Instagram'] as $field => $label)
                        @if ($producer->$field)<a href="{{ $producer->$field }}" target="_blank" rel="noopener nofollow" class="btn-ghost btn-sm">{{ $label }}</a>@endif
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="container-site mt-14 grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-7">
            @if ($producer->t('story'))
                <h2 class="text-3xl">{{ __('Povestea noastră') }}</h2>
                <div class="prose-am mt-4">{!! nl2br(e($producer->t('story'))) !!}</div>
            @endif
            @if ($producer->t('description'))
                <div class="prose-am mt-6">{!! nl2br(e($producer->t('description'))) !!}</div>
            @endif
            @if ($producer->t('products'))
                <h2 class="mt-10 text-3xl">{{ __('Ce găsești la stand') }}</h2>
                <div class="prose-am mt-4">{!! nl2br(e($producer->t('products'))) !!}</div>
            @endif

            @if ($producer->gallery->isNotEmpty())
                <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($producer->gallery as $photo)
                        <img src="{{ $photo->url() }}" alt="{{ $photo->t('caption') }}" loading="lazy" class="aspect-square w-full rounded-2xl object-cover">
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="space-y-6 lg:col-span-5">
            <div class="card card-pad">
                <h3 class="text-xl">{{ __('Participări la târguri') }}</h3>
                @forelse ($events as $event)
                    <a href="{{ lroute('events.show', $event->slug) }}" class="mt-3 flex items-center justify-between border-t border-line pt-3 text-sm hover:text-clay">
                        <span>{{ $event->t('title') }}</span>
                        <span class="text-ink-soft">{{ format_date_local($event->event_date) }}</span>
                    </a>
                @empty
                    <p class="mt-3 text-sm text-ink-soft">{{ __('Primele participări vor apărea aici.') }}</p>
                @endforelse
            </div>

            <div class="card card-pad">
                <h3 class="text-xl">{{ __('Recenzii') }}</h3>
                @forelse ($reviews as $review)
                    <figure class="mt-4 border-t border-line pt-4">
                        @include('site.partials.stars', ['rating' => $review->rating])
                        @if ($review->body)<blockquote class="mt-2 text-sm text-ink">„{{ $review->body }}”</blockquote>@endif
                        @if ($review->photoUrl())<img src="{{ $review->photoUrl() }}" alt="" loading="lazy" class="mt-2 h-28 rounded-xl object-cover">@endif
                        <figcaption class="mt-1 text-xs text-ink-soft">{{ $review->authorDisplayName() }}@if ($review->product_name) · {{ $review->product_name }}@endif</figcaption>
                    </figure>
                @empty
                    <p class="mt-3 text-sm text-ink-soft">{{ __('Recenziile vin de la vizitatorii care au cumpărat de la acest producător la târg.') }}</p>
                @endforelse
            </div>
        </aside>
    </section>
</x-layouts.site>
