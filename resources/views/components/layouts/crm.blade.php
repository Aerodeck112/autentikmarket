@props(['title' => null, 'actions' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}CRM Autentik Market</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('icons/favicon-64.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f6f1e2]" x-data="{ side: false }">
@php
    $nav = [
        [__('Dashboard'), 'crm.dashboard', 'dashboard.view', 'M4 13h6V4H4zm10 7h6v-9h-6zM4 20h6v-4H4zm10-12h6V4h-6z'],
        [__('Evenimente'), 'crm.events.index', 'events.view', 'M8 7V3m8 4V3M4 11h16M5 5h14v16H5z'],
        [__('Hartă & standuri'), 'crm.venues.index', 'maps.manage', 'M9 4L3 6v14l6-2 6 2 6-2V4l-6 2-6-2zm0 0v14m6-12v14'],
        [__('Producători'), 'crm.producers.index', 'producers.view', 'M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6'],
        [__('Vizitatori'), 'crm.visitors.index', 'visitors.view', 'M17 20h5v-2a4 4 0 00-5-3.9M9 20H2v-2a4 4 0 014-4h3a4 4 0 014 4v2zm3-12a4 4 0 11-8 0 4 4 0 018 0zm7 1a3 3 0 11-6 0 3 3 0 016 0z'],
        [__('Check-in'), 'crm.checkins.index', 'checkin.perform', 'M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3M9 12l2 2 4-4'],
        [__('Fidelitate'), 'crm.loyalty.index', 'loyalty.manage', 'M12 21l-8-8a5 5 0 017-7l1 1 1-1a5 5 0 017 7z'],
        [__('Tombole'), 'crm.raffles.index', 'raffles.manage', 'M20 12v8H4v-8M2 7h20v5H2zM12 22V7'],
        [__('Recenzii'), 'crm.reviews.index', 'reviews.moderate', 'M12 3l2.7 5.6 6.3.9-4.5 4.4 1 6.1L12 17l-5.5 3 1-6.1L3 9.5l6.3-.9z'],
        [__('Contracte'), 'crm.contracts.index', 'contracts.manage', 'M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6'],
        [__('Task-uri'), 'crm.tasks.index', 'tasks.view', 'M9 11l3 3 8-8M20 12v7a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2h9'],
        [__('Blog'), 'crm.blog.index', 'blog.manage', 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5'],
        [__('Automatizări'), 'crm.automations.index', 'notifications.manage', 'M13 2L3 14h9l-1 8 10-12h-9z'],
        [__('Rapoarte'), 'crm.reports.index', 'reports.view', 'M4 20V10m6 10V4m6 16v-7m4 7H2'],
        [__('Actualizări'), 'crm.updates.index', 'system.update', 'M4 4v6h6M20 20v-6h-6M5 15a7 7 0 0012 3m2-9A7 7 0 007 6'],
        [__('Setări'), 'crm.settings.index', 'settings.manage', 'M12 15a3 3 0 100-6 3 3 0 000 6zm7.4-3a7.4 7.4 0 00-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 00-2-1.2L14.5 3h-5l-.4 2.6a7 7 0 00-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 000 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 002 1.2l.4 2.6h5l.4-2.6a7 7 0 002-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z'],
    ];
    $user = auth()->user();
@endphp

<aside :class="side ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-forest-dark text-cream/80 transition-transform duration-200">
    <div class="flex h-16 items-center gap-2 border-b border-white/10 px-5">
        <x-logo light />
        <span class="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-honey">CRM</span>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
        @foreach ($nav as [$label, $route, $perm, $icon])
            @can($perm)
                @php $active = request()->routeIs(Str::beforeLast($route, '.').'*') || request()->routeIs($route); @endphp
                <a href="{{ route($route) }}" @class(['mb-0.5 flex items-center gap-3 rounded-xl px-3 py-2 transition', 'bg-white/10 text-white' => $active, 'hover:bg-white/5 hover:text-white' => ! $active])>
                    <svg class="size-[18px] shrink-0 opacity-80" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
                    {{ $label }}
                </a>
            @endcan
        @endforeach
        @can('system.view')
            <a href="{{ route('crm.system.index') }}" @class(['mb-0.5 mt-3 flex items-center gap-3 rounded-xl px-3 py-2 text-xs uppercase tracking-wider', 'bg-white/10 text-white' => request()->routeIs('crm.system.*'), 'text-cream/50 hover:text-white' => ! request()->routeIs('crm.system.*')])>
                {{ __('Sistem') }} · v{{ $appVersion }}
            </a>
        @endcan
    </nav>
    <div class="border-t border-white/10 p-3 text-xs">
        <a href="{{ route('staff.index') }}" class="mb-1 block rounded-lg px-3 py-2 hover:bg-white/5">📱 {{ __('Deschide Staff PWA') }}</a>
        <a href="{{ lroute('home') }}" target="_blank" class="block rounded-lg px-3 py-2 hover:bg-white/5">↗ {{ __('Vezi site-ul') }}</a>
    </div>
</aside>
<div x-show="side" x-cloak @click="side = false" class="fixed inset-0 z-30 bg-ink/40 lg:hidden"></div>

<div class="lg:pl-64">
    <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-paper/90 px-4 backdrop-blur sm:px-6">
        <button class="rounded-lg p-2 lg:hidden" @click="side = true" aria-label="{{ __('Meniu') }}">
            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <form action="{{ route('crm.search') }}" method="GET" class="relative max-w-md flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Căutare globală: vizitator, producător, contract, eveniment…') }}" class="field-input !rounded-full !py-2 pl-9">
        </form>
        <div class="ml-auto flex items-center gap-2">
            <div class="hidden items-center rounded-full border border-line bg-white p-0.5 text-xs font-semibold sm:flex">
                @foreach (['ro' => 'RO', 'hu' => 'HU'] as $loc => $lbl)
                    <a href="{{ route('language.switch', $loc) }}" @class(['rounded-full px-2.5 py-1', 'bg-forest text-cream' => app()->getLocale() === $loc, 'text-ink-soft' => app()->getLocale() !== $loc])>{{ $lbl }}</a>
                @endforeach
            </div>
            <span class="hidden text-sm text-ink-soft md:inline">{{ $user->name }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost btn-sm">{{ __('Ieșire') }}</button></form>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 lg:px-8">
        @if ($title || $actions)
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    {{ $breadcrumb ?? '' }}
                    @if ($title)<h1 class="font-display text-3xl text-ink">{{ $title }}</h1>@endif
                </div>
                @if ($actions)<div class="flex flex-wrap gap-2">{{ $actions }}</div>@endif
            </div>
        @endif
        <x-flash />
        {{ $slot }}
    </main>
</div>
</body>
</html>
