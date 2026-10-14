<x-layouts.app :title="__('Recenzii')" area="producer">
    <h1 class="text-3xl sm:text-4xl">{{ __('Recenziile clienților') }}</h1>
    <div class="mt-6 grid gap-4 md:grid-cols-2">
        @forelse ($reviews as $review)
            <div class="card card-pad">
                @include('site.partials.stars', ['rating' => $review->rating])
                @if ($review->body)<p class="mt-2">„{{ $review->body }}”</p>@endif
                <p class="mt-2 text-xs text-ink-soft">{{ $review->authorDisplayName() }} · {{ $review->event?->t('title') }}</p>
            </div>
        @empty
            <x-empty class="md:col-span-2" :title="__('Nicio recenzie încă')" :text="__('Pune codul QR la stand ca clienții să poată lăsa recenzii după târg.')" />
        @endforelse
    </div>
    {{ $reviews->links() }}
</x-layouts.app>
