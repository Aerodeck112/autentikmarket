@props(['light' => false, 'size' => 'md', 'variant' => 'wordmark'])
@php
    // Sigla oficială (public/images/brand). Variante: wordmark (fără „by My Family Center"),
    // horizontal (cu semnătura), stacked (rotundă), emblem (doar coroana de spice/soare).
    // Variantele -light au textul verde recolorat în crem, pentru fundaluri închise.
    $custom = setting('brand.logo_path');
    $variant = in_array($variant, ['wordmark', 'horizontal', 'stacked', 'emblem'], true) ? $variant : 'wordmark';
    $file = 'images/brand/'.($variant === 'emblem' ? 'emblem' : 'logo-'.$variant).($light ? '-light' : '');
    $dims = ['wordmark' => [1200, 412], 'horizontal' => [1400, 606], 'stacked' => [544, 817], 'emblem' => [512, 511]][$variant];
    $height = [
        'sm' => ['wordmark' => 'h-8', 'horizontal' => 'h-10', 'stacked' => 'h-16', 'emblem' => 'h-8'],
        'md' => ['wordmark' => 'h-11', 'horizontal' => 'h-14', 'stacked' => 'h-24', 'emblem' => 'h-10'],
        'lg' => ['wordmark' => 'h-14', 'horizontal' => 'h-20', 'stacked' => 'h-36', 'emblem' => 'h-14'],
    ][$size][$variant] ?? 'h-11';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center']) }}>
    @if ($custom)
        <img src="{{ Storage::disk('public')->url($custom) }}" alt="Autentik Market" class="{{ $height }} w-auto">
    @else
        <picture>
            <source srcset="{{ asset($file.'.webp') }}" type="image/webp">
            <img src="{{ asset($file.'.png') }}" alt="Autentik Market" width="{{ $dims[0] }}" height="{{ $dims[1] }}" class="{{ $height }} w-auto" decoding="async">
        </picture>
    @endif
</span>
