@props(['title' => null, 'description' => null, 'image' => null, 'noindex' => false])
@php
    $locale = app()->getLocale();
    $fullTitle = $title ? $title.' · Autentik Market' : 'Autentik Market — '.tr('târg lunar de producători și artizani', 'havi termelői és kézműves vásár');
    $desc = $description ?: setting('seo.default_description_'.$locale);
    $isLocalized = (bool) preg_match('/^(ro|hu)\./', (string) Route::currentRouteName());
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ Str::limit(strip_tags((string) $desc), 160) }}">
@if ($noindex || ! $isLocalized)
    <meta name="robots" content="noindex, nofollow">
@else
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="ro" href="{{ \App\Support\Locales::switchUrl('ro') }}">
    <link rel="alternate" hreflang="hu" href="{{ \App\Support\Locales::switchUrl('hu') }}">
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Locales::switchUrl('ro') }}">
@endif
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ Str::limit(strip_tags((string) $desc), 200) }}">
<meta property="og:locale" content="{{ $locale === 'hu' ? 'hu_HU' : 'ro_RO' }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($image)<meta property="og:image" content="{{ $image }}">@endif
