<x-layouts.site :title="$event->t('title').' — '.format_date_local($event->event_date)" :description="Str::limit((string) $event->t('description'), 160)" :image="$event->coverUrl()">
    @php
        $statusLabels = ['available' => __('Disponibil'), 'reserved' => __('Ocupat'), 'blocked' => __('Indisponibil'), 'unavailable' => __('Indisponibil')];
    @endphp
    <section class="relative overflow-hidden bg-forest-dark text-cream grain">
        @if ($event->coverUrl())
            <img src="{{ $event->coverUrl() }}" alt="" class="absolute inset-0 size-full object-cover opacity-30">
        @endif
        <div class="container-site relative z-10 py-16 sm:py-24">
            <a href="{{ lroute('events.index') }}" class="text-sm text-cream/70 hover:text-white">← {{ __('Calendarul târgurilor') }}</a>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-status :status="$event->status" />
                @if ($event->edition)<span class="rounded-full border border-cream/25 px-3 py-0.5 text-xs font-semibold uppercase tracking-wider text-wheat">{{ $event->editionLabel() }}</span>@endif
            </div>
            <h1 class="mt-4 max-w-3xl text-5xl leading-tight sm:text-6xl">{{ $event->t('title') }}</h1>
            <div class="mt-6 grid max-w-3xl gap-4 text-cream/85 sm:grid-cols-3">
                <div><p class="text-xs uppercase tracking-wider text-honey">{{ __('Data') }}</p><p class="mt-1 text-lg capitalize">{{ format_date_local($event->event_date, true) }}</p></div>
                <div><p class="text-xs uppercase tracking-wider text-honey">{{ __('Program') }}</p><p class="mt-1 text-lg">{{ $event->timeRange() ?: '—' }}</p></div>
                <div><p class="text-xs uppercase tracking-wider text-honey">{{ __('Locația') }}</p><p class="mt-1 text-lg">{{ $event->venue_name ?: $event->localizedCity() }}</p><p class="text-sm text-cream/60">{{ $event->localizedAddress() }}, {{ $event->localizedCity() }}</p></div>
            </div>
            <div class="mt-8 flex flex-wrap gap-3">
                @guest<a href="{{ lroute('register') }}" class="btn bg-honey text-ink hover:bg-white btn-lg">{{ __('Ia-ți cardul Autentik') }}</a>@endguest
                @if ($event->registrationsOpen())
                    <a href="{{ auth()->user()?->producer ? route('producer.events.show', $event) : lroute('for-producers') }}" class="btn border border-cream/30 text-cream hover:bg-white/10 btn-lg">{{ __('Participă ca producător') }}</a>
                @endif
                @if ($event->venue?->lat && $event->venue?->lng)
                    <a href="https://www.openstreetmap.org/?mlat={{ $event->venue->lat }}&mlon={{ $event->venue->lng }}#map=17/{{ $event->venue->lat }}/{{ $event->venue->lng }}" target="_blank" rel="noopener" class="btn border border-cream/30 text-cream hover:bg-white/10 btn-lg">{{ __('Vezi pe hartă') }} ↗</a>
                @endif
            </div>
        </div>
    </section>

    <section class="container-site mt-14 grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-7">
            @if ($event->t('description'))
                <div class="prose-am">{!! nl2br(e($event->t('description'))) !!}</div>
            @endif
            @if ($event->t('program'))
                <h2 class="mt-10 text-3xl">{{ __('Program') }}</h2>
                <div class="prose-am mt-4">{!! nl2br(e($event->t('program'))) !!}</div>
            @endif

            @if ($event->layout)
                <h2 class="mt-12 text-3xl">{{ __('Harta târgului') }}</h2>
                <p class="mt-2 text-sm text-ink-soft">{{ __('Atinge un stand pentru a vedea producătorul.') }}</p>
                <div class="mt-5 card overflow-hidden" x-data="standMap({ dataUrl: '{{ lroute('events.map', $event->slug) }}', mode: 'public', pollSeconds: 60, labels: { status: @js($statusLabels) } })">
                    <div class="relative">
                        <svg x-ref="svg" class="block h-[360px] w-full touch-none select-none bg-cream/40 sm:h-[460px]" preserveAspectRatio="xMidYMid meet"><g x-ref="layer"></g></svg>
                        <div class="absolute right-3 top-3 flex flex-col gap-1">
                            <button type="button" @click="zoomIn" class="btn-ghost btn-sm !px-3" aria-label="Zoom +">＋</button>
                            <button type="button" @click="zoomOut" class="btn-ghost btn-sm !px-3" aria-label="Zoom −">−</button>
                            <button type="button" @click="reset" class="btn-ghost btn-sm !px-3" aria-label="Reset">⤢</button>
                        </div>
                    </div>
                    <div class="border-t border-line p-4 text-sm" x-show="selected" x-cloak>
                        <p class="font-display text-xl" x-text="selected?.code"></p>
                        <template x-if="selected?.producer"><a :href="selected.producer.url" class="text-clay underline" x-text="selected.producer.name"></a></template>
                        <template x-if="!selected?.producer"><p class="text-ink-soft" x-text="statusLabel(selected?.status)"></p></template>
                    </div>
                </div>
            @endif

            @if ($event->photos->isNotEmpty())
                <h2 class="mt-12 text-3xl">{{ __('Galerie') }}</h2>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($event->photos as $photo)
                        <img src="{{ $photo->url() }}" alt="{{ $photo->t('caption') }}" loading="lazy" class="aspect-[4/3] w-full rounded-2xl object-cover">
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="space-y-6 lg:col-span-5">
            @if ($raffle)
                <div class="rounded-[var(--radius-card)] bg-honey p-6 text-ink">
                    <p class="eyebrow !text-ink/70">{{ __('Tombolă') }}</p>
                    <h3 class="mt-2 text-2xl">{{ $raffle->t('name') }}</h3>
                    @if ($raffle->t('description'))<p class="mt-2 text-sm text-ink/80">{{ $raffle->t('description') }}</p>@endif
                    @if ($raffle->prizes->isNotEmpty())
                        <ul class="mt-3 space-y-1 text-sm">@foreach ($raffle->prizes as $prize)<li>🎁 {{ $prize->t('name') }}@if ($prize->quantity > 1) × {{ $prize->quantity }}@endif</li>@endforeach</ul>
                    @endif
                    @if ($raffle->t('conditions'))<p class="mt-3 text-xs text-ink/70">{{ $raffle->t('conditions') }}</p>@endif
                    <a href="{{ auth()->check() ? route('account.raffles') : lroute('register') }}" class="btn-primary btn-sm mt-4">{{ auth()->check() ? __('Statusul meu la tombolă') : __('Creează cont pentru a participa') }}</a>
                </div>
            @endif

            <div class="card card-pad">
                <h3 class="text-xl">{{ __('Producători confirmați') }} <span class="text-ink-soft">({{ $producers->count() }})</span></h3>
                @forelse ($producers as $reg)
                    <a href="{{ lroute('producers.show', $reg->producer->slug) }}" class="mt-3 flex items-center gap-3 border-t border-line pt-3 hover:text-clay">
                        <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-full bg-cream text-sm font-bold text-forest">
                            @if ($reg->producer->logoUrl())<img src="{{ $reg->producer->logoUrl() }}" alt="" class="size-full object-cover">@else{{ $reg->producer->initials() }}@endif
                        </span>
                        <span class="flex-1">
                            <span class="block font-medium">{{ $reg->producer->brand_name }}</span>
                            <span class="block text-xs text-ink-soft">{{ $reg->producer->category?->t('name') }}</span>
                        </span>
                        @if ($reg->activeReservation?->slot)<span class="rounded-full bg-cream px-2 py-0.5 text-xs font-semibold">{{ $reg->activeReservation->slot->code }}</span>@endif
                    </a>
                @empty
                    <p class="mt-3 text-sm text-ink-soft">{{ __('Lista producătorilor confirmați va fi publicată în curând.') }}</p>
                @endforelse
            </div>

            <div class="card card-pad">
                <h3 class="text-xl">{{ __('Informații utile') }}</h3>
                <ul class="mt-3 space-y-2 text-sm text-ink-soft">
                    <li>💳 {{ __('Arată cardul Autentik la intrare — fiecare vizită contează pentru cadouri.') }}</li>
                    <li>🛍️ {{ __('Adu-ți propria sacoșă și recipiente.') }}</li>
                    <li>🤝 {{ __('Cumpără direct de la producător și scanează codul QR de la stand pentru a lăsa o recenzie.') }}</li>
                </ul>
            </div>
        </aside>
    </section>
</x-layouts.site>
