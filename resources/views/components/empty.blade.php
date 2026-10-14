@props(['title', 'text' => null])
<div {{ $attributes->merge(['class' => 'rounded-[var(--radius-card)] border border-dashed border-line bg-cream/40 px-6 py-12 text-center']) }}>
    <p class="font-display text-xl text-ink">{{ $title }}</p>
    @if ($text)<p class="mx-auto mt-2 max-w-md text-sm text-ink-soft">{{ $text }}</p>@endif
    {{ $slot }}
</div>
