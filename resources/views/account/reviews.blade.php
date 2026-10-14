<x-layouts.app :title="__('Recenziile mele')">
    <h1 class="text-3xl sm:text-4xl">{{ __('Recenziile mele') }}</h1>
    @if ($pending->isNotEmpty())
        <div class="card card-pad mt-8">
            <h2 class="text-xl">{{ __('Așteaptă recenzia ta') }}</h2>
            @foreach ($pending as $i)
                <div class="mt-3 flex items-center justify-between border-t border-line pt-3">
                    <span>{{ $i->producer->brand_name }} <span class="text-sm text-ink-soft">· {{ format_date_local($i->event->event_date) }}</span></span>
                    <a href="{{ URL::temporarySignedRoute('reviews.create', now()->addDays(7), ['interaction' => $i->id]) }}" class="btn-accent btn-sm">{{ __('Scrie recenzia') }}</a>
                </div>
            @endforeach
        </div>
    @endif
    <div class="mt-8 grid gap-4 md:grid-cols-2">
        @forelse ($reviews as $review)
            <div class="card card-pad">
                <div class="flex items-center justify-between">
                    <p class="font-medium">{{ $review->producer->brand_name }}</p>
                    <x-status :status="$review->status" />
                </div>
                @include('site.partials.stars', ['rating' => $review->rating])
                @if ($review->body)<p class="mt-2 text-sm text-ink-soft">„{{ $review->body }}”</p>@endif
            </div>
        @empty
            <x-empty class="md:col-span-2" :title="__('Nicio recenzie încă')" :text="__('După ce cumperi de la un producător la târg, vei putea lăsa o recenzie.')" />
        @endforelse
    </div>
</x-layouts.app>
