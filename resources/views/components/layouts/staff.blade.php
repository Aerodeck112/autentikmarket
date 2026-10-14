@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#133e23">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <title>{{ $title ?? 'Staff' }} · Autentik</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-forest-dark text-cream">
<header class="sticky top-0 z-20 flex items-center justify-between gap-3 bg-forest-dark/95 px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] backdrop-blur">
    <a href="{{ route('staff.index') }}"><x-logo light /></a>
    <div class="flex items-center gap-2 text-xs">
        @foreach (['ro' => 'RO', 'hu' => 'HU'] as $loc => $lbl)
            <a href="{{ route('language.switch', $loc) }}" @class(['rounded-full px-2.5 py-1 font-semibold', 'bg-honey text-ink' => app()->getLocale() === $loc, 'text-cream/60' => app()->getLocale() !== $loc])>{{ $lbl }}</a>
        @endforeach
        @can('crm.access')<a href="{{ route('crm.dashboard') }}" class="rounded-full border border-cream/20 px-3 py-1">CRM</a>@endcan
    </div>
</header>
<main class="mx-auto max-w-lg px-4 pb-[max(2rem,env(safe-area-inset-bottom))]">
    {{ $slot }}
</main>
<script>
    if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/staff' }).catch(() => {})); }
</script>
</body>
</html>
