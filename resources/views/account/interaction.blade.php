<x-layouts.app :title="$producer->brand_name">
    <div class="mx-auto max-w-md text-center">
        <div class="mx-auto grid size-24 place-items-center overflow-hidden rounded-full bg-cream font-display text-3xl text-forest">
            @if ($producer->logoUrl())<img src="{{ $producer->logoUrl() }}" alt="" class="size-full object-cover">@else{{ $producer->initials() }}@endif
        </div>
        <h1 class="mt-4 text-3xl">{{ $producer->brand_name }}</h1>
        @if ($event)
            <p class="mt-2 text-ink-soft">{{ $event->t('title') }} · {{ format_date_local($event->event_date) }}</p>
            <form method="POST" action="{{ route('qr.producer.store', $producer->qr_token) }}" class="mt-8">
                @csrf
                <button class="btn-accent btn-lg w-full">🛍️ {{ __('AM CUMPĂRAT DE AICI') }}</button>
            </form>
            <p class="mt-3 text-xs text-ink-soft">{{ __('După târg vei primi invitația de a lăsa o recenzie, în limba ta.') }}</p>
        @else
            <p class="mt-6 text-ink-soft">{{ __('Producătorul nu participă astăzi la un târg Autentik Market.') }}</p>
            <a href="{{ lroute('producers.show', $producer->slug) }}" class="btn-ghost mt-6">{{ __('Vezi profilul producătorului') }}</a>
        @endif
    </div>
</x-layouts.app>
