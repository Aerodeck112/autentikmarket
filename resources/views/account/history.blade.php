<x-layouts.app :title="__('Istoricul târgurilor')">
    <h1 class="text-3xl sm:text-4xl">{{ __('Istoricul târgurilor') }}</h1>
    <div class="mt-8 grid gap-8 lg:grid-cols-2">
        <div class="card card-pad">
            <h2 class="text-xl">{{ __('Vizitele mele') }}</h2>
            @forelse ($checkins as $c)
                <div class="mt-3 flex items-center justify-between border-t border-line pt-3">
                    <a href="{{ lroute('events.show', $c->event->slug) }}" class="hover:text-clay">{{ $c->event->t('title') }}</a>
                    <span class="text-sm text-ink-soft">{{ format_date_local($c->event->event_date) }}</span>
                </div>
            @empty
                <p class="mt-3 text-sm text-ink-soft">{{ __('Încă nu ai vizite înregistrate. Arată cardul la intrarea în târg.') }}</p>
            @endforelse
            {{ $checkins->links() }}
        </div>
        <div class="card card-pad">
            <h2 class="text-xl">{{ __('Producători de la care am cumpărat') }}</h2>
            @forelse ($interactions as $i)
                <div class="mt-3 flex items-center justify-between border-t border-line pt-3">
                    <a href="{{ lroute('producers.show', $i->producer->slug) }}" class="hover:text-clay">{{ $i->producer->brand_name }}</a>
                    <span class="text-sm text-ink-soft">{{ format_date_local($i->event->event_date) }}</span>
                </div>
            @empty
                <p class="mt-3 text-sm text-ink-soft">{{ __('Scanează codul QR de la standul producătorului („Am cumpărat de aici”) ca să poți lăsa o recenzie după târg.') }}</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
