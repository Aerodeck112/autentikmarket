@props(['label', 'value', 'hint' => null, 'href' => null, 'tone' => 'default'])
@php $toneCls = ['default' => 'text-ink', 'alert' => 'text-red-700', 'good' => 'text-forest', 'warn' => 'text-amber-700'][$tone] ?? 'text-ink'; @endphp
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card card-pad block transition '.($href ? 'hover:shadow-[var(--shadow-lift)]' : '')]) }}>
    <p class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ $label }}</p>
    <p class="mt-2 font-display text-3xl {{ $toneCls }}">{{ $value }}</p>
    @if ($hint)<p class="mt-1 text-xs text-ink-soft">{{ $hint }}</p>@endif
    {{ $slot }}
</{{ $href ? 'a' : 'div' }}>
