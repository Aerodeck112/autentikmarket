@props(['title' => null, 'area' => 'account'])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2f4a3a">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}Autentik Market</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-cream/60 pb-24 lg:pb-0">
@php
    $user = auth()->user();
    $items = $area === 'producer'
        ? [
            ['producer.dashboard', __('Panou'), 'M3 12l9-9 9 9M5 10v10h14V10'],
            ['producer.events', __('Târguri'), 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z'],
            ['producer.profile', __('Profil'), 'M12 12a4 4 0 100-8 4 4 0 000 8zm-7 9a7 7 0 0114 0'],
            ['producer.documents', __('Documente'), 'M7 3h7l5 5v13H7zM14 3v5h5'],
            ['producer.scan', __('Scanează'), 'M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3M8 12h8'],
        ]
        : [
            ['account.index', __('Acasă'), 'M3 12l9-9 9 9M5 10v10h14V10'],
            ['account.card', __('Card'), 'M3 7h18v10H3zM3 11h18'],
            ['account.history', __('Istoric'), 'M12 8v4l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['account.raffles', __('Tombole'), 'M20 12v8H4v-8M2 7h20v5H2zM12 22V7M12 7S10 2 7.5 3.5 9 7 12 7zm0 0s2-5 4.5-3.5S15 7 12 7z'],
            ['account.profile', __('Profil'), 'M12 12a4 4 0 100-8 4 4 0 000 8zm-7 9a7 7 0 0114 0'],
        ];
@endphp
<header class="sticky top-0 z-30 border-b border-line bg-paper/90 backdrop-blur">
    <div class="container-site flex h-16 items-center justify-between gap-4">
        <a href="{{ lroute('home') }}"><x-logo /></a>
        <nav class="hidden items-center gap-1 lg:flex">
            @foreach ($items as [$route, $label])
                <a href="{{ route($route) }}" @class(['rounded-full px-3.5 py-2 text-sm font-medium transition hover:bg-sand/60', 'bg-sand/70 text-forest' => request()->routeIs($route.'*')])>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-2">
            <div class="hidden items-center rounded-full border border-line bg-white p-0.5 text-xs font-semibold sm:flex">
                @foreach (['ro' => 'RO', 'hu' => 'HU'] as $loc => $lbl)
                    <a href="{{ route('language.switch', $loc) }}" @class(['rounded-full px-2.5 py-1', 'bg-forest text-cream' => app()->getLocale() === $loc, 'text-ink-soft' => app()->getLocale() !== $loc])>{{ $lbl }}</a>
                @endforeach
            </div>
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex items-center gap-2 rounded-full border border-line bg-white py-1 pl-1 pr-3 text-sm">
                    <span class="grid size-8 place-items-center rounded-full bg-forest text-xs font-bold text-cream">{{ mb_substr($user->first_name, 0, 1) }}{{ mb_substr($user->last_name, 0, 1) }}</span>
                    <span class="hidden max-w-32 truncate sm:inline">{{ $user->first_name }}</span>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-line bg-white py-1 text-sm shadow-[var(--shadow-lift)]">
                    @if ($user->producer)<a href="{{ route('producer.dashboard') }}" class="block px-4 py-2 hover:bg-cream">{{ __('Zona producător') }}</a>@endif
                    <a href="{{ route('account.index') }}" class="block px-4 py-2 hover:bg-cream">{{ __('Cardul meu Autentik') }}</a>
                    @can('checkin.perform')<a href="{{ route('staff.index') }}" class="block px-4 py-2 hover:bg-cream">{{ __('Staff check-in') }}</a>@endcan
                    @can('crm.access')<a href="{{ route('crm.dashboard') }}" class="block px-4 py-2 hover:bg-cream">CRM</a>@endcan
                    <a href="{{ route('account.notifications') }}" class="block px-4 py-2 hover:bg-cream">{{ __('Mesaje') }}</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full px-4 py-2 text-left text-red-700 hover:bg-cream">{{ __('Ieșire') }}</button></form>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="container-site py-6 sm:py-10">
    @unless ($user->hasVerifiedEmail())
        <div class="mb-5 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ __('Te rugăm să confirmi adresa de e-mail. Unele funcții (tombole, recenzii, trimitere spre verificare) necesită confirmarea.') }}</span>
            <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn-ghost btn-sm">{{ __('Retrimite e-mailul') }}</button></form>
        </div>
    @endunless
    <x-flash />
    {{ $slot }}
</main>

<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden" aria-label="{{ __('Navigare') }}">
    <div class="grid grid-cols-5">
        @foreach ($items as [$route, $label, $icon])
            <a href="{{ route($route) }}" @class(['flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium', 'text-forest' => request()->routeIs($route.'*'), 'text-ink-soft' => ! request()->routeIs($route.'*')])>
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
</body>
</html>
