@php /** @var \App\Models\Event $event */ @endphp
<a href="{{ lroute('events.show', $event->slug) }}" class="group card flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-[var(--shadow-lift)]">
    <div class="relative flex aspect-[16/9] items-end bg-forest p-5 text-cream grain">
        @if ($event->coverUrl())
            <img src="{{ $event->coverUrl() }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover opacity-80 transition duration-500 group-hover:scale-105">
            <div class="absolute inset-0 bg-gradient-to-t from-ink/80 via-ink/20 to-transparent"></div>
        @endif
        <div class="relative z-10 flex items-end gap-4">
            <div class="rounded-2xl bg-cream px-3 py-2 text-center text-ink shadow">
                <p class="text-[11px] font-bold uppercase tracking-wider text-clay">{{ $event->event_date->locale(app()->getLocale())->translatedFormat('M') }}</p>
                <p class="font-display text-3xl leading-none">{{ $event->event_date->format('j') }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-wheat">{{ $event->event_date->locale(app()->getLocale())->translatedFormat('l') }}</p>
                <p class="font-display text-2xl leading-tight">{{ $event->localizedCity() }}</p>
            </div>
        </div>
    </div>
    <div class="flex flex-1 flex-col gap-2 p-5">
        <p class="font-display text-lg leading-snug text-ink">{{ $event->t('title') }}</p>
        <p class="text-sm text-ink-soft">{{ $event->venue_name ?: $event->venue?->name }}@if ($event->timeRange()) · {{ $event->timeRange() }}@endif</p>
        <span class="mt-auto pt-3 text-sm font-semibold text-clay group-hover:underline">{{ __('Vezi eveniment') }} →</span>
    </div>
</a>
