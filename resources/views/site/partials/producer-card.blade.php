@php /** @var \App\Models\Producer $producer */ @endphp
<a href="{{ lroute('producers.show', $producer->slug) }}" class="group block">
    <div class="relative aspect-[4/5] overflow-hidden rounded-[var(--radius-card)] bg-sand">
        @if ($producer->coverUrl())
            <img src="{{ $producer->coverUrl() }}" alt="{{ $producer->brand_name }}" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-105">
        @else
            <div class="grid size-full place-items-center bg-gradient-to-br from-sand via-cream to-[#e8dcc4] grain">
                <span class="font-display text-6xl text-forest/70">{{ $producer->initials() }}</span>
            </div>
        @endif
        @if ($producer->logoUrl())
            <img src="{{ $producer->logoUrl() }}" alt="" loading="lazy" class="absolute bottom-3 left-3 size-14 rounded-full border-2 border-white bg-white object-cover shadow">
        @endif
        @if ($producer->category)
            <span class="absolute right-3 top-3 rounded-full bg-white/90 px-3 py-1 text-xs font-medium text-ink backdrop-blur">{{ $producer->category->icon }} {{ $producer->category->t('name') }}</span>
        @endif
    </div>
    <div class="mt-3 flex items-start justify-between gap-2">
        <div>
            <p class="font-display text-lg leading-tight text-ink group-hover:text-clay">{{ $producer->brand_name }}</p>
            <p class="text-sm text-ink-soft">{{ $producer->city }}</p>
        </div>
        @if (($producer->rating ?? null) > 0)
            <span class="shrink-0 text-sm font-semibold text-honey">★ {{ number_format($producer->rating, 1) }}</span>
        @endif
    </div>
</a>
