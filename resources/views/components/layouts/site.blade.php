@props(['title' => null, 'description' => null, 'image' => null, 'noindex' => false])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2f4a3a">
    <x-seo :title="$title" :description="$description" :image="$image" :noindex="$noindex" />
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-paper">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-ink focus:px-4 focus:py-2 focus:text-cream">{{ __('Sari la conținut') }}</a>

<header x-data="{ open: false, scrolled: false }" @scroll.window="scrolled = window.scrollY > 12"
        :class="scrolled ? 'bg-paper/90 shadow-[0_1px_0_#e2d8c5] backdrop-blur' : 'bg-transparent'"
        class="sticky top-0 z-40 transition-all duration-300">
    <div class="container-site flex h-[72px] items-center justify-between gap-6">
        <a href="{{ lroute('home') }}" aria-label="Autentik Market — {{ __('Acasă') }}"><x-logo /></a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('Meniu principal') }}">
            @php $nav = [
                'events.index' => __('Târguri'),
                'producers.index' => __('Producători'),
                'about' => __('Despre'),
                'blog.index' => __('Blog'),
                'for-producers' => __('Pentru producători'),
            ]; @endphp
            @foreach ($nav as $route => $label)
                <a href="{{ lroute($route) }}" @class(['rounded-full px-3.5 py-2 text-sm font-medium transition hover:bg-sand/60', 'text-forest bg-sand/70' => request()->routeIs('*.'.$route) || request()->routeIs('*.'.Str::before($route, '.').'.*')])>{{ $label }}</a>
            @endforeach
            @if ($shopUrl)
                <a href="{{ $shopUrl }}" target="_blank" rel="noopener" class="rounded-full px-3.5 py-2 text-sm font-medium text-clay hover:bg-sand/60">Autentik Shop ↗</a>
            @endif
        </nav>

        <div class="flex items-center gap-2">
            <div class="flex items-center rounded-full border border-line bg-white/70 p-0.5 text-xs font-semibold" role="group" aria-label="{{ __('Limba') }}">
                @foreach (['ro' => 'RO', 'hu' => 'HU'] as $loc => $lbl)
                    <a href="{{ \App\Support\Locales::switchUrl($loc) }}" hreflang="{{ $loc }}" lang="{{ $loc }}"
                       @class(['rounded-full px-2.5 py-1 transition', 'bg-forest text-cream' => app()->getLocale() === $loc, 'text-ink-soft hover:text-ink' => app()->getLocale() !== $loc])>{{ $lbl }}</a>
                @endforeach
            </div>
            @auth
                <a href="{{ route('dashboard.redirect') }}" class="btn-primary btn-sm hidden sm:inline-flex">{{ __('Contul meu') }}</a>
            @else
                <a href="{{ lroute('login') }}" class="btn-ghost btn-sm hidden sm:inline-flex">{{ __('Intră în cont') }}</a>
            @endauth
            <button type="button" class="rounded-full p-2 lg:hidden" @click="open = !open" :aria-expanded="open" aria-label="{{ __('Meniu') }}">
                <svg x-show="!open" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg x-show="open" x-cloak class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </div>
    <div x-show="open" x-cloak x-transition class="border-t border-line bg-paper lg:hidden">
        <nav class="container-site flex flex-col py-3" aria-label="{{ __('Meniu mobil') }}">
            @foreach ($nav as $route => $label)
                <a href="{{ lroute($route) }}" class="border-b border-line/60 py-3 text-lg font-display">{{ $label }}</a>
            @endforeach
            @if ($shopUrl)
                <a href="{{ $shopUrl }}" target="_blank" rel="noopener" class="border-b border-line/60 py-3 text-lg font-display text-clay">Autentik Shop ↗</a>
            @endif
            @auth
                <a href="{{ route('dashboard.redirect') }}" class="btn-primary mt-4">{{ __('Contul meu') }}</a>
            @else
                <a href="{{ lroute('register') }}" class="btn-primary mt-4">{{ __('Creează card Autentik') }}</a>
                <a href="{{ lroute('login') }}" class="btn-ghost mt-2">{{ __('Intră în cont') }}</a>
            @endauth
        </nav>
    </div>
</header>

<main id="main">
    @if (session('success') || session('error') || session('info'))
        <div class="container-site pt-6"><x-flash /></div>
    @endif
    {{ $slot }}
