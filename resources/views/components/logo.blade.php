@props(['light' => false, 'size' => 'md'])
@php $logo = setting('brand.logo_path'); @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    @if ($logo)
        <img src="{{ Storage::disk('public')->url($logo) }}" alt="Autentik Market" class="{{ $size === 'lg' ? 'h-14' : 'h-10' }} w-auto">
    @else
        <svg viewBox="0 0 40 40" class="{{ $size === 'lg' ? 'size-12' : 'size-9' }} shrink-0" aria-hidden="true">
            <circle cx="20" cy="20" r="19" fill="{{ $light ? '#f6f1e7' : '#2f4a3a' }}"/>
            <path d="M11 27c3-9 6-14 9-14s6 5 9 14" fill="none" stroke="{{ $light ? '#2f4a3a' : '#f6f1e7' }}" stroke-width="2.4" stroke-linecap="round"/>
            <path d="M15 22h10" stroke="#d9a43b" stroke-width="2.4" stroke-linecap="round"/>
        </svg>
        <span class="leading-none">
            <span class="block font-display text-[1.35rem] font-semibold tracking-tight {{ $light ? 'text-cream' : 'text-ink' }}">Autentik</span>
            <span class="block text-[0.62rem] font-semibold uppercase tracking-[0.32em] {{ $light ? 'text-honey' : 'text-clay' }}">Market</span>
        </span>
    @endif
</span>
