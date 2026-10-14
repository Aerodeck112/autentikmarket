<x-layouts.site :title="__('Calendarul târgurilor')" :description="__('Calendarul târgurilor Autentik Market: date, locații și program.')">
    <section class="container-site pt-10">
        <p class="eyebrow">{{ __('Calendar') }}</p>
        <h1 class="mt-3 text-5xl sm:text-6xl">{{ __('Calendarul târgurilor Autentik Market') }}</h1>

        <form method="GET" class="mt-8 flex flex-wrap items-center gap-2">
            <a href="{{ lroute('events.index', array_filter(['city' => $city])) }}" @class(['chip', 'chip-active' => $view === 'list'])>{{ __('Listă') }}</a>
            <a href="{{ lroute('events.index', array_filter(['city' => $city, 'view' => 'calendar', 'month' => $monthStart->format('Y-m')])) }}" @class(['chip', 'chip-active' => $view === 'calendar'])>{{ __('Calendar') }}</a>
            <span class="mx-2 h-6 w-px bg-line"></span>
            <a href="{{ lroute('events.index', array_filter(['view' => $view === 'calendar' ? 'calendar' : null])) }}" @class(['chip', 'chip-active' => ! $city])>{{ __('Toate orașele') }}</a>
            @foreach ($cities as $c)
                <a href="{{ lroute('events.index', array_filter(['city' => $c, 'view' => $view === 'calendar' ? 'calendar' : null])) }}" @class(['chip', 'chip-active' => $city === $c])>{{ $c }}</a>
            @endforeach
        </form>

        @if ($view === 'calendar')
            @php
                $start = $monthStart->copy()->startOfWeek();
                $end = $monthStart->copy()->endOfMonth()->endOfWeek();
                $days = [];
                for ($d = $start->copy(); $d->lte($end); $d->addDay()) { $days[] = $d->copy(); }
            @endphp
            <div class="mt-8 card overflow-hidden">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <a class="btn-ghost btn-sm" href="{{ lroute('events.index', array_filter(['view' => 'calendar', 'city' => $city, 'month' => $monthStart->copy()->subMonth()->format('Y-m')])) }}">←</a>
                    <p class="font-display text-2xl capitalize">{{ $monthStart->locale(app()->getLocale())->translatedFormat('F Y') }}</p>
                    <a class="btn-ghost btn-sm" href="{{ lroute('events.index', array_filter(['view' => 'calendar', 'city' => $city, 'month' => $monthStart->copy()->addMonth()->format('Y-m')])) }}">→</a>
                </div>
                <div class="grid grid-cols-7 text-center text-xs font-semibold uppercase tracking-wider text-ink-soft">
                    @foreach (array_slice($days, 0, 7) as $d)<div class="py-2">{{ $d->locale(app()->getLocale())->translatedFormat('D') }}</div>@endforeach
                </div>
                <div class="grid grid-cols-7 border-t border-line">
                    @foreach ($days as $d)
                        @php $dayEvents = $calendarEvents->get($d->toDateString(), collect()); @endphp
                        <div @class(['min-h-24 border-b border-r border-line/70 p-1.5 text-left text-xs sm:min-h-28', 'bg-cream/40 text-ink-soft/50' => ! $d->isSameMonth($monthStart)])>
                            <span @class(['inline-grid size-6 place-items-center rounded-full', 'bg-clay text-white' => $d->isToday()])>{{ $d->day }}</span>
                            @foreach ($dayEvents as $ev)
                                <a href="{{ lroute('events.show', $ev->slug) }}" class="mt-1 block truncate rounded-lg bg-forest px-1.5 py-1 text-[11px] font-medium text-cream hover:bg-clay">{{ $ev->localizedCity() }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            @if ($upcoming->isEmpty())
                <x-empty class="mt-10" :title="__('Calendarul se actualizează')" :text="__('Datele următoarelor ediții vor fi anunțate în curând. Abonează-te la newsletter ca să afli primul.')" />
            @else
                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($upcoming as $event)
                        @include('site.partials.event-card')
                    @endforeach
                </div>
            @endif
        @endif

        @if ($past->isNotEmpty())
            <h2 class="mt-20 text-3xl">{{ __('Ediții anterioare') }}</h2>
            <div class="mt-6 divide-y divide-line border-y border-line">
                @foreach ($past as $event)
                    <a href="{{ lroute('events.show', $event->slug) }}" class="flex items-center justify-between gap-4 py-4 hover:text-clay">
                        <span class="font-display text-lg">{{ $event->t('title') }}</span>
                        <span class="text-sm text-ink-soft">{{ format_date_local($event->event_date) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.site>