</main>

<footer class="mt-24 bg-forest-dark text-cream/80 grain">
    <div class="container-site relative z-10 grid gap-12 py-16 md:grid-cols-12">
        <div class="md:col-span-5">
            <x-logo light size="lg" />
            <p class="mt-5 max-w-sm text-sm leading-relaxed text-cream/70">
                {{ __('Târg lunar de producători locali și artizani. Oameni reali, produse autentice, cumpărate direct de la cei care le fac.') }}
            </p>
            <p class="mt-3 text-sm italic text-honey/90">„{{ __('Despre oameni și pentru oameni.') }}”</p>
        </div>
        <div class="md:col-span-2">
            <p class="eyebrow !text-honey">{{ __('Explorează') }}</p>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a class="hover:text-white" href="{{ lroute('events.index') }}">{{ __('Calendarul târgurilor') }}</a></li>
                <li><a class="hover:text-white" href="{{ lroute('producers.index') }}">{{ __('Producători') }}</a></li>
                <li><a class="hover:text-white" href="{{ lroute('reviews.index') }}">{{ __('Recenzii') }}</a></li>
                <li><a class="hover:text-white" href="{{ lroute('blog.index') }}">{{ __('Blog') }}</a></li>
            </ul>
        </div>
        <div class="md:col-span-2">
            <p class="eyebrow !text-honey">{{ __('Comunitate') }}</p>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a class="hover:text-white" href="{{ lroute('about') }}">{{ __('Scopul Autentik Market') }}</a></li>
                <li><a class="hover:text-white" href="{{ lroute('for-producers') }}">{{ __('Pentru producători') }}</a></li>
                <li><a class="hover:text-white" href="{{ lroute('faq') }}">{{ __('Întrebări frecvente') }}</a></li>
                <li><a class="hover:text-white" href="{{ lroute('contact') }}">{{ __('Contact') }}</a></li>
            </ul>
        </div>
        <div class="md:col-span-3">
            <p class="eyebrow !text-honey">{{ __('Urmărește-ne') }}</p>
            <div class="mt-4 flex flex-wrap gap-2 text-sm">
                @foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'] as $k => $label)
                    @if (setting('social.'.$k))
                        <a href="{{ setting('social.'.$k) }}" target="_blank" rel="noopener" class="rounded-full border border-cream/20 px-3 py-1.5 hover:border-honey hover:text-white">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
            @if ($shopUrl)
                <a href="{{ $shopUrl }}" target="_blank" rel="noopener" class="mt-6 inline-flex items-center gap-2 rounded-full bg-honey px-4 py-2 text-sm font-semibold text-ink hover:bg-white">Autentik Shop ↗</a>
            @endif
        </div>
    </div>
    <div class="relative z-10 border-t border-cream/10">
        <div class="container-site flex flex-col gap-3 py-6 text-xs text-cream/50 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ date('Y') }} Autentik Market</p>
            <div class="flex flex-wrap gap-4">
                <a href="{{ lroute('legal.terms') }}" class="hover:text-cream">{{ __('Termeni și condiții') }}</a>
                <a href="{{ lroute('legal.privacy') }}" class="hover:text-cream">{{ __('Confidențialitate') }}</a>
                <a href="{{ lroute('legal.cookies') }}" class="hover:text-cream">{{ __('Cookies') }}</a>
            </div>
        </div>
    </div>
</footer>

<div x-data="{ show: false }" x-init="try { show = !localStorage.getItem('am_cookie_ok') } catch (e) {}" x-show="show" x-cloak x-transition
     class="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-xl rounded-2xl border border-line bg-white p-4 text-sm shadow-[var(--shadow-lift)] sm:bottom-5">
    <p class="text-ink-soft">{{ __('Folosim doar cookie-uri strict necesare funcționării site-ului (sesiune, securitate, limbă). Nu folosim cookie-uri de urmărire.') }}
        <a href="{{ lroute('legal.cookies') }}" class="text-clay underline">{{ __('Detalii') }}</a></p>
    <button type="button" class="btn-primary btn-sm mt-3" @click="try { localStorage.setItem('am_cookie_ok', '1') } catch (e) {}; show = false">{{ __('Am înțeles') }}</button>
</div>
</body>
</html>
